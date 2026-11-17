<?php
/**
 * Uninstall: removes data only when "Delete everything" was chosen in the settings.
 *
 * @package Smart_Multistep_Lead_Forms
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

wp_clear_scheduled_hook( 'smlf_daily' );

if ( 'delete' !== get_option( 'smlf_uninstall_data_action', 'keep' ) ) {
	return;
}

global $wpdb;
foreach ( array( 'smlf_forms', 'smlf_leads', 'smlf_lead_notes', 'smlf_stats', 'smlf_email_logs' ) as $table ) {
	$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}{$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
}
$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE 'smlf\\_%' OR option_name LIKE '\\_transient%smlf\\_%'" );

// Private upload folder.
$up  = wp_get_upload_dir();
$dir = trailingslashit( $up['basedir'] ) . 'smlf-private';
if ( is_dir( $dir ) ) {
	$it = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $dir, FilesystemIterator::SKIP_DOTS ), RecursiveIteratorIterator::CHILD_FIRST );
	foreach ( $it as $file ) {
		$file->isDir() ? @rmdir( $file->getPathname() ) : @unlink( $file->getPathname() );
	}
	@rmdir( $dir );
}
