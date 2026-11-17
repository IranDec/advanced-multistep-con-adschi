<?php
/**
 * Tables, upgrades (1.x -> 2.0 migration) and scheduled clean-up.
 *
 * @package Smart_Multistep_Lead_Forms
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SMLF_Install {

	public static function activate() {
		self::install();
		if ( ! wp_next_scheduled( 'smlf_daily' ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'smlf_daily' );
		}
	}

	public static function deactivate() {
		wp_clear_scheduled_hook( 'smlf_daily' );
	}

	/** Runs on every load: installs / upgrades when the DB version changed. */
	public static function maybe_upgrade() {
		if ( get_option( 'smlf_db_version' ) !== SMLF_DB_VERSION ) {
			self::install();
		}
		if ( ! wp_next_scheduled( 'smlf_daily' ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'smlf_daily' );
		}
	}

	public static function install() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$c = $wpdb->get_charset_collate();
		$p = $wpdb->prefix;

		dbDelta( "CREATE TABLE {$p}smlf_forms (
			id bigint(20) NOT NULL AUTO_INCREMENT,
			title varchar(255) NOT NULL,
			form_data longtext NOT NULL,
			settings longtext,
			created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
			updated_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
			status varchar(50) DEFAULT 'publish' NOT NULL,
			PRIMARY KEY  (id)
		) $c;" );

		dbDelta( "CREATE TABLE {$p}smlf_leads (
			id bigint(20) NOT NULL AUTO_INCREMENT,
			form_id bigint(20) NOT NULL,
			token varchar(64) DEFAULT '' NOT NULL,
			lead_data longtext NOT NULL,
			status varchar(50) DEFAULT 'started' NOT NULL,
			lead_status varchar(50) DEFAULT 'new' NOT NULL,
			current_step varchar(40) DEFAULT '' NOT NULL,
			name varchar(255) DEFAULT '' NOT NULL,
			email varchar(255),
			phone varchar(50),
			ip_address varchar(50),
			user_agent text,
			referrer text,
			page_url text,
			utm_source varchar(255),
			utm_medium varchar(255),
			utm_campaign varchar(255),
			followup_at datetime DEFAULT NULL,
			created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
			updated_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
			completed_at datetime,
			admin_notes text,
			PRIMARY KEY  (id),
			KEY form_id (form_id),
			KEY status (status),
			KEY lead_status (lead_status),
			KEY token (token),
			KEY created_at (created_at)
		) $c;" );

		dbDelta( "CREATE TABLE {$p}smlf_lead_notes (
			id bigint(20) NOT NULL AUTO_INCREMENT,
			lead_id bigint(20) NOT NULL,
			user_id bigint(20) DEFAULT 0 NOT NULL,
			note text,
			status_from varchar(50) DEFAULT '' NOT NULL,
			status_to varchar(50) DEFAULT '' NOT NULL,
			created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
			PRIMARY KEY  (id),
			KEY lead_id (lead_id)
		) $c;" );

		dbDelta( "CREATE TABLE {$p}smlf_stats (
			form_id bigint(20) NOT NULL,
			day date NOT NULL,
			metric varchar(20) NOT NULL,
			step varchar(40) DEFAULT '' NOT NULL,
			hits int(11) DEFAULT 0 NOT NULL,
			PRIMARY KEY  (form_id,day,metric,step)
		) $c;" );

		dbDelta( "CREATE TABLE {$p}smlf_email_logs (
			id bigint(20) NOT NULL AUTO_INCREMENT,
			lead_id bigint(20),
			recipient_email varchar(255) NOT NULL,
			subject varchar(255) NOT NULL,
			body longtext NOT NULL,
			status varchar(50) DEFAULT 'sent' NOT NULL,
			sent_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
			PRIMARY KEY  (id),
			KEY lead_id (lead_id)
		) $c;" );

		$previous = (string) get_option( 'smlf_db_version', '' );
		if ( '' !== $previous && version_compare( $previous, '2.0', '<' ) || ( '' === $previous && get_option( 'smlf_version' ) ) ) {
			self::migrate_1x();
		}

		self::default_options();
		update_option( 'smlf_db_version', SMLF_DB_VERSION );
		update_option( 'smlf_version', SMLF_VERSION );
	}

	private static function default_options() {
		$defaults = array(
			'smlf_admin_email'             => get_option( 'admin_email' ),
			'smlf_captcha_method'          => 'none',
			'smlf_captcha_site_key'        => '',
			'smlf_captcha_secret_key'      => '',
			'smlf_enable_partial'          => 1,
			'smlf_partial_retention_days'  => 30,
			'smlf_lead_retention_months'   => 0,
			'smlf_max_file_size_mb'        => 5,
			'smlf_max_file_count'          => 3,
			'smlf_allowed_file_extensions' => 'jpg,jpeg,png,pdf',
			'smlf_lead_statuses'           => "new:New\ncontacted:Contacted\nqualified:Qualified\nwon:Won\nlost:Lost",
			'smlf_uninstall_data_action'   => 'keep',
		);
		foreach ( $defaults as $key => $value ) {
			if ( false === get_option( $key ) ) {
				add_option( $key, $value, '', false );
			}
		}
	}

	/**
	 * 1.x -> 2.0: forms to the v2 schema (steps with routes, options as lists, choice fields),
	 * random tokens for existing leads, the old single admin note as first history entry.
	 */
	private static function migrate_1x() {
		global $wpdb;
		$p = $wpdb->prefix;

		foreach ( (array) $wpdb->get_results( "SELECT id, title, form_data FROM {$p}smlf_forms" ) as $row ) {
			$data = json_decode( (string) $row->form_data, true );
			if ( ! is_array( $data ) || ( isset( $data['schema'] ) && 2 === (int) $data['schema'] ) ) {
				continue;
			}
			$v2 = SMLF_Forms::normalize( $data );
			$wpdb->update(
				"{$p}smlf_forms",
				array( 'form_data' => wp_json_encode( $v2 ), 'updated_at' => current_time( 'mysql' ) ),
				array( 'id' => (int) $row->id )
			);
		}

		foreach ( (array) $wpdb->get_col( "SELECT id FROM {$p}smlf_leads WHERE token = ''" ) as $lead_id ) {
			$wpdb->update( "{$p}smlf_leads", array( 'token' => wp_generate_password( 40, false ) ), array( 'id' => (int) $lead_id ) );
		}

		$notes = $wpdb->get_results( "SELECT id, admin_notes, updated_at FROM {$p}smlf_leads WHERE admin_notes IS NOT NULL AND admin_notes <> ''" );
		foreach ( (array) $notes as $lead ) {
			$has = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$p}smlf_lead_notes WHERE lead_id = %d", $lead->id ) );
			if ( ! $has ) {
				$wpdb->insert( "{$p}smlf_lead_notes", array( 'lead_id' => $lead->id, 'note' => $lead->admin_notes, 'created_at' => $lead->updated_at ) );
			}
		}

		// The 1.x customer e-mail repeated visitor input to any typed address: its texts are not reused.
		delete_option( 'smlf_email_user_subject' );
		delete_option( 'smlf_email_user_intro' );
	}

	/** Daily: delete abandoned partial leads and (optional) old leads; GDPR data minimisation. */
	public static function daily_cleanup() {
		global $wpdb;
		$p       = $wpdb->prefix;
		$partial = (int) get_option( 'smlf_partial_retention_days', 30 );
		if ( $partial > 0 ) {
			$ids = $wpdb->get_col( $wpdb->prepare( "SELECT id FROM {$p}smlf_leads WHERE status IN ('started','partial') AND updated_at < %s", gmdate( 'Y-m-d H:i:s', strtotime( current_time( 'mysql' ) ) - $partial * DAY_IN_SECONDS ) ) );
			SMLF_Leads::delete( $ids );
		}
		$months = (int) get_option( 'smlf_lead_retention_months', 0 );
		if ( $months > 0 ) {
			$ids = $wpdb->get_col( $wpdb->prepare( "SELECT id FROM {$p}smlf_leads WHERE created_at < %s", gmdate( 'Y-m-d H:i:s', strtotime( current_time( 'mysql' ) . " -{$months} months" ) ) ) );
			SMLF_Leads::delete( $ids );
		}
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$p}smlf_stats WHERE day < %s", gmdate( 'Y-m-d', strtotime( '-400 days' ) ) ) );
	}
}
