<?php
/**
 * Leads list: tabs (completed / unfinished / all), filters, sorting, bulk actions, CSV export.
 *
 * @package Smart_Multistep_Lead_Forms
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$f        = SMLF_Admin::lead_filters();
list( $smlf_rows, $smlf_total ) = SMLF_Leads::query( $f );
$smlf_forms    = SMLF_Forms::all();
$smlf_titles   = wp_list_pluck( $smlf_forms, 'title', 'id' );
$smlf_statuses = SMLF_Leads::statuses();
$smlf_pages    = max( 1, (int) ceil( $smlf_total / $f['per_page'] ) );
$smlf_base     = array_filter( array(
	'page' => 'smlf-leads', 'form' => $f['form'] ? $f['form'] : '', 'state' => $f['state'], 'status' => $f['status'], 'due' => $f['due'] ? 1 : '',
	'q' => $f['q'], 'from' => $f['from'], 'to' => $f['to'], 'orderby' => $f['orderby'], 'order' => strtolower( $f['order'] ), 'per_page' => $f['per_page'],
), 'strlen' );
$smlf_url      = function ( $args ) use ( $smlf_base ) {
	return add_query_arg( array_merge( $smlf_base, $args ), admin_url( 'admin.php' ) );
};
$smlf_current  = $smlf_url( array( 'paged' => $f['paged'] ) );
$smlf_counts   = SMLF_Leads::counts( $f['form'] );
$smlf_export   = wp_nonce_url( add_query_arg( array_merge( $smlf_base, array( 'action' => 'smlf_export' ) ), admin_url( 'admin-post.php' ) ), 'smlf_export' );
$smlf_sort     = function ( $col, $label ) use ( $f, $smlf_url ) {
	$dir   = ( $f['orderby'] === $col && 'DESC' === $f['order'] ) ? 'asc' : 'desc';
	$arrow = $f['orderby'] === $col ? ( 'DESC' === $f['order'] ? ' ↓' : ' ↑' ) : '';
	return '<a href="' . esc_url( $smlf_url( array( 'orderby' => $col, 'order' => $dir, 'paged' => 1 ) ) ) . '">' . esc_html( $label ) . $arrow . '</a>';
};
$smlf_now = current_time( 'mysql' );
?>
<div class="smlf-head">
	<h1><span class="dashicons dashicons-groups"></span><?php esc_html_e( 'Leads', 'smart-multistep-lead-forms' ); ?></h1>
	<a class="button" href="<?php echo esc_url( $smlf_export ); ?>"><span class="dashicons dashicons-download"></span><?php esc_html_e( 'Export CSV', 'smart-multistep-lead-forms' ); ?></a>
</div>

<ul class="smlf-tabs">
	<li><a class="<?php echo 'completed' === $f['state'] && ! $f['due'] ? 'is-active' : ''; ?>" href="<?php echo esc_url( $smlf_url( array( 'state' => 'completed', 'due' => '', 'paged' => 1 ) ) ); ?>"><?php esc_html_e( 'Completed', 'smart-multistep-lead-forms' ); ?> <span class="smlf-count"><?php echo (int) $smlf_counts['completed']; ?></span></a></li>
	<li><a class="<?php echo 'partial' === $f['state'] ? 'is-active' : ''; ?>" href="<?php echo esc_url( $smlf_url( array( 'state' => 'partial', 'due' => '', 'paged' => 1 ) ) ); ?>"><?php esc_html_e( 'Unfinished', 'smart-multistep-lead-forms' ); ?> <span class="smlf-count"><?php echo (int) $smlf_counts['partial']; ?></span></a></li>
	<li><a class="<?php echo $f['due'] ? 'is-active' : ''; ?>" href="<?php echo esc_url( $smlf_url( array( 'state' => 'all', 'due' => 1, 'paged' => 1 ) ) ); ?>"><?php esc_html_e( 'Follow-ups due', 'smart-multistep-lead-forms' ); ?> <span class="smlf-count"><?php echo (int) $smlf_counts['due']; ?></span></a></li>
	<li><a class="<?php echo 'all' === $f['state'] && ! $f['due'] ? 'is-active' : ''; ?>" href="<?php echo esc_url( $smlf_url( array( 'state' => 'all', 'due' => '', 'paged' => 1 ) ) ); ?>"><?php esc_html_e( 'All', 'smart-multistep-lead-forms' ); ?></a></li>
</ul>

<form class="smlf-filters" method="get" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>">
	<input type="hidden" name="page" value="smlf-leads">
	<input type="hidden" name="state" value="<?php echo esc_attr( $f['state'] ); ?>">
	<?php if ( $f['due'] ) : ?><input type="hidden" name="due" value="1"><?php endif; ?>
	<input type="search" name="q" value="<?php echo esc_attr( $f['q'] ); ?>" placeholder="<?php esc_attr_e( 'Search name, e-mail, phone, answers…', 'smart-multistep-lead-forms' ); ?>">
	<select name="form">
		<option value=""><?php esc_html_e( 'All forms', 'smart-multistep-lead-forms' ); ?></option>
		<?php foreach ( $smlf_forms as $fm ) : ?>
			<option value="<?php echo (int) $fm['id']; ?>" <?php selected( $f['form'], (int) $fm['id'] ); ?>><?php echo esc_html( $fm['title'] ); ?></option>
		<?php endforeach; ?>
	</select>
	<select name="status">
		<option value=""><?php esc_html_e( 'All statuses', 'smart-multistep-lead-forms' ); ?></option>
		<?php foreach ( $smlf_statuses as $k => $label ) : ?>
			<option value="<?php echo esc_attr( $k ); ?>" <?php selected( $f['status'], $k ); ?>><?php echo esc_html( $label ); ?></option>
		<?php endforeach; ?>
	</select>
	<label class="smlf-date"><?php esc_html_e( 'From', 'smart-multistep-lead-forms' ); ?> <input type="date" name="from" value="<?php echo esc_attr( $f['from'] ); ?>"></label>
	<label class="smlf-date"><?php esc_html_e( 'To', 'smart-multistep-lead-forms' ); ?> <input type="date" name="to" value="<?php echo esc_attr( $f['to'] ); ?>"></label>
	<select name="per_page" data-smlf-autosubmit>
		<?php foreach ( array( 20, 50, 100 ) as $n ) : ?>
			<option value="<?php echo (int) $n; ?>" <?php selected( $f['per_page'], $n ); ?>><?php echo esc_html( sprintf( __( '%d per page', 'smart-multistep-lead-forms' ), $n ) ); ?></option>
		<?php endforeach; ?>
	</select>
	<button class="button button-primary"><span class="dashicons dashicons-search"></span><?php esc_html_e( 'Filter', 'smart-multistep-lead-forms' ); ?></button>
	<?php if ( $f['q'] || $f['form'] || $f['status'] || $f['from'] || $f['to'] ) : ?>
		<a class="button-link" href="<?php echo esc_url( admin_url( 'admin.php?page=smlf-leads&state=' . $f['state'] ) ); ?>"><?php esc_html_e( 'Reset', 'smart-multistep-lead-forms' ); ?></a>
	<?php endif; ?>
</form>

<form method="post" id="smlf-bulk-form" action="<?php echo esc_url( $smlf_current ); ?>">
	<?php wp_nonce_field( 'smlf_action', 'smlf_nonce' ); ?>
	<input type="hidden" name="smlf_do" value="lead_bulk">
	<input type="hidden" name="smlf_back" value="<?php echo esc_url( $smlf_current ); ?>">
</form>

<div class="smlf-card smlf-card-flush">
	<?php if ( ! $smlf_rows ) : ?>
		<div class="smlf-empty"><span class="dashicons dashicons-groups"></span><p><?php esc_html_e( 'No leads match this filter.', 'smart-multistep-lead-forms' ); ?></p></div>
	<?php else : ?>
	<table class="smlf-table">
		<thead><tr>
			<th class="smlf-col-check"><input type="checkbox" data-smlf-check-all aria-label="<?php esc_attr_e( 'Select all', 'smart-multistep-lead-forms' ); ?>"></th>
			<th><?php echo $smlf_sort( 'name', __( 'Contact', 'smart-multistep-lead-forms' ) ); // phpcs:ignore WordPress.Security.EscapeOutput ?></th>
			<th><?php esc_html_e( 'Form / answers', 'smart-multistep-lead-forms' ); ?></th>
			<th><?php echo $smlf_sort( 'lead_status', __( 'Status', 'smart-multistep-lead-forms' ) ); // phpcs:ignore WordPress.Security.EscapeOutput ?></th>
			<th><?php echo $smlf_sort( 'followup_at', __( 'Follow-up', 'smart-multistep-lead-forms' ) ); // phpcs:ignore WordPress.Security.EscapeOutput ?></th>
			<th><?php echo $smlf_sort( 'created_at', __( 'Received', 'smart-multistep-lead-forms' ) ); // phpcs:ignore WordPress.Security.EscapeOutput ?></th>
			<th></th>
		</tr></thead>
		<tbody>
		<?php
		$smlf_cache = array();
		foreach ( $smlf_rows as $l ) :
			if ( ! isset( $smlf_cache[ $l['form_id'] ] ) ) {
				$row                          = SMLF_Forms::get( $l['form_id'] );
				$smlf_cache[ $l['form_id'] ] = $row ? SMLF_Forms::fields_flat( $row['form'] ) : array();
			}
			$fields  = $smlf_cache[ $l['form_id'] ];
			$summary = array();
			foreach ( $l['answers'] as $k => $v ) {
				if ( isset( $fields[ $k ] ) && in_array( $fields[ $k ]['type'], array( 'choice', 'number', 'range', 'rating' ), true ) && count( $summary ) < 3 ) {
					$summary[] = SMLF_Leads::display_value( $v, $fields[ $k ] );
				}
			}
			$detail  = add_query_arg( array( 'lead' => (int) $l['id'], 'back' => rawurlencode( $smlf_current ) ), admin_url( 'admin.php?page=smlf-leads' ) );
			$overdue = $l['followup_at'] && $l['followup_at'] < $smlf_now;
			?>
			<tr class="<?php echo 'completed' !== $l['status'] ? 'smlf-row-partial' : ''; ?>">
				<td class="smlf-col-check"><input type="checkbox" name="ids[]" value="<?php echo (int) $l['id']; ?>" form="smlf-bulk-form" data-smlf-check></td>
				<td>
					<a class="smlf-name" href="<?php echo esc_url( $detail ); ?>"><?php echo esc_html( $l['name'] ? $l['name'] : ( $l['email'] ? $l['email'] : '#' . $l['id'] ) ); ?></a>
					<div class="smlf-contact">
						<?php if ( $l['phone'] ) : ?><a href="tel:<?php echo esc_attr( preg_replace( '/[^0-9+]/', '', $l['phone'] ) ); ?>"><span class="dashicons dashicons-phone"></span><?php echo esc_html( $l['phone'] ); ?></a><?php endif; ?>
						<?php if ( $l['email'] ) : ?><a href="mailto:<?php echo esc_attr( $l['email'] ); ?>"><span class="dashicons dashicons-email"></span><?php echo esc_html( $l['email'] ); ?></a><?php endif; ?>
					</div>
				</td>
				<td>
					<div class="smlf-meta"><span>#<?php echo (int) $l['id']; ?></span><span><?php echo esc_html( isset( $smlf_titles[ $l['form_id'] ] ) ? $smlf_titles[ $l['form_id'] ] : '—' ); ?></span>
						<?php if ( 'completed' !== $l['status'] ) : ?><span class="smlf-pill-partial"><?php esc_html_e( 'unfinished', 'smart-multistep-lead-forms' ); ?></span><?php endif; ?>
						<?php if ( $l['files'] ) : ?><span class="dashicons dashicons-paperclip" title="<?php esc_attr_e( 'Files attached', 'smart-multistep-lead-forms' ); ?>"></span><?php endif; ?>
					</div>
					<?php if ( $summary ) : ?><div class="smlf-excerpt"><?php echo esc_html( implode( ' · ', $summary ) ); ?></div><?php endif; ?>
				</td>
				<td><?php echo SMLF_Admin::status_badge( $l['lead_status'] ); // phpcs:ignore WordPress.Security.EscapeOutput ?></td>
				<td class="<?php echo $overdue ? 'smlf-overdue' : ''; ?>"><?php echo $l['followup_at'] ? esc_html( SMLF_Admin::dt( $l['followup_at'] ) ) : '<span class="smlf-muted">—</span>'; ?></td>
				<td><span title="<?php echo esc_attr( SMLF_Admin::dt( $l['created_at'] ) ); ?>"><?php echo esc_html( SMLF_Admin::ago( $l['created_at'] ) ); ?></span></td>
				<td class="smlf-actions"><a class="button button-small button-primary" href="<?php echo esc_url( $detail ); ?>"><?php esc_html_e( 'Open', 'smart-multistep-lead-forms' ); ?></a></td>
			</tr>
		<?php endforeach; ?>
		</tbody>
	</table>
	<?php endif; ?>
</div>

<div class="smlf-list-foot">
	<?php if ( $smlf_rows ) : ?>
	<div class="smlf-bulk">
		<select name="bulk_action" form="smlf-bulk-form">
			<option value=""><?php esc_html_e( 'Bulk action', 'smart-multistep-lead-forms' ); ?></option>
			<?php foreach ( $smlf_statuses as $k => $label ) : ?>
				<option value="<?php echo esc_attr( $k ); ?>"><?php echo esc_html( sprintf( __( 'Status: %s', 'smart-multistep-lead-forms' ), $label ) ); ?></option>
			<?php endforeach; ?>
			<option value="delete"><?php esc_html_e( 'Delete (incl. files)', 'smart-multistep-lead-forms' ); ?></option>
		</select>
		<button type="submit" form="smlf-bulk-form" class="button" data-smlf-confirm="<?php esc_attr_e( 'Apply to the selected leads?', 'smart-multistep-lead-forms' ); ?>"><?php esc_html_e( 'Apply', 'smart-multistep-lead-forms' ); ?></button>
	</div>
	<?php endif; ?>
	<div class="smlf-pages">
		<span><?php echo esc_html( sprintf( _n( '%d lead', '%d leads', $smlf_total, 'smart-multistep-lead-forms' ), $smlf_total ) ); ?></span>
		<?php if ( $smlf_pages > 1 ) : ?>
			<?php for ( $p = max( 1, $f['paged'] - 2 ); $p <= min( $smlf_pages, $f['paged'] + 2 ); $p++ ) : ?>
				<a class="button<?php echo $p === $f['paged'] ? ' button-primary' : ''; ?>" href="<?php echo esc_url( $smlf_url( array( 'paged' => $p ) ) ); ?>"><?php echo (int) $p; ?></a>
			<?php endfor; ?>
		<?php endif; ?>
	</div>
</div>
