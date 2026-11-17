<?php
/**
 * Abuse protection for the public endpoints: rate limits, honeypot, signed render time
 * (too fast = bot, too old = replay), captcha (fail closed), visitor IP.
 *
 * @package Smart_Multistep_Lead_Forms
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SMLF_Security {

	const MIN_SECONDS = 3;
	const MAX_AGE     = 2 * DAY_IN_SECONDS;

	/** Counts a hit; false when $max hits in $window seconds are exceeded for this IP + bucket. */
	public static function rate_ok( $bucket, $max, $window ) {
		$key   = 'smlf_rl_' . $bucket . '_' . md5( self::ip() );
		$count = (int) get_transient( $key );
		if ( $count >= $max ) {
			return false;
		}
		set_transient( $key, $count + 1, $window );
		return true;
	}

	/** Signed "rendered at" stamp put into the form config. */
	public static function render_stamp( $form_id ) {
		$t = time();
		return $t . '.' . substr( hash_hmac( 'sha256', $form_id . '|' . $t, wp_salt( 'nonce' ) ), 0, 24 );
	}

	/** @return true|WP_Error */
	public static function check_stamp( $form_id, $stamp ) {
		$parts = explode( '.', (string) $stamp );
		if ( 2 !== count( $parts ) || ! ctype_digit( $parts[0] ) ) {
			return new WP_Error( 'smlf_stamp', __( 'Please reload the page and try again.', 'smart-multistep-lead-forms' ) );
		}
		$expected = substr( hash_hmac( 'sha256', $form_id . '|' . $parts[0], wp_salt( 'nonce' ) ), 0, 24 );
		if ( ! hash_equals( $expected, $parts[1] ) ) {
			return new WP_Error( 'smlf_stamp', __( 'Please reload the page and try again.', 'smart-multistep-lead-forms' ) );
		}
		$age = time() - (int) $parts[0];
		if ( $age < self::MIN_SECONDS ) {
			return new WP_Error( 'smlf_fast', __( 'That was a bit too fast. Please try again.', 'smart-multistep-lead-forms' ) );
		}
		if ( $age > self::MAX_AGE ) {
			return new WP_Error( 'smlf_stamp', __( 'This page was open for a long time. Please reload it and try again.', 'smart-multistep-lead-forms' ) );
		}
		return true;
	}

	/** Effective captcha method of a form ('none', 'turnstile', 'recaptcha_v2', 'recaptcha_v3'). */
	public static function captcha_method( array $form ) {
		$m = isset( $form['settings']['captcha'] ) ? $form['settings']['captcha'] : 'inherit';
		if ( 'inherit' === $m ) {
			$m = (string) get_option( 'smlf_captcha_method', 'none' );
		}
		if ( ! in_array( $m, array( 'turnstile', 'recaptcha_v2', 'recaptcha_v3' ), true ) ) {
			return 'none';
		}
		// Without keys a captcha cannot work; treat as none (the settings page warns).
		return get_option( 'smlf_captcha_site_key' ) && get_option( 'smlf_captcha_secret_key' ) ? $m : 'none';
	}

	/** @return true|WP_Error - fail closed: missing token, network error or bad score = rejected */
	public static function verify_captcha( $method, $token ) {
		if ( 'none' === $method ) {
			return true;
		}
		$fail = new WP_Error( 'smlf_captcha', __( 'Please confirm that you are not a robot.', 'smart-multistep-lead-forms' ) );
		if ( '' === (string) $token ) {
			return $fail;
		}
		$endpoint = 'turnstile' === $method
			? 'https://challenges.cloudflare.com/turnstile/v0/siteverify'
			: 'https://www.google.com/recaptcha/api/siteverify';
		$response = wp_remote_post( $endpoint, array(
			'timeout' => 8,
			'body'    => array(
				'secret'   => (string) get_option( 'smlf_captcha_secret_key' ),
				'response' => (string) $token,
				'remoteip' => self::ip(),
			),
		) );
		if ( is_wp_error( $response ) ) {
			return $fail;
		}
		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( empty( $body['success'] ) ) {
			return $fail;
		}
		if ( 'recaptcha_v3' === $method && isset( $body['score'] ) && (float) $body['score'] < 0.5 ) {
			return $fail;
		}
		return true;
	}

	/** Links / typical scam markers in visitor input (used before any mail to a visitor address). */
	public static function looks_like_spam( array $values ) {
		foreach ( $values as $v ) {
			foreach ( (array) $v as $s ) {
				if ( preg_match( '~https?://|www\.|>>>|\[url|<a\s|\b[a-z0-9-]{2,}\.(?:cc|ru|cn|xyz|top|icu|click|link|shop|online|site|live|bond|sbs|cfd|tk|gq|ml)\b~i', (string) $s ) ) {
					return true;
				}
			}
		}
		return false;
	}

	/** Visitor IP; behind Cloudflare its header is used, X-Forwarded-For is not trusted. */
	public static function ip() {
		foreach ( array( 'HTTP_CF_CONNECTING_IP', 'REMOTE_ADDR' ) as $h ) {
			if ( ! empty( $_SERVER[ $h ] ) ) {
				$ip = trim( sanitize_text_field( wp_unslash( $_SERVER[ $h ] ) ) );
				if ( filter_var( $ip, FILTER_VALIDATE_IP ) ) {
					return $ip;
				}
			}
		}
		return '0.0.0.0';
	}

	/** Stored IP: the last part is removed (privacy), enough to recognise floods. */
	public static function ip_anonymized() {
		$ip = self::ip();
		if ( false !== strpos( $ip, ':' ) ) {
			return preg_replace( '/:[0-9a-f]*:[0-9a-f]*$/i', ':0:0', $ip );
		}
		return preg_replace( '/\.\d+$/', '.0', $ip );
	}
}
