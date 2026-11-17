<?php
/**
 * Conditional logic on the server - the same rules as assets/public/form.js:
 * which steps a visitor goes through (routes), which fields are visible (show_if).
 * Used to validate only what the visitor actually saw and to drop answers of skipped steps.
 *
 * @package Smart_Multistep_Lead_Forms
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SMLF_Logic {

	public static function test( array $c, array $data ) {
		$v = isset( $data[ $c['field'] ] ) ? $data[ $c['field'] ] : '';
		$x = (string) $c['value'];
		switch ( $c['op'] ) {
			case 'eq':
				return is_array( $v ) ? in_array( $x, array_map( 'strval', $v ), true ) : 0 === strcasecmp( (string) $v, $x );
			case 'neq':
				return ! self::test( array( 'field' => $c['field'], 'op' => 'eq', 'value' => $x ), $data );
			case 'contains':
				return is_array( $v ) ? in_array( $x, array_map( 'strval', $v ), true ) : ( '' !== $x && false !== stripos( (string) $v, $x ) );
			case 'not_contains':
				return ! self::test( array( 'field' => $c['field'], 'op' => 'contains', 'value' => $x ), $data );
			case 'empty':
				return is_array( $v ) ? ! array_filter( $v, 'strlen' ) : '' === trim( (string) $v );
			case 'not_empty':
				return ! self::test( array( 'field' => $c['field'], 'op' => 'empty', 'value' => '' ), $data );
			case 'gt':
				return is_numeric( $v ) && is_numeric( $x ) && (float) $v > (float) $x;
			case 'lt':
				return is_numeric( $v ) && is_numeric( $x ) && (float) $v < (float) $x;
		}
		return false;
	}

	public static function visible( array $field, array $data ) {
		$rules = isset( $field['show_if']['rules'] ) ? $field['show_if']['rules'] : array();
		if ( ! $rules ) {
			return true;
		}
		$any = 'any' === $field['show_if']['match'];
		foreach ( $rules as $r ) {
			$ok = self::test( $r, $data );
			if ( $any && $ok ) {
				return true;
			}
			if ( ! $any && ! $ok ) {
				return false;
			}
		}
		return ! $any;
	}

	/**
	 * Steps a visitor with these answers goes through, in order.
	 *
	 * @return array ['steps' => string[], 'dead_end' => bool] dead_end = path ends on a "reset" step
	 */
	public static function path( array $form, array $data ) {
		$steps = $form['steps'];
		$index = array();
		foreach ( $steps as $i => $s ) {
			$index[ $s['id'] ] = $i;
		}
		$path = array();
		$i    = 0;
		while ( isset( $steps[ $i ] ) && ! in_array( $steps[ $i ]['id'], $path, true ) ) {
			$step   = $steps[ $i ];
			$path[] = $step['id'];
			if ( 'reset' === $step['terminal'] ) {
				return array( 'steps' => $path, 'dead_end' => true );
			}
			$target = self::next_step( $step, $data );
			if ( 'end' === $target ) {
				break;
			}
			$i = '' === $target ? self::following( $steps, $i ) : ( isset( $index[ $target ] ) ? $index[ $target ] : self::following( $steps, $i ) );
		}
		return array( 'steps' => $path, 'dead_end' => false );
	}

	/** "Next step in the list" skips dead-end steps: they are only reached through an explicit jump. */
	public static function following( array $steps, $i ) {
		$j = $i + 1;
		while ( isset( $steps[ $j ] ) && 'reset' === $steps[ $j ]['terminal'] ) {
			$j++;
		}
		return $j;
	}

	/** '' = following step, 'end' = submit, otherwise a step id. */
	public static function next_step( array $step, array $data ) {
		foreach ( $step['routes'] as $r ) {
			if ( self::test( $r['when'], $data ) ) {
				return $r['goto'];
			}
		}
		return $step['next'];
	}
}
