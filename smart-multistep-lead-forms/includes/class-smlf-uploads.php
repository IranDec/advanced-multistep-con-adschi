<?php
/**
 * File uploads: only with the final submission, real type check (content, not just the name),
 * size / count limits, stored in a private folder with unguessable names, downloaded only by
 * admins through admin-post.php (never linked publicly).
 *
 * @package Smart_Multistep_Lead_Forms
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SMLF_Uploads {

	const DIR = 'smlf-private';

	public static function base_dir() {
		$up  = wp_get_upload_dir();
		$dir = trailingslashit( $up['basedir'] ) . self::DIR;
		if ( ! is_dir( $dir ) ) {
			wp_mkdir_p( $dir );
		}
		if ( ! file_exists( $dir . '/index.php' ) ) {
			file_put_contents( $dir . '/index.php', "<?php // Silence is golden.\n" );
		}
		if ( ! file_exists( $dir . '/.htaccess' ) ) {
			file_put_contents( $dir . '/.htaccess', "Require all denied\nDeny from all\n" );
		}
		return $dir;
	}

	/** Allowed extensions of a field (intersection of the field list and the global list). */
	public static function allowed_ext( array $field ) {
		$global = array_filter( array_map( 'trim', explode( ',', strtolower( (string) get_option( 'smlf_allowed_file_extensions', 'jpg,jpeg,png,pdf' ) ) ) ) );
		$own    = array_filter( array_map( 'trim', explode( ',', strtolower( (string) $field['accept'] ) ) ) );
		$blocked = array( 'php', 'phtml', 'phar', 'php3', 'php4', 'php5', 'php7', 'pht', 'shtml', 'html', 'htm', 'svg', 'js', 'exe', 'sh', 'bat', 'cgi', 'pl', 'py', 'htaccess' );
		return array_values( array_diff( $own ? array_intersect( $own, $global ) : $global, $blocked ) );
	}

	/**
	 * Stores the files of all visible file fields.
	 *
	 * @return array|WP_Error [field_id => [ ['name','path','size','type'] ]]
	 */
	public static function handle( array $file_fields ) {
		if ( empty( $_FILES['smlf_files'] ) || ! is_array( $_FILES['smlf_files']['name'] ) ) {
			return array();
		}
		$max_mb   = max( 1, (int) get_option( 'smlf_max_file_size_mb', 5 ) );
		$max_all  = max( 1, (int) get_option( 'smlf_max_file_count', 3 ) );
		$dir      = self::base_dir() . '/' . gmdate( 'Y-m' ) . '-' . wp_generate_password( 20, false );
		$out      = array();
		$total    = 0;

		foreach ( $file_fields as $field ) {
			$fid = $field['id'];
			if ( empty( $_FILES['smlf_files']['name'][ $fid ] ) ) {
				continue;
			}
			$names = (array) $_FILES['smlf_files']['name'][ $fid ];
			if ( count( array_filter( $names ) ) > min( $field['max_files'], $max_all ) ) {
				return new WP_Error( 'smlf_files', sprintf( __( 'Please upload at most %d files.', 'smart-multistep-lead-forms' ), min( $field['max_files'], $max_all ) ) );
			}
			$allowed = self::allowed_ext( $field );
			foreach ( $names as $i => $name ) {
				if ( '' === (string) $name ) {
					continue;
				}
				$tmp   = $_FILES['smlf_files']['tmp_name'][ $fid ][ $i ];
				$error = (int) $_FILES['smlf_files']['error'][ $fid ][ $i ];
				$size  = (int) $_FILES['smlf_files']['size'][ $fid ][ $i ];
				if ( UPLOAD_ERR_OK !== $error || ! is_uploaded_file( $tmp ) ) {
					return new WP_Error( 'smlf_files', __( 'A file could not be uploaded.', 'smart-multistep-lead-forms' ) );
				}
				if ( $size > $max_mb * MB_IN_BYTES ) {
					return new WP_Error( 'smlf_files', sprintf( __( 'Files may be at most %d MB.', 'smart-multistep-lead-forms' ), $max_mb ) );
				}
				$check = wp_check_filetype_and_ext( $tmp, sanitize_file_name( $name ) );
				$ext   = strtolower( (string) $check['ext'] );
				if ( ! $ext || ! $check['type'] || ! in_array( $ext, $allowed, true ) ) {
					/* translators: %s: list of file extensions */
					return new WP_Error( 'smlf_files', sprintf( __( 'File type not allowed. Allowed: %s', 'smart-multistep-lead-forms' ), implode( ', ', $allowed ) ) );
				}
				if ( ++$total > $max_all * 3 ) {
					break 2;
				}
				if ( ! is_dir( $dir ) ) {
					wp_mkdir_p( $dir );
				}
				$stored = wp_generate_password( 24, false ) . '.' . $ext;
				if ( ! move_uploaded_file( $tmp, $dir . '/' . $stored ) ) {
					return new WP_Error( 'smlf_files', __( 'A file could not be uploaded.', 'smart-multistep-lead-forms' ) );
				}
				$out[ $fid ][] = array(
					'name' => sanitize_file_name( $name ),
					'path' => str_replace( self::base_dir() . '/', '', $dir . '/' . $stored ),
					'size' => $size,
					'type' => $check['type'],
				);
			}
		}
		return $out;
	}

	public static function absolute( $relative ) {
		$base = realpath( self::base_dir() );
		$file = realpath( self::base_dir() . '/' . ltrim( (string) $relative, '/' ) );
		return $base && $file && 0 === strpos( $file, $base . DIRECTORY_SEPARATOR ) && is_file( $file ) ? $file : '';
	}

	public static function download_url( $lead_id, $field_id, $index ) {
		return wp_nonce_url( add_query_arg( array( 'action' => 'smlf_download', 'lead' => (int) $lead_id, 'field' => $field_id, 'i' => (int) $index ), admin_url( 'admin-post.php' ) ), 'smlf_download_' . (int) $lead_id );
	}

	/** admin-post.php?action=smlf_download */
	public static function download() {
		$lead_id = isset( $_GET['lead'] ) ? (int) $_GET['lead'] : 0;
		if ( ! current_user_can( 'manage_options' ) || ! wp_verify_nonce( isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '', 'smlf_download_' . $lead_id ) ) {
			wp_die( esc_html__( 'Not allowed.', 'smart-multistep-lead-forms' ), 403 );
		}
		$lead  = SMLF_Leads::get( $lead_id );
		$field = isset( $_GET['field'] ) ? sanitize_key( wp_unslash( $_GET['field'] ) ) : '';
		$i     = isset( $_GET['i'] ) ? (int) $_GET['i'] : 0;
		$entry = $lead && isset( $lead['files'][ $field ][ $i ] ) ? $lead['files'][ $field ][ $i ] : null;
		$file  = $entry ? self::absolute( $entry['path'] ) : '';
		if ( ! $file ) {
			wp_die( esc_html__( 'File not found.', 'smart-multistep-lead-forms' ), 404 );
		}
		nocache_headers();
		header( 'Content-Type: ' . ( $entry['type'] ? $entry['type'] : 'application/octet-stream' ) );
		header( 'Content-Disposition: attachment; filename="' . str_replace( '"', '', $entry['name'] ) . '"' );
		header( 'Content-Length: ' . filesize( $file ) );
		header( 'X-Content-Type-Options: nosniff' );
		readfile( $file );
		exit;
	}

	public static function delete_files( array $files ) {
		$dirs = array();
		foreach ( $files as $list ) {
			foreach ( (array) $list as $entry ) {
				$file = isset( $entry['path'] ) ? self::absolute( $entry['path'] ) : '';
				if ( $file ) {
					$dirs[ dirname( $file ) ] = true;
					wp_delete_file( $file );
				}
			}
		}
		foreach ( array_keys( $dirs ) as $d ) {
			@rmdir( $d ); // only removed when empty
		}
	}
}
