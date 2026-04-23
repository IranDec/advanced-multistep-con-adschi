<div class="wrap">
	<h1 class="wp-heading-inline"><?php esc_html_e( 'Forms', 'smart-multistep-lead-forms' ); ?></h1>
	<a href="<?php echo esc_url( admin_url( 'admin.php?page=smlf-add-form' ) ); ?>" class="page-title-action"><?php esc_html_e( 'Add New', 'smart-multistep-lead-forms' ); ?></a>
	<hr class="wp-header-end">

	<table class="wp-list-table widefat fixed striped">
		<thead>
			<tr>
				<th><?php esc_html_e( 'ID', 'smart-multistep-lead-forms' ); ?></th>
				<th><?php esc_html_e( 'Title', 'smart-multistep-lead-forms' ); ?></th>
				<th><?php esc_html_e( 'Shortcode', 'smart-multistep-lead-forms' ); ?></th>
				<th><?php esc_html_e( 'Date', 'smart-multistep-lead-forms' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php
			global $wpdb;
			$table_name = $wpdb->prefix . 'smlf_forms';
			$forms = $wpdb->get_results( "SELECT * FROM $table_name ORDER BY id DESC LIMIT 20" );

			if ( ! empty( $forms ) ) {
				foreach ( $forms as $form ) {
					?>
					<tr>
						<td><?php echo esc_html( $form->id ); ?></td>
						<td>
							<strong><a href="<?php echo esc_url( admin_url( 'admin.php?page=smlf-add-form&id=' . $form->id ) ); ?>"><?php echo esc_html( $form->title ); ?></a></strong>
						</td>
						<td><code>[smlf_form id="<?php echo esc_attr( $form->id ); ?>"]</code></td>
						<td><?php echo esc_html( $form->created_at ); ?></td>
					</tr>
					<?php
				}
			} else {
				?>
				<tr>
					<td colspan="4"><?php esc_html_e( 'No forms found.', 'smart-multistep-lead-forms' ); ?></td>
				</tr>
				<?php
			}
			?>
		</tbody>
	</table>
</div>
