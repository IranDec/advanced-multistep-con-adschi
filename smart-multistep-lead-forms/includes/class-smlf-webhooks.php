<?php
/**
 * Webhooks (Zapier, Make, n8n, own systems): JSON POST per form, signed with HMAC-SHA256
 * (header X-SMLF-Signature: sha256=<hex of the raw body>). wp_safe_remote_post blocks internal
 * addresses (no SSRF); non-blocking so the visitor never waits.
 *
 * @package Smart_Multistep_Lead_Forms
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SMLF_Webhooks {

	public static function send( $event, array $form_row, $lead_id ) {
		$s = $form_row['form']['settings'];
		if ( '' === $s['webhook_url'] || ( 'partial' === $event && ! $s['webhook_partial'] ) ) {
			return;
		}
		$lead = SMLF_Leads::get( $lead_id );
		if ( ! $lead ) {
			return;
		}
		$fields  = SMLF_Forms::fields_flat( $form_row['form'] );
		$answers = array();
		foreach ( $lead['answers'] as $id => $value ) {
			$answers[ $id ] = array(
				'label' => isset( $fields[ $id ] ) ? $fields[ $id ]['label'] : $id,
				'value' => $value,
				'text'  => SMLF_Leads::display_value( $value, isset( $fields[ $id ] ) ? $fields[ $id ] : null ),
			);
		}
		$body = wp_json_encode( array(
			'event'      => 'partial' === $event ? 'lead.partial' : 'lead.completed',
			'site'       => home_url( '/' ),
			'form'       => array( 'id' => (int) $form_row['id'], 'title' => $form_row['title'] ),
			'lead'       => array(
				'id'         => (int) $lead['id'],
				'status'     => $lead['status'],
				'name'       => $lead['name'],
				'email'      => $lead['email'],
				'phone'      => $lead['phone'],
				'page_url'   => $lead['page_url'],
				'utm'        => array( 'source' => $lead['utm_source'], 'medium' => $lead['utm_medium'], 'campaign' => $lead['utm_campaign'] ),
				'created_at' => $lead['created_at'],
				'admin_url'  => admin_url( 'admin.php?page=smlf-leads&lead=' . (int) $lead['id'] ),
			),
			'answers'    => $answers,
			'path'       => $lead['path'],
		) );
		$headers = array( 'Content-Type' => 'application/json', 'X-SMLF-Event' => 'partial' === $event ? 'lead.partial' : 'lead.completed' );
		if ( '' !== $s['webhook_secret'] ) {
			$headers['X-SMLF-Signature'] = 'sha256=' . hash_hmac( 'sha256', $body, $s['webhook_secret'] );
		}
		wp_safe_remote_post( $s['webhook_url'], array(
			'timeout'  => 5,
			'blocking' => false,
			'headers'  => $headers,
			'body'     => $body,
		) );
	}
}
