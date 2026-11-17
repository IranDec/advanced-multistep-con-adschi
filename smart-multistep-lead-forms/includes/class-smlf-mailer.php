<?php
/**
 * E-mails after a submission.
 *
 * - Admin notification: every answer (escaped), grouped by step, Reply-To = the visitor, link to the lead.
 * - Visitor confirmation: FIXED text - never anything the visitor typed (1.x repeated all answers to
 *   any typed address, which made the form usable for spam). Sent through the "KAS Contact
 *   Confirmation" plugin when it is installed, otherwise with the same rules built in:
 *   no links / spam text, 1 per address per 24 h, 10 per hour and 40 per day in total.
 *
 * @package Smart_Multistep_Lead_Forms
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SMLF_Mailer {

	const PLACEHOLDERS = array( '{site_name}', '{form_title}', '{date}' );

	public static function after_submit( $lead_id, array $form_row ) {
		$lead = SMLF_Leads::get( $lead_id );
		if ( ! $lead ) {
			return;
		}
		$form     = $form_row['form'];
		$s        = $form['settings'];
		$switched = self::switch_locale( $s['language'] );

		if ( $s['notify_admin'] ) {
			self::admin( $lead, $form_row );
		}
		if ( $s['confirm_customer'] && is_email( $lead['email'] ) ) {
			self::customer( $lead, $form_row );
		}

		if ( $switched ) {
			restore_previous_locale();
		}
	}

	private static function admin( array $lead, array $form_row ) {
		$form   = $form_row['form'];
		$s      = $form['settings'];
		$to     = $s['admin_emails'] ? array_map( 'trim', explode( ',', $s['admin_emails'] ) ) : array( (string) get_option( 'smlf_admin_email', get_option( 'admin_email' ) ) );
		$to     = array_values( array_filter( $to, 'is_email' ) );
		if ( ! $to ) {
			return;
		}
		$fields  = SMLF_Forms::fields_flat( $form );
		$subject = $s['admin_subject'] ? $s['admin_subject'] : sprintf( __( 'New lead #%1$d - %2$s', 'smart-multistep-lead-forms' ), $lead['id'], $form_row['title'] );
		$subject = self::fill( $subject, $form_row['title'] );
		$link    = admin_url( 'admin.php?page=smlf-leads&lead=' . (int) $lead['id'] );

		$rows = '';
		foreach ( $form['steps'] as $step ) {
			if ( $lead['path'] && ! in_array( $step['id'], $lead['path'], true ) ) {
				continue;
			}
			$step_rows = '';
			foreach ( $step['fields'] as $f ) {
				if ( in_array( $f['type'], array( 'message' ), true ) ) {
					continue;
				}
				if ( 'file' === $f['type'] ) {
					$n = isset( $lead['files'][ $f['id'] ] ) ? count( $lead['files'][ $f['id'] ] ) : 0;
					if ( ! $n ) {
						continue;
					}
					$value = sprintf( _n( '%d file (download in the lead view)', '%d files (download in the lead view)', $n, 'smart-multistep-lead-forms' ), $n );
				} elseif ( ! array_key_exists( $f['id'], $lead['answers'] ) || '' === SMLF_Leads::display_value( $lead['answers'][ $f['id'] ], $f ) ) {
					continue;
				} else {
					$value = SMLF_Leads::display_value( $lead['answers'][ $f['id'] ], $f );
				}
				$step_rows .= '<tr><td style="padding:8px 12px;color:#64748b;width:38%;vertical-align:top;border-bottom:1px solid #f1f5f9">' . esc_html( $f['label'] ? $f['label'] : $f['id'] ) . '</td>'
					. '<td style="padding:8px 12px;color:#0f172a;vertical-align:top;border-bottom:1px solid #f1f5f9">' . nl2br( esc_html( $value ) ) . '</td></tr>';
			}
			if ( $step_rows ) {
				$rows .= '<tr><td colspan="2" style="padding:14px 12px 6px;font-weight:bold;color:#0f172a">' . esc_html( $step['title'] ) . '</td></tr>' . $step_rows;
			}
		}

		$body = self::wrap(
			'<h2 style="margin:0 0 6px;font-size:19px;color:#0f172a">' . esc_html( sprintf( __( 'New lead #%d', 'smart-multistep-lead-forms' ), $lead['id'] ) ) . '</h2>'
			. '<p style="margin:0 0 16px;color:#64748b">' . esc_html( $form_row['title'] ) . ( $lead['page_url'] ? ' &middot; ' . esc_html( $lead['page_url'] ) : '' ) . '</p>'
			. '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="font-size:14px;border-collapse:collapse">' . $rows . '</table>'
			. '<p style="margin:22px 0 0"><a href="' . esc_url( $link ) . '" style="display:inline-block;background:#0f172a;color:#fff;text-decoration:none;padding:11px 20px;border-radius:8px;font-weight:bold">' . esc_html__( 'Open lead', 'smart-multistep-lead-forms' ) . '</a></p>'
		);
		$headers = array( 'Content-Type: text/html; charset=UTF-8' );
		if ( is_email( $lead['email'] ) ) {
			$headers[] = 'Reply-To: ' . str_replace( array( "\r", "\n", '<', '>', ',' ), '', $lead['name'] ? $lead['name'] : $lead['email'] ) . ' <' . $lead['email'] . '>';
		}
		foreach ( $to as $address ) {
			$ok = wp_mail( $address, $subject, $body, $headers );
			self::log( $lead['id'], $address, $subject, $ok ? 'sent' : 'failed', '' );
		}
	}

	private static function customer( array $lead, array $form_row ) {
		$email = strtolower( trim( $lead['email'] ) );
		$flat  = array();
		foreach ( $lead['answers'] as $k => $v ) {
			$flat[ $k ] = is_array( $v ) ? implode( ' ', array_map( 'strval', $v ) ) : (string) $v;
		}

		if ( class_exists( 'KAS_Contact_Confirmation' ) ) {
			$result = KAS_Contact_Confirmation::maybe_confirm( $email, $flat, 'smlf:' . (int) $form_row['id'] );
			self::log( $lead['id'], $email, __( 'Confirmation (KAS Contact Confirmation)', 'smart-multistep-lead-forms' ), 'sent' === $result ? 'sent' : 'skipped', 'sent' === $result ? '' : $result );
			return;
		}

		$reason = self::guard( $email, $flat );
		$s      = $form_row['form']['settings'];
		$subject = self::fill( $s['customer_subject'] ? $s['customer_subject'] : __( 'We have received your request - {site_name}', 'smart-multistep-lead-forms' ), $form_row['title'] );
		if ( 'ok' !== $reason ) {
			self::log( $lead['id'], $email, $subject, 'skipped', $reason );
			return;
		}
		$text = self::fill( $s['customer_body'] ? $s['customer_body'] : __( "Hello,\n\nthank you for your request. We have received it and will get back to you as soon as possible.\n\nKind regards\n{site_name}", 'smart-multistep-lead-forms' ), $form_row['title'] );
		$body = self::wrap( '<div style="font-size:15px;line-height:1.6;color:#0f172a">' . nl2br( esc_html( $text ) ) . '</div>'
			. '<p style="margin:22px 0 0;font-size:12px;color:#94a3b8">' . esc_html__( 'You did not send this request? Then someone entered your address in our form - you do not need to do anything.', 'smart-multistep-lead-forms' ) . '</p>' );
		$headers = array( 'Content-Type: text/html; charset=UTF-8', 'Auto-Submitted: auto-replied', 'X-Auto-Response-Suppress: All' );
		$ok      = wp_mail( $email, $subject, $body, $headers );
		if ( $ok ) {
			self::count_sent( $email );
		}
		self::log( $lead['id'], $email, $subject, $ok ? 'sent' : 'failed', '' );
	}

	/** 'ok' or the reason why no confirmation is sent. */
	private static function guard( $email, array $values ) {
		if ( SMLF_Security::looks_like_spam( $values ) ) {
			return 'link_or_spam_text';
		}
		if ( get_transient( 'smlf_conf_' . self::address_key( $email ) ) ) {
			return 'address_limit_24h';
		}
		$times = self::recent();
		$hour  = count( array_filter( $times, function ( $t ) {
			return $t > time() - HOUR_IN_SECONDS;
		} ) );
		if ( $hour >= 10 || count( $times ) >= 40 ) {
			return 'total_limit_paused';
		}
		return 'ok';
	}

	private static function address_key( $email ) {
		list( $local, $domain ) = array_pad( explode( '@', strtolower( $email ), 2 ), 2, '' );
		$local = preg_replace( '/\+.*$/', '', $local );
		if ( in_array( $domain, array( 'gmail.com', 'googlemail.com' ), true ) ) {
			$local  = str_replace( '.', '', $local );
			$domain = 'gmail.com';
		}
		return md5( $local . '@' . $domain );
	}

	private static function recent() {
		return array_values( array_filter( (array) get_option( 'smlf_confirm_times', array() ), function ( $t ) {
			return (int) $t > time() - DAY_IN_SECONDS;
		} ) );
	}

	private static function count_sent( $email ) {
		set_transient( 'smlf_conf_' . self::address_key( $email ), 1, DAY_IN_SECONDS );
		$times   = self::recent();
		$times[] = time();
		update_option( 'smlf_confirm_times', array_slice( $times, -100 ), false );
	}

	/** Only these placeholders exist; everything else in braces is removed. */
	private static function fill( $text, $form_title ) {
		$text = strtr( (string) $text, array(
			'{site_name}'  => wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ),
			'{form_title}' => $form_title,
			'{date}'       => date_i18n( get_option( 'date_format' ) ),
		) );
		return trim( preg_replace( '/[ \t]*\{[a-z0-9_]+\}/i', '', $text ) );
	}

	private static function wrap( $inner ) {
		$site = esc_html( wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ) );
		return '<!DOCTYPE html><html><head><meta charset="utf-8"></head><body style="margin:0;padding:24px 10px;background:#f1f5f9;font-family:Arial,Helvetica,sans-serif">'
			. '<table role="presentation" width="100%" cellpadding="0" cellspacing="0"><tr><td align="center">'
			. '<table role="presentation" width="620" cellpadding="0" cellspacing="0" style="width:100%;max-width:620px;background:#fff;border-radius:12px;overflow:hidden">'
			. '<tr><td style="height:4px;background:#0ea5e9;font-size:0;line-height:4px">&nbsp;</td></tr>'
			. '<tr><td style="padding:26px 28px">' . $inner . '</td></tr>'
			. '<tr><td style="padding:14px 28px;background:#f8fafc;color:#94a3b8;font-size:12px;text-align:center">' . $site . '</td></tr>'
			. '</table></td></tr></table></body></html>';
	}

	private static function log( $lead_id, $to, $subject, $status, $detail ) {
		global $wpdb;
		$wpdb->insert( "{$wpdb->prefix}smlf_email_logs", array(
			'lead_id'         => (int) $lead_id,
			'recipient_email' => substr( (string) $to, 0, 255 ),
			'subject'         => substr( (string) $subject, 0, 255 ),
			'body'            => (string) $detail,
			'status'          => $status,
			'sent_at'         => current_time( 'mysql' ),
		) );
	}

	private static function switch_locale( $lang ) {
		$map = array( 'de' => 'de_DE', 'en' => 'en_US', 'fa' => 'fa_IR' );
		if ( isset( $map[ $lang ] ) && function_exists( 'switch_to_locale' ) && determine_locale() !== $map[ $lang ] ) {
			return switch_to_locale( $map[ $lang ] );
		}
		return false;
	}
}
