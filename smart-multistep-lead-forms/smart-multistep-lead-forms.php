<?php
/**
 * Plugin Name:       Smart MultiStep Lead Forms
 * Plugin URI:        https://adschi.com
 * Description:       Visual multi-step lead forms: drag-and-drop builder with conditional logic, fast accessible frontend, lead CRM with notes and follow-ups, partial-lead recovery, conversion funnel, signed webhooks and abuse-safe e-mails.
 * Version:           2.0.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            Mohammad Babaei
 * Author URI:        https://adschi.com
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       smart-multistep-lead-forms
 * Domain Path:       /languages
 *
 * @package Smart_Multistep_Lead_Forms
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'SMLF_VERSION', '2.0.0' );
define( 'SMLF_DB_VERSION', '2.0' );
define( 'SMLF_FILE', __FILE__ );
define( 'SMLF_DIR', plugin_dir_path( __FILE__ ) );
define( 'SMLF_URL', plugin_dir_url( __FILE__ ) );

foreach ( array( 'install', 'forms', 'logic', 'security', 'uploads', 'leads', 'stats', 'mailer', 'webhooks', 'submit', 'public', 'plugin' ) as $smlf_class ) {
	require_once SMLF_DIR . 'includes/class-smlf-' . $smlf_class . '.php';
}
if ( is_admin() ) {
	require_once SMLF_DIR . 'includes/class-smlf-admin.php';
}

register_activation_hook( __FILE__, array( 'SMLF_Install', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'SMLF_Install', 'deactivate' ) );

add_action( 'plugins_loaded', array( 'SMLF_Plugin', 'init' ) );
