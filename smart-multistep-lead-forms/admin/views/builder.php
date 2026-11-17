<?php
/**
 * Builder page: the app in assets/admin/builder.js renders into #smlf-builder.
 *
 * @package Smart_Multistep_Lead_Forms
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$smlf_id  = isset( $_GET['form'] ) ? absint( $_GET['form'] ) : 0;
$smlf_row = $smlf_id ? SMLF_Forms::get( $smlf_id ) : null;
if ( ! $smlf_row ) {
	$tpl      = SMLF_Forms::templates()['blank'];
	$smlf_row = array( 'id' => 0, 'title' => $tpl['title'], 'status' => 'draft', 'form' => SMLF_Forms::normalize( $tpl['form'] ) );
}
$smlf_preview = SMLF_Public::config( $smlf_row, true );
unset( $smlf_preview['form'] );

$smlf_types = array(
	'text'     => array( '✏️', __( 'Text', 'smart-multistep-lead-forms' ) ),
	'textarea' => array( '📝', __( 'Long text', 'smart-multistep-lead-forms' ) ),
	'email'    => array( '✉️', __( 'E-mail', 'smart-multistep-lead-forms' ) ),
	'phone'    => array( '📞', __( 'Phone', 'smart-multistep-lead-forms' ) ),
	'choice'   => array( '🔘', __( 'Choice', 'smart-multistep-lead-forms' ) ),
	'number'   => array( '🔢', __( 'Number', 'smart-multistep-lead-forms' ) ),
	'range'    => array( '🎚️', __( 'Slider', 'smart-multistep-lead-forms' ) ),
	'rating'   => array( '⭐', __( 'Rating', 'smart-multistep-lead-forms' ) ),
	'date'     => array( '📅', __( 'Date', 'smart-multistep-lead-forms' ) ),
	'address'  => array( '🏠', __( 'Address', 'smart-multistep-lead-forms' ) ),
	'url'      => array( '🔗', __( 'Website', 'smart-multistep-lead-forms' ) ),
	'file'     => array( '📎', __( 'File upload', 'smart-multistep-lead-forms' ) ),
	'consent'  => array( '☑️', __( 'Consent', 'smart-multistep-lead-forms' ) ),
	'message'  => array( '💬', __( 'Text block', 'smart-multistep-lead-forms' ) ),
	'hidden'   => array( '🕶️', __( 'Hidden (UTM)', 'smart-multistep-lead-forms' ) ),
);

$smlf_i18n = array(
	'back' => __( 'Forms', 'smart-multistep-lead-forms' ), 'title' => __( 'Form name', 'smart-multistep-lead-forms' ), 'save' => __( 'Save', 'smart-multistep-lead-forms' ),
	'saving' => __( 'Saving…', 'smart-multistep-lead-forms' ), 'saved' => __( 'Saved', 'smart-multistep-lead-forms' ), 'unsaved' => __( 'Unsaved changes', 'smart-multistep-lead-forms' ),
	'error' => __( 'Could not save', 'smart-multistep-lead-forms' ), 'published' => __( 'Published', 'smart-multistep-lead-forms' ), 'draft' => __( 'Draft', 'smart-multistep-lead-forms' ),
	'preview' => __( 'Preview', 'smart-multistep-lead-forms' ), 'desktop' => __( 'Desktop', 'smart-multistep-lead-forms' ), 'mobile' => __( 'Mobile', 'smart-multistep-lead-forms' ), 'close' => __( 'Close', 'smart-multistep-lead-forms' ),
	'steps' => __( 'Steps', 'smart-multistep-lead-forms' ), 'add_step' => __( 'Add step', 'smart-multistep-lead-forms' ), 'step' => __( 'Step', 'smart-multistep-lead-forms' ),
	'fields' => __( 'Add a field', 'smart-multistep-lead-forms' ), 'empty_step' => __( 'This step is empty. Click a field type on the left to add it.', 'smart-multistep-lead-forms' ),
	'tab_field' => __( 'Field', 'smart-multistep-lead-forms' ), 'tab_step' => __( 'Step', 'smart-multistep-lead-forms' ), 'tab_form' => __( 'Settings', 'smart-multistep-lead-forms' ),
	'pick_field' => __( 'Select a field in the middle to edit it.', 'smart-multistep-lead-forms' ),
	'label' => __( 'Question / label', 'smart-multistep-lead-forms' ), 'id' => __( 'Field key', 'smart-multistep-lead-forms' ), 'id_help' => __( 'Used in exports, webhooks and conditions.', 'smart-multistep-lead-forms' ),
	'placeholder' => __( 'Placeholder', 'smart-multistep-lead-forms' ), 'help' => __( 'Help text', 'smart-multistep-lead-forms' ), 'required' => __( 'Required', 'smart-multistep-lead-forms' ),
	'width' => __( 'Width', 'smart-multistep-lead-forms' ), 'w_full' => __( 'Full', 'smart-multistep-lead-forms' ), 'w_half' => __( 'Half', 'smart-multistep-lead-forms' ), 'w_third' => __( 'Third', 'smart-multistep-lead-forms' ),
	'default' => __( 'Default value', 'smart-multistep-lead-forms' ), 'options' => __( 'Options', 'smart-multistep-lead-forms' ), 'add_option' => __( 'Add option', 'smart-multistep-lead-forms' ),
	'option_label' => __( 'Label', 'smart-multistep-lead-forms' ), 'option_value' => __( 'Value', 'smart-multistep-lead-forms' ), 'icon' => __( 'Icon', 'smart-multistep-lead-forms' ), 'image' => __( 'Image URL', 'smart-multistep-lead-forms' ),
	'display' => __( 'Display', 'smart-multistep-lead-forms' ), 'd_cards' => __( 'Cards', 'smart-multistep-lead-forms' ), 'd_buttons' => __( 'Buttons', 'smart-multistep-lead-forms' ), 'd_list' => __( 'List', 'smart-multistep-lead-forms' ), 'd_dropdown' => __( 'Dropdown', 'smart-multistep-lead-forms' ),
	'multiple' => __( 'Several answers allowed', 'smart-multistep-lead-forms' ), 'min' => __( 'Minimum', 'smart-multistep-lead-forms' ), 'max' => __( 'Maximum', 'smart-multistep-lead-forms' ),
	'stepsize' => __( 'Step size', 'smart-multistep-lead-forms' ), 'unit' => __( 'Unit', 'smart-multistep-lead-forms' ), 'stars' => __( 'Stars', 'smart-multistep-lead-forms' ),
	'accept' => __( 'Allowed types', 'smart-multistep-lead-forms' ), 'max_files' => __( 'Max. files', 'smart-multistep-lead-forms' ),
	'consent_text' => __( 'Consent text', 'smart-multistep-lead-forms' ), 'link_text' => __( 'Link text', 'smart-multistep-lead-forms' ), 'link_url' => __( 'Link URL', 'smart-multistep-lead-forms' ),
	'content' => __( 'Text (HTML allowed)', 'smart-multistep-lead-forms' ), 'source' => __( 'Value from', 'smart-multistep-lead-forms' ), 'param' => __( 'URL parameter', 'smart-multistep-lead-forms' ),
	'src_url_param' => __( 'URL parameter', 'smart-multistep-lead-forms' ), 'src_page_url' => __( 'Page URL', 'smart-multistep-lead-forms' ), 'src_referrer' => __( 'Referrer', 'smart-multistep-lead-forms' ), 'src_static' => __( 'Fixed value', 'smart-multistep-lead-forms' ),
	'show_if' => __( 'Show this field only if…', 'smart-multistep-lead-forms' ), 'always' => __( 'Always shown.', 'smart-multistep-lead-forms' ), 'add_condition' => __( 'Add condition', 'smart-multistep-lead-forms' ),
	'match_all' => __( 'all conditions are true', 'smart-multistep-lead-forms' ), 'match_any' => __( 'any condition is true', 'smart-multistep-lead-forms' ),
	'op_eq' => __( 'is', 'smart-multistep-lead-forms' ), 'op_neq' => __( 'is not', 'smart-multistep-lead-forms' ), 'op_contains' => __( 'contains', 'smart-multistep-lead-forms' ), 'op_not_contains' => __( 'does not contain', 'smart-multistep-lead-forms' ),
	'op_empty' => __( 'is empty', 'smart-multistep-lead-forms' ), 'op_not_empty' => __( 'is filled', 'smart-multistep-lead-forms' ), 'op_gt' => __( 'greater than', 'smart-multistep-lead-forms' ), 'op_lt' => __( 'less than', 'smart-multistep-lead-forms' ),
	'step_title' => __( 'Step title', 'smart-multistep-lead-forms' ), 'step_desc' => __( 'Description', 'smart-multistep-lead-forms' ),
	'routes' => __( 'Jumps', 'smart-multistep-lead-forms' ), 'routes_help' => __( 'The first matching rule decides where the visitor goes next.', 'smart-multistep-lead-forms' ), 'add_route' => __( 'Add jump', 'smart-multistep-lead-forms' ),
	'if' => __( 'If', 'smart-multistep-lead-forms' ), 'goto' => __( 'go to', 'smart-multistep-lead-forms' ), 'otherwise' => __( 'Otherwise', 'smart-multistep-lead-forms' ),
	'following' => __( 'next step in the list', 'smart-multistep-lead-forms' ), 'submit_here' => __( 'submit the form', 'smart-multistep-lead-forms' ),
	'terminal' => __( 'Dead end: only show this step\'s text and a "start over" button (e.g. "we cannot help with this")', 'smart-multistep-lead-forms' ),
	'delete_step' => __( 'Delete step', 'smart-multistep-lead-forms' ), 'confirm_step' => __( 'Delete this step with all its fields?', 'smart-multistep-lead-forms' ),
	'duplicate' => __( 'Duplicate', 'smart-multistep-lead-forms' ), 'delete' => __( 'Delete', 'smart-multistep-lead-forms' ), 'move_to' => __( 'Move to step', 'smart-multistep-lead-forms' ),
	'conditional' => __( 'conditional', 'smart-multistep-lead-forms' ), 'jumps' => __( 'jumps', 'smart-multistep-lead-forms' ), 'shortcode' => __( 'Shortcode', 'smart-multistep-lead-forms' ),
	'g_design' => __( 'Design', 'smart-multistep-lead-forms' ), 'g_behavior' => __( 'Behaviour', 'smart-multistep-lead-forms' ), 'g_emails' => __( 'E-mails', 'smart-multistep-lead-forms' ), 'g_security' => __( 'Spam protection', 'smart-multistep-lead-forms' ), 'g_webhook' => __( 'Webhook', 'smart-multistep-lead-forms' ),
	'theme' => __( 'Theme', 'smart-multistep-lead-forms' ), 't_light' => __( 'Light', 'smart-multistep-lead-forms' ), 't_dark' => __( 'Dark', 'smart-multistep-lead-forms' ), 't_glass' => __( 'Glass', 'smart-multistep-lead-forms' ), 't_minimal' => __( 'Minimal', 'smart-multistep-lead-forms' ),
	'primary' => __( 'Main colour', 'smart-multistep-lead-forms' ), 'accent' => __( 'Accent colour', 'smart-multistep-lead-forms' ), 'background' => __( 'Background', 'smart-multistep-lead-forms' ), 'text' => __( 'Text colour', 'smart-multistep-lead-forms' ),
	'radius' => __( 'Corner radius', 'smart-multistep-lead-forms' ), 'max_width' => __( 'Max. width (px)', 'smart-multistep-lead-forms' ), 'font' => __( 'Font (CSS)', 'smart-multistep-lead-forms' ),
	'progress' => __( 'Progress', 'smart-multistep-lead-forms' ), 'p_steps' => __( 'Step dots', 'smart-multistep-lead-forms' ), 'p_bar' => __( 'Bar', 'smart-multistep-lead-forms' ), 'p_none' => __( 'Hidden', 'smart-multistep-lead-forms' ),
	'animate' => __( 'Animations', 'smart-multistep-lead-forms' ), 'save_progress' => __( 'Remember answers ("continue where you left off") and save unfinished leads', 'smart-multistep-lead-forms' ),
	'review' => __( 'Show a review page before sending', 'smart-multistep-lead-forms' ), 'language' => __( 'Language of the form texts', 'smart-multistep-lead-forms' ), 'auto' => __( 'Site language', 'smart-multistep-lead-forms' ),
	'success_title' => __( 'Thank-you title', 'smart-multistep-lead-forms' ), 'success_message' => __( 'Thank-you message', 'smart-multistep-lead-forms' ), 'redirect_url' => __( 'Or redirect to (URL)', 'smart-multistep-lead-forms' ),
	'next_label' => __( 'Button "Next"', 'smart-multistep-lead-forms' ), 'back_label' => __( 'Button "Back"', 'smart-multistep-lead-forms' ), 'submit_label' => __( 'Button "Send"', 'smart-multistep-lead-forms' ),
	'notify_admin' => __( 'E-mail every new lead to the team', 'smart-multistep-lead-forms' ), 'admin_emails' => __( 'Recipients (comma separated, empty = default from Settings)', 'smart-multistep-lead-forms' ), 'admin_subject' => __( 'Subject for the team', 'smart-multistep-lead-forms' ),
	'confirm_customer' => __( 'Send the visitor a confirmation', 'smart-multistep-lead-forms' ), 'customer_subject' => __( 'Confirmation subject', 'smart-multistep-lead-forms' ), 'customer_body' => __( 'Confirmation text', 'smart-multistep-lead-forms' ),
	'customer_help' => __( 'For security the confirmation never contains what the visitor typed. Placeholders: {site_name}, {form_title}, {date}.', 'smart-multistep-lead-forms' ),
	'captcha' => __( 'Captcha for this form', 'smart-multistep-lead-forms' ), 'inherit' => __( 'As in the global settings', 'smart-multistep-lead-forms' ), 'none' => __( 'None', 'smart-multistep-lead-forms' ),
	'webhook_url' => __( 'Webhook URL (Zapier, Make, n8n …)', 'smart-multistep-lead-forms' ), 'webhook_secret' => __( 'Signing secret', 'smart-multistep-lead-forms' ), 'generate' => __( 'Generate', 'smart-multistep-lead-forms' ),
	'webhook_partial' => __( 'Also send unfinished leads', 'smart-multistep-lead-forms' ), 'webhook_help' => __( 'Each request carries the header X-SMLF-Signature: sha256=HMAC(body, secret).', 'smart-multistep-lead-forms' ),
	'leave' => __( 'You have unsaved changes.', 'smart-multistep-lead-forms' ), 'new_field' => __( 'New question', 'smart-multistep-lead-forms' ),
);

$smlf_cfg = array(
	'ajax'      => admin_url( 'admin-ajax.php' ),
	'nonce'     => wp_create_nonce( 'smlf_builder' ),
	'formsUrl'  => admin_url( 'admin.php?page=smlf-forms' ),
	'builderUrl' => admin_url( 'admin.php?page=smlf-builder&form=' ),
	'id'        => (int) $smlf_row['id'],
	'title'     => $smlf_row['title'],
	'status'    => $smlf_row['status'],
	'form'      => $smlf_row['form'],
	'types'     => $smlf_types,
	'i18n'      => $smlf_i18n,
	'preview'   => $smlf_preview,
);
?>
<div id="smlf-builder" data-config="<?php echo esc_attr( wp_json_encode( $smlf_cfg ) ); ?>"><p class="smlf-muted"><?php esc_html_e( 'Loading the builder…', 'smart-multistep-lead-forms' ); ?></p></div>
