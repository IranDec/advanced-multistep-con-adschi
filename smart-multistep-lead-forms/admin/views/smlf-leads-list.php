<div class="wrap">
	<h1 class="wp-heading-inline"><?php esc_html_e( 'Leads', 'smart-multistep-lead-forms' ); ?></h1>
	<hr class="wp-header-end">

	<table class="wp-list-table widefat fixed striped">
		<thead>
			<tr>
				<th><?php esc_html_e( 'ID', 'smart-multistep-lead-forms' ); ?></th>
				<th><?php esc_html_e( 'Form ID', 'smart-multistep-lead-forms' ); ?></th>
				<th><?php esc_html_e( 'Status', 'smart-multistep-lead-forms' ); ?></th>
				<th><?php esc_html_e( 'Email / Phone', 'smart-multistep-lead-forms' ); ?></th>
				<th><?php esc_html_e( 'Date', 'smart-multistep-lead-forms' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php
			global $wpdb;
			$table_name = $wpdb->prefix . 'smlf_leads';
			$leads = $wpdb->get_results( "SELECT * FROM $table_name ORDER BY id DESC LIMIT 50" );

			if ( ! empty( $leads ) ) {
				foreach ( $leads as $lead ) {
					?>
					<tr>
						<td><?php echo esc_html( $lead->id ); ?></td>
						<td><?php echo esc_html( $lead->form_id ); ?></td>
						<td>
							<?php
								$status_class = ( $lead->status === 'completed' ) ? 'updated' : 'error';
								echo '<span class="smlf-badge ' . esc_attr( $status_class ) . '">' . esc_html( $lead->status ) . '</span>';
							?>
						</td>
						<td><?php echo esc_html( $lead->email . ' / ' . $lead->phone ); ?></td>
						<td><?php echo esc_html( $lead->created_at ); ?></td>
					</tr>
					<?php
				}
			} else {
				?>
				<tr>
					<td colspan="5"><?php esc_html_e( 'No leads found.', 'smart-multistep-lead-forms' ); ?></td>
				</tr>
				<?php
			}
			?>
		</tbody>
	</table>
</div>
