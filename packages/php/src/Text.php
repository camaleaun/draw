<?php
/**
 * The line height and the letter spacing of a text, which may have a unit: the same as `typography.ts` of the TypeScript package.
 *
 * A number is what files wrote before units: the line height a multiplier of the font size (1.2), the letter spacing points. A text is a length with its unit: `em` (the font size: 1.4em is 1.4, 0.05em is 5%), `lh` (a
 * multiplier, the line height's own), `%` (of the font size), px, pt, mm, cm, in; with no unit a letter spacing is a percentage and a line height a multiplier.
 *
 * @package Camaleaun\Draw
 */

namespace Camaleaun\Draw;

/**
 * Typography lengths.
 */
final class Text {

	const PARSE = '/^\s*(-?\d*\.?\d+)\s*(lh|em|%|px|pt|mm|cm|in)?\s*$/i';

	const FONT_SIZE = '/^\s*(\d*\.?\d+)\s*(px|pt|mm|cm|in)?\s*$/i';

	/**
	 * A text length as a number and its unit ('' when it has none).
	 *
	 * @param mixed $value Value.
	 * @return array{0: float, 1: string}|null Null when it is not a length.
	 */
	public static function parse( $value ) {
		if ( ! is_string( $value ) || 1 !== preg_match( self::PARSE, $value, $m ) ) {
			return null;
		}

		return array( (float) $m[1], strtolower( $m[2] ?? '' ) );
	}

	/**
	 * A font size in points: a number is points, a text is a length with its unit (`12.5pt`, `4.4mm`, `16px`; no unit is points) rounded to eight decimals; the fallback for none or
	 * for what is not a size (an `em` is not one).
	 *
	 * @param mixed $value    Value.
	 * @param float $fallback What none is.
	 * @return float
	 */
	public static function font_size( $value, $fallback = 14.0 ) {
		if ( is_int( $value ) || is_float( $value ) ) {
			return $value > 0 ? (float) $value : (float) $fallback;
		}
		if ( ! is_string( $value ) || 1 !== preg_match( self::FONT_SIZE, $value, $m ) || (float) $m[1] <= 0 ) {
			return (float) $fallback;
		}

		return round( (float) $m[1] * ( Page::UNITS[ strtolower( $m[2] ?? 'pt' ) ] ?? 1 ), 8 );
	}

	/**
	 * The line height as a multiplier of the font size.
	 *
	 * @param mixed $value    Value (a number, or a length with a unit).
	 * @param float $size     Font size.
	 * @param float $fallback What none is.
	 * @return float
	 */
	public static function line_height( $value, $size, $fallback = 1.2 ) {
		if ( is_int( $value ) || is_float( $value ) ) {
			return $value > 0 ? (float) $value : (float) $fallback;
		}
		$p = self::parse( $value );
		if ( null === $p || $p[0] <= 0 || $size <= 0 ) {
			return (float) $fallback;
		}
		if ( '%' === $p[1] ) {
			return $p[0] / 100;
		}
		if ( '' === $p[1] || 'lh' === $p[1] || 'em' === $p[1] ) {
			return $p[0];
		}

		return $p[0] * ( Page::UNITS[ $p[1] ] ?? 1 ) / $size;
	}

	/**
	 * The letter spacing in points.
	 *
	 * @param mixed $value Value (a number: points; or a length with a unit).
	 * @param float $size  Font size.
	 * @return float
	 */
	public static function letter_spacing( $value, $size ) {
		if ( is_int( $value ) || is_float( $value ) ) {
			return (float) $value;
		}
		$p = self::parse( $value );
		if ( null === $p ) {
			return 0.0;
		}
		if ( in_array( $p[1], array( '%', '', 'lh' ), true ) ) {
			return $p[0] / 100 * $size;
		}

		if ( 'em' === $p[1] ) {
			return $p[0] * $size;
		}

		return $p[0] * ( Page::UNITS[ $p[1] ] ?? 1 );
	}
}
