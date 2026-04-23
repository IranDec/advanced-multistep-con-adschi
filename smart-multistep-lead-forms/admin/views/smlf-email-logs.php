<div class="wrap">
	<h1 class="wp-heading-inline"><?php esc_html_e( 'Email Logs', 'smart-multistep-lead-forms' ); ?></h1>
	<hr class="wp-header-end">

	<table class="wp-list-table widefat fixed striped">
		<thead>
			<tr>
				<th><?php esc_html_e( 'ID', 'smart-multistep-lead-forms' ); ?></th>
				<th><?php esc_html_e( 'Recipient', 'smart-multistep-lead-forms' ); ?></th>
				<th><?php esc_html_e( 'Subject', 'smart-multistep-lead-forms' ); ?></th>
				<th><?php esc_html_e( 'Status', 'smart-multistep-lead-forms' ); ?></th>
				<th><?php esc_html_e( 'Date', 'smart-multistep-lead-forms' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php
			global $wpdb;
			$table_name = $wpdb->prefix . 'smlf_email_logs';
			$logs = $wpdb->get_results( "SELECT * FROM $table_name ORDER BY id DESC LIMIT 50" );

			if ( ! empty( $logs ) ) {
				foreach ( $logs as $log ) {
					?>
					<tr>
						<td><?php echo esc_html( $log->id ); ?></td>
						<td><?php echo esc_html( $log->recipient_email ); ?></td>
						<td><?php echo esc_html( $log->subject ); ?></td>
						<td><?php echo esc_html( $log->status ); ?></td>
						<td><?php echo esc_html( $log->sent_at ); ?></td>
					</tr>
					<?php
				}
			} else {
				?>
				<tr>
					<td colspan="5"><?php esc_html_e( 'No email logs found.', 'smart-multistep-lead-forms' ); ?></td>
				</tr>
				<?php
			}
			?>
		</tbody>
	</table>
</div>
