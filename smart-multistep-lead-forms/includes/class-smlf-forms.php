<?php
/**
 * Form model (schema 2): load / save, normalisation of everything the builder sends,
 * conversion of 1.x forms, templates.
 *
 * Form = { schema: 2, steps: [ step ], settings: {...} }
 * Step = { id, title, description, fields: [ field ], routes: [ {when:{field,op,value}, goto} ], next, terminal }
 * Field = { id, type, label, placeholder, help, required, width, options:[{label,value,icon,image}],
 *           display, multiple, min, max, step, unit, max_stars, accept, max_files, consent_text,
 *           link_text, link_url, source, content, default, show_if:{match, rules:[{field,op,value}]} }
 *
 * @package Smart_Multistep_Lead_Forms
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SMLF_Forms {

	const TYPES     = array( 'text', 'textarea', 'email', 'phone', 'number', 'url', 'date', 'choice', 'range', 'rating', 'address', 'file', 'consent', 'hidden', 'message' );
	const OPERATORS = array( 'eq', 'neq', 'contains', 'not_contains', 'empty', 'not_empty', 'gt', 'lt' );

	// -------------------------------------------------------------------------------------------
	// Storage
	// -------------------------------------------------------------------------------------------

	/** @return array|null form row with decoded 'form' (schema 2) */
	public static function get( $id, $published_only = false ) {
		global $wpdb;
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}smlf_forms WHERE id = %d", $id ), ARRAY_A );
		if ( ! $row || ( $published_only && 'publish' !== $row['status'] ) ) {
			return null;
		}
		$row['form'] = self::normalize( json_decode( (string) $row['form_data'], true ) );
		return $row;
	}

	public static function all() {
		global $wpdb;
		return (array) $wpdb->get_results( "SELECT id, title, status, created_at, updated_at FROM {$wpdb->prefix}smlf_forms ORDER BY id DESC", ARRAY_A );
	}

	/** @return int form id */
	public static function save( $id, $title, array $form, $status = 'publish' ) {
		global $wpdb;
		$form  = self::normalize( $form );
		$title = sanitize_text_field( $title );
		$data  = array(
			'title'      => '' !== $title ? $title : __( 'Untitled form', 'smart-multistep-lead-forms' ),
			'form_data'  => wp_json_encode( $form ),
			'status'     => in_array( $status, array( 'publish', 'draft' ), true ) ? $status : 'publish',
			'updated_at' => current_time( 'mysql' ),
		);
		if ( $id && $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$wpdb->prefix}smlf_forms WHERE id = %d", $id ) ) ) {
			$wpdb->update( "{$wpdb->prefix}smlf_forms", $data, array( 'id' => (int) $id ) );
			return (int) $id;
		}
		$data['created_at'] = current_time( 'mysql' );
		$wpdb->insert( "{$wpdb->prefix}smlf_forms", $data );
		return (int) $wpdb->insert_id;
	}

	public static function delete( $id ) {
		global $wpdb;
		$lead_ids = $wpdb->get_col( $wpdb->prepare( "SELECT id FROM {$wpdb->prefix}smlf_leads WHERE form_id = %d", $id ) );
		SMLF_Leads::delete( $lead_ids );
		$wpdb->delete( "{$wpdb->prefix}smlf_stats", array( 'form_id' => (int) $id ) );
		return (bool) $wpdb->delete( "{$wpdb->prefix}smlf_forms", array( 'id' => (int) $id ) );
	}

	// -------------------------------------------------------------------------------------------
	// Normalisation
	// -------------------------------------------------------------------------------------------

	/** Accepts schema 2 or 1.x data and returns a clean schema-2 form. */
	public static function normalize( $raw ) {
		$raw = is_array( $raw ) ? $raw : array();
		if ( empty( $raw['schema'] ) || 2 !== (int) $raw['schema'] ) {
			$raw = self::from_v1( $raw );
		}

		$steps = array();
		$used  = array();
		foreach ( array_values( isset( $raw['steps'] ) && is_array( $raw['steps'] ) ? $raw['steps'] : array() ) as $i => $s ) {
			if ( ! is_array( $s ) ) {
				continue;
			}
			$sid = self::key( isset( $s['id'] ) ? $s['id'] : '', 's' . ( $i + 1 ) );
			while ( isset( $used[ $sid ] ) ) {
				$sid .= '_' . ( $i + 1 );
			}
			$used[ $sid ] = true;
			$fields = array();
			foreach ( isset( $s['fields'] ) && is_array( $s['fields'] ) ? $s['fields'] : array() as $f ) {
				if ( is_array( $f ) ) {
					$fields[] = self::field( $f );
				}
			}
			$steps[] = array(
				'id'          => $sid,
				'title'       => self::text( isset( $s['title'] ) ? $s['title'] : '', 150 ),
				'description' => self::text( isset( $s['description'] ) ? $s['description'] : '', 500, true ),
				'fields'      => $fields,
				'routes'      => self::routes( isset( $s['routes'] ) ? $s['routes'] : array() ),
				'next'        => self::key( isset( $s['next'] ) ? $s['next'] : '', '' ),
				'terminal'    => isset( $s['terminal'] ) && 'reset' === $s['terminal'] ? 'reset' : '',
			);
		}
		if ( ! $steps ) {
			$steps[] = array( 'id' => 's1', 'title' => '', 'description' => '', 'fields' => array(), 'routes' => array(), 'next' => '', 'terminal' => '' );
		}

		// Field ids must be unique in the whole form (they are the answer keys).
		$seen = array();
		foreach ( $steps as &$step ) {
			foreach ( $step['fields'] as $k => &$field ) {
				$base = $field['id'];
				$n    = 2;
				while ( isset( $seen[ $field['id'] ] ) ) {
					$field['id'] = $base . '_' . $n++;
				}
				$seen[ $field['id'] ] = true;
			}
			unset( $field );
		}
		unset( $step );

		// Routes / next / show_if may only point to existing steps / fields.
		$step_ids = wp_list_pluck( $steps, 'id' );
		foreach ( $steps as &$step ) {
			$step['routes'] = array_values( array_filter( $step['routes'], function ( $r ) use ( $step_ids, $seen ) {
				return in_array( $r['goto'], $step_ids, true ) && isset( $seen[ $r['when']['field'] ] );
			} ) );
			if ( '' !== $step['next'] && 'end' !== $step['next'] && ! in_array( $step['next'], $step_ids, true ) ) {
				$step['next'] = '';
			}
			foreach ( $step['fields'] as &$field ) {
				$field['show_if']['rules'] = array_values( array_filter( $field['show_if']['rules'], function ( $r ) use ( $seen, $field ) {
					return isset( $seen[ $r['field'] ] ) && $r['field'] !== $field['id'];
				} ) );
			}
			unset( $field );
		}
		unset( $step );

		return array(
			'schema'   => 2,
			'steps'    => $steps,
			'settings' => self::settings( isset( $raw['settings'] ) && is_array( $raw['settings'] ) ? $raw['settings'] : array() ),
		);
	}

	public static function field( array $f ) {
		$type = isset( $f['type'] ) ? sanitize_key( $f['type'] ) : 'text';
		$type = in_array( $type, self::TYPES, true ) ? $type : 'text';
		$out  = array(
			'id'          => self::key( isset( $f['id'] ) ? $f['id'] : '', 'field_' . wp_generate_password( 5, false, false ) ),
			'type'        => $type,
			'label'       => self::text( isset( $f['label'] ) ? $f['label'] : '', 300 ),
			'placeholder' => self::text( isset( $f['placeholder'] ) ? $f['placeholder'] : '', 150 ),
			'help'        => self::text( isset( $f['help'] ) ? $f['help'] : '', 300 ),
			'required'    => ! empty( $f['required'] ) && ! in_array( $type, array( 'message', 'hidden' ), true ),
			'width'       => self::choice( isset( $f['width'] ) ? $f['width'] : 'full', array( 'full', 'half', 'third' ), 'full' ),
			'default'     => self::text( isset( $f['default'] ) ? $f['default'] : '', 200 ),
			'show_if'     => self::condition_group( isset( $f['show_if'] ) ? $f['show_if'] : array() ),
		);

		switch ( $type ) {
			case 'choice':
				$out['options']  = self::options( isset( $f['options'] ) ? $f['options'] : array() );
				$out['display']  = self::choice( isset( $f['display'] ) ? $f['display'] : 'cards', array( 'cards', 'buttons', 'list', 'dropdown' ), 'cards' );
				$out['multiple'] = ! empty( $f['multiple'] ) && 'dropdown' !== $out['display'];
				break;
			case 'number':
			case 'range':
				$out['min']  = isset( $f['min'] ) && '' !== $f['min'] ? (float) $f['min'] : ( 'range' === $type ? 0 : '' );
				$out['max']  = isset( $f['max'] ) && '' !== $f['max'] ? (float) $f['max'] : ( 'range' === $type ? 100 : '' );
				$out['step'] = isset( $f['step'] ) && (float) $f['step'] > 0 ? (float) $f['step'] : 1;
				$out['unit'] = self::text( isset( $f['unit'] ) ? $f['unit'] : '', 20 );
				break;
			case 'rating':
				$out['max_stars'] = max( 3, min( 10, isset( $f['max_stars'] ) ? (int) $f['max_stars'] : 5 ) );
				break;
			case 'file':
				$out['accept']    = implode( ',', array_slice( array_filter( array_map( function ( $e ) {
					return preg_replace( '/[^a-z0-9]/', '', strtolower( trim( $e ) ) );
				}, explode( ',', isset( $f['accept'] ) ? (string) $f['accept'] : '' ) ) ), 0, 15 ) );
				$out['max_files'] = max( 1, min( 10, isset( $f['max_files'] ) ? (int) $f['max_files'] : 3 ) );
				break;
			case 'consent':
				$out['consent_text'] = self::text( isset( $f['consent_text'] ) ? $f['consent_text'] : '', 1000, true );
				$out['link_text']    = self::text( isset( $f['link_text'] ) ? $f['link_text'] : '', 100 );
				$out['link_url']     = isset( $f['link_url'] ) ? esc_url_raw( (string) $f['link_url'] ) : '';
				break;
			case 'hidden':
				$out['source'] = self::choice( isset( $f['source'] ) ? $f['source'] : 'url_param', array( 'url_param', 'utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content', 'page_url', 'referrer', 'static' ), 'url_param' );
				$out['param']  = self::key( isset( $f['param'] ) ? $f['param'] : '', '' );
				break;
			case 'message':
				$out['content'] = isset( $f['content'] ) ? wp_kses_post( (string) $f['content'] ) : '';
				break;
		}
		return $out;
	}

	private static function options( $options ) {
		if ( is_string( $options ) ) {
			$options = array_map( function ( $o ) {
				return array( 'label' => trim( $o ) );
			}, preg_split( '/\r\n|\n|,/', $options ) );
		}
		$out  = array();
		$seen = array();
		foreach ( (array) $options as $o ) {
			if ( ! is_array( $o ) ) {
				continue;
			}
			$label = self::text( isset( $o['label'] ) ? $o['label'] : '', 150 );
			if ( '' === $label ) {
				continue;
			}
			$value = self::text( isset( $o['value'] ) && '' !== $o['value'] ? $o['value'] : $label, 150 );
			if ( isset( $seen[ $value ] ) ) {
				continue;
			}
			$seen[ $value ] = true;
			$out[]          = array(
				'label' => $label,
				'value' => $value,
				'icon'  => self::text( isset( $o['icon'] ) ? $o['icon'] : '', 16 ),
				'image' => isset( $o['image'] ) ? esc_url_raw( (string) $o['image'] ) : '',
			);
		}
		return array_slice( $out, 0, 50 );
	}

	private static function condition( $r ) {
		if ( ! is_array( $r ) ) {
			return null;
		}
		$field = self::key( isset( $r['field'] ) ? $r['field'] : '', '' );
		$op    = isset( $r['op'] ) && in_array( $r['op'], self::OPERATORS, true ) ? $r['op'] : 'eq';
		if ( '' === $field ) {
			return null;
		}
		return array( 'field' => $field, 'op' => $op, 'value' => self::text( isset( $r['value'] ) ? $r['value'] : '', 150 ) );
	}

	private static function condition_group( $g ) {
		$rules = array();
		foreach ( is_array( $g ) && isset( $g['rules'] ) && is_array( $g['rules'] ) ? $g['rules'] : array() as $r ) {
			$c = self::condition( $r );
			if ( $c ) {
				$rules[] = $c;
			}
		}
		return array( 'match' => is_array( $g ) && isset( $g['match'] ) && 'any' === $g['match'] ? 'any' : 'all', 'rules' => array_slice( $rules, 0, 10 ) );
	}

	private static function routes( $routes ) {
		$out = array();
		foreach ( is_array( $routes ) ? $routes : array() as $r ) {
			if ( ! is_array( $r ) ) {
				continue;
			}
			$when = self::condition( isset( $r['when'] ) ? $r['when'] : array() );
			$goto = self::key( isset( $r['goto'] ) ? $r['goto'] : '', '' );
			if ( $when && '' !== $goto ) {
				$out[] = array( 'when' => $when, 'goto' => $goto );
			}
		}
		return array_slice( $out, 0, 20 );
	}

	public static function default_settings() {
		return array(
			'theme'            => 'light',
			'primary'          => '#0ea5e9',
			'accent'           => '#14b8a6',
			'background'       => '#ffffff',
			'text'             => '#0f172a',
			'radius'           => 14,
			'max_width'        => 720,
			'font'             => 'inherit',
			'progress'         => 'steps',
			'animate'          => true,
			'language'         => 'auto',
			'save_progress'    => true,
			'review'           => false,
			'success_title'    => '',
			'success_message'  => '',
			'redirect_url'     => '',
			'next_label'       => '',
			'back_label'       => '',
			'submit_label'     => '',
			'notify_admin'     => true,
			'admin_emails'     => '',
			'admin_subject'    => '',
			'confirm_customer' => true,
			'customer_subject' => '',
			'customer_body'    => '',
			'captcha'          => 'inherit',
			'webhook_url'      => '',
			'webhook_secret'   => '',
			'webhook_partial'  => false,
		);
	}

	public static function settings( array $s ) {
		$d   = self::default_settings();
		$hex = function ( $v, $fallback ) {
			$v = sanitize_hex_color( (string) $v );
			return $v ? $v : $fallback;
		};
		$emails = array_filter( array_map( 'sanitize_email', array_map( 'trim', explode( ',', isset( $s['admin_emails'] ) ? (string) $s['admin_emails'] : '' ) ) ), 'is_email' );
		return array(
			'theme'            => self::choice( isset( $s['theme'] ) ? $s['theme'] : $d['theme'], array( 'light', 'dark', 'glass', 'minimal' ), 'light' ),
			'primary'          => $hex( isset( $s['primary'] ) ? $s['primary'] : '', $d['primary'] ),
			'accent'           => $hex( isset( $s['accent'] ) ? $s['accent'] : '', $d['accent'] ),
			'background'       => $hex( isset( $s['background'] ) ? $s['background'] : '', $d['background'] ),
			'text'             => $hex( isset( $s['text'] ) ? $s['text'] : '', $d['text'] ),
			'radius'           => max( 0, min( 32, isset( $s['radius'] ) ? (int) $s['radius'] : $d['radius'] ) ),
			'max_width'        => max( 360, min( 1400, isset( $s['max_width'] ) ? (int) $s['max_width'] : $d['max_width'] ) ),
			'font'             => preg_replace( '/[^a-zA-Z0-9 ,\-"\']/', '', isset( $s['font'] ) && '' !== $s['font'] ? (string) $s['font'] : 'inherit' ),
			'progress'         => self::choice( isset( $s['progress'] ) ? $s['progress'] : $d['progress'], array( 'steps', 'bar', 'none' ), 'steps' ),
			'animate'          => ! isset( $s['animate'] ) || ! empty( $s['animate'] ),
			'language'         => self::choice( isset( $s['language'] ) ? $s['language'] : 'auto', array( 'auto', 'en', 'de', 'fa' ), 'auto' ),
			'save_progress'    => ! isset( $s['save_progress'] ) || ! empty( $s['save_progress'] ),
			'review'           => ! empty( $s['review'] ),
			'success_title'    => self::text( isset( $s['success_title'] ) ? $s['success_title'] : '', 150 ),
			'success_message'  => self::text( isset( $s['success_message'] ) ? $s['success_message'] : '', 1000, true ),
			'redirect_url'     => isset( $s['redirect_url'] ) ? esc_url_raw( (string) $s['redirect_url'] ) : '',
			'next_label'       => self::text( isset( $s['next_label'] ) ? $s['next_label'] : '', 40 ),
			'back_label'       => self::text( isset( $s['back_label'] ) ? $s['back_label'] : '', 40 ),
			'submit_label'     => self::text( isset( $s['submit_label'] ) ? $s['submit_label'] : '', 40 ),
			'notify_admin'     => ! isset( $s['notify_admin'] ) || ! empty( $s['notify_admin'] ),
			'admin_emails'     => implode( ', ', array_slice( $emails, 0, 5 ) ),
			'admin_subject'    => self::text( isset( $s['admin_subject'] ) ? $s['admin_subject'] : '', 200 ),
			'confirm_customer' => ! isset( $s['confirm_customer'] ) || ! empty( $s['confirm_customer'] ),
			'customer_subject' => self::text( isset( $s['customer_subject'] ) ? $s['customer_subject'] : '', 200 ),
			'customer_body'    => self::text( isset( $s['customer_body'] ) ? $s['customer_body'] : '', 3000, true ),
			'captcha'          => self::choice( isset( $s['captcha'] ) ? $s['captcha'] : 'inherit', array( 'inherit', 'none', 'turnstile', 'recaptcha_v2', 'recaptcha_v3' ), 'inherit' ),
			'webhook_url'      => isset( $s['webhook_url'] ) && wp_http_validate_url( (string) $s['webhook_url'] ) ? esc_url_raw( (string) $s['webhook_url'] ) : '',
			'webhook_secret'   => self::text( isset( $s['webhook_secret'] ) ? $s['webhook_secret'] : '', 128 ),
			'webhook_partial'  => ! empty( $s['webhook_partial'] ),
		);
	}

	// -------------------------------------------------------------------------------------------
	// 1.x conversion
	// -------------------------------------------------------------------------------------------

	private static function from_v1( array $v1 ) {
		$steps = array();
		foreach ( isset( $v1['steps'] ) && is_array( $v1['steps'] ) ? $v1['steps'] : array() as $i => $s ) {
			if ( ! is_array( $s ) ) {
				continue;
			}
			$sid    = 's' . ( isset( $s['step_id'] ) && (int) $s['step_id'] ? (int) $s['step_id'] : $i + 1 );
			$fields = array();
			$choice = '';
			foreach ( isset( $s['fields'] ) && is_array( $s['fields'] ) ? $s['fields'] : array() as $f ) {
				$type = isset( $f['type'] ) ? $f['type'] : 'text';
				$new  = array(
					'id'       => isset( $f['field_id'] ) ? $f['field_id'] : '',
					'label'    => isset( $f['label'] ) ? $f['label'] : '',
					'required' => ! empty( $f['required'] ),
					'width'    => isset( $f['field_width'] ) ? $f['field_width'] : 'full',
				);
				if ( 'cards' === $type || 'radio' === $type ) {
					$new['type']    = 'choice';
					$new['options'] = isset( $f['options'] ) ? (string) $f['options'] : '';
					$mode           = isset( $f['display_mode'] ) ? $f['display_mode'] : 'default';
					$new['display'] = in_array( $mode, array( 'cards', 'dropdown', 'list' ), true ) ? $mode : ( 'cards' === $type ? 'cards' : 'list' );
					$choice         = $choice ? $choice : $new['id'];
				} elseif ( 'message' === $type ) {
					$new['type']    = 'message';
					$new['content'] = esc_html( $new['label'] );
					$new['label']   = '';
				} elseif ( 'consent' === $type ) {
					$new['type']         = 'consent';
					$new['consent_text'] = isset( $f['consent_text'] ) ? $f['consent_text'] : $new['label'];
					$new['link_text']    = isset( $f['link_text'] ) ? $f['link_text'] : '';
					$new['link_url']     = isset( $f['link_url'] ) ? $f['link_url'] : '';
				} else {
					$new['type'] = in_array( $type, array( 'text', 'email', 'phone', 'textarea', 'file' ), true ) ? $type : 'text';
				}
				$fields[] = $new;
			}

			// 1.x: "if the answer in this step is X, go to step Y" (choice field of the step).
			$routes = array();
			$rules  = isset( $s['logic_rules'] ) && is_array( $s['logic_rules'] ) ? $s['logic_rules'] : array();
			if ( ! empty( $s['logic_target'] ) && isset( $s['logic_value'] ) && '' !== $s['logic_value'] ) {
				$rules[] = array( 'target' => $s['logic_target'], 'value' => $s['logic_value'] );
			}
			foreach ( $rules as $r ) {
				if ( $choice && ! empty( $r['target'] ) ) {
					$routes[] = array( 'when' => array( 'field' => $choice, 'op' => 'eq', 'value' => $r['value'] ), 'goto' => 's' . (int) $r['target'] );
				}
			}

			$steps[] = array(
				'id'       => $sid,
				'title'    => isset( $s['title'] ) ? $s['title'] : '',
				'fields'   => $fields,
				'routes'   => $routes,
				'next'     => ! empty( $s['next_step'] ) ? 's' . (int) $s['next_step'] : '',
				'terminal' => isset( $s['terminal'] ) && 'reset' === $s['terminal'] ? 'reset' : '',
			);
		}

		$old = isset( $v1['settings'] ) && is_array( $v1['settings'] ) ? $v1['settings'] : array();
		$settings = array(
			'theme'      => isset( $old['theme'] ) && 'hvac_3d' === $old['theme'] ? 'dark' : 'light',
			'primary'    => isset( $old['primary_color'] ) ? $old['primary_color'] : '',
			'accent'     => isset( $old['accent_color'] ) ? $old['accent_color'] : '',
			'background' => isset( $old['background_color'] ) ? $old['background_color'] : '',
			'text'       => isset( $old['text_color'] ) ? $old['text_color'] : '',
			'font'       => isset( $old['font_family'] ) ? $old['font_family'] : 'inherit',
			'language'   => isset( $old['form_language'] ) ? $old['form_language'] : 'auto',
			'captcha'    => isset( $old['captcha_method'] ) && 'custom' !== $old['captcha_method'] ? $old['captcha_method'] : 'inherit',
		);

		if ( 'dark' === $settings['theme'] ) {
			$settings['background'] = $settings['background'] ? $settings['background'] : '#0b1220';
			$settings['text']       = $settings['text'] ? $settings['text'] : '#e2e8f0';
		}

		return array( 'schema' => 2, 'steps' => $steps, 'settings' => $settings );
	}

	// -------------------------------------------------------------------------------------------
	// Templates
	// -------------------------------------------------------------------------------------------

	public static function templates() {
		$de = 0 === strpos( get_locale(), 'de' );
		$t  = function ( $en, $german ) use ( $de ) {
			return $de ? $german : $en;
		};
		$contact_step = array(
			'id'     => 'contact',
			'title'  => $t( 'Your contact details', 'Ihre Kontaktdaten' ),
			'fields' => array(
				array( 'id' => 'full_name', 'type' => 'text', 'label' => $t( 'Name', 'Name' ), 'required' => true, 'width' => 'half' ),
				array( 'id' => 'phone', 'type' => 'phone', 'label' => $t( 'Phone', 'Telefon' ), 'width' => 'half' ),
				array( 'id' => 'email', 'type' => 'email', 'label' => $t( 'E-mail', 'E-Mail' ), 'required' => true ),
				array( 'id' => 'message', 'type' => 'textarea', 'label' => $t( 'Anything else we should know?', 'Möchten Sie uns noch etwas mitteilen?' ) ),
				array( 'id' => 'privacy', 'type' => 'consent', 'required' => true, 'consent_text' => $t( 'I agree that my details are used to answer my request.', 'Ich bin einverstanden, dass meine Angaben zur Bearbeitung meiner Anfrage verwendet werden.' ), 'link_text' => $t( 'Privacy policy', 'Datenschutzerklärung' ), 'link_url' => get_privacy_policy_url() ),
			),
		);

		return array(
			'hvac'    => array(
				'title' => $t( 'Heating & cooling consultation', 'Beratung Klima & Heizung' ),
				'icon'  => '❄️',
				'form'  => array(
					'schema'   => 2,
					'settings' => array( 'theme' => 'light', 'primary' => '#0369a1', 'accent' => '#f97316', 'review' => true ),
					'steps'    => array(
						array(
							'id'     => 'service',
							'title'  => $t( 'How can we help?', 'Wobei dürfen wir helfen?' ),
							'fields' => array(
								array(
									'id' => 'service', 'type' => 'choice', 'display' => 'cards', 'required' => true, 'label' => $t( 'Service', 'Leistung' ),
									'options' => array(
										array( 'label' => $t( 'New installation', 'Neuanlage' ), 'value' => 'new', 'icon' => '🏗️' ),
										array( 'label' => $t( 'Repair', 'Reparatur' ), 'value' => 'repair', 'icon' => '🛠️' ),
										array( 'label' => $t( 'Maintenance', 'Wartung' ), 'value' => 'maintenance', 'icon' => '🧰' ),
									),
								),
							),
							'routes' => array(
								array( 'when' => array( 'field' => 'service', 'op' => 'eq', 'value' => 'repair' ), 'goto' => 'repair' ),
								array( 'when' => array( 'field' => 'service', 'op' => 'eq', 'value' => 'maintenance' ), 'goto' => 'maintenance' ),
							),
						),
						array(
							'id' => 'new', 'title' => $t( 'Your project', 'Ihr Projekt' ), 'next' => 'contact',
							'fields' => array(
								array( 'id' => 'system', 'type' => 'choice', 'display' => 'cards', 'required' => true, 'label' => $t( 'System', 'Anlage' ), 'options' => array(
									array( 'label' => $t( 'Air conditioning', 'Klimaanlage' ), 'icon' => '❄️' ),
									array( 'label' => $t( 'Heat pump', 'Wärmepumpe' ), 'icon' => '♨️' ),
									array( 'label' => $t( 'Refrigeration', 'Kältetechnik' ), 'icon' => '🧊' ),
									array( 'label' => $t( 'Not sure yet', 'Noch unsicher' ), 'icon' => '💡' ),
								) ),
								array( 'id' => 'rooms', 'type' => 'range', 'label' => $t( 'Number of rooms', 'Anzahl Räume' ), 'min' => 1, 'max' => 12, 'default' => '2' ),
								array( 'id' => 'area', 'type' => 'number', 'label' => $t( 'Area', 'Fläche' ), 'unit' => 'm²', 'width' => 'half' ),
								array( 'id' => 'postcode', 'type' => 'text', 'label' => $t( 'Postcode', 'PLZ' ), 'width' => 'half', 'required' => true ),
								array( 'id' => 'timeline', 'type' => 'choice', 'display' => 'buttons', 'label' => $t( 'When?', 'Wann?' ), 'options' => $t( 'As soon as possible, Within 3 months, Just planning', 'So schnell wie möglich, In den nächsten 3 Monaten, Erst in Planung' ) ),
							),
						),
						array(
							'id' => 'repair', 'title' => $t( 'What is wrong?', 'Was ist das Problem?' ), 'next' => 'contact',
							'fields' => array(
								array( 'id' => 'issue', 'type' => 'choice', 'display' => 'list', 'multiple' => true, 'required' => true, 'label' => $t( 'Symptoms', 'Symptome' ), 'options' => $t( 'Does not cool, Does not heat, Water leak, Strange noise, Error code', 'Kühlt nicht, Heizt nicht, Wasseraustritt, Ungewöhnliche Geräusche, Fehlercode' ) ),
								array( 'id' => 'error_code', 'type' => 'text', 'label' => $t( 'Error code', 'Fehlercode' ), 'show_if' => array( 'match' => 'all', 'rules' => array( array( 'field' => 'issue', 'op' => 'contains', 'value' => $t( 'Error code', 'Fehlercode' ) ) ) ) ),
								array( 'id' => 'photos', 'type' => 'file', 'label' => $t( 'Photos (optional)', 'Fotos (optional)' ), 'accept' => 'jpg,jpeg,png,pdf', 'max_files' => 3 ),
							),
						),
						array(
							'id' => 'maintenance', 'title' => $t( 'Maintenance', 'Wartung' ),
							'fields' => array(
								array( 'id' => 'units', 'type' => 'number', 'label' => $t( 'Number of units', 'Anzahl Geräte' ), 'min' => 1, 'width' => 'half' ),
								array( 'id' => 'last_service', 'type' => 'date', 'label' => $t( 'Last service', 'Letzte Wartung' ), 'width' => 'half' ),
							),
						),
						$contact_step,
					),
				),
			),
			'contact' => array(
				'title' => $t( 'Contact form', 'Kontaktformular' ),
				'icon'  => '✉️',
				'form'  => array(
					'schema' => 2,
					'steps'  => array(
						array(
							'id' => 'topic', 'title' => $t( 'Your topic', 'Ihr Anliegen' ),
							'fields' => array(
								array( 'id' => 'topic', 'type' => 'choice', 'display' => 'buttons', 'required' => true, 'label' => $t( 'Topic', 'Thema' ), 'options' => $t( 'Quote, Service, Question, Other', 'Angebot, Service, Frage, Sonstiges' ) ),
							),
						),
						$contact_step,
					),
				),
			),
			'feedback' => array(
				'title' => $t( 'Customer feedback', 'Kundenfeedback' ),
				'icon'  => '⭐',
				'form'  => array(
					'schema' => 2,
					'steps'  => array(
						array(
							'id' => 'rate', 'title' => $t( 'How did we do?', 'Wie zufrieden waren Sie?' ),
							'fields' => array(
								array( 'id' => 'rating', 'type' => 'rating', 'required' => true, 'label' => $t( 'Overall rating', 'Gesamtbewertung' ) ),
								array( 'id' => 'improve', 'type' => 'textarea', 'label' => $t( 'What can we improve?', 'Was können wir besser machen?' ), 'show_if' => array( 'match' => 'all', 'rules' => array( array( 'field' => 'rating', 'op' => 'lt', 'value' => '4' ) ) ) ),
							),
						),
						array(
							'id' => 'contact', 'title' => $t( 'May we contact you?', 'Dürfen wir Sie kontaktieren?' ),
							'fields' => array(
								array( 'id' => 'email', 'type' => 'email', 'label' => $t( 'E-mail (optional)', 'E-Mail (optional)' ) ),
							),
						),
					),
				),
			),
			'blank'   => array(
				'title' => $t( 'Blank form', 'Leeres Formular' ),
				'icon'  => '➕',
				'form'  => array( 'schema' => 2, 'steps' => array( array( 'id' => 's1', 'title' => $t( 'Step 1', 'Schritt 1' ), 'fields' => array() ) ) ),
			),
		);
	}

	// -------------------------------------------------------------------------------------------
	// Helpers
	// -------------------------------------------------------------------------------------------

	public static function fields_flat( array $form ) {
		$out = array();
		foreach ( $form['steps'] as $step ) {
			foreach ( $step['fields'] as $field ) {
				$field['step'] = $step['id'];
				$out[ $field['id'] ] = $field;
			}
		}
		return $out;
	}

	private static function key( $value, $fallback ) {
		$value = substr( preg_replace( '/[^a-z0-9_]/', '', strtolower( str_replace( '-', '_', (string) $value ) ) ), 0, 40 );
		return '' !== $value ? $value : $fallback;
	}

	private static function text( $value, $max, $multiline = false ) {
		$value = $multiline ? sanitize_textarea_field( (string) $value ) : sanitize_text_field( (string) $value );
		return function_exists( 'mb_substr' ) ? mb_substr( $value, 0, $max ) : substr( $value, 0, $max );
	}

	private static function choice( $value, array $allowed, $fallback ) {
		return in_array( $value, $allowed, true ) ? $value : $fallback;
	}
}
