<?php
/**
 * Wires everything together.
 *
 * @package Smart_Multistep_Lead_Forms
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SMLF_Plugin {

	public static function init() {
		load_plugin_textdomain( 'smart-multistep-lead-forms', false, dirname( plugin_basename( SMLF_FILE ) ) . '/languages' );
		SMLF_Install::maybe_upgrade();
		SMLF_Submit::register();
		SMLF_Public::init();
		add_action( 'smlf_daily', array( 'SMLF_Install', 'daily_cleanup' ) );
		add_action( 'admin_post_smlf_download', array( 'SMLF_Uploads', 'download' ) );
		if ( is_admin() && class_exists( 'SMLF_Admin' ) ) {
			SMLF_Admin::init();
		}
	}
}
