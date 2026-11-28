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
	const GENERATED_ID = '/^(?:[a-z]+-(?:[a-z0-9]{6,9}-)?\d+-)*[a-z]+-(?:[a-z0-9]{6,9}-)?\d+$/';

	/**
	 * The name an element has when it is made, by type: it is not written, as the type already says it. A host adds its own (`names` option of `Design`),
	 * by type or by `type:slot` (`image:logo`); a group has one by its layout (`group:row` is Row).
	 */
	const DEFAULT_NAMES = array(
		'text'         => 'Text',
		'rect'         => 'Rectangle',
		'ellipse'      => 'Ellipse',
		'line'         => 'Line',
		'image'        => 'Image',
		'svg'          => 'SVG',
		'group'        => 'Group',
		'group:row'    => 'Row',
		'group:stack'  => 'Stack',
		'group:grid'   => 'Grid',
		'qr'           => 'Authenticity',
		'verification' => 'Verification',
	);

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
	private static function names( array $element, $full, $fields = null, $is_text = false ) {
		$out = self::rename( $element, self::TEXT, $full );
		$out = $full ? self::write_text( $out ) : self::read_text( $out, $fields, $is_text );
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
	 * The runs of a text element as the text that is written (`text`, where `runs` was; each language of `variants` the same).
	 *
	 * @param array<string, mixed> $element Element, with the model's names.
	 * @return array<string, mixed>
	 */
	private static function write_text( array $element ) {
		if ( isset( $element['variants'] ) && is_array( $element['variants'] ) ) {
			foreach ( $element['variants'] as $locale => $runs ) {
				if ( is_array( $runs ) ) {
					$element['variants'][ $locale ] = Runs::to_text( $runs );
				}
			}
		}
		if ( ! isset( $element['runs'] ) || ! is_array( $element['runs'] ) ) {
			return $element;
		}
		$out = array();
		foreach ( $element as $key => $value ) {
			if ( 'runs' === $key ) {
				$out['text'] = Runs::to_text( $value );
			} elseif ( 'text' !== $key ) {
				$out[ $key ] = $value;
			}
		}

		return $out;
	}

	/**
	 * The text that is written as the runs of a text element: `text` (a string) becomes `runs`, in its place, and so does each language of `variants`
	 * (a list is what files wrote before, with `bold` and `italic`).
	 *
	 * @param array<string, mixed>    $element Element, with the file's names.
	 * @param array<int, string>|null $fields  The keys that are fields.
	 * @param bool                    $is_text Whether it is a text element (any other keeps its `text`).
	 * @return array<string, mixed>
	 */
	private static function read_text( array $element, $fields, $is_text ) {
		if ( isset( $element['variants'] ) && is_array( $element['variants'] ) ) {
			foreach ( $element['variants'] as $locale => $runs ) {
				if ( is_string( $runs ) ) {
					$element['variants'][ $locale ] = Runs::parse( $runs, $fields );
				} elseif ( is_array( $runs ) ) {
					foreach ( $runs as $i => $run ) {
						$element['variants'][ $locale ][ $i ] = self::rename( $run, self::MARKS, false );
					}
				}
			}
		}
		if ( ! $is_text || ! isset( $element['text'] ) || ! is_string( $element['text'] ) || isset( $element['runs'] ) ) {
			return $element;
		}
		$out = array();
		foreach ( $element as $key => $value ) {
			$out['text' === $key ? 'runs' : $key] = 'text' === $key ? Runs::parse( $value, $fields ) : $value;
		}

		return $out;
	}

	/**
	 * An element as the attributes of its block.
	 *
	 * @param array<string, mixed>  $element Element (as the model says it).
	 * @param array<string, string> $names   The default names a host adds (by type or `type:slot`).
	 * @param array<int, string>|null $fields The keys that are fields (not used to write: a field is written as it is).
	 * @param array<int, string>|null $fonts The families the host has (a font of them is written as its slug).
	 * @return array<string, mixed>
	 */
	public static function to_attrs( array $element, array $names = array(), $fields = null, $fonts = null ) {
		$default = self::default_name( $element, $names );
		$element = self::names( $element, true, $fields );
		$out     = array();
		foreach ( $element as $key => $value ) {
			if ( 'name' === $key && '' !== $default && $default === $value ) {
				continue;
			}
			// An id that only counts is not written, and a group never says one: nothing points at a group (what is in it is placed by `ref: 'parent'`), and it is made again by position.
			if ( 'id' === $key && is_string( $value ) && ( 1 === preg_match( self::GENERATED_ID, $value ) || 'group' === ( $element['type'] ?? '' ) ) ) {
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

		// The attributes of a core block: `style.typography`, `fontFamily`, a length with its unit.
		return Core::to_core( $out, (string) ( $element['type'] ?? '' ), $fonts );
	}

	/**
	 * The name an element has when it is made: the host's for its type and slot, else the type's.
	 *
	 * @param array<string, mixed>  $element Element.
	 * @param array<string, string> $names   The host's default names.
	 * @return string
	 */
	public static function default_name( array $element, array $names = array() ) {
		$type  = (string) ( $element['type'] ?? '' );
		$slot  = (string) ( $element['slot'] ?? '' );
		$names = array_merge( self::DEFAULT_NAMES, $names );

		// By `type:slot` (`image:logo`), then, for a group, by its layout (`group:row`), then by type.
		return (string) ( $names[ $type . ':' . $slot ] ?? ( 'group' === $type ? ( $names[ 'group:' . (string) ( $element['arrange'] ?? '' ) ] ?? null ) : null ) ?? $names[ $type ] ?? '' );
	}

	/**
	 * The attributes of a block as an element (as the model says it), with no id when the block said none.
	 *
	 * @param array<string, mixed>    $attrs   Attributes.
	 * @param array<int, string>|null $fields  The keys that are fields (null: any valid key).
	 * @param bool                    $is_text Whether it is the block of a text (its `text` is written text, to read as runs).
	 * @param string                  $type    The type of the element ('' when it is not known).
	 * @param array<int, string>|null $fonts   The families the host has (a `fontFamily` slug is read as the name of one of them).
	 * @return array<string, mixed>
	 */
	public static function from_attrs( array $attrs, $fields = null, $is_text = false, $type = '', $fonts = null ) {
		$out = array();
		// The attributes of a core block (`style.typography`, `fontFamily`, lengths with units) are read as the names the file used before: both forms come through the same code.
		foreach ( self::names( Core::from_core( $attrs, '' !== $type ? $type : ( $is_text ? 'text' : '' ), $fonts ), false, $fields, $is_text ) as $key => $value ) {
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
	 * Gives an id (`<type>-<position>`) to the elements that have none; the ids that are there are kept. What is in a group (`children`) gets
	 * `<id of the group>-<type>-<position>`.
	 *
	 * @param array<int, array<string, mixed>> $elements Elements read from a file.
	 * @param string                           $prefix   What an id starts with (the group's id and a dash).
	 * @return array<int, array<string, mixed>>
	 */
	public static function with_ids( array $elements, $prefix = '' ) {
		$taken = array();
		foreach ( $elements as $element ) {
			if ( isset( $element['id'] ) ) {
				$taken[ (string) $element['id'] ] = true;
			}
		}
		foreach ( $elements as $i => $element ) {
			if ( ! isset( $element['id'] ) || '' === $element['id'] ) {
				$n    = $i + 1;
				$type = (string) ( $element['type'] ?? 'element' );
				while ( isset( $taken[ $prefix . $type . '-' . $n ] ) ) {
					++$n;
				}
				$elements[ $i ]['id']                    = $prefix . $type . '-' . $n;
				$taken[ $prefix . $type . '-' . $n ] = true;
			}
			// What is in a group has ids of its own, after the group's, so no id is on the page twice.
			if ( is_array( $elements[ $i ]['children'] ?? null ) ) {
				$elements[ $i ]['children'] = self::with_ids( $elements[ $i ]['children'], $elements[ $i ]['id'] . '-' );
			}
		}

		return $elements;
	}
}
