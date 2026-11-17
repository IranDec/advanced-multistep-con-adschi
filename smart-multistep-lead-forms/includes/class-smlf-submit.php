<?php
/**
 * Public endpoints (admin-ajax, no login): smlf_event (funnel), smlf_save (partial lead),
 * smlf_submit (final submission).
 *
 * Submission pipeline: rate limit -> honeypot -> signed render time -> captcha -> logic path ->
 * per-type validation of the visible fields of the visited steps -> files -> store -> e-mails / webhook.
 *
 * @package Smart_Multistep_Lead_Forms
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SMLF_Submit {

	public static function register() {
		foreach ( array( 'event', 'save', 'submit' ) as $a ) {
			add_action( 'wp_ajax_smlf_' . $a, array( __CLASS__, $a ) );
			add_action( 'wp_ajax_nopriv_smlf_' . $a, array( __CLASS__, $a ) );
		}
	}

	private static function form_from_request() {
		$id   = isset( $_POST['form_id'] ) ? absint( $_POST['form_id'] ) : 0;
		$form = $id ? SMLF_Forms::get( $id, true ) : null;
		if ( ! $form ) {
			wp_send_json_error( array( 'message' => __( 'This form is not available.', 'smart-multistep-lead-forms' ) ), 404 );
		}
		return $form;
	}

	private static function answers_from_request() {
		$raw = isset( $_POST['answers'] ) ? json_decode( wp_unslash( (string) $_POST['answers'] ), true ) : array();
		return is_array( $raw ) ? array_slice( $raw, 0, 200, true ) : array();
	}

	private static function meta_from_request() {
		$m = array();
		foreach ( array( 'page_url', 'referrer' ) as $k ) {
			$m[ $k ] = isset( $_POST[ $k ] ) ? substr( esc_url_raw( wp_unslash( (string) $_POST[ $k ] ) ), 0, 500 ) : '';
		}
		foreach ( array( 'utm_source', 'utm_medium', 'utm_campaign' ) as $k ) {
			$m[ $k ] = isset( $_POST[ $k ] ) ? substr( sanitize_text_field( wp_unslash( (string) $_POST[ $k ] ) ), 0, 120 ) : '';
		}
		return $m;
	}

	// -------------------------------------------------------------------------------------------

	public static function event() {
		if ( ! SMLF_Security::rate_ok( 'ev', 150, 10 * MINUTE_IN_SECONDS ) ) {
			wp_send_json_success();
		}
		$form = self::form_from_request();
		$type = isset( $_POST['type'] ) ? sanitize_key( $_POST['type'] ) : '';
		$step = isset( $_POST['step'] ) ? sanitize_key( $_POST['step'] ) : '';
		if ( in_array( $type, array( 'view', 'start', 'step' ), true ) ) {
			if ( 'step' === $type && ! in_array( $step, wp_list_pluck( $form['form']['steps'], 'id' ), true ) ) {
				wp_send_json_success();
			}
			SMLF_Stats::hit( (int) $form['id'], $type, $step );
		}
		wp_send_json_success();
	}

	public static function save() {
		if ( ! SMLF_Security::rate_ok( 'save', 60, 10 * MINUTE_IN_SECONDS ) ) {
			wp_send_json_error( array( 'message' => 'rate' ), 429 );
		}
		$form = self::form_from_request();
		if ( ! get_option( 'smlf_enable_partial', 1 ) || empty( $form['form']['settings']['save_progress'] ) ) {
			wp_send_json_success( array( 'token' => '' ) );
		}
		$answers = self::clean( $form['form'], self::answers_from_request(), false );
		$contact = SMLF_Leads::contact( $answers['values'] );
		$token   = isset( $_POST['token'] ) ? sanitize_text_field( wp_unslash( $_POST['token'] ) ) : '';
		// Partial leads are only kept once the visitor gave a way to contact them.
		if ( '' === $contact['email'] && '' === $contact['phone'] ) {
			wp_send_json_success( array( 'token' => $token ) );
		}
		$step = isset( $_POST['step'] ) ? sanitize_key( $_POST['step'] ) : '';
		list( $lead_id, $token ) = SMLF_Leads::store( (int) $form['id'], $answers['values'], $answers['path'], array(), 'partial', $step, $token, self::meta_from_request() );
		SMLF_Webhooks::send( 'partial', $form, $lead_id );
		wp_send_json_success( array( 'token' => $token ) );
	}

	public static function submit() {
		if ( ! SMLF_Security::rate_ok( 'submit', 6, 10 * MINUTE_IN_SECONDS ) ) {
			wp_send_json_error( array( 'message' => __( 'Too many submissions. Please wait a few minutes.', 'smart-multistep-lead-forms' ) ), 429 );
		}
		$form = self::form_from_request();

		// Honeypot: a field humans never see. Bots get a "success" so they learn nothing.
		if ( ! empty( $_POST['smlf_hp'] ) ) {
			wp_send_json_success( array( 'message' => '' ) );
		}
		$stamp = SMLF_Security::check_stamp( (int) $form['id'], isset( $_POST['stamp'] ) ? sanitize_text_field( wp_unslash( $_POST['stamp'] ) ) : '' );
		if ( is_wp_error( $stamp ) ) {
			wp_send_json_error( array( 'message' => $stamp->get_error_message() ), 400 );
		}
		$captcha = SMLF_Security::verify_captcha( SMLF_Security::captcha_method( $form['form'] ), isset( $_POST['captcha'] ) ? sanitize_text_field( wp_unslash( $_POST['captcha'] ) ) : '' );
		if ( is_wp_error( $captcha ) ) {
			wp_send_json_error( array( 'message' => $captcha->get_error_message() ), 400 );
		}

		$result = self::clean( $form['form'], self::answers_from_request(), true );
		if ( $result['dead_end'] ) {
			wp_send_json_error( array( 'message' => __( 'This form cannot be submitted with these answers.', 'smart-multistep-lead-forms' ) ), 400 );
		}
		if ( $result['errors'] ) {
			wp_send_json_error( array( 'message' => __( 'Please check the highlighted fields.', 'smart-multistep-lead-forms' ), 'errors' => $result['errors'], 'step' => $result['error_step'] ), 422 );
		}

		$files = SMLF_Uploads::handle( $result['file_fields'] );
		if ( is_wp_error( $files ) ) {
			wp_send_json_error( array( 'message' => $files->get_error_message() ), 400 );
		}
		foreach ( $result['file_fields'] as $f ) {
			if ( $f['required'] && empty( $files[ $f['id'] ] ) ) {
				SMLF_Uploads::delete_files( $files );
				wp_send_json_error( array( 'message' => __( 'Please check the highlighted fields.', 'smart-multistep-lead-forms' ), 'errors' => array( $f['id'] => __( 'Please add a file.', 'smart-multistep-lead-forms' ) ), 'step' => $f['step'] ), 422 );
			}
		}

		$token = isset( $_POST['token'] ) ? sanitize_text_field( wp_unslash( $_POST['token'] ) ) : '';
		$last  = end( $result['path'] );
		list( $lead_id ) = SMLF_Leads::store( (int) $form['id'], $result['values'], $result['path'], $files, 'completed', $last ? $last : '', $token, self::meta_from_request() );

		SMLF_Stats::hit( (int) $form['id'], 'submit' );
		SMLF_Mailer::after_submit( $lead_id, $form );
		SMLF_Webhooks::send( 'completed', $form, $lead_id );
		do_action( 'smlf_form_submitted', $lead_id, (int) $form['id'], $result['values'] );

		$s = $form['form']['settings'];
		wp_send_json_success( array(
			'title'    => $s['success_title'] ? $s['success_title'] : __( 'Thank you!', 'smart-multistep-lead-forms' ),
			'message'  => $s['success_message'] ? $s['success_message'] : __( 'We have received your request and will get back to you soon.', 'smart-multistep-lead-forms' ),
			'redirect' => $s['redirect_url'],
		) );
	}

	// -------------------------------------------------------------------------------------------
	// Validation
	// -------------------------------------------------------------------------------------------

	/**
	 * Keeps only answers of visible fields on the visited path and validates them.
	 *
	 * @return array values, path, errors [field => message], error_step, file_fields, dead_end
	 */
	public static function clean( array $form, array $raw, $strict ) {
		// Path and visibility are evaluated on the (sanitised) raw answers first.
		$pre = array();
		foreach ( SMLF_Forms::fields_flat( $form ) as $id => $f ) {
			if ( isset( $raw[ $id ] ) ) {
				$pre[ $id ] = self::value( $f, $raw[ $id ] )[0];
			}
		}
		$path   = SMLF_Logic::path( $form, $pre );
		$out    = array( 'values' => array(), 'path' => $path['steps'], 'errors' => array(), 'error_step' => '', 'file_fields' => array(), 'dead_end' => $path['dead_end'] );
		foreach ( $form['steps'] as $step ) {
			if ( ! in_array( $step['id'], $path['steps'], true ) ) {
				continue;
			}
			foreach ( $step['fields'] as $f ) {
				if ( 'message' === $f['type'] || ! SMLF_Logic::visible( $f, $pre ) ) {
					continue;
				}
				if ( 'file' === $f['type'] ) {
					$f['step']             = $step['id'];
					$out['file_fields'][] = $f;
					continue;
				}
				list( $value, $error ) = self::value( $f, isset( $raw[ $f['id'] ] ) ? $raw[ $f['id'] ] : '' );
				if ( $strict && ! $error && $f['required'] && self::is_empty( $value ) ) {
					$error = 'consent' === $f['type'] ? __( 'Please confirm.', 'smart-multistep-lead-forms' ) : __( 'This field is required.', 'smart-multistep-lead-forms' );
				}
				if ( $strict && $error ) {
					$out['errors'][ $f['id'] ] = $error;
					$out['error_step']         = $out['error_step'] ? $out['error_step'] : $step['id'];
					continue;
				}
				if ( ! self::is_empty( $value ) ) {
					$out['values'][ $f['id'] ] = $value;
				}
			}
		}
		return $out;
	}

	private static function is_empty( $v ) {
		if ( is_array( $v ) ) {
			return ! array_filter( $v, function ( $x ) {
				return '' !== trim( (string) $x );
			} );
		}
		return '' === trim( (string) $v );
	}

	/** @return array [value, error message or ''] */
	public static function value( array $f, $raw ) {
		$s   = function ( $v, $max ) {
			$v = sanitize_text_field( is_scalar( $v ) ? (string) $v : '' );
			return function_exists( 'mb_substr' ) ? mb_substr( $v, 0, $max ) : substr( $v, 0, $max );
		};
		$err = '';
		switch ( $f['type'] ) {
			case 'textarea':
				$v = sanitize_textarea_field( is_scalar( $raw ) ? (string) $raw : '' );
				$v = function_exists( 'mb_substr' ) ? mb_substr( $v, 0, 5000 ) : substr( $v, 0, 5000 );
				break;
			case 'email':
				$v = $s( $raw, 190 );
				if ( '' !== $v && ! is_email( $v ) ) {
					$err = __( 'Please enter a valid e-mail address.', 'smart-multistep-lead-forms' );
				}
				break;
			case 'phone':
				$v = $s( $raw, 40 );
				if ( '' !== $v && ( ! preg_match( '/^[0-9+()\-\s\/.]+$/', $v ) || preg_match_all( '/\d/', $v ) < 5 ) ) {
					$err = __( 'Please enter a valid phone number.', 'smart-multistep-lead-forms' );
				}
				break;
			case 'number':
			case 'range':
				$v = str_replace( ',', '.', $s( $raw, 30 ) );
				if ( '' !== $v ) {
					if ( ! is_numeric( $v ) ) {
						$err = __( 'Please enter a number.', 'smart-multistep-lead-forms' );
					} elseif ( ( '' !== $f['min'] && (float) $v < (float) $f['min'] ) || ( '' !== $f['max'] && (float) $v > (float) $f['max'] ) ) {
						/* translators: 1: minimum, 2: maximum */
						$err = sprintf( __( 'Please enter a value between %1$s and %2$s.', 'smart-multistep-lead-forms' ), '' !== $f['min'] ? $f['min'] : '-∞', '' !== $f['max'] ? $f['max'] : '∞' );
					}
				}
				break;
			case 'url':
				$v = esc_url_raw( $s( $raw, 300 ), array( 'http', 'https' ) );
				if ( '' !== $s( $raw, 300 ) && '' === $v ) {
					$err = __( 'Please enter a valid web address.', 'smart-multistep-lead-forms' );
				}
				break;
			case 'date':
				$v = $s( $raw, 10 );
				$d = '' !== $v ? DateTime::createFromFormat( '!Y-m-d', $v ) : null;
				if ( '' !== $v && ( ! $d || $d->format( 'Y-m-d' ) !== $v ) ) {
					$err = __( 'Please enter a valid date.', 'smart-multistep-lead-forms' );
				}
				break;
			case 'choice':
				$allowed = wp_list_pluck( $f['options'], 'value' );
				$given   = array_map( function ( $x ) use ( $s ) {
					return $s( $x, 150 );
				}, is_array( $raw ) ? $raw : ( '' === (string) $raw ? array() : array( $raw ) ) );
				$valid   = array_values( array_intersect( $given, $allowed ) );
				if ( count( $valid ) !== count( $given ) ) {
					$err = __( 'Please choose one of the options.', 'smart-multistep-lead-forms' );
				}
				$v = $f['multiple'] ? $valid : ( $valid ? $valid[0] : '' );
				break;
			case 'rating':
				$v = '' === (string) $raw ? '' : (int) $raw;
				if ( '' !== $v && ( $v < 1 || $v > $f['max_stars'] ) ) {
					$err = __( 'Please choose a rating.', 'smart-multistep-lead-forms' );
				}
				break;
			case 'address':
				$a = is_array( $raw ) ? $raw : array();
				$v = array(
					'street' => $s( isset( $a['street'] ) ? $a['street'] : '', 120 ),
					'zip'    => $s( isset( $a['zip'] ) ? $a['zip'] : '', 12 ),
					'city'   => $s( isset( $a['city'] ) ? $a['city'] : '', 80 ),
				);
				if ( $f['required'] && ( '' === $v['zip'] || '' === $v['city'] ) ) {
					$v = array_filter( $v ) ? $v : array();
					if ( $v ) {
						$err = __( 'Please enter postcode and city.', 'smart-multistep-lead-forms' );
					}
				}
				break;
			case 'consent':
				$v = ! empty( $raw ) && '0' !== (string) $raw ? '1' : '';
				break;
			case 'hidden':
				$v = $s( $raw, 255 );
				break;
			default:
				$v = $s( $raw, 300 );
		}
		return array( $v, $err );
	}
}
