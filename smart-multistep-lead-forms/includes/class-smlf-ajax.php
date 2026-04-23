<?php

class SMLF_Ajax {

	public function save_form_admin() {
		check_ajax_referer( 'smlf_admin_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Permission denied.' );
		}

		global $wpdb;
		$table_name = $wpdb->prefix . 'smlf_forms';

		$form_id   = isset( $_POST['form_id'] ) ? intval( $_POST['form_id'] ) : 0;
		// Normally we sanitize deep, but for complex JSON structure in this demo we use wp_unslash
		$form_data_raw = isset( $_POST['form_data'] ) ? wp_unslash( $_POST['form_data'] ) : '';

		$form_data = json_decode( $form_data_raw, true );
		$title = isset( $form_data['title'] ) ? sanitize_text_field( $form_data['title'] ) : 'New Form';

		if ( $form_id ) {
			$wpdb->update(
				$table_name,
				array(
					'title'     => $title,
					'form_data' => $form_data_raw,
				),
				array( 'id' => $form_id )
			);
			wp_send_json_success( array( 'form_id' => $form_id ) );
		} else {
			$wpdb->insert(
				$table_name,
				array(
					'title'     => $title,
					'form_data' => $form_data_raw,
				)
			);
			wp_send_json_success( array( 'form_id' => $wpdb->insert_id ) );
		}
	}

	public function verify_bot() {
		check_ajax_referer( 'smlf_public_nonce', 'nonce' );
		// Simple verification success for demo
		wp_send_json_success();
	}

	public function save_partial_lead() {
		check_ajax_referer( 'smlf_public_nonce', 'nonce' );

		$enable_partial = get_option( 'smlf_enable_partial', 1 );
		if ( ! $enable_partial ) {
			wp_send_json_success(); // Fake success if disabled
		}

		global $wpdb;
		$table_name = $wpdb->prefix . 'smlf_leads';

		$form_id = intval( $_POST['form_id'] );
		$lead_id = isset( $_POST['lead_id'] ) ? intval( $_POST['lead_id'] ) : 0;
		$data_raw = isset( $_POST['data'] ) ? wp_unslash( $_POST['data'] ) : array();

		// Extract email or phone if present
		$email = '';
		$phone = '';
		$structured_data = array();

		if ( is_array( $data_raw ) ) {
			foreach ( $data_raw as $field ) {
				$name = sanitize_text_field( $field['name'] );
				$val  = sanitize_text_field( $field['value'] );
				$structured_data[ $name ] = $val;

				if ( strpos( strtolower( $name ), 'email' ) !== false && is_email( $val ) ) {
					$email = $val;
				}
				if ( strpos( strtolower( $name ), 'phone' ) !== false ) {
					$phone = $val;
				}
			}
		}

		$lead_data_json = wp_json_encode( $structured_data );

		if ( $lead_id ) {
			$wpdb->update(
				$table_name,
				array(
					'lead_data' => $lead_data_json,
					'email'     => $email,
					'phone'     => $phone,
					'status'    => 'partial lead'
				),
				array( 'id' => $lead_id )
			);
			wp_send_json_success( array( 'lead_id' => $lead_id ) );
		} else {
			$wpdb->insert(
				$table_name,
				array(
					'form_id'   => $form_id,
					'lead_data' => $lead_data_json,
					'email'     => $email,
					'phone'     => $phone,
					'status'    => 'started',
					'ip_address'=> $_SERVER['REMOTE_ADDR'],
					'user_agent'=> $_SERVER['HTTP_USER_AGENT']
				)
			);
			wp_send_json_success( array( 'lead_id' => $wpdb->insert_id ) );
		}
	}

	public function submit_form() {
		check_ajax_referer( 'smlf_public_nonce', 'nonce' );

		global $wpdb;
		$table_name = $wpdb->prefix . 'smlf_leads';

		$form_id = intval( $_POST['form_id'] );
		$lead_id = isset( $_POST['lead_id'] ) ? intval( $_POST['lead_id'] ) : 0;
		$data_raw = isset( $_POST['data'] ) ? wp_unslash( $_POST['data'] ) : array();

		$email = '';
		$phone = '';
		$structured_data = array();

		if ( is_array( $data_raw ) ) {
			foreach ( $data_raw as $field ) {
				$name = sanitize_text_field( $field['name'] );
				$val  = sanitize_text_field( $field['value'] );
				$structured_data[ $name ] = $val;

				if ( strpos( strtolower( $name ), 'email' ) !== false && is_email( $val ) ) {
					$email = $val;
				}
				if ( strpos( strtolower( $name ), 'phone' ) !== false ) {
					$phone = $val;
				}
			}
		}

		$lead_data_json = wp_json_encode( $structured_data );

		if ( $lead_id ) {
			$wpdb->update(
				$table_name,
				array(
					'lead_data'    => $lead_data_json,
					'email'        => $email,
					'phone'        => $phone,
					'status'       => 'completed',
					'completed_at' => current_time( 'mysql' )
				),
				array( 'id' => $lead_id )
			);
		} else {
			$wpdb->insert(
				$table_name,
				array(
					'form_id'      => $form_id,
					'lead_data'    => $lead_data_json,
					'email'        => $email,
					'phone'        => $phone,
					'status'       => 'completed',
					'ip_address'   => $_SERVER['REMOTE_ADDR'],
					'user_agent'   => $_SERVER['HTTP_USER_AGENT'],
					'completed_at' => current_time( 'mysql' )
				)
			);
			$lead_id = $wpdb->insert_id;
		}

		// Trigger emails
		do_action( 'smlf_form_submitted', $lead_id, $form_id, $structured_data );

		wp_send_json_success( array( 'lead_id' => $lead_id ) );
	}
}
