<?php
/**
 * How a group places what is in it: the same as `flowBoxes()` of the TypeScript package.
 *
 * @package Camaleaun\Draw
 */

namespace Camaleaun\Draw;

/**
 * Row and stack: the boxes of what is in a group, one after the other.
 */
final class Flow {

	/**
	 * The boxes (x, y, w, h from the group's corner) of what is in a row or a stack, one after the other along the main axis (the width of a row, the
	 * height of a stack) inside the padding, with `gap` between them. `justify` spreads them along it (start, center, end, between); an element with
	 * `grow` shares the room that is left with the others that grow; `align` puts them on the cross axis (start, center, end, stretch); `wrap` sends what
	 * does not fit to a new line. The group keeps its size.
	 *
	 * @param array<string, mixed>             $group Group (w, h, arrange, gap, padding, justify, align, wrap).
	 * @param array<int, array<string, mixed>> $items Items (w, h, grow).
	 * @return array<int, array{x: float, y: float, w: float, h: float}>
	 */
	public static function boxes( array $group, array $items ) {
		$items   = array_values( $items );
		$row     = 'stack' !== ( $group['arrange'] ?? 'row' );
		$pad     = max( 0.0, (float) ( $group['padding'] ?? 0 ) );
		$gap     = max( 0.0, (float) ( $group['gap'] ?? 0 ) );
		$main    = max( 0.0, (float) ( $row ? ( $group['w'] ?? 0 ) : ( $group['h'] ?? 0 ) ) - $pad * 2 );
		$cross   = max( 0.0, (float) ( $row ? ( $group['h'] ?? 0 ) : ( $group['w'] ?? 0 ) ) - $pad * 2 );
		$wrap    = ! empty( $group['wrap'] );
		$justify = (string) ( $group['justify'] ?? 'start' );
		$align   = (string) ( $group['align'] ?? 'start' );
		$along   = static function ( $it ) use ( $row ) {
			return (float) ( $row ? ( $it['w'] ?? 0 ) : ( $it['h'] ?? 0 ) );
		};
		$across  = static function ( $it ) use ( $row ) {
			return (float) ( $row ? ( $it['h'] ?? 0 ) : ( $it['w'] ?? 0 ) );
		};
		$lines   = array();
		$line    = array();
		$used    = 0.0;
		foreach ( $items as $i => $it ) {
			$need = $along( $it ) + ( $line ? $gap : 0 );
			if ( $wrap && $line && $used + $need > $main + 0.001 ) {
				$lines[] = $line;
				$line    = array( $i );
				$used    = $along( $it );
			} else {
				$line[] = $i;
				$used  += $need;
			}
		}
		if ( $line ) {
			$lines[] = $line;
		}
		$out    = array();
		$offset = 0.0;
		foreach ( $lines as $indexes ) {
			$size = $cross;
			if ( $wrap ) {
				$size = 0.0;
				foreach ( $indexes as $i ) {
					$size = max( $size, $across( $items[ $i ] ) );
				}
			}
			$sum = $gap * ( count( $indexes ) - 1 );
			$grows = 0.0;
			foreach ( $indexes as $i ) {
				$sum   += $along( $items[ $i ] );
				$grows += max( 0.0, (float) ( $items[ $i ]['grow'] ?? 0 ) );
			}
			$left    = $main - $sum;
			$room    = $left > 0 && $grows > 0 ? $left : 0.0;
			$free    = $grows > 0 && $left > 0 ? 0.0 : max( 0.0, $left );
			$at      = 'center' === $justify ? $free / 2 : ( 'end' === $justify ? $free : 0.0 );
			$between = 'between' === $justify && count( $indexes ) > 1 ? $free / ( count( $indexes ) - 1 ) : 0.0;
			foreach ( $indexes as $i ) {
				$it     = $items[ $i ];
				$length = $along( $it ) + ( $room ? $room * max( 0.0, (float) ( $it['grow'] ?? 0 ) ) / $grows : 0.0 );
				$thick  = 'stretch' === $align ? $size : $across( $it );
				$place  = 'center' === $align ? ( $size - $thick ) / 2 : ( 'end' === $align ? $size - $thick : 0.0 );
				$m      = $pad + $at;
				$c      = $pad + $offset + $place;
				$out[ $i ] = $row
					? array( 'x' => $m, 'y' => $c, 'w' => $length, 'h' => $thick ) // phpcs:ignore Universal.Arrays.MultipleStatementAlignment
					: array( 'x' => $c, 'y' => $m, 'w' => $thick, 'h' => $length ); // phpcs:ignore Universal.Arrays.MultipleStatementAlignment
				$at += $length + $gap + $between;
			}
			$offset += $size + $gap;
		}
		ksort( $out );

		return $out;
	}
}
