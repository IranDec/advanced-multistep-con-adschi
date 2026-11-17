<?php
/**
 * Dashboard: KPIs, conversion funnel with step drop-off, 30-day chart, latest leads.
 *
 * @package Smart_Multistep_Lead_Forms
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$smlf_forms   = SMLF_Forms::all();
$smlf_form_id = isset( $_GET['form'] ) ? absint( $_GET['form'] ) : ( $smlf_forms ? (int) $smlf_forms[0]['id'] : 0 );
$smlf_row     = $smlf_form_id ? SMLF_Forms::get( $smlf_form_id ) : null;
$smlf_days    = isset( $_GET['days'] ) && in_array( (int) $_GET['days'], array( 7, 30, 90 ), true ) ? (int) $_GET['days'] : 30;
$smlf_counts  = SMLF_Leads::counts();
$smlf_funnel  = SMLF_Stats::funnel( $smlf_row ? $smlf_form_id : 0, $smlf_days );
list( $smlf_latest ) = SMLF_Leads::query( array( 'form' => 0, 'state' => 'completed', 'status' => '', 'due' => false, 'q' => '', 'from' => '', 'to' => '', 'orderby' => 'created_at', 'order' => 'DESC', 'per_page' => 6, 'paged' => 1 ) );
$smlf_titles  = wp_list_pluck( $smlf_forms, 'title', 'id' );
$smlf_leads   = admin_url( 'admin.php?page=smlf-leads' );
?>
<div class="smlf-head">
	<h1><span class="dashicons dashicons-feedback"></span><?php esc_html_e( 'Lead Forms', 'smart-multistep-lead-forms' ); ?></h1>
	<a class="button button-primary" href="<?php echo esc_url( admin_url( 'admin.php?page=smlf-forms#new' ) ); ?>"><span class="dashicons dashicons-plus-alt2"></span><?php esc_html_e( 'New form', 'smart-multistep-lead-forms' ); ?></a>
</div>

<div class="smlf-kpis">
	<a class="smlf-kpi" style="--c:#b45309" href="<?php echo esc_url( add_query_arg( 'status', key( SMLF_Leads::statuses() ), $smlf_leads ) ); ?>">
		<span class="dashicons dashicons-star-filled"></span><strong><?php echo (int) $smlf_counts['new']; ?></strong><span><?php esc_html_e( 'New leads', 'smart-multistep-lead-forms' ); ?></span>
	</a>
	<a class="smlf-kpi" style="--c:#b91c1c" href="<?php echo esc_url( add_query_arg( array( 'due' => 1, 'state' => 'all' ), $smlf_leads ) ); ?>">
		<span class="dashicons dashicons-bell"></span><strong><?php echo (int) $smlf_counts['due']; ?></strong><span><?php esc_html_e( 'Follow-ups due', 'smart-multistep-lead-forms' ); ?></span>
	</a>
	<a class="smlf-kpi" style="--c:#0e7490" href="<?php echo esc_url( add_query_arg( 'state', 'partial', $smlf_leads ) ); ?>">
		<span class="dashicons dashicons-backup"></span><strong><?php echo (int) $smlf_counts['partial']; ?></strong><span><?php esc_html_e( 'Unfinished (recoverable)', 'smart-multistep-lead-forms' ); ?></span>
	</a>
	<a class="smlf-kpi" style="--c:#15803d" href="<?php echo esc_url( add_query_arg( 'from', gmdate( 'Y-m-01', strtotime( current_time( 'mysql' ) ) ), $smlf_leads ) ); ?>">
		<span class="dashicons dashicons-chart-line"></span><strong><?php echo (int) $smlf_counts['month']; ?></strong><span><?php esc_html_e( 'Leads this month', 'smart-multistep-lead-forms' ); ?></span>
	</a>
</div>

<div class="smlf-grid-2">
	<div class="smlf-card">
		<div class="smlf-card-head">
			<h2><?php esc_html_e( 'Conversion funnel', 'smart-multistep-lead-forms' ); ?></h2>
			<form method="get" class="smlf-inline-form">
				<input type="hidden" name="page" value="smlf-dashboard">
				<select name="form" data-smlf-autosubmit>
					<?php foreach ( $smlf_forms as $f ) : ?>
						<option value="<?php echo (int) $f['id']; ?>" <?php selected( $smlf_form_id, (int) $f['id'] ); ?>><?php echo esc_html( $f['title'] ); ?></option>
					<?php endforeach; ?>
				</select>
				<select name="days" data-smlf-autosubmit>
					<?php foreach ( array( 7, 30, 90 ) as $d ) : ?>
						<option value="<?php echo (int) $d; ?>" <?php selected( $smlf_days, $d ); ?>><?php echo esc_html( sprintf( __( 'Last %d days', 'smart-multistep-lead-forms' ), $d ) ); ?></option>
					<?php endforeach; ?>
				</select>
			</form>
		</div>
		<?php if ( ! $smlf_row ) : ?>
			<p class="smlf-muted"><?php esc_html_e( 'Create a form to see its funnel.', 'smart-multistep-lead-forms' ); ?></p>
		<?php else :
			$t     = $smlf_funnel['totals'];
			$base  = max( 1, $t['view'] );
			$stages = array( array( __( 'Seen', 'smart-multistep-lead-forms' ), $t['view'] ), array( __( 'Started', 'smart-multistep-lead-forms' ), $t['start'] ) );
			foreach ( $smlf_row['form']['steps'] as $i => $st ) {
				if ( $i > 0 && isset( $smlf_funnel['steps'][ $st['id'] ] ) ) {
					$stages[] = array( $st['title'] ? $st['title'] : $st['id'], $smlf_funnel['steps'][ $st['id'] ] );
				}
			}
			$stages[] = array( __( 'Submitted', 'smart-multistep-lead-forms' ), $t['submit'] );
			?>
			<div class="smlf-rates">
				<div><strong><?php echo esc_html( $smlf_funnel['conversion'] ); ?>%</strong><span><?php esc_html_e( 'Conversion (seen to submitted)', 'smart-multistep-lead-forms' ); ?></span></div>
				<div><strong><?php echo esc_html( $smlf_funnel['start_rate'] ); ?>%</strong><span><?php esc_html_e( 'Start rate', 'smart-multistep-lead-forms' ); ?></span></div>
				<div><strong><?php echo esc_html( $smlf_funnel['completion'] ); ?>%</strong><span><?php esc_html_e( 'Completion of started forms', 'smart-multistep-lead-forms' ); ?></span></div>
			</div>
			<ol class="smlf-funnel">
				<?php
				$prev = null;
				foreach ( $stages as $stage ) :
					$pct  = round( 100 * $stage[1] / $base, 1 );
					$drop = null !== $prev && $prev > 0 ? round( 100 * ( $prev - $stage[1] ) / $prev ) : null;
					$prev = $stage[1];
					?>
					<li>
						<span class="smlf-funnel-label"><?php echo esc_html( $stage[0] ); ?></span>
						<span class="smlf-funnel-bar"><span style="width:<?php echo esc_attr( max( 1.5, min( 100, $pct ) ) ); ?>%"></span></span>
						<span class="smlf-funnel-num"><?php echo (int) $stage[1]; ?></span>
						<span class="smlf-funnel-drop"><?php echo null !== $drop && $drop > 0 ? esc_html( '−' . $drop . '%' ) : ''; ?></span>
					</li>
				<?php endforeach; ?>
			</ol>
			<?php
			$max = 1;
			foreach ( $smlf_funnel['daily'] as $d ) {
				$max = max( $max, $d['view'], $d['submit'] );
			}
			$n = count( $smlf_funnel['daily'] );
			$w = 100 / $n;
			?>
			<svg class="smlf-chart" viewBox="0 0 100 40" preserveAspectRatio="none" role="img" aria-label="<?php esc_attr_e( 'Views and submissions per day', 'smart-multistep-lead-forms' ); ?>">
				<?php
				$i = 0;
				foreach ( $smlf_funnel['daily'] as $day => $d ) :
					$vh = 38 * $d['view'] / $max;
					$sh = 38 * $d['submit'] / $max;
					?>
					<g><title><?php echo esc_html( mysql2date( get_option( 'date_format' ), $day ) . ': ' . $d['view'] . ' / ' . $d['submit'] ); ?></title>
						<rect class="smlf-c-view" x="<?php echo esc_attr( $i * $w + $w * 0.12 ); ?>" y="<?php echo esc_attr( 40 - $vh ); ?>" width="<?php echo esc_attr( $w * 0.76 ); ?>" height="<?php echo esc_attr( $vh ); ?>" rx="0.4"></rect>
						<rect class="smlf-c-sub" x="<?php echo esc_attr( $i * $w + $w * 0.3 ); ?>" y="<?php echo esc_attr( 40 - $sh ); ?>" width="<?php echo esc_attr( $w * 0.4 ); ?>" height="<?php echo esc_attr( $sh ); ?>" rx="0.4"></rect>
					</g>
					<?php
					$i++;
				endforeach;
				?>
			</svg>
			<div class="smlf-legend"><span class="smlf-l-view"><?php esc_html_e( 'Seen', 'smart-multistep-lead-forms' ); ?></span><span class="smlf-l-sub"><?php esc_html_e( 'Submitted', 'smart-multistep-lead-forms' ); ?></span></div>
		<?php endif; ?>
	</div>

	<div class="smlf-card">
		<div class="smlf-card-head">
			<h2><?php esc_html_e( 'Latest leads', 'smart-multistep-lead-forms' ); ?></h2>
			<a href="<?php echo esc_url( $smlf_leads ); ?>"><?php esc_html_e( 'All leads', 'smart-multistep-lead-forms' ); ?> &rarr;</a>
		</div>
		<?php if ( ! $smlf_latest ) : ?>
			<div class="smlf-empty"><span class="dashicons dashicons-format-chat"></span><p><?php esc_html_e( 'No leads yet. Put a form on a page with its shortcode.', 'smart-multistep-lead-forms' ); ?></p></div>
		<?php else : ?>
			<ul class="smlf-latest">
				<?php foreach ( $smlf_latest as $l ) : ?>
					<li>
						<a href="<?php echo esc_url( add_query_arg( 'lead', (int) $l['id'], $smlf_leads ) ); ?>">
							<strong><?php echo esc_html( $l['name'] ? $l['name'] : ( $l['email'] ? $l['email'] : '#' . $l['id'] ) ); ?></strong>
							<span class="smlf-muted"><?php echo esc_html( ( isset( $smlf_titles[ $l['form_id'] ] ) ? $smlf_titles[ $l['form_id'] ] : '' ) . ' · ' . SMLF_Admin::ago( $l['created_at'] ) ); ?></span>
						</a>
						<?php echo SMLF_Admin::status_badge( $l['lead_status'] ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
					</li>
				<?php endforeach; ?>
			</ul>
		<?php endif; ?>
	</div>
</div>
