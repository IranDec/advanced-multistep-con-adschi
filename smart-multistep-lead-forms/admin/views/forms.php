<?php
/**
 * Forms: cards with shortcode, leads, 30-day conversion; actions; new form from a template.
 *
 * @package Smart_Multistep_Lead_Forms
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

global $wpdb;
$smlf_forms  = SMLF_Forms::all();
$smlf_leadsn = $wpdb->get_results( "SELECT form_id, COUNT(*) n FROM {$wpdb->prefix}smlf_leads WHERE status = 'completed' GROUP BY form_id", OBJECT_K );
?>
<div class="smlf-head">
	<h1><span class="dashicons dashicons-forms"></span><?php esc_html_e( 'Forms', 'smart-multistep-lead-forms' ); ?></h1>
	<a class="button button-primary" href="#new"><span class="dashicons dashicons-plus-alt2"></span><?php esc_html_e( 'New form', 'smart-multistep-lead-forms' ); ?></a>
</div>

<?php if ( $smlf_forms ) : ?>
<div class="smlf-form-cards">
	<?php
	foreach ( $smlf_forms as $f ) :
		$fid     = (int) $f['id'];
		$funnel  = SMLF_Stats::funnel( $fid, 30 );
		$builder = admin_url( 'admin.php?page=smlf-builder&form=' . $fid );
		?>
		<div class="smlf-card smlf-form-card">
			<div class="smlf-card-head">
				<h2><a href="<?php echo esc_url( $builder ); ?>"><?php echo esc_html( $f['title'] ); ?></a></h2>
				<span class="smlf-badge" style="--c:<?php echo 'publish' === $f['status'] ? '#15803d' : '#6b7280'; ?>"><?php echo 'publish' === $f['status'] ? esc_html__( 'Published', 'smart-multistep-lead-forms' ) : esc_html__( 'Draft', 'smart-multistep-lead-forms' ); ?></span>
			</div>
			<div class="smlf-form-stats">
				<div><strong><?php echo (int) ( isset( $smlf_leadsn[ $fid ] ) ? $smlf_leadsn[ $fid ]->n : 0 ); ?></strong><span><?php esc_html_e( 'Leads', 'smart-multistep-lead-forms' ); ?></span></div>
				<div><strong><?php echo (int) $funnel['totals']['view']; ?></strong><span><?php esc_html_e( 'Seen (30 days)', 'smart-multistep-lead-forms' ); ?></span></div>
				<div><strong><?php echo esc_html( $funnel['conversion'] ); ?>%</strong><span><?php esc_html_e( 'Conversion', 'smart-multistep-lead-forms' ); ?></span></div>
			</div>
			<div class="smlf-shortcode">
				<input type="text" readonly value="<?php echo esc_attr( '[smlf_form id="' . $fid . '"]' ); ?>" id="smlf-sc-<?php echo (int) $fid; ?>" aria-label="<?php esc_attr_e( 'Shortcode', 'smart-multistep-lead-forms' ); ?>">
				<button type="button" class="button" data-smlf-copy="#smlf-sc-<?php echo (int) $fid; ?>"><?php esc_html_e( 'Copy', 'smart-multistep-lead-forms' ); ?></button>
			</div>
			<div class="smlf-card-actions">
				<a class="button button-primary" href="<?php echo esc_url( $builder ); ?>"><span class="dashicons dashicons-edit"></span><?php esc_html_e( 'Edit', 'smart-multistep-lead-forms' ); ?></a>
				<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=smlf-leads&state=all&form=' . $fid ) ); ?>"><?php esc_html_e( 'Leads', 'smart-multistep-lead-forms' ); ?></a>
				<?php
				SMLF_Admin::action_form( 'toggle_form', array( 'id' => $fid ), 'publish' === $f['status'] ? esc_html__( 'Unpublish', 'smart-multistep-lead-forms' ) : esc_html__( 'Publish', 'smart-multistep-lead-forms' ) );
				SMLF_Admin::action_form( 'duplicate_form', array( 'id' => $fid ), esc_html__( 'Duplicate', 'smart-multistep-lead-forms' ) );
				SMLF_Admin::action_form( 'delete_form', array( 'id' => $fid ), '<span class="dashicons dashicons-trash"></span>', 'button smlf-danger', __( 'Delete this form together with all its leads and files?', 'smart-multistep-lead-forms' ) );
				?>
			</div>
		</div>
	<?php endforeach; ?>
</div>
<?php endif; ?>

<div class="smlf-card" id="new">
	<h2><?php esc_html_e( 'Start a new form', 'smart-multistep-lead-forms' ); ?></h2>
	<div class="smlf-templates">
		<?php foreach ( SMLF_Forms::templates() as $key => $tpl ) : ?>
			<form method="post" class="smlf-template">
				<?php wp_nonce_field( 'smlf_action', 'smlf_nonce' ); ?>
				<input type="hidden" name="smlf_do" value="new_form">
				<input type="hidden" name="template" value="<?php echo esc_attr( $key ); ?>">
				<input type="hidden" name="smlf_back" value="<?php echo esc_url( admin_url( 'admin.php?page=smlf-forms' ) ); ?>">
				<button>
					<span class="smlf-template-icon" aria-hidden="true"><?php echo esc_html( $tpl['icon'] ); ?></span>
					<strong><?php echo esc_html( $tpl['title'] ); ?></strong>
					<span class="smlf-muted"><?php echo esc_html( sprintf( _n( '%d step', '%d steps', count( $tpl['form']['steps'] ), 'smart-multistep-lead-forms' ), count( $tpl['form']['steps'] ) ) ); ?></span>
				</button>
			</form>
		<?php endforeach; ?>
	</div>
</div>
