<?php
/**
 * E-mail log (last 200).
 *
 * @package Smart_Multistep_Lead_Forms
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

global $wpdb;
$smlf_logs  = $wpdb->get_results( "SELECT id, lead_id, recipient_email, subject, status, body, sent_at FROM {$wpdb->prefix}smlf_email_logs ORDER BY id DESC LIMIT 200" );
$smlf_label = array(
	'sent'    => array( __( 'Sent', 'smart-multistep-lead-forms' ), '#15803d' ),
	'failed'  => array( __( 'Failed', 'smart-multistep-lead-forms' ), '#b91c1c' ),
	'skipped' => array( __( 'Not sent (abuse protection)', 'smart-multistep-lead-forms' ), '#b45309' ),
);
?>
<div class="smlf-head"><h1><span class="dashicons dashicons-email-alt"></span><?php esc_html_e( 'E-mail log', 'smart-multistep-lead-forms' ); ?></h1></div>
<div class="smlf-card smlf-card-flush">
	<?php if ( ! $smlf_logs ) : ?>
		<div class="smlf-empty"><span class="dashicons dashicons-email-alt"></span><p><?php esc_html_e( 'No e-mails yet.', 'smart-multistep-lead-forms' ); ?></p></div>
	<?php else : ?>
	<table class="smlf-table">
		<thead><tr><th><?php esc_html_e( 'Date', 'smart-multistep-lead-forms' ); ?></th><th><?php esc_html_e( 'Recipient', 'smart-multistep-lead-forms' ); ?></th><th><?php esc_html_e( 'Subject', 'smart-multistep-lead-forms' ); ?></th><th><?php esc_html_e( 'Status', 'smart-multistep-lead-forms' ); ?></th><th><?php esc_html_e( 'Lead', 'smart-multistep-lead-forms' ); ?></th></tr></thead>
		<tbody>
		<?php foreach ( $smlf_logs as $log ) : $l = isset( $smlf_label[ $log->status ] ) ? $smlf_label[ $log->status ] : array( $log->status, '#4b5563' ); ?>
			<tr>
				<td><?php echo esc_html( SMLF_Admin::dt( $log->sent_at ) ); ?></td>
				<td><?php echo esc_html( $log->recipient_email ); ?></td>
				<td><?php echo esc_html( $log->subject ); ?></td>
				<td><span class="smlf-badge" style="--c:<?php echo esc_attr( $l[1] ); ?>"><?php echo esc_html( $l[0] ); ?></span><?php if ( 'skipped' === $log->status && $log->body && strlen( $log->body ) < 60 ) : ?> <span class="smlf-muted"><?php echo esc_html( $log->body ); ?></span><?php endif; ?></td>
				<td><?php if ( $log->lead_id ) : ?><a href="<?php echo esc_url( admin_url( 'admin.php?page=smlf-leads&lead=' . (int) $log->lead_id ) ); ?>">#<?php echo (int) $log->lead_id; ?></a><?php endif; ?></td>
			</tr>
		<?php endforeach; ?>
		</tbody>
	</table>
	<?php endif; ?>
</div>
