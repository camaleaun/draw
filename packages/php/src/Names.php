<?php
/**
 * The names of an element in a file, as the blocks say them. The model keeps short names (w, h, vw, vh, lh, ls, a layout's h and v, a run's b and i);
 * a block says them in full (width, height, viewBox, lineHeight, letterSpacing, horizontal and vertical, bold and italic), and does not say an id
 * that only counts the elements (`rect-2`): it is made again by position when the file is read. x and y, and the coordinates of a line, stay.
 *
 * The same as `names.ts` of the JavaScript package.
 *
 * @package Camaleaun\Draw
 */

namespace Camaleaun\Draw;

/**
 * The names of an element in a file.
 */
class Names {

	/**
	 * An id that only counts: a type and a number (`rect-2`), or the editor's (`svg-mwzbwe9c-1`).
	 */
	const GENERATED_ID = '/^[a-z]+-(?:[a-z0-9]{6,9}-)?\d+$/';

	const TEXT = array(
		'lh' => 'lineHeight',
		'ls' => 'letterSpacing',
	);

	const AXES = array(
		'h' => 'horizontal',
		'v' => 'vertical',
	);

	const MARKS = array(
		'b' => 'bold',
		'i' => 'italic',
	);

	/**
	 * Renames the keys of a map (not of a list), in order.
	 *
	 * @param mixed                 $value Value.
	 * @param array<string, string> $map   Short => full.
	 * @param bool                  $full  True to write in full, false to read.
	 * @return mixed
	 */
	private static function rename( $value, array $map, $full ) {
		if ( ! is_array( $value ) || ( array() !== $value && array_values( $value ) === $value ) ) {
			return $value;
		}
		$map = $full ? $map : array_flip( $map );
		$out = array();
		foreach ( $value as $key => $item ) {
			$out[ $map[ $key ] ?? $key ] = $item;
		}

		return $out;
	}

	/**
	 * What an element's keys are in the full names (or back): the text's, a verification's hint and code, a layout's axes, a run's marks.
	 *
	 * @param array<string, mixed> $element Element (without its type, which is the block's).
	 * @param bool                 $full    True to write in full, false to read.
	 * @return array<string, mixed>
	 */
	private static function names( array $element, $full ) {
		$out = self::rename( $element, self::TEXT, $full );
		foreach ( array( 'hint', 'code' ) as $key ) {
			if ( isset( $out[ $key ] ) ) {
				$out[ $key ] = self::rename( $out[ $key ], self::TEXT, $full );
			}
		}
		if ( isset( $out['layout'] ) ) {
			$out['layout'] = self::rename( $out['layout'], self::AXES, $full );
		}
		if ( isset( $out['runs'] ) && is_array( $out['runs'] ) ) {
			foreach ( $out['runs'] as $i => $run ) {
				$out['runs'][ $i ] = self::rename( $run, self::MARKS, $full );
			}
		}

		return $out;
	}

	/**
	 * An element as the attributes of its block.
	 *
	 * @param array<string, mixed> $element Element (as the model says it).
	 * @return array<string, mixed>
	 */
	public static function to_attrs( array $element ) {
		$element = self::names( $element, true );
		$out     = array();
		foreach ( $element as $key => $value ) {
			if ( 'id' === $key && is_string( $value ) && 1 === preg_match( self::GENERATED_ID, $value ) ) {
				continue;
			}
			if ( 'w' === $key ) {
				$out['width'] = $value;
			} elseif ( 'h' === $key ) {
				$out['height'] = $value;
			} elseif ( 'vw' === $key && isset( $element['vh'] ) ) {
				$out['viewBox'] = '0 0 ' . $value . ' ' . $element['vh'];
			} elseif ( 'vh' !== $key || ! isset( $element['vw'] ) ) {
				$out[ $key ] = $value;
			}
		}

		return $out;
	}

	/**
	 * The attributes of a block as an element (as the model says it), with no id when the block said none.
	 *
	 * @param array<string, mixed> $attrs Attributes.
	 * @return array<string, mixed>
	 */
	public static function from_attrs( array $attrs ) {
		$out = array();
		foreach ( self::names( $attrs, false ) as $key => $value ) {
			if ( 'width' === $key ) {
				$out['w'] = $value;
			} elseif ( 'height' === $key ) {
				$out['h'] = $value;
			} elseif ( 'viewBox' === $key && is_string( $value ) && 1 === preg_match( '/^\s*[-\d.]+[\s,]+[-\d.]+[\s,]+([\d.]+)[\s,]+([\d.]+)\s*$/', $value, $m ) ) {
				$out['vw'] = $m[1] + 0;
				$out['vh'] = $m[2] + 0;
			} else {
				$out[ $key ] = $value;
			}
		}

		return $out;
	}

	/**
	 * Gives an id (`<type>-<position>`) to the elements that have none; the ids that are there are kept.
	 *
	 * @param array<int, array<string, mixed>> $elements Elements read from a file.
	 * @return array<int, array<string, mixed>>
	 */
	public static function with_ids( array $elements ) {
		$taken = array();
		foreach ( $elements as $element ) {
			if ( isset( $element['id'] ) ) {
				$taken[ (string) $element['id'] ] = true;
			}
		}
		foreach ( $elements as $i => $element ) {
			if ( isset( $element['id'] ) && '' !== $element['id'] ) {
				continue;
			}
			$n    = $i + 1;
			$type = (string) ( $element['type'] ?? 'element' );
			while ( isset( $taken[ $type . '-' . $n ] ) ) {
				++$n;
			}
			$elements[ $i ]['id']            = $type . '-' . $n;
			$taken[ $type . '-' . $n ]       = true;
		}

		return $elements;
	}
}
