<?php
/**
 * Frontend: [smlf_form id="1"] renders a container with the form config; assets/public/form.js
 * builds the form. Assets load only on pages with a form.
 *
 * @package Smart_Multistep_Lead_Forms
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SMLF_Public {

	public static function init() {
		add_shortcode( 'smlf_form', array( __CLASS__, 'shortcode' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'register_assets' ) );
	}

	public static function register_assets() {
		wp_register_style( 'smlf-form', SMLF_URL . 'assets/public/form.css', array(), SMLF_VERSION );
		wp_register_script( 'smlf-form', SMLF_URL . 'assets/public/form.js', array(), SMLF_VERSION, true );
	}

	public static function shortcode( $atts ) {
		$atts = shortcode_atts( array( 'id' => 0 ), $atts, 'smlf_form' );
		$row  = SMLF_Forms::get( absint( $atts['id'] ), true );
		if ( ! $row ) {
			return current_user_can( 'manage_options' ) ? '<p><em>' . esc_html__( 'Lead form not found or not published.', 'smart-multistep-lead-forms' ) . '</em></p>' : '';
		}
		if ( ! wp_style_is( 'smlf-form', 'registered' ) ) {
			self::register_assets();
		}
		wp_enqueue_style( 'smlf-form' );
		wp_enqueue_script( 'smlf-form' );

		$config  = self::config( $row );
		$captcha = $config['captcha'];
		if ( 'turnstile' === $captcha['method'] ) {
			wp_enqueue_script( 'smlf-turnstile', 'https://challenges.cloudflare.com/turnstile/v0/api.js?render=explicit', array(), null, true );
		} elseif ( 'recaptcha_v2' === $captcha['method'] ) {
			wp_enqueue_script( 'smlf-recaptcha', 'https://www.google.com/recaptcha/api.js?render=explicit', array(), null, true );
		} elseif ( 'recaptcha_v3' === $captcha['method'] ) {
			wp_enqueue_script( 'smlf-recaptcha', 'https://www.google.com/recaptcha/api.js?render=' . rawurlencode( $captcha['site_key'] ), array(), null, true );
		}

		return '<div class="smlf-root" id="smlf-' . (int) $row['id'] . '-' . wp_rand( 1000, 9999 ) . '" data-smlf="' . esc_attr( wp_json_encode( $config ) ) . '">'
			. '<noscript>' . esc_html__( 'Please enable JavaScript to use this form.', 'smart-multistep-lead-forms' ) . '</noscript></div>';
	}

	/** Everything form.js needs (no secrets). Texts in the form's language. */
	public static function config( array $row, $preview = false ) {
		$form     = $row['form'];
		$s        = $form['settings'];
		$map      = array( 'de' => 'de_DE', 'en' => 'en_US', 'fa' => 'fa_IR' );
		$switched = isset( $map[ $s['language'] ] ) && function_exists( 'switch_to_locale' ) && determine_locale() !== $map[ $s['language'] ] ? switch_to_locale( $map[ $s['language'] ] ) : false;
		$rtl      = 'fa' === $s['language'] || ( 'auto' === $s['language'] && is_rtl() );

		$public_form = $form;
		unset( $public_form['settings']['webhook_url'], $public_form['settings']['webhook_secret'], $public_form['settings']['admin_emails'],
			$public_form['settings']['admin_subject'], $public_form['settings']['customer_subject'], $public_form['settings']['customer_body'] );

		$method = SMLF_Security::captcha_method( $form );
		$config = array(
			'id'       => (int) $row['id'],
			'ajax'     => admin_url( 'admin-ajax.php' ),
			'stamp'    => SMLF_Security::render_stamp( (int) $row['id'] ),
			'preview'  => (bool) $preview,
			'rtl'      => $rtl,
			'form'     => $public_form,
			'partial'  => (bool) get_option( 'smlf_enable_partial', 1 ) && $s['save_progress'],
			'captcha'  => array( 'method' => $method, 'site_key' => 'none' === $method ? '' : (string) get_option( 'smlf_captcha_site_key' ) ),
			'upload'   => array( 'max_mb' => (int) get_option( 'smlf_max_file_size_mb', 5 ), 'max_count' => (int) get_option( 'smlf_max_file_count', 3 ) ),
			'i18n'     => array(
				'next'        => $s['next_label'] ? $s['next_label'] : __( 'Next', 'smart-multistep-lead-forms' ),
				'back'        => $s['back_label'] ? $s['back_label'] : __( 'Back', 'smart-multistep-lead-forms' ),
				'submit'      => $s['submit_label'] ? $s['submit_label'] : __( 'Send request', 'smart-multistep-lead-forms' ),
				'review'      => __( 'Check your answers', 'smart-multistep-lead-forms' ),
				'edit'        => __( 'Edit', 'smart-multistep-lead-forms' ),
				'required'    => __( 'This field is required.', 'smart-multistep-lead-forms' ),
				'confirm'     => __( 'Please confirm.', 'smart-multistep-lead-forms' ),
				'email'       => __( 'Please enter a valid e-mail address.', 'smart-multistep-lead-forms' ),
				'phone'       => __( 'Please enter a valid phone number.', 'smart-multistep-lead-forms' ),
				'number'      => __( 'Please enter a number.', 'smart-multistep-lead-forms' ),
				'url'         => __( 'Please enter a valid web address.', 'smart-multistep-lead-forms' ),
				'choose'      => __( 'Please choose', 'smart-multistep-lead-forms' ),
				'step'        => __( 'Step %1$d of %2$d', 'smart-multistep-lead-forms' ),
				'sending'     => __( 'Sending...', 'smart-multistep-lead-forms' ),
				'error'       => __( 'Something went wrong. Please try again.', 'smart-multistep-lead-forms' ),
				'resume'      => __( 'Welcome back! Continue where you left off?', 'smart-multistep-lead-forms' ),
				'resume_yes'  => __( 'Continue', 'smart-multistep-lead-forms' ),
				'resume_no'   => __( 'Start over', 'smart-multistep-lead-forms' ),
				'restart'     => __( 'Start over', 'smart-multistep-lead-forms' ),
				'files'       => __( 'Choose files or drop them here', 'smart-multistep-lead-forms' ),
				'too_big'     => __( 'Files may be at most %d MB.', 'smart-multistep-lead-forms' ),
				'too_many'    => __( 'Please upload at most %d files.', 'smart-multistep-lead-forms' ),
				'street'      => __( 'Street and number', 'smart-multistep-lead-forms' ),
				'zip'         => __( 'Postcode', 'smart-multistep-lead-forms' ),
				'city'        => __( 'City', 'smart-multistep-lead-forms' ),
				'optional'    => __( 'optional', 'smart-multistep-lead-forms' ),
				'stars'       => __( '%d of %d stars', 'smart-multistep-lead-forms' ),
				'remove'      => __( 'Remove', 'smart-multistep-lead-forms' ),
				'robot'       => __( 'Please confirm that you are not a robot.', 'smart-multistep-lead-forms' ),
				'preview'     => __( 'Preview - nothing is sent.', 'smart-multistep-lead-forms' ),
			),
		);

		if ( $switched ) {
			restore_previous_locale();
		}
		return $config;
	}
}
