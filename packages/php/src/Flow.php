<?php
/**
 * How a group places what is in it: the same as `flowBoxes()` of the TypeScript package.
 *
 * @package Camaleaun\Draw
 */

namespace Camaleaun\Draw;

/**
 * Row, stack and grid: the boxes of what is in a group.
 */
final class Flow {

	/**
	 * The boxes (x, y, w, h from the group's corner) of what is in a row, a stack or a grid.
	 *
	 * Row and stack: one after the other along the main axis (the width of a row, the height of a stack) inside the padding, with `gap` between
	 * them; `justify` spreads them along it (start, center, end, between); an element with `grow` shares the room that is left with the others that
	 * grow; `align` puts them on the cross axis (start, center, end, stretch).
	 * Grid: `columns` columns of the same width, each element in the next cell, as wide as its cell and as high as itself (as the highest of its row with
	 * `align` stretch). The group keeps its size.
	 *
	 * @param array<string, mixed>             $group Group (w, h, arrange, gap, padding, justify, align, columns).
	 * @param array<int, array<string, mixed>> $items Items (w, h, grow).
	 * @return array<int, array{x: float, y: float, w: float, h: float}>
	 */
	public static function boxes( array $group, array $items ) {
		$items   = array_values( $items );
		$pad     = max( 0.0, (float) ( $group['padding'] ?? 0 ) );
		$gap     = max( 0.0, (float) ( $group['gap'] ?? 0 ) );
		$justify = (string) ( $group['justify'] ?? 'start' );
		$align   = (string) ( $group['align'] ?? 'start' );
		if ( 'grid' === ( $group['arrange'] ?? '' ) ) {
			$columns = max( 1, (int) floor( (float) ( $group['columns'] ?? 2 ) ) );
			$cell    = max( 0.0, ( (float) ( $group['w'] ?? 0 ) - $pad * 2 - $gap * ( $columns - 1 ) ) / $columns );
			$out     = array();
			$y       = $pad;
			foreach ( array_chunk( $items, $columns ) as $row ) {
				$high = 0.0;
				foreach ( $row as $it ) {
					$high = max( $high, (float) ( $it['h'] ?? 0 ) );
				}
				foreach ( $row as $i => $it ) {
					$out[] = array(
						'x' => $pad + $i * ( $cell + $gap ),
						'y' => $y,
						'w' => $cell,
						'h' => 'stretch' === $align ? $high : (float) ( $it['h'] ?? 0 ),
					);
				}
				$y += $high + $gap;
			}

			return $out;
		}
		$row    = 'stack' !== ( $group['arrange'] ?? 'row' );
		$main   = max( 0.0, (float) ( $row ? ( $group['w'] ?? 0 ) : ( $group['h'] ?? 0 ) ) - $pad * 2 );
		$cross  = max( 0.0, (float) ( $row ? ( $group['h'] ?? 0 ) : ( $group['w'] ?? 0 ) ) - $pad * 2 );
		$along  = static function ( $it ) use ( $row ) {
			return (float) ( $row ? ( $it['w'] ?? 0 ) : ( $it['h'] ?? 0 ) );
		};
		$across = static function ( $it ) use ( $row ) {
			return (float) ( $row ? ( $it['h'] ?? 0 ) : ( $it['w'] ?? 0 ) );
		};
		$sum    = $gap * max( 0, count( $items ) - 1 );
		$grows  = 0.0;
		foreach ( $items as $it ) {
			$sum   += $along( $it );
			$grows += max( 0.0, (float) ( $it['grow'] ?? 0 ) );
		}
		$left    = $main - $sum;
		$room    = $left > 0 && $grows > 0 ? $left : 0.0;
		$free    = $grows > 0 && $left > 0 ? 0.0 : max( 0.0, $left );
		$at      = 'center' === $justify ? $free / 2 : ( 'end' === $justify ? $free : 0.0 );
		$between = 'between' === $justify && count( $items ) > 1 ? $free / ( count( $items ) - 1 ) : 0.0;
		$out     = array();
		foreach ( $items as $it ) {
			$length = $along( $it ) + ( $room ? $room * max( 0.0, (float) ( $it['grow'] ?? 0 ) ) / $grows : 0.0 );
			$thick  = 'stretch' === $align ? $cross : $across( $it );
			$place  = 'center' === $align ? ( $cross - $thick ) / 2 : ( 'end' === $align ? $cross - $thick : 0.0 );
			$m      = $pad + $at;
			$c      = $pad + $place;
			$at    += $length + $gap + $between;
			$out[]  = $row
				? array( 'x' => $m, 'y' => $c, 'w' => $length, 'h' => $thick ) // phpcs:ignore Universal.Arrays.MultipleStatementAlignment
				: array( 'x' => $c, 'y' => $m, 'w' => $thick, 'h' => $length ); // phpcs:ignore Universal.Arrays.MultipleStatementAlignment
		}

		return $out;
	}
}
