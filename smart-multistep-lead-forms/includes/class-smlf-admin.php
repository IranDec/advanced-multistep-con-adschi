<?php
/**
 * Admin: menu, assets, builder save endpoint, form / lead / settings actions, CSV export.
 * Every change: capability manage_options + nonce.
 *
 * @package Smart_Multistep_Lead_Forms
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SMLF_Admin {

	const CAP = 'manage_options';

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
		add_action( 'admin_init', array( __CLASS__, 'handle_post' ) );
		add_action( 'wp_ajax_smlf_admin_save_form', array( __CLASS__, 'ajax_save_form' ) );
		add_action( 'admin_post_smlf_export', array( __CLASS__, 'export_csv' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( SMLF_FILE ), function ( $links ) {
			array_unshift( $links, '<a href="' . esc_url( admin_url( 'admin.php?page=smlf-forms' ) ) . '">' . esc_html__( 'Forms', 'smart-multistep-lead-forms' ) . '</a>' );
			return $links;
		} );
	}

	public static function menu() {
		$new   = SMLF_Leads::counts()['new'];
		$label = __( 'Lead Forms', 'smart-multistep-lead-forms' ) . ( $new ? ' <span class="awaiting-mod">' . (int) $new . '</span>' : '' );
		add_menu_page( __( 'Lead Forms', 'smart-multistep-lead-forms' ), $label, self::CAP, 'smlf-dashboard', array( __CLASS__, 'page' ), 'dashicons-feedback', 26 );
		add_submenu_page( 'smlf-dashboard', __( 'Dashboard', 'smart-multistep-lead-forms' ), __( 'Dashboard', 'smart-multistep-lead-forms' ), self::CAP, 'smlf-dashboard', array( __CLASS__, 'page' ) );
		add_submenu_page( 'smlf-dashboard', __( 'Leads', 'smart-multistep-lead-forms' ), __( 'Leads', 'smart-multistep-lead-forms' ), self::CAP, 'smlf-leads', array( __CLASS__, 'page' ) );
		add_submenu_page( 'smlf-dashboard', __( 'Forms', 'smart-multistep-lead-forms' ), __( 'Forms', 'smart-multistep-lead-forms' ), self::CAP, 'smlf-forms', array( __CLASS__, 'page' ) );
		add_submenu_page( 'smlf-dashboard', __( 'Settings', 'smart-multistep-lead-forms' ), __( 'Settings', 'smart-multistep-lead-forms' ), self::CAP, 'smlf-settings', array( __CLASS__, 'page' ) );
		add_submenu_page( 'smlf-dashboard', __( 'E-mail log', 'smart-multistep-lead-forms' ), __( 'E-mail log', 'smart-multistep-lead-forms' ), self::CAP, 'smlf-emails', array( __CLASS__, 'page' ) );
		$builder = add_submenu_page( '', __( 'Form builder', 'smart-multistep-lead-forms' ), '', self::CAP, 'smlf-builder', array( __CLASS__, 'page' ) );
		// Pages without a parent have no menu title, which admin-header.php needs.
		add_action( 'load-' . $builder, function () {
			$GLOBALS['title'] = __( 'Form builder', 'smart-multistep-lead-forms' );
		} );
		// The hidden builder page keeps "Lead Forms > Forms" highlighted.
		add_filter( 'parent_file', function ( $file ) {
			return 'smlf-builder' === SMLF_Admin::page_slug() ? 'smlf-dashboard' : $file;
		} );
		add_filter( 'submenu_file', function ( $file ) {
			return 'smlf-builder' === SMLF_Admin::page_slug() ? 'smlf-forms' : $file;
		} );
	}

	public static function page_slug() {
		return self::current_page();
	}

	private static function current_page() {
		return isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
	}

	public static function assets() {
		$page = self::current_page();
		if ( 0 !== strpos( $page, 'smlf-' ) ) {
			return;
		}
		wp_enqueue_style( 'smlf-admin', SMLF_URL . 'assets/admin/admin.css', array(), SMLF_VERSION );
		wp_enqueue_script( 'smlf-admin', SMLF_URL . 'assets/admin/admin.js', array(), SMLF_VERSION, true );
		wp_localize_script( 'smlf-admin', 'smlfAdmin', array(
			'confirmTitle' => __( 'Please confirm', 'smart-multistep-lead-forms' ),
			'yes'          => __( 'Yes, continue', 'smart-multistep-lead-forms' ),
			'no'           => __( 'Cancel', 'smart-multistep-lead-forms' ),
			'copied'       => __( 'Copied', 'smart-multistep-lead-forms' ),
		) );
		if ( 'smlf-builder' === $page ) {
			wp_enqueue_style( 'smlf-form', SMLF_URL . 'assets/public/form.css', array(), SMLF_VERSION );
			wp_enqueue_script( 'smlf-form', SMLF_URL . 'assets/public/form.js', array(), SMLF_VERSION, true );
			wp_enqueue_style( 'smlf-builder', SMLF_URL . 'assets/admin/builder.css', array( 'smlf-admin' ), SMLF_VERSION );
			wp_enqueue_script( 'smlf-builder', SMLF_URL . 'assets/admin/builder.js', array( 'smlf-form' ), SMLF_VERSION, true );
		}
	}

	public static function page() {
		if ( ! current_user_can( self::CAP ) ) {
			return;
		}
		$views = array(
			'smlf-dashboard' => 'dashboard',
			'smlf-leads'     => isset( $_GET['lead'] ) ? 'lead' : 'leads',
			'smlf-forms'     => 'forms',
			'smlf-builder'   => 'builder',
			'smlf-settings'  => 'settings',
			'smlf-emails'    => 'emails',
		);
		$view = isset( $views[ self::current_page() ] ) ? $views[ self::current_page() ] : 'dashboard';
		echo '<div class="wrap smlf-admin smlf-view-' . esc_attr( $view ) . '">';
		self::notice();
		include SMLF_DIR . 'admin/views/' . $view . '.php';
		echo '</div>';
		self::footer();
	}

	public static function footer() {
		echo '<div class="smlf-footer"><div class="smlf-footer-card">'
			. '<div class="smlf-footer-title">Smart MultiStep Lead Forms <span class="smlf-footer-ver">v' . esc_html( SMLF_VERSION ) . '</span></div>'
			. '<div class="smlf-footer-by">' . esc_html__( 'Developed by', 'smart-multistep-lead-forms' ) . ' <a href="https://adschi.com" target="_blank" rel="noopener noreferrer">Mohammad Babaei</a> &middot; <a href="https://adschi.com" target="_blank" rel="noopener noreferrer">adschi.com</a></div>'
			. '</div></div>';
	}

	// -------------------------------------------------------------------------------------------
	// Messages after redirects
	// -------------------------------------------------------------------------------------------

	private static function redirect( $url, $msg ) {
		wp_safe_redirect( add_query_arg( 'smlf_msg', rawurlencode( $msg ), $url ) );
		exit;
	}

	private static function notice() {
		if ( empty( $_GET['smlf_msg'] ) ) {
			return;
		}
		list( $code, $n ) = array_pad( explode( ':', sanitize_text_field( wp_unslash( $_GET['smlf_msg'] ) ), 2 ), 2, '' );
		$texts = array(
			'saved'      => array( 'success', __( 'Saved.', 'smart-multistep-lead-forms' ) ),
			'deleted'    => array( 'success', sprintf( __( '%d item(s) deleted.', 'smart-multistep-lead-forms' ), (int) $n ) ),
			'bulk'       => array( 'success', sprintf( __( '%d lead(s) updated.', 'smart-multistep-lead-forms' ), (int) $n ) ),
			'duplicated' => array( 'success', __( 'Form duplicated.', 'smart-multistep-lead-forms' ) ),
			'nothing'    => array( 'warning', __( 'Nothing selected.', 'smart-multistep-lead-forms' ) ),
			'bad_date'   => array( 'error', __( 'Invalid follow-up date.', 'smart-multistep-lead-forms' ) ),
			'error'      => array( 'error', __( 'That did not work.', 'smart-multistep-lead-forms' ) ),
		);
		if ( isset( $texts[ $code ] ) ) {
			printf( '<div class="notice notice-%s is-dismissible"><p>%s</p></div>', esc_attr( $texts[ $code ][0] ), esc_html( $texts[ $code ][1] ) );
		}
	}

	// -------------------------------------------------------------------------------------------
	// Builder save (AJAX)
	// -------------------------------------------------------------------------------------------

	public static function ajax_save_form() {
		check_ajax_referer( 'smlf_builder', 'nonce' );
		if ( ! current_user_can( self::CAP ) ) {
			wp_send_json_error( array( 'message' => __( 'Not allowed.', 'smart-multistep-lead-forms' ) ), 403 );
		}
		$form = json_decode( wp_unslash( isset( $_POST['form'] ) ? (string) $_POST['form'] : '' ), true );
		if ( ! is_array( $form ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid data.', 'smart-multistep-lead-forms' ) ), 400 );
		}
		$form['schema'] = 2;
		$id = SMLF_Forms::save(
			isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0,
			isset( $_POST['title'] ) ? sanitize_text_field( wp_unslash( $_POST['title'] ) ) : '',
			$form,
			isset( $_POST['status'] ) ? sanitize_key( $_POST['status'] ) : 'publish'
		);
		$row = SMLF_Forms::get( $id );
		wp_send_json_success( array( 'id' => $id, 'form' => $row['form'], 'title' => $row['title'], 'status' => $row['status'] ) );
	}

	// -------------------------------------------------------------------------------------------
	// POST actions (forms, leads, settings) -> redirect
	// -------------------------------------------------------------------------------------------

	public static function handle_post() {
		if ( empty( $_POST['smlf_do'] ) || 0 !== strpos( self::current_page(), 'smlf-' ) ) {
			return;
		}
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( esc_html__( 'Not allowed.', 'smart-multistep-lead-forms' ), 403 );
		}
		check_admin_referer( 'smlf_action', 'smlf_nonce' );
		$do   = sanitize_key( wp_unslash( $_POST['smlf_do'] ) );
		$back = isset( $_POST['smlf_back'] ) ? esc_url_raw( wp_unslash( $_POST['smlf_back'] ) ) : admin_url( 'admin.php?page=smlf-dashboard' );
		if ( 0 !== strpos( $back, admin_url() ) ) {
			$back = admin_url( 'admin.php?page=smlf-dashboard' );
		}
		$id = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;

		switch ( $do ) {
			case 'new_form':
				$templates = SMLF_Forms::templates();
				$key       = isset( $_POST['template'] ) ? sanitize_key( $_POST['template'] ) : 'blank';
				$tpl       = isset( $templates[ $key ] ) ? $templates[ $key ] : $templates['blank'];
				$new       = SMLF_Forms::save( 0, $tpl['title'], $tpl['form'], 'draft' );
				wp_safe_redirect( admin_url( 'admin.php?page=smlf-builder&form=' . $new ) );
				exit;
			case 'duplicate_form':
				$row = SMLF_Forms::get( $id );
				if ( $row ) {
					SMLF_Forms::save( 0, sprintf( __( '%s (copy)', 'smart-multistep-lead-forms' ), $row['title'] ), $row['form'], 'draft' );
				}
				self::redirect( $back, $row ? 'duplicated' : 'error' );
				break;
			case 'delete_form':
				self::redirect( $back, SMLF_Forms::delete( $id ) ? 'deleted:1' : 'error' );
				break;
			case 'toggle_form':
				$row = SMLF_Forms::get( $id );
				if ( $row ) {
					SMLF_Forms::save( $id, $row['title'], $row['form'], 'publish' === $row['status'] ? 'draft' : 'publish' );
				}
				self::redirect( $back, $row ? 'saved' : 'error' );
				break;
			case 'lead_update':
				$follow = isset( $_POST['followup_at'] ) ? trim( sanitize_text_field( wp_unslash( $_POST['followup_at'] ) ) ) : '';
				if ( '' !== $follow ) {
					$d = DateTime::createFromFormat( '!Y-m-d\TH:i', $follow );
					if ( ! $d || $d->format( 'Y-m-d\TH:i' ) !== $follow ) {
						self::redirect( $back, 'bad_date' );
					}
					$follow = $d->format( 'Y-m-d H:i:00' );
				}
				$ok = SMLF_Leads::update_crm( $id, sanitize_key( wp_unslash( isset( $_POST['lead_status'] ) ? $_POST['lead_status'] : '' ) ), sanitize_textarea_field( wp_unslash( isset( $_POST['note'] ) ? $_POST['note'] : '' ) ), $follow );
				self::redirect( $back, $ok ? 'saved' : 'error' );
				break;
			case 'lead_delete':
				self::redirect( remove_query_arg( 'lead', $back ), 'deleted:' . SMLF_Leads::delete( array( $id ) ) );
				break;
			case 'lead_bulk':
				$ids    = array_filter( array_map( 'intval', isset( $_POST['ids'] ) ? (array) $_POST['ids'] : array() ) );
				$action = isset( $_POST['bulk_action'] ) ? sanitize_key( $_POST['bulk_action'] ) : '';
				if ( ! $ids || '' === $action ) {
					self::redirect( $back, 'nothing' );
				}
				if ( 'delete' === $action ) {
					self::redirect( $back, 'deleted:' . SMLF_Leads::delete( $ids ) );
				}
				$n = 0;
				foreach ( $ids as $lead_id ) {
					$n += (int) SMLF_Leads::update_crm( $lead_id, $action );
				}
				self::redirect( $back, 'bulk:' . $n );
				break;
			case 'settings':
				self::save_settings();
				self::redirect( $back, 'saved' );
				break;
		}
		self::redirect( $back, 'error' );
	}

	private static function save_settings() {
		$p    = function ( $k, $d = '' ) {
			return isset( $_POST[ $k ] ) ? wp_unslash( $_POST[ $k ] ) : $d;
		};
		$exts = array_filter( array_map( function ( $e ) {
			return preg_replace( '/[^a-z0-9]/', '', strtolower( trim( $e ) ) );
		}, explode( ',', (string) $p( 'smlf_allowed_file_extensions' ) ) ) );
		$exts = array_diff( $exts, array( 'php', 'phtml', 'phar', 'html', 'htm', 'svg', 'js', 'exe', 'sh' ) );
		$statuses = array();
		foreach ( preg_split( '/\r\n|\n/', (string) $p( 'smlf_lead_statuses' ) ) as $line ) {
			$parts = array_map( 'trim', explode( ':', $line, 2 ) );
			$key   = sanitize_key( $parts[0] );
			if ( '' !== $key ) {
				$statuses[] = $key . ':' . sanitize_text_field( isset( $parts[1] ) ? $parts[1] : $key );
			}
		}
		$email = sanitize_email( (string) $p( 'smlf_admin_email' ) );
		update_option( 'smlf_admin_email', is_email( $email ) ? $email : get_option( 'admin_email' ) );
		update_option( 'smlf_captcha_method', in_array( $p( 'smlf_captcha_method' ), array( 'none', 'turnstile', 'recaptcha_v2', 'recaptcha_v3' ), true ) ? $p( 'smlf_captcha_method' ) : 'none' );
		update_option( 'smlf_captcha_site_key', sanitize_text_field( (string) $p( 'smlf_captcha_site_key' ) ) );
		if ( '' !== trim( (string) $p( 'smlf_captcha_secret_key' ) ) ) { // empty = keep the stored secret
			update_option( 'smlf_captcha_secret_key', sanitize_text_field( (string) $p( 'smlf_captcha_secret_key' ) ) );
		}
		update_option( 'smlf_enable_partial', $p( 'smlf_enable_partial' ) ? 1 : 0 );
		update_option( 'smlf_partial_retention_days', max( 0, min( 365, (int) $p( 'smlf_partial_retention_days', 30 ) ) ) );
		update_option( 'smlf_lead_retention_months', max( 0, min( 120, (int) $p( 'smlf_lead_retention_months', 0 ) ) ) );
		update_option( 'smlf_max_file_size_mb', max( 1, min( 50, (int) $p( 'smlf_max_file_size_mb', 5 ) ) ) );
		update_option( 'smlf_max_file_count', max( 1, min( 10, (int) $p( 'smlf_max_file_count', 3 ) ) ) );
		update_option( 'smlf_allowed_file_extensions', implode( ',', $exts ? $exts : array( 'jpg', 'jpeg', 'png', 'pdf' ) ) );
		update_option( 'smlf_lead_statuses', $statuses ? implode( "\n", $statuses ) : "new:New" );
		update_option( 'smlf_uninstall_data_action', 'delete' === $p( 'smlf_uninstall_data_action' ) ? 'delete' : 'keep' );
	}

	// -------------------------------------------------------------------------------------------
	// Lead filters (shared by the list and the export)
	// -------------------------------------------------------------------------------------------

	public static function lead_filters() {
		$g = function ( $k, $d = '' ) {
			return isset( $_GET[ $k ] ) && is_string( $_GET[ $k ] ) ? sanitize_text_field( wp_unslash( $_GET[ $k ] ) ) : $d;
		};
		$date = function ( $v ) {
			return preg_match( '/^\d{4}-\d{2}-\d{2}$/', $v ) ? $v : '';
		};
		$state = $g( 'state', 'completed' );
		$per   = (int) $g( 'per_page', 20 );
		$status = sanitize_key( $g( 'status', '' ) );
		return array(
			'form'     => absint( $g( 'form', 0 ) ),
			'state'    => in_array( $state, array( 'completed', 'partial', 'all' ), true ) ? $state : 'completed',
			'status'   => isset( SMLF_Leads::statuses()[ $status ] ) ? $status : '',
			'due'      => '1' === $g( 'due', '' ),
			'q'        => function_exists( 'mb_substr' ) ? mb_substr( $g( 'q', '' ), 0, 100 ) : substr( $g( 'q', '' ), 0, 100 ),
			'from'     => $date( $g( 'from', '' ) ),
			'to'       => $date( $g( 'to', '' ) ),
			'orderby'  => sanitize_key( $g( 'orderby', 'created_at' ) ),
			'order'    => 'asc' === strtolower( $g( 'order', 'desc' ) ) ? 'ASC' : 'DESC',
			'per_page' => in_array( $per, array( 20, 50, 100 ), true ) ? $per : 20,
			'paged'    => max( 1, absint( $g( 'paged', 1 ) ) ),
		);
	}

	/** CSV of the current lead filter; one column per field when a form is selected. */
	public static function export_csv() {
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( esc_html__( 'Not allowed.', 'smart-multistep-lead-forms' ), 403 );
		}
		check_admin_referer( 'smlf_export' );
		$f = self::lead_filters();
		list( $rows ) = SMLF_Leads::query( $f, false );
		$form   = $f['form'] ? SMLF_Forms::get( $f['form'] ) : null;
		$fields = $form ? array_filter( SMLF_Forms::fields_flat( $form['form'] ), function ( $x ) {
			return ! in_array( $x['type'], array( 'message', 'file' ), true );
		} ) : array();
		$statuses = SMLF_Leads::statuses();

		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="leads-' . ( $form ? sanitize_title( $form['title'] ) . '-' : '' ) . gmdate( 'Y-m-d' ) . '.csv"' );
		$out  = fopen( 'php://output', 'w' );
		fwrite( $out, "\xEF\xBB\xBF" );
		$cell = function ( $v ) {
			$v = is_array( $v ) ? implode( ', ', $v ) : (string) $v;
			return preg_match( '/^[=+\-@\t\r]/', $v ) ? "'" . $v : $v; // no spreadsheet formulas
		};
		$head = array( 'ID', __( 'Form', 'smart-multistep-lead-forms' ), __( 'Status', 'smart-multistep-lead-forms' ), __( 'Completed', 'smart-multistep-lead-forms' ), __( 'Name', 'smart-multistep-lead-forms' ), 'E-Mail', __( 'Phone', 'smart-multistep-lead-forms' ) );
		foreach ( $fields as $fld ) {
			$head[] = $fld['label'] ? $fld['label'] : $fld['id'];
		}
		$head = array_merge( $head, array( __( 'Page', 'smart-multistep-lead-forms' ), 'UTM source', 'UTM medium', 'UTM campaign', __( 'Created', 'smart-multistep-lead-forms' ) ) );
		fputcsv( $out, array_map( $cell, $head ), ';' );
		$titles = wp_list_pluck( SMLF_Forms::all(), 'title', 'id' );
		foreach ( $rows as $r ) {
			$line = array( $r['id'], isset( $titles[ $r['form_id'] ] ) ? $titles[ $r['form_id'] ] : $r['form_id'], isset( $statuses[ $r['lead_status'] ] ) ? $statuses[ $r['lead_status'] ] : $r['lead_status'], 'completed' === $r['status'] ? 'yes' : 'no', $r['name'], $r['email'], $r['phone'] );
			foreach ( $fields as $id => $fld ) {
				$line[] = isset( $r['answers'][ $id ] ) ? SMLF_Leads::display_value( $r['answers'][ $id ], $fld ) : '';
			}
			$line = array_merge( $line, array( $r['page_url'], $r['utm_source'], $r['utm_medium'], $r['utm_campaign'], $r['created_at'] ) );
			fputcsv( $out, array_map( $cell, $line ), ';' );
		}
		fclose( $out );
		exit;
	}

	// -------------------------------------------------------------------------------------------
	// Small view helpers
	// -------------------------------------------------------------------------------------------

	public static function action_form( $do, array $fields, $button, $class = 'button', $confirm = '' ) {
		echo '<form method="post" class="smlf-inline-form">';
		wp_nonce_field( 'smlf_action', 'smlf_nonce' );
		echo '<input type="hidden" name="smlf_do" value="' . esc_attr( $do ) . '">';
		echo '<input type="hidden" name="smlf_back" value="' . esc_url( self::current_url() ) . '">';
		foreach ( $fields as $k => $v ) {
			echo '<input type="hidden" name="' . esc_attr( $k ) . '" value="' . esc_attr( $v ) . '">';
		}
		echo '<button class="' . esc_attr( $class ) . '"' . ( $confirm ? ' data-smlf-confirm="' . esc_attr( $confirm ) . '"' : '' ) . '>' . $button . '</button></form>'; // $button is escaped by callers
	}

	public static function current_url() {
		return admin_url( 'admin.php?' . http_build_query( array_map( function ( $v ) {
			return is_string( $v ) ? sanitize_text_field( wp_unslash( $v ) ) : '';
		}, array_diff_key( $_GET, array( 'smlf_msg' => 1 ) ) ) ) );
	}

	public static function status_badge( $key ) {
		$statuses = SMLF_Leads::statuses();
		$keys     = array_keys( $statuses );
		$i        = array_search( $key, $keys, true );
		$palette  = array( '#b45309', '#1d4ed8', '#7c3aed', '#15803d', '#b91c1c', '#0e7490', '#4b5563' );
		$color    = false === $i ? '#4b5563' : $palette[ $i % count( $palette ) ];
		return '<span class="smlf-badge" style="--c:' . esc_attr( $color ) . '">' . esc_html( isset( $statuses[ $key ] ) ? $statuses[ $key ] : $key ) . '</span>';
	}

	public static function ago( $mysql ) {
		$ts = strtotime( (string) $mysql );
		return $ts ? sprintf( __( '%s ago', 'smart-multistep-lead-forms' ), human_time_diff( $ts, strtotime( current_time( 'mysql' ) ) ) ) : '';
	}

	public static function dt( $mysql ) {
		return $mysql ? mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $mysql ) : '';
	}
}
