<?php
/**
 * The page of a design: kept with units (version 2: width and height in px, safe and bleed in mm, as CSS says them) and worked on in
 * points (w, h, bg, safe, bleed), which is what editors and renderers use. A px is a point here.
 *
 * @package Camaleaun\Draw
 */

namespace Camaleaun\Draw;

final class Page {

	/**
	 * Points in each unit.
	 */
	const UNITS = array(
		'pt' => 1,
		'px' => 1,
		'mm' => 2.83465,
		'cm' => 28.3465,
		'in' => 72,
	);

	/**
	 * A length as points: "20pt", "10mm", "5%" (of $total), "calc(100% - 40pt)", or a number (points).
	 *
	 * @param mixed $value Length.
	 * @param float $total What 100% is.
	 * @return float
	 */
	public static function length( $value, $total = 0.0 ) {
		if ( is_int( $value ) || is_float( $value ) ) {
			return (float) $value;
		}
		$expr = strtolower( trim( (string) $value ) );
		if ( '' === $expr ) {
			return 0.0;
		}
		if ( preg_match( '/^calc\((.*)\)$/', $expr, $m ) ) {
			$expr = $m[1];
		}
		if ( ! preg_match_all( '/([+-]?)\s*(-?\d*\.?\d+)\s*(pt|px|mm|cm|in|%)?/', $expr, $all, PREG_SET_ORDER ) ) {
			return 0.0;
		}
		$sum = 0.0;
		foreach ( $all as $m ) {
			$n    = (float) $m[2] * ( '-' === $m[1] ? -1 : 1 );
			$unit = $m[3] ?? '';
			$sum += '%' === $unit ? $n * $total / 100 : $n * ( self::UNITS[ '' === $unit ? 'pt' : $unit ] ?? 1 );
		}

		return $sum;
	}

	/**
	 * The page of a stored design as numbers in points; a design that has no `width` (an earlier version, or already in points) is
	 * given back as it is. Safe to run again.
	 *
	 * @param array<string, mixed> $design Design.
	 * @return array<string, mixed>
	 */
	public static function in_points( array $design ) {
		$page = $design['page'] ?? null;
		if ( ! is_array( $page ) || ! isset( $page['width'] ) ) {
			return $design;
		}
		$out            = array_diff_key( $page, array_flip( array( 'width', 'height', 'backgroundColor', 'safe', 'bleed' ) ) );
		$width          = self::length( $page['width'] );
		$height         = self::length( $page['height'] ?? '' );
		$out['w']       = $width > 0 ? $width : 841.89;
		$out['h']       = $height > 0 ? $height : 595.28;
		$out['bg']      = $page['backgroundColor'] ?? '$background';
		$out['safe']    = self::length( $page['safe'] ?? 0 );
		$out['bleed']   = self::length( $page['bleed'] ?? 0 );
		$design['page'] = $out;

		return $design;
	}

	/**
	 * A page in points as it is kept: lengths with their unit, and no bleed when there is none.
	 *
	 * @param array<string, mixed> $page Page (w, h, bg, safe, bleed).
	 * @return array<string, mixed>
	 */
	public static function stored( array $page ) {
		$n   = static function ( $value ) {
			return rtrim( rtrim( number_format( round( (float) $value, 2 ), 2, '.', '' ), '0' ), '.' );
		};
		$out = array(
			'width'           => $n( $page['w'] ?? 841.89 ) . 'px',
			'height'          => $n( $page['h'] ?? 595.28 ) . 'px',
			'backgroundColor' => $page['bg'] ?? '$background',
			'safe'            => $n( (float) ( $page['safe'] ?? 0 ) / self::UNITS['mm'] ) . 'mm',
		);
		if ( (float) ( $page['bleed'] ?? 0 ) > 0 ) {
			$out['bleed'] = $n( (float) $page['bleed'] / self::UNITS['mm'] ) . 'mm';
		}

		return $out + array_diff_key( $page, array_flip( array( 'w', 'h', 'bg', 'safe', 'bleed' ) ) );
	}
}
