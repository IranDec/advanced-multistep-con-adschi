<?php
/**
 * Conversion funnel: daily counters per form (view, start, step reached, submit).
 * No personal data and no per-visitor rows - only numbers.
 *
 * @package Smart_Multistep_Lead_Forms
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SMLF_Stats {

	const METRICS = array( 'view', 'start', 'step', 'submit' );

	public static function hit( $form_id, $metric, $step = '' ) {
		global $wpdb;
		if ( ! in_array( $metric, self::METRICS, true ) ) {
			return;
		}
		$wpdb->query( $wpdb->prepare(
			"INSERT INTO {$wpdb->prefix}smlf_stats (form_id, day, metric, step, hits) VALUES (%d, %s, %s, %s, 1)
			 ON DUPLICATE KEY UPDATE hits = hits + 1",
			$form_id,
			current_time( 'Y-m-d' ),
			$metric,
			'step' === $metric ? substr( preg_replace( '/[^a-z0-9_]/', '', (string) $step ), 0, 40 ) : ''
		) );
	}

	/** Funnel of a form (or all forms with $form_id = 0) for the last $days days. */
	public static function funnel( $form_id, $days = 30 ) {
		global $wpdb;
		$from  = gmdate( 'Y-m-d', strtotime( current_time( 'Y-m-d' ) . ' -' . ( (int) $days - 1 ) . ' days' ) );
		$where = $wpdb->prepare( 'day >= %s', $from ) . ( $form_id ? $wpdb->prepare( ' AND form_id = %d', $form_id ) : '' );
		$tot   = array_fill_keys( array( 'view', 'start', 'submit' ), 0 );
		foreach ( (array) $wpdb->get_results( "SELECT metric, SUM(hits) n FROM {$wpdb->prefix}smlf_stats WHERE $where AND metric <> 'step' GROUP BY metric" ) as $r ) {
			$tot[ $r->metric ] = (int) $r->n;
		}
		$steps = array();
		if ( $form_id ) {
			foreach ( (array) $wpdb->get_results( "SELECT step, SUM(hits) n FROM {$wpdb->prefix}smlf_stats WHERE $where AND metric = 'step' GROUP BY step" ) as $r ) {
				$steps[ $r->step ] = (int) $r->n;
			}
		}
		$daily = array();
		for ( $i = (int) $days - 1; $i >= 0; $i-- ) {
			$d           = gmdate( 'Y-m-d', strtotime( current_time( 'Y-m-d' ) . " -$i days" ) );
			$daily[ $d ] = array( 'view' => 0, 'submit' => 0 );
		}
		foreach ( (array) $wpdb->get_results( "SELECT day, metric, SUM(hits) n FROM {$wpdb->prefix}smlf_stats WHERE $where AND metric IN ('view','submit') GROUP BY day, metric" ) as $r ) {
			if ( isset( $daily[ $r->day ] ) ) {
				$daily[ $r->day ][ $r->metric ] = (int) $r->n;
			}
		}
		return array(
			'totals'     => $tot,
			'steps'      => $steps,
			'daily'      => $daily,
			'start_rate' => $tot['view'] ? round( 100 * $tot['start'] / $tot['view'], 1 ) : 0,
			'conversion' => $tot['view'] ? round( 100 * $tot['submit'] / $tot['view'], 1 ) : 0,
			'completion' => $tot['start'] ? round( 100 * $tot['submit'] / $tot['start'], 1 ) : 0,
		);
	}
}
