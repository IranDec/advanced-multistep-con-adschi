<div class="wrap">
	<h1 class="wp-heading-inline"><?php esc_html_e( 'Settings', 'smart-multistep-lead-forms' ); ?></h1>
	<hr class="wp-header-end">

	<form method="post" action="options.php">
		<?php
		settings_fields( 'smlf_options_group' );
		do_settings_sections( 'smlf-settings' );
		?>
		<table class="form-table">
			<tr valign="top">
				<th scope="row"><?php esc_html_e( 'Admin Notification Email', 'smart-multistep-lead-forms' ); ?></th>
				<td>
					<input type="email" name="smlf_admin_email" value="<?php echo esc_attr( get_option('smlf_admin_email', get_option('admin_email')) ); ?>" class="regular-text" />
					<p class="description"><?php esc_html_e( 'Where should lead notifications be sent?', 'smart-multistep-lead-forms' ); ?></p>
				</td>
			</tr>
			<tr valign="top">
				<th scope="row"><?php esc_html_e( 'Enable Partial Lead Saving', 'smart-multistep-lead-forms' ); ?></th>
				<td>
					<input type="checkbox" name="smlf_enable_partial" value="1" <?php checked( 1, get_option( 'smlf_enable_partial', 1 ), true ); ?> />
					<p class="description"><?php esc_html_e( 'Save leads instantly when email/phone is typed.', 'smart-multistep-lead-forms' ); ?></p>
				</td>
			</tr>
		</table>
		<?php submit_button(); ?>
	</form>
</div>

<?php
// Simple register settings logic placeholder
add_action( 'admin_init', function() {
	register_setting( 'smlf_options_group', 'smlf_admin_email' );
	register_setting( 'smlf_options_group', 'smlf_enable_partial' );
});
