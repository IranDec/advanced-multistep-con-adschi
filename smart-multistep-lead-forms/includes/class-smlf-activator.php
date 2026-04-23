<?php

/**
 * Fired during plugin activation
 *
 * @package    Smart_Multistep_Lead_Forms
 * @subpackage Smart_Multistep_Lead_Forms/includes
 */
class SMLF_Activator {

	/**
	 * Short Description. (use period)
	 *
	 * Long Description.
	 *
	 * @since    1.0.0
	 */
	public static function activate() {
		self::create_tables();
	}

	private static function create_tables() {
		global $wpdb;
		$charset_collate = $wpdb->get_charset_collate();

		require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );

		// Forms table
		$table_forms = $wpdb->prefix . 'smlf_forms';
		$sql_forms = "CREATE TABLE $table_forms (
			id bigint(20) NOT NULL AUTO_INCREMENT,
			title varchar(255) NOT NULL,
			form_data longtext NOT NULL,
			settings longtext,
			created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
			updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP NOT NULL,
			status varchar(50) DEFAULT 'publish' NOT NULL,
			PRIMARY KEY  (id)
		) $charset_collate;";

		// Leads table
		$table_leads = $wpdb->prefix . 'smlf_leads';
		$sql_leads = "CREATE TABLE $table_leads (
			id bigint(20) NOT NULL AUTO_INCREMENT,
			form_id bigint(20) NOT NULL,
			lead_data longtext NOT NULL,
			status varchar(50) DEFAULT 'started' NOT NULL,
			email varchar(255),
			phone varchar(50),
			ip_address varchar(50),
			user_agent text,
			referrer text,
			utm_source varchar(255),
			utm_medium varchar(255),
			utm_campaign varchar(255),
			created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
			updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP NOT NULL,
			completed_at datetime,
			admin_notes text,
			PRIMARY KEY  (id),
			KEY form_id (form_id),
			KEY status (status)
		) $charset_collate;";

		// Email Logs table
		$table_emails = $wpdb->prefix . 'smlf_email_logs';
		$sql_emails = "CREATE TABLE $table_emails (
			id bigint(20) NOT NULL AUTO_INCREMENT,
			lead_id bigint(20),
			recipient_email varchar(255) NOT NULL,
			subject varchar(255) NOT NULL,
			body longtext NOT NULL,
			status varchar(50) DEFAULT 'sent' NOT NULL,
			sent_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
			PRIMARY KEY  (id),
			KEY lead_id (lead_id)
		) $charset_collate;";

		dbDelta( $sql_forms );
		dbDelta( $sql_leads );
		dbDelta( $sql_emails );
	}

}
