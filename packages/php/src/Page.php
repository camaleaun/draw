<?php
/**
 * The page of a design: kept with units (width and height in the unit that makes them round — 297mm by 210mm for an A4, 11in by 8.5in for a letter —,
 * safe and bleed in mm, as CSS says them) and worked on in
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
		// To the hundredth of a point, as it was kept before units: 297mm is 841.89105 with the factor of a mm, and that much over the width of the
		// sheet is a page more in the PDF.
		$out['w']       = $width > 0 ? round( $width, 2 ) : 841.89;
		$out['h']       = $height > 0 ? round( $height, 2 ) : 595.28;
		$out['bg']      = $page['backgroundColor'] ?? '$background';
		$out['safe']    = self::length( $page['safe'] ?? 0 );
		$out['bleed']   = self::length( $page['bleed'] ?? 0 );
		$design['page'] = $out;

		return $design;
	}

	/**
	 * The width and height of a page, as lengths in the unit that makes both round: inches, millimeters or, when neither does, points. An
	 * A4 is "297mm" by "210mm" (in points it would be 841.89 by 595.28), a letter "11in" by "8.5in". Of the two units that are exact (to what a
	 * length in points is kept to) the one with the fewest decimals wins, and a tie is in; points are for a page that is round in neither.
	 *
	 * @param float $w Width in points.
	 * @param float $h Height in points.
	 * @return array{0: string, 1: string}
	 */
	public static function size( $w, $h ) {
		$best = null;
		foreach ( array( 'in', 'mm' ) as $unit ) {
			$factor = self::UNITS[ $unit ];
			$parts  = array();
			$digits = 0;
			foreach ( array( $w, $h ) as $points ) {
				$found = null;
				for ( $d = 0; $d <= 2; $d++ ) {
					$value = round( $points / $factor, $d );
					// Round when the value in this unit is the length to within what a length in points is kept to.
					if ( abs( $value * $factor - $points ) < 0.006 ) {
						$found = array( $d, $value );
						break;
					}
				}
				if ( null === $found ) {
					continue 2;
				}
				$digits  = max( $digits, $found[0] );
				$parts[] = rtrim( rtrim( number_format( $found[1], 2, '.', '' ), '0' ), '.' ) . $unit;
			}
			if ( null === $best || $digits < $best[0] ) {
				$best = array( $digits, $parts );
			}
		}

		return $best ? $best[1] : array( round( $w, 2 ) . 'pt', round( $h, 2 ) . 'pt' );
	}

	/**
	 * A page in points as it is kept: lengths with their unit, and no safe margin and no bleed when there is none.
	 *
	 * @param array<string, mixed> $page Page (w, h, bg, safe, bleed).
	 * @return array<string, mixed>
	 */
	public static function stored( array $page ) {
		$n   = static function ( $value ) {
			return rtrim( rtrim( number_format( round( (float) $value, 2 ), 2, '.', '' ), '0' ), '.' );
		};
		$size = self::size( (float) ( $page['w'] ?? 841.89 ), (float) ( $page['h'] ?? 595.28 ) );
		$out  = array(
			'width'           => $size[0],
			'height'          => $size[1],
			'backgroundColor' => $page['bg'] ?? '$background',
		);
		// No safe margin and no bleed when there is none.
		if ( (float) ( $page['safe'] ?? 0 ) > 0 ) {
			$out['safe'] = $n( (float) $page['safe'] / self::UNITS['mm'] ) . 'mm';
		}
		if ( (float) ( $page['bleed'] ?? 0 ) > 0 ) {
			$out['bleed'] = $n( (float) $page['bleed'] / self::UNITS['mm'] ) . 'mm';
		}

		return $out + array_diff_key( $page, array_flip( array( 'w', 'h', 'bg', 'safe', 'bleed' ) ) );
	}
}
