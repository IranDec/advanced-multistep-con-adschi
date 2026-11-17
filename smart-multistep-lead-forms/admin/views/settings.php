<?php
/**
 * Global settings.
 *
 * @package Smart_Multistep_Lead_Forms
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$smlf_o = function ( $k, $d = '' ) {
	$v = get_option( $k, $d );
	return false === $v ? $d : $v;
};
$smlf_cap = (string) $smlf_o( 'smlf_captcha_method', 'none' );
?>
<div class="smlf-head"><h1><span class="dashicons dashicons-admin-generic"></span><?php esc_html_e( 'Settings', 'smart-multistep-lead-forms' ); ?></h1></div>

<form method="post" class="smlf-settings">
	<?php wp_nonce_field( 'smlf_action', 'smlf_nonce' ); ?>
	<input type="hidden" name="smlf_do" value="settings">
	<input type="hidden" name="smlf_back" value="<?php echo esc_url( admin_url( 'admin.php?page=smlf-settings' ) ); ?>">

	<div class="smlf-card">
		<h2><?php esc_html_e( 'Notifications', 'smart-multistep-lead-forms' ); ?></h2>
		<p><label for="smlf-ae"><?php esc_html_e( 'Default recipient for new leads', 'smart-multistep-lead-forms' ); ?></label>
			<input type="email" id="smlf-ae" name="smlf_admin_email" class="regular-text" value="<?php echo esc_attr( $smlf_o( 'smlf_admin_email' ) ); ?>">
			<span class="description"><?php esc_html_e( 'Each form can have its own recipients (builder > Settings > E-mails).', 'smart-multistep-lead-forms' ); ?></span></p>
		<p class="smlf-note">
			<?php if ( class_exists( 'KAS_Contact_Confirmation' ) ) : ?>
				<span class="dashicons dashicons-yes-alt"></span><?php esc_html_e( 'Confirmations to visitors are sent by the "KAS Contact Confirmation" plugin (fixed text, shared limits for all forms of the site).', 'smart-multistep-lead-forms' ); ?>
			<?php else : ?>
				<span class="dashicons dashicons-shield"></span><?php esc_html_e( 'Confirmations to visitors never contain what they typed and are limited (1 per address per day, 10 per hour, 40 per day) - the form cannot be misused to send spam.', 'smart-multistep-lead-forms' ); ?>
			<?php endif; ?>
		</p>
	</div>

	<div class="smlf-card">
		<h2><?php esc_html_e( 'Spam protection', 'smart-multistep-lead-forms' ); ?></h2>
		<p class="smlf-note"><span class="dashicons dashicons-shield-alt"></span><?php esc_html_e( 'Always on: invisible honeypot, minimum fill time, signed form stamp, rate limits per visitor and server-side validation of every answer.', 'smart-multistep-lead-forms' ); ?></p>
		<p><label for="smlf-cm"><?php esc_html_e( 'Captcha', 'smart-multistep-lead-forms' ); ?></label>
			<select id="smlf-cm" name="smlf_captcha_method">
				<option value="none" <?php selected( $smlf_cap, 'none' ); ?>><?php esc_html_e( 'None', 'smart-multistep-lead-forms' ); ?></option>
				<option value="turnstile" <?php selected( $smlf_cap, 'turnstile' ); ?>>Cloudflare Turnstile (<?php esc_html_e( 'recommended, privacy-friendly', 'smart-multistep-lead-forms' ); ?>)</option>
				<option value="recaptcha_v3" <?php selected( $smlf_cap, 'recaptcha_v3' ); ?>>Google reCAPTCHA v3</option>
				<option value="recaptcha_v2" <?php selected( $smlf_cap, 'recaptcha_v2' ); ?>>Google reCAPTCHA v2</option>
			</select></p>
		<p><label for="smlf-ck"><?php esc_html_e( 'Site key', 'smart-multistep-lead-forms' ); ?></label>
			<input type="text" id="smlf-ck" name="smlf_captcha_site_key" class="regular-text" value="<?php echo esc_attr( $smlf_o( 'smlf_captcha_site_key' ) ); ?>"></p>
		<p><label for="smlf-cs"><?php esc_html_e( 'Secret key', 'smart-multistep-lead-forms' ); ?></label>
			<input type="password" id="smlf-cs" name="smlf_captcha_secret_key" class="regular-text" value="" autocomplete="new-password" placeholder="<?php echo $smlf_o( 'smlf_captcha_secret_key' ) ? esc_attr__( 'saved - leave empty to keep', 'smart-multistep-lead-forms' ) : ''; ?>"></p>
		<?php if ( 'none' !== $smlf_cap && ( ! $smlf_o( 'smlf_captcha_site_key' ) || ! $smlf_o( 'smlf_captcha_secret_key' ) ) ) : ?>
			<p class="smlf-warn"><?php esc_html_e( 'The captcha is not active until both keys are saved.', 'smart-multistep-lead-forms' ); ?></p>
		<?php endif; ?>
	</div>

	<div class="smlf-card">
		<h2><?php esc_html_e( 'Unfinished forms and privacy', 'smart-multistep-lead-forms' ); ?></h2>
		<p><label class="smlf-check"><input type="checkbox" name="smlf_enable_partial" value="1" <?php checked( (int) $smlf_o( 'smlf_enable_partial', 1 ), 1 ); ?>> <?php esc_html_e( 'Save unfinished forms as leads once the visitor entered an e-mail address or phone number (to call back abandoned requests)', 'smart-multistep-lead-forms' ); ?></label></p>
		<p><label for="smlf-pr"><?php esc_html_e( 'Delete unfinished leads after', 'smart-multistep-lead-forms' ); ?></label>
			<input type="number" id="smlf-pr" name="smlf_partial_retention_days" min="0" max="365" class="small-text" value="<?php echo (int) $smlf_o( 'smlf_partial_retention_days', 30 ); ?>"> <?php esc_html_e( 'days (0 = never)', 'smart-multistep-lead-forms' ); ?></p>
		<p><label for="smlf-lr"><?php esc_html_e( 'Delete all leads after', 'smart-multistep-lead-forms' ); ?></label>
			<input type="number" id="smlf-lr" name="smlf_lead_retention_months" min="0" max="120" class="small-text" value="<?php echo (int) $smlf_o( 'smlf_lead_retention_months', 0 ); ?>"> <?php esc_html_e( 'months (0 = keep)', 'smart-multistep-lead-forms' ); ?></p>
		<p class="description"><?php esc_html_e( 'IP addresses are stored shortened. Funnel statistics contain only counts, no personal data.', 'smart-multistep-lead-forms' ); ?></p>
	</div>

	<div class="smlf-card">
		<h2><?php esc_html_e( 'File uploads', 'smart-multistep-lead-forms' ); ?></h2>
		<p><label for="smlf-fs"><?php esc_html_e( 'Maximum size per file', 'smart-multistep-lead-forms' ); ?></label>
			<input type="number" id="smlf-fs" name="smlf_max_file_size_mb" min="1" max="50" class="small-text" value="<?php echo (int) $smlf_o( 'smlf_max_file_size_mb', 5 ); ?>"> MB</p>
		<p><label for="smlf-fc"><?php esc_html_e( 'Maximum files per submission', 'smart-multistep-lead-forms' ); ?></label>
			<input type="number" id="smlf-fc" name="smlf_max_file_count" min="1" max="10" class="small-text" value="<?php echo (int) $smlf_o( 'smlf_max_file_count', 3 ); ?>"></p>
		<p><label for="smlf-fe"><?php esc_html_e( 'Allowed file types', 'smart-multistep-lead-forms' ); ?></label>
			<input type="text" id="smlf-fe" name="smlf_allowed_file_extensions" class="regular-text" value="<?php echo esc_attr( $smlf_o( 'smlf_allowed_file_extensions', 'jpg,jpeg,png,pdf' ) ); ?>">
			<span class="description"><?php esc_html_e( 'Files are stored in a private folder and can only be downloaded by administrators. Scripts are never allowed.', 'smart-multistep-lead-forms' ); ?></span></p>
	</div>

	<div class="smlf-card">
		<h2><?php esc_html_e( 'Lead statuses', 'smart-multistep-lead-forms' ); ?></h2>
		<p><textarea name="smlf_lead_statuses" rows="6" class="large-text code"><?php echo esc_textarea( $smlf_o( 'smlf_lead_statuses' ) ); ?></textarea>
			<span class="description"><?php esc_html_e( 'One per line: key:Label. The first one is given to new leads.', 'smart-multistep-lead-forms' ); ?></span></p>
	</div>

	<div class="smlf-card">
		<h2><?php esc_html_e( 'Uninstall', 'smart-multistep-lead-forms' ); ?></h2>
		<p><label class="smlf-check"><input type="radio" name="smlf_uninstall_data_action" value="keep" <?php checked( $smlf_o( 'smlf_uninstall_data_action', 'keep' ), 'keep' ); ?>> <?php esc_html_e( 'Keep forms and leads when the plugin is deleted', 'smart-multistep-lead-forms' ); ?></label>
			<label class="smlf-check"><input type="radio" name="smlf_uninstall_data_action" value="delete" <?php checked( $smlf_o( 'smlf_uninstall_data_action', 'keep' ), 'delete' ); ?>> <?php esc_html_e( 'Delete everything (forms, leads, files, statistics)', 'smart-multistep-lead-forms' ); ?></label></p>
	</div>

	<p><button class="button button-primary button-large"><?php esc_html_e( 'Save settings', 'smart-multistep-lead-forms' ); ?></button></p>
</form>
