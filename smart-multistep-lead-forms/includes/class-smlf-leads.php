<?php
/**
 * Leads: storage, CRM status / notes / follow-ups, list queries, deletion (incl. files).
 *
 * lead_data JSON (2.0): { answers: {field_id: value}, path: [step ids], files: {field_id: [..]} }
 * 1.x leads stored the answers directly; get() reads both.
 *
 * @package Smart_Multistep_Lead_Forms
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SMLF_Leads {

	public static function table() {
		global $wpdb;
		return $wpdb->prefix . 'smlf_leads';
	}

	/** CRM statuses from the settings ("key:Label" per line). */
	public static function statuses() {
		$out = array();
		foreach ( preg_split( '/\r\n|\n/', (string) get_option( 'smlf_lead_statuses', '' ) ) as $line ) {
			$parts = array_map( 'trim', explode( ':', $line, 2 ) );
			$key   = sanitize_key( $parts[0] );
			if ( '' !== $key ) {
				$out[ $key ] = isset( $parts[1] ) && '' !== $parts[1] ? $parts[1] : ucfirst( $key );
			}
		}
		return $out ? $out : array( 'new' => __( 'New', 'smart-multistep-lead-forms' ) );
	}

	public static function get( $id ) {
		global $wpdb;
		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::table() . ' WHERE id = %d', $id ), ARRAY_A );
		return $row ? self::decode( $row ) : null;
	}

	public static function by_token( $form_id, $token ) {
		global $wpdb;
		if ( strlen( (string) $token ) < 30 ) {
			return null;
		}
		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::table() . ' WHERE form_id = %d AND token = %s', $form_id, $token ), ARRAY_A );
		return $row ? self::decode( $row ) : null;
	}

	private static function decode( array $row ) {
		$data = json_decode( (string) $row['lead_data'], true );
		$data = is_array( $data ) ? $data : array();
		if ( ! isset( $data['answers'] ) ) { // 1.x
			$files = isset( $data['uploaded_files'] ) ? $data['uploaded_files'] : array();
			unset( $data['uploaded_files'] );
			$data = array( 'answers' => $data, 'path' => array(), 'files' => array(), 'legacy_files' => $files );
		}
		$row['answers'] = isset( $data['answers'] ) && is_array( $data['answers'] ) ? $data['answers'] : array();
		$row['path']    = isset( $data['path'] ) ? (array) $data['path'] : array();
		$row['files']   = isset( $data['files'] ) && is_array( $data['files'] ) ? $data['files'] : array();
		$row['legacy_files'] = isset( $data['legacy_files'] ) ? (array) $data['legacy_files'] : array();
		return $row;
	}

	/** Inserts or updates a lead; returns [id, token]. */
	public static function store( $form_id, array $answers, array $path, array $files, $status, $current_step, $token = '', array $meta = array() ) {
		global $wpdb;
		$existing = $token ? self::by_token( $form_id, $token ) : null;
		$contact  = self::contact( $answers );
		$data     = array(
			'lead_data'    => wp_json_encode( array( 'answers' => $answers, 'path' => array_values( $path ), 'files' => $existing ? array_merge( $existing['files'], $files ) : $files ) ),
			'status'       => $status,
			'current_step' => substr( (string) $current_step, 0, 40 ),
			'name'         => $contact['name'],
			'email'        => $contact['email'],
			'phone'        => $contact['phone'],
			'updated_at'   => current_time( 'mysql' ),
		);
		if ( 'completed' === $status ) {
			$data['completed_at'] = current_time( 'mysql' );
		}
		if ( $existing && 'completed' !== $existing['status'] ) {
			// Source data the first (partial) save did not have yet.
			foreach ( array( 'page_url', 'referrer', 'utm_source', 'utm_medium', 'utm_campaign' ) as $k ) {
				if ( empty( $existing[ $k ] ) && ! empty( $meta[ $k ] ) ) {
					$data[ $k ] = in_array( $k, array( 'page_url', 'referrer' ), true ) ? esc_url_raw( $meta[ $k ] ) : sanitize_text_field( $meta[ $k ] );
				}
			}
			$wpdb->update( self::table(), $data, array( 'id' => (int) $existing['id'] ) );
			return array( (int) $existing['id'], $existing['token'] );
		}
		$token = wp_generate_password( 40, false );
		$wpdb->insert( self::table(), array_merge( $data, array(
			'form_id'      => (int) $form_id,
			'token'        => $token,
			'lead_status'  => key( self::statuses() ),
			'ip_address'   => SMLF_Security::ip_anonymized(),
			'user_agent'   => substr( isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '', 0, 500 ),
			'referrer'     => isset( $meta['referrer'] ) ? esc_url_raw( $meta['referrer'] ) : '',
			'page_url'     => isset( $meta['page_url'] ) ? esc_url_raw( $meta['page_url'] ) : '',
			'utm_source'   => isset( $meta['utm_source'] ) ? sanitize_text_field( $meta['utm_source'] ) : '',
			'utm_medium'   => isset( $meta['utm_medium'] ) ? sanitize_text_field( $meta['utm_medium'] ) : '',
			'utm_campaign' => isset( $meta['utm_campaign'] ) ? sanitize_text_field( $meta['utm_campaign'] ) : '',
			'created_at'   => current_time( 'mysql' ),
		) ) );
		return array( (int) $wpdb->insert_id, $token );
	}

	/** Name / e-mail / phone from the answers (by field type first, then by id). */
	public static function contact( array $answers ) {
		$out = array( 'name' => '', 'email' => '', 'phone' => '' );
		foreach ( $answers as $k => $v ) {
			if ( is_array( $v ) ) {
				continue;
			}
			$v = (string) $v;
			if ( '' === $out['email'] && is_email( $v ) ) {
				$out['email'] = $v;
			} elseif ( '' === $out['phone'] && preg_match( '/(phone|tel|mobil|handy)/', $k ) ) {
				$out['phone'] = substr( $v, 0, 50 );
			} elseif ( '' === $out['name'] && preg_match( '/(^name$|full_?name|your_?name|vorname|nachname|first_?name|last_?name)/', $k ) ) {
				$out['name'] = substr( $v, 0, 255 );
			}
		}
		return $out;
	}

	// -------------------------------------------------------------------------------------------
	// CRM
	// -------------------------------------------------------------------------------------------

	public static function add_note( $lead_id, $note, $from = '', $to = '' ) {
		global $wpdb;
		$wpdb->insert( $wpdb->prefix . 'smlf_lead_notes', array(
			'lead_id'     => (int) $lead_id,
			'user_id'     => get_current_user_id(),
			'note'        => (string) $note,
			'status_from' => (string) $from,
			'status_to'   => (string) $to,
			'created_at'  => current_time( 'mysql' ),
		) );
	}

	public static function notes( $lead_id ) {
		global $wpdb;
		return (array) $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}smlf_lead_notes WHERE lead_id = %d ORDER BY created_at DESC, id DESC", $lead_id ) );
	}

	/** Status / note / follow-up change with a history entry. $followup: null = unchanged, '' = remove. */
	public static function update_crm( $id, $status, $note = '', $followup = null ) {
		global $wpdb;
		$lead = self::get( $id );
		if ( ! $lead || ! isset( self::statuses()[ $status ] ) ) {
			return false;
		}
		$data = array( 'lead_status' => $status, 'updated_at' => current_time( 'mysql' ) );
		if ( null !== $followup ) {
			$data['followup_at'] = '' === $followup ? null : $followup;
		}
		$wpdb->update( self::table(), $data, array( 'id' => (int) $id ) );
		$changed  = $lead['lead_status'] !== $status;
		$follow_t = null !== $followup && '' !== $followup && $followup !== $lead['followup_at'];
		if ( $changed || '' !== $note || $follow_t ) {
			$text = $note;
			if ( $follow_t ) {
				$text = trim( $text . "\n" . sprintf( __( 'Follow-up: %s', 'smart-multistep-lead-forms' ), mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $followup ) ) );
			}
			self::add_note( $id, $text, $changed ? $lead['lead_status'] : '', $changed ? $status : '' );
		}
		return true;
	}

	public static function delete( $ids ) {
		global $wpdb;
		$n = 0;
		foreach ( array_filter( array_map( 'intval', (array) $ids ) ) as $id ) {
			$lead = self::get( $id );
			if ( $lead ) {
				SMLF_Uploads::delete_files( $lead['files'] );
			}
			$n += (int) $wpdb->delete( self::table(), array( 'id' => $id ) );
			$wpdb->delete( $wpdb->prefix . 'smlf_lead_notes', array( 'lead_id' => $id ) );
			$wpdb->delete( $wpdb->prefix . 'smlf_email_logs', array( 'lead_id' => $id ) );
		}
		return $n;
	}

	// -------------------------------------------------------------------------------------------
	// Lists
	// -------------------------------------------------------------------------------------------

	/** @param array $f form, state (completed|partial|all), status, q, from, to, due, orderby, order, per_page, paged */
	public static function query( array $f, $limit = true ) {
		global $wpdb;
		$where  = array( '1=1' );
		$params = array();
		if ( ! empty( $f['form'] ) ) {
			$where[]  = 'form_id = %d';
			$params[] = (int) $f['form'];
		}
		if ( 'partial' === $f['state'] ) {
			$where[] = "status IN ('started','partial')";
		} elseif ( 'completed' === $f['state'] ) {
			$where[] = "status = 'completed'";
		}
		if ( ! empty( $f['status'] ) ) {
			$where[]  = 'lead_status = %s';
			$params[] = $f['status'];
		}
		if ( ! empty( $f['due'] ) ) {
			$where[]  = 'followup_at IS NOT NULL AND followup_at <= %s';
			$params[] = gmdate( 'Y-m-d 23:59:59', strtotime( current_time( 'mysql' ) ) );
		}
		if ( ! empty( $f['q'] ) ) {
			$like     = '%' . $wpdb->esc_like( $f['q'] ) . '%';
			$where[]  = '(name LIKE %s OR email LIKE %s OR phone LIKE %s OR lead_data LIKE %s OR id = %d)';
			$params   = array_merge( $params, array( $like, $like, $like, $like, (int) $f['q'] ) );
		}
		if ( ! empty( $f['from'] ) ) {
			$where[]  = 'created_at >= %s';
			$params[] = $f['from'] . ' 00:00:00';
		}
		if ( ! empty( $f['to'] ) ) {
			$where[]  = 'created_at <= %s';
			$params[] = $f['to'] . ' 23:59:59';
		}
		$sql_where = implode( ' AND ', $where );
		$prep      = function ( $sql ) use ( $wpdb, $params ) {
			return $params ? $wpdb->prepare( $sql, $params ) : $sql;
		};
		$total   = (int) $wpdb->get_var( $prep( 'SELECT COUNT(*) FROM ' . self::table() . " WHERE $sql_where" ) );
		$orderby = in_array( $f['orderby'], array( 'created_at', 'name', 'lead_status', 'followup_at', 'id' ), true ) ? $f['orderby'] : 'created_at';
		$order   = 'ASC' === $f['order'] ? 'ASC' : 'DESC';
		$sql     = 'SELECT * FROM ' . self::table() . " WHERE $sql_where ORDER BY " . ( 'followup_at' === $orderby ? 'followup_at IS NULL, ' : '' ) . "$orderby $order, id DESC";
		if ( $limit ) {
			$sql .= $wpdb->prepare( ' LIMIT %d OFFSET %d', $f['per_page'], ( $f['paged'] - 1 ) * $f['per_page'] );
		}
		$rows = array_map( array( __CLASS__, 'decode' ), (array) $wpdb->get_results( $prep( $sql ), ARRAY_A ) );
		return array( $rows, $total );
	}

	public static function counts( $form_id = 0 ) {
		global $wpdb;
		$w   = $form_id ? $wpdb->prepare( ' AND form_id = %d', $form_id ) : '';
		$t   = self::table();
		$now = current_time( 'mysql' );
		return array(
			'completed' => (int) $wpdb->get_var( "SELECT COUNT(*) FROM $t WHERE status = 'completed'$w" ),
			'partial'   => (int) $wpdb->get_var( "SELECT COUNT(*) FROM $t WHERE status IN ('started','partial')$w" ),
			'new'       => (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $t WHERE status = 'completed' AND lead_status = %s$w", key( self::statuses() ) ) ),
			'due'       => (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $t WHERE followup_at IS NOT NULL AND followup_at <= %s$w", gmdate( 'Y-m-d 23:59:59', strtotime( $now ) ) ) ),
			'month'     => (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $t WHERE status = 'completed' AND created_at >= %s$w", gmdate( 'Y-m-01 00:00:00', strtotime( $now ) ) ) ),
		);
	}

	/** Display value of an answer (choice values -> labels, arrays joined). */
	public static function display_value( $value, $field = null ) {
		if ( $field && 'choice' === $field['type'] ) {
			$map = array();
			foreach ( $field['options'] as $o ) {
				$map[ $o['value'] ] = $o['label'];
			}
			$value = array_map( function ( $v ) use ( $map ) {
				return isset( $map[ $v ] ) ? $map[ $v ] : $v;
			}, (array) $value );
		}
		if ( $field && 'address' === $field['type'] && is_array( $value ) ) {
			return trim( implode( ', ', array_filter( array( isset( $value['street'] ) ? $value['street'] : '', trim( ( isset( $value['zip'] ) ? $value['zip'] : '' ) . ' ' . ( isset( $value['city'] ) ? $value['city'] : '' ) ) ) ) ) );
		}
		if ( $field && 'consent' === $field['type'] ) {
			return $value ? __( 'Yes', 'smart-multistep-lead-forms' ) : __( 'No', 'smart-multistep-lead-forms' );
		}
		if ( $field && 'rating' === $field['type'] && '' !== (string) $value ) {
			return $value . ' / ' . $field['max_stars'];
		}
		return is_array( $value ) ? implode( ', ', array_map( 'strval', $value ) ) : (string) $value;
	}
}
