<?php
/**
 * The paths of a drawing (SVG as data: a viewBox and filled paths, never markup): a path is read command by command, with exactly the
 * numbers each command takes, and nothing else.
 *
 * @package Camaleaun\Draw
 */

namespace Camaleaun\Draw;

final class Paths {

	const MAX_SEGMENTS  = 5000;
	const MAX_NUMBER    = 100000;
	const MAX_VIEWBOX   = 5000;
	const MAX_PATHS     = 50;
	const MAX_DATA      = 20000;
	const MAX_READ_DATA = 200000;

	/**
	 * Whether a text is the data of a path: commands M L H V C S Q T A Z and the numbers each takes (the flags of an arc a single 0 or 1),
	 * finite and in range, starting with M, up to MAX_SEGMENTS segments.
	 *
	 * @param mixed $d Path data.
	 * @return bool
	 */
	public static function is_data( $d ) {
		$d = (string) $d;
		$n = strlen( $d );
		if ( 0 === $n || $n > self::MAX_READ_DATA ) {
			return false;
		}
		$args     = array(
			'M' => 2,
			'L' => 2,
			'H' => 1,
			'V' => 1,
			'C' => 6,
			'S' => 4,
			'Q' => 4,
			'T' => 2,
			'A' => 7,
			'Z' => 0,
		);
		$i        = 0;
		$segments = 0;
		$command  = '';
		$skip     = static function () use ( $d, $n, &$i ) {
			while ( $i < $n && false !== strpos( " \t\r\n,", $d[ $i ] ) ) {
				++$i;
			}
		};
		$skip();
		while ( $i < $n ) {
			$char = $d[ $i ];
			if ( ctype_alpha( $char ) ) {
				$upper = strtoupper( $char );
				if ( ! isset( $args[ $upper ] ) || ( '' === $command && 'M' !== $upper ) ) {
					return false;
				}
				$command = $char;
				++$i;
				if ( 'Z' === $upper ) {
					++$segments;
					$skip();
					continue;
				}
			} elseif ( '' === $command || 'Z' === strtoupper( $command ) ) {
				return false; // Numbers with no command to take them.
			}
			$upper = strtoupper( $command );
			for ( $k = 0; $k < $args[ $upper ]; $k++ ) {
				$skip();
				if ( $i >= $n ) {
					return false;
				}
				if ( 'A' === $upper && ( 3 === $k || 4 === $k ) ) {
					if ( '0' !== $d[ $i ] && '1' !== $d[ $i ] ) {
						return false;
					}
					++$i;
					continue;
				}
				if ( ! preg_match( '/\G[+-]?(?:\d+\.?\d*|\.\d+)(?:[eE][+-]?\d+)?/', $d, $m, 0, $i ) || strlen( $m[0] ) > 30 ) {
					return false;
				}
				$number = (float) $m[0];
				if ( is_nan( $number ) || is_infinite( $number ) || abs( $number ) > self::MAX_NUMBER ) {
					return false;
				}
				$i += strlen( $m[0] );
			}
			if ( ++$segments > self::MAX_SEGMENTS ) {
				return false;
			}
			$skip();
		}

		return '' !== $command;
	}

	/**
	 * A drawing kept: its viewBox, its valid paths (each `d`, a `fill` that is a theme variable or a hex color, and `rule` evenodd) and the
	 * size it prefers to be shown at (`w` and `h`, both or none, not the viewBox itself).
	 *
	 * @param mixed $drawing  JSON, or the decoded drawing.
	 * @param int   $max_data Most characters of a path's data (0: MAX_DATA).
	 * @return array<string, mixed>|null Null when there is no valid viewBox or no valid path.
	 */
	public static function drawing( $drawing, $max_data = 0 ) {
		$drawing = is_string( $drawing ) ? json_decode( $drawing, true ) : $drawing;
		if ( ! is_array( $drawing ) || ! isset( $drawing['vw'], $drawing['vh'], $drawing['paths'] ) || ! is_array( $drawing['paths'] ) ) {
			return null;
		}
		$vw = (float) $drawing['vw'];
		$vh = (float) $drawing['vh'];
		if ( $vw <= 0 || $vh <= 0 || $vw > self::MAX_VIEWBOX || $vh > self::MAX_VIEWBOX ) {
			return null;
		}
		$limit = $max_data ? $max_data : self::MAX_DATA;
		$paths = array();
		foreach ( array_slice( $drawing['paths'], 0, self::MAX_PATHS ) as $path ) {
			$d = is_array( $path ) && is_string( $path['d'] ?? null ) ? trim( $path['d'] ) : '';
			if ( '' === $d || strlen( $d ) > $limit || ! self::is_data( $d ) ) {
				continue;
			}
			$item = array( 'd' => $d );
			$fill = (string) ( $path['fill'] ?? '' );
			if ( preg_match( '/^(\$[a-z0-9_-]+|#[0-9a-f]{3,8})$/i', $fill ) ) {
				$item['fill'] = $fill;
			}
			if ( 'evenodd' === ( $path['rule'] ?? '' ) ) {
				$item['rule'] = 'evenodd';
			}
			$paths[] = $item;
		}
		if ( ! $paths ) {
			return null;
		}
		$out = array(
			'vw'    => $vw,
			'vh'    => $vh,
			'paths' => $paths,
		);
		$w   = (float) ( $drawing['w'] ?? 0 );
		$h   = (float) ( $drawing['h'] ?? 0 );
		if ( $w > 0 && $h > 0 && $w <= self::MAX_VIEWBOX && $h <= self::MAX_VIEWBOX && ( $w !== $vw || $h !== $vh ) ) {
			$out['w'] = $w;
			$out['h'] = $h;
		}

		return $out;
	}
}
