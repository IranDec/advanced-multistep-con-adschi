<?php
/**
 * Lead detail: answers by step, files, contact buttons, status / follow-up / note, history, source.
 *
 * @package Smart_Multistep_Lead_Forms
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$smlf_lead = SMLF_Leads::get( absint( $_GET['lead'] ) );
$smlf_back = isset( $_GET['back'] ) ? esc_url_raw( rawurldecode( wp_unslash( $_GET['back'] ) ) ) : admin_url( 'admin.php?page=smlf-leads' );
if ( 0 !== strpos( $smlf_back, admin_url() ) ) {
	$smlf_back = admin_url( 'admin.php?page=smlf-leads' );
}
?>
<p><a class="smlf-back" href="<?php echo esc_url( $smlf_back ); ?>">&larr; <?php esc_html_e( 'Back to the list', 'smart-multistep-lead-forms' ); ?></a></p>
<?php
if ( ! $smlf_lead ) {
	echo '<div class="notice notice-error"><p>' . esc_html__( 'Lead not found.', 'smart-multistep-lead-forms' ) . '</p></div>';
	return;
}
$smlf_row   = SMLF_Forms::get( $smlf_lead['form_id'] );
$smlf_self  = SMLF_Admin::current_url();
$smlf_phone = preg_replace( '/[^0-9+]/', '', (string) $smlf_lead['phone'] );
$smlf_wa    = preg_match( '/^(\+|00)([1-9][0-9]{6,14})$/', $smlf_phone, $m ) ? 'https://wa.me/' . $m[2] : '';
$smlf_title = $smlf_lead['name'] ? $smlf_lead['name'] : ( $smlf_lead['email'] ? $smlf_lead['email'] : '#' . $smlf_lead['id'] );
?>
<div class="smlf-head">
	<h1><?php echo esc_html( $smlf_title ); ?> <span class="smlf-id">#<?php echo (int) $smlf_lead['id']; ?></span> <?php echo SMLF_Admin::status_badge( $smlf_lead['lead_status'] ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
		<?php if ( 'completed' !== $smlf_lead['status'] ) : ?><span class="smlf-pill-partial"><?php esc_html_e( 'unfinished', 'smart-multistep-lead-forms' ); ?></span><?php endif; ?></h1>
	<div class="smlf-head-actions">
		<?php if ( $smlf_phone ) : ?><a class="button" href="tel:<?php echo esc_attr( $smlf_phone ); ?>"><span class="dashicons dashicons-phone"></span><?php esc_html_e( 'Call', 'smart-multistep-lead-forms' ); ?></a><?php endif; ?>
		<?php if ( $smlf_wa ) : ?><a class="button" href="<?php echo esc_url( $smlf_wa ); ?>" target="_blank" rel="noopener noreferrer"><span class="dashicons dashicons-whatsapp"></span>WhatsApp</a><?php endif; ?>
		<?php if ( $smlf_lead['email'] ) : ?><a class="button" href="mailto:<?php echo esc_attr( $smlf_lead['email'] ); ?>"><span class="dashicons dashicons-email"></span><?php esc_html_e( 'E-mail', 'smart-multistep-lead-forms' ); ?></a><?php endif; ?>
	</div>
</div>

<div class="smlf-detail">
	<div>
		<div class="smlf-card">
			<div class="smlf-card-head"><h2><?php esc_html_e( 'Answers', 'smart-multistep-lead-forms' ); ?></h2><span class="smlf-muted"><?php echo esc_html( $smlf_row ? $smlf_row['title'] : '' ); ?></span></div>
			<?php
			$shown = array();
			if ( $smlf_row ) :
				foreach ( $smlf_row['form']['steps'] as $step ) :
					if ( $smlf_lead['path'] && ! in_array( $step['id'], $smlf_lead['path'], true ) ) {
						continue;
					}
					$items = array();
					foreach ( $step['fields'] as $fld ) {
						if ( 'message' === $fld['type'] ) {
							continue;
						}
						$shown[ $fld['id'] ] = true;
						if ( 'file' === $fld['type'] ) {
							if ( ! empty( $smlf_lead['files'][ $fld['id'] ] ) ) {
								$links = array();
								foreach ( $smlf_lead['files'][ $fld['id'] ] as $i => $file ) {
									$links[] = '<a class="button button-small" href="' . esc_url( SMLF_Uploads::download_url( $smlf_lead['id'], $fld['id'], $i ) ) . '"><span class="dashicons dashicons-download"></span>' . esc_html( $file['name'] ) . ' <span class="smlf-muted">(' . esc_html( size_format( $file['size'] ) ) . ')</span></a>';
								}
								$items[] = array( $fld['label'] ? $fld['label'] : $fld['id'], implode( ' ', $links ), true );
							}
							continue;
						}
						if ( ! array_key_exists( $fld['id'], $smlf_lead['answers'] ) ) {
							continue;
						}
						$items[] = array( $fld['label'] ? $fld['label'] : ( 'consent' === $fld['type'] ? $fld['consent_text'] : $fld['id'] ), SMLF_Leads::display_value( $smlf_lead['answers'][ $fld['id'] ], $fld ), false );
					}
					if ( ! $items ) {
						continue;
					}
					?>
					<h3 class="smlf-step-head"><?php echo esc_html( $step['title'] ? $step['title'] : $step['id'] ); ?></h3>
					<dl class="smlf-dl">
						<?php foreach ( $items as $it ) : ?>
							<dt><?php echo esc_html( $it[0] ); ?></dt>
							<dd><?php echo $it[2] ? wp_kses_post( $it[1] ) : nl2br( esc_html( $it[1] ) ); ?></dd>
						<?php endforeach; ?>
					</dl>
					<?php
				endforeach;
			endif;
			// Answers without a field in the current form (field deleted, or 1.x data).
			$rest = array_diff_key( $smlf_lead['answers'], $shown );
			if ( $rest ) :
				?>
				<h3 class="smlf-step-head"><?php esc_html_e( 'Other answers', 'smart-multistep-lead-forms' ); ?></h3>
				<dl class="smlf-dl">
					<?php foreach ( $rest as $k => $v ) : ?>
						<dt><?php echo esc_html( $k ); ?></dt><dd><?php echo esc_html( is_array( $v ) ? implode( ', ', array_map( 'strval', $v ) ) : (string) $v ); ?></dd>
					<?php endforeach; ?>
				</dl>
			<?php endif; ?>
			<?php if ( $smlf_lead['legacy_files'] ) : ?>
				<h3 class="smlf-step-head"><?php esc_html_e( 'Files (version 1)', 'smart-multistep-lead-forms' ); ?></h3>
				<p><?php foreach ( $smlf_lead['legacy_files'] as $file ) : $u = is_array( $file ) && isset( $file['url'] ) ? $file['url'] : ( is_string( $file ) ? $file : '' ); if ( $u ) : ?><a class="button button-small" href="<?php echo esc_url( $u ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( wp_basename( wp_parse_url( $u, PHP_URL_PATH ) ) ); ?></a> <?php endif; endforeach; ?></p>
			<?php endif; ?>
		</div>

		<div class="smlf-card">
			<h2><?php esc_html_e( 'Source', 'smart-multistep-lead-forms' ); ?></h2>
			<dl class="smlf-dl smlf-dl-small">
				<dt><?php esc_html_e( 'Received', 'smart-multistep-lead-forms' ); ?></dt><dd><?php echo esc_html( SMLF_Admin::dt( $smlf_lead['created_at'] ) . ' (' . SMLF_Admin::ago( $smlf_lead['created_at'] ) . ')' ); ?></dd>
				<?php if ( $smlf_lead['completed_at'] ) : ?><dt><?php esc_html_e( 'Completed', 'smart-multistep-lead-forms' ); ?></dt><dd><?php echo esc_html( SMLF_Admin::dt( $smlf_lead['completed_at'] ) ); ?></dd><?php endif; ?>
				<?php if ( $smlf_lead['page_url'] ) : ?><dt><?php esc_html_e( 'Page', 'smart-multistep-lead-forms' ); ?></dt><dd><a href="<?php echo esc_url( $smlf_lead['page_url'] ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( $smlf_lead['page_url'] ); ?></a></dd><?php endif; ?>
				<?php if ( $smlf_lead['referrer'] ) : ?><dt><?php esc_html_e( 'Came from', 'smart-multistep-lead-forms' ); ?></dt><dd><?php echo esc_html( $smlf_lead['referrer'] ); ?></dd><?php endif; ?>
				<?php if ( $smlf_lead['utm_source'] || $smlf_lead['utm_campaign'] ) : ?><dt>UTM</dt><dd><?php echo esc_html( implode( ' / ', array_filter( array( $smlf_lead['utm_source'], $smlf_lead['utm_medium'], $smlf_lead['utm_campaign'] ) ) ) ); ?></dd><?php endif; ?>
				<?php if ( 'completed' !== $smlf_lead['status'] && $smlf_lead['current_step'] ) : ?><dt><?php esc_html_e( 'Stopped at step', 'smart-multistep-lead-forms' ); ?></dt><dd><?php echo esc_html( $smlf_lead['current_step'] ); ?></dd><?php endif; ?>
				<dt><?php esc_html_e( 'Device', 'smart-multistep-lead-forms' ); ?></dt><dd><?php echo esc_html( preg_match( '/Mobile|Android|iPhone/i', (string) $smlf_lead['user_agent'] ) ? __( 'Mobile', 'smart-multistep-lead-forms' ) : __( 'Desktop', 'smart-multistep-lead-forms' ) ); ?> · <?php echo esc_html( $smlf_lead['ip_address'] ); ?></dd>
			</dl>
		</div>
	</div>

	<div class="smlf-side">
		<div class="smlf-card">
			<h2><?php esc_html_e( 'Status and follow-up', 'smart-multistep-lead-forms' ); ?></h2>
			<form method="post" action="<?php echo esc_url( $smlf_self ); ?>" class="smlf-update">
				<?php wp_nonce_field( 'smlf_action', 'smlf_nonce' ); ?>
				<input type="hidden" name="smlf_do" value="lead_update">
				<input type="hidden" name="id" value="<?php echo (int) $smlf_lead['id']; ?>">
				<input type="hidden" name="smlf_back" value="<?php echo esc_url( $smlf_self ); ?>">
				<div class="smlf-status-pick">
					<?php foreach ( SMLF_Leads::statuses() as $k => $label ) : ?>
						<label><input type="radio" name="lead_status" value="<?php echo esc_attr( $k ); ?>" <?php checked( $smlf_lead['lead_status'], $k ); ?>><span><?php echo esc_html( $label ); ?></span></label>
					<?php endforeach; ?>
				</div>
				<p>
					<label for="smlf-follow"><?php esc_html_e( 'Follow-up', 'smart-multistep-lead-forms' ); ?></label>
					<input type="datetime-local" id="smlf-follow" name="followup_at" value="<?php echo esc_attr( $smlf_lead['followup_at'] ? mysql2date( 'Y-m-d\TH:i', $smlf_lead['followup_at'] ) : '' ); ?>">
					<span class="smlf-quick">
						<button type="button" class="button button-small" data-smlf-in="2h">+2 h</button>
						<button type="button" class="button button-small" data-smlf-in="tomorrow"><?php esc_html_e( 'Tomorrow 9:00', 'smart-multistep-lead-forms' ); ?></button>
						<button type="button" class="button button-small" data-smlf-in="week"><?php esc_html_e( 'In a week', 'smart-multistep-lead-forms' ); ?></button>
						<button type="button" class="button button-small button-link" data-smlf-in="clear"><?php esc_html_e( 'Remove', 'smart-multistep-lead-forms' ); ?></button>
					</span>
				</p>
				<p>
					<label for="smlf-note"><?php esc_html_e( 'New note', 'smart-multistep-lead-forms' ); ?></label>
					<textarea id="smlf-note" name="note" rows="3" placeholder="<?php esc_attr_e( 'e.g. called, sending a quote tomorrow', 'smart-multistep-lead-forms' ); ?>"></textarea>
				</p>
				<button class="button button-primary button-large"><?php esc_html_e( 'Save', 'smart-multistep-lead-forms' ); ?></button>
			</form>
		</div>

		<div class="smlf-card">
			<h2><?php esc_html_e( 'History', 'smart-multistep-lead-forms' ); ?></h2>
			<ul class="smlf-timeline">
				<?php foreach ( SMLF_Leads::notes( $smlf_lead['id'] ) as $n ) : $u = $n->user_id ? get_userdata( $n->user_id ) : null; ?>
					<li>
						<div class="smlf-tl-head"><strong><?php echo esc_html( $u ? $u->display_name : __( 'System', 'smart-multistep-lead-forms' ) ); ?></strong><span class="smlf-muted"><?php echo esc_html( SMLF_Admin::dt( $n->created_at ) ); ?></span></div>
						<?php if ( $n->status_to ) : ?><div><?php echo $n->status_from ? SMLF_Admin::status_badge( $n->status_from ) . ' → ' : ''; echo SMLF_Admin::status_badge( $n->status_to ); // phpcs:ignore WordPress.Security.EscapeOutput ?></div><?php endif; ?>
						<?php if ( '' !== trim( (string) $n->note ) ) : ?><div class="smlf-tl-note"><?php echo nl2br( esc_html( $n->note ) ); ?></div><?php endif; ?>
					</li>
				<?php endforeach; ?>
				<li><div class="smlf-tl-head"><strong><?php esc_html_e( 'Lead received', 'smart-multistep-lead-forms' ); ?></strong><span class="smlf-muted"><?php echo esc_html( SMLF_Admin::dt( $smlf_lead['created_at'] ) ); ?></span></div></li>
			</ul>
		</div>

		<div class="smlf-delete">
			<?php SMLF_Admin::action_form( 'lead_delete', array( 'id' => (int) $smlf_lead['id'] ), '<span class="dashicons dashicons-trash"></span>' . esc_html__( 'Delete lead', 'smart-multistep-lead-forms' ), 'button-link smlf-danger', __( 'Delete this lead and its files permanently?', 'smart-multistep-lead-forms' ) ); ?>
		</div>
	</div>
</div>
