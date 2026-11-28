<?php
/**
 * The attributes of an element as a core block says them: `style.typography` (fontSize, lineHeight, letterSpacing, fontWeight, textAlign, textTransform), `style.color` (text, background),
 * `style.border`, `style.spacing`, `fontFamily`, `lock: { move, remove }`, `metadata`, `anchor`, and every length with its unit (`"13pt"`, `"0pt"`). This reads them as the names the file used
 * before (`Names` takes it from there), so both forms are read with the same code; a key of the new form wins over the old one.
 *
 * The same as `core.ts` of the TypeScript package.
 *
 * @package Camaleaun\Draw
 */

namespace Camaleaun\Draw;

/**
 * Core block attributes, read.
 */
final class Core {

	const LENGTH = '/^\s*(-?\d*\.?\d+)\s*(px|pt|mm|cm|in)?\s*$/i';

	/**
	 * A number the way JSON says it: an integer when it is one.
	 *
	 * @param float|int $n Number.
	 * @return float|int
	 */
	private static function num( $n ) {
		return is_float( $n ) && floor( $n ) === $n && abs( $n ) < 1e12 ? (int) $n : $n;
	}

	/**
	 * Whether a value is a map (a JSON object), not a list.
	 *
	 * @param mixed $value Value.
	 * @return bool
	 */
	private static function is_map( $value ) {
		return is_array( $value ) && ( array() === $value || array_keys( $value ) !== range( 0, count( $value ) - 1 ) );
	}

	/**
	 * The kebab case core gives a name (`CertSans` is `cert-sans`): the slug of a font family.
	 *
	 * @param string $name Name.
	 * @return string
	 */
	public static function slug( $name ) {
		$name = preg_replace( '/([a-z0-9])([A-Z])/', '$1-$2', (string) $name );

		return trim( (string) preg_replace( '/[^a-z0-9]+/', '-', strtolower( (string) $name ) ), '-' );
	}

	/**
	 * A length in points: a number is points; a text is a length with its unit (px, pt, mm, cm, in; none is points). What is not a plain length is kept.
	 *
	 * @param mixed $value Value.
	 * @return mixed
	 */
	public static function length_pt( $value ) {
		if ( ! is_string( $value ) || 1 !== preg_match( self::LENGTH, $value, $m ) ) {
			return $value;
		}

		return self::num( round( (float) $m[1] * ( Page::UNITS[ strtolower( $m[2] ?? 'pt' ) ] ?? 1 ), 8 ) );
	}

	/**
	 * A font size or a minimum: points as a number ("13pt", "13px", "13"), any other unit as the length it is ("4.4mm").
	 *
	 * @param mixed $value Value.
	 * @return mixed
	 */
	private static function size_from( $value ) {
		if ( is_string( $value ) && 1 === preg_match( self::LENGTH, $value, $m ) && in_array( strtolower( $m[2] ?? 'pt' ), array( 'pt', 'px' ), true ) ) {
			return self::num( (float) $m[1] );
		}

		return $value;
	}

	/**
	 * A number written as text ("1.4", "400") as the number.
	 *
	 * @param mixed $value Value.
	 * @return mixed
	 */
	private static function numeric( $value ) {
		return is_string( $value ) && 1 === preg_match( '/^\s*-?\d*\.?\d+\s*$/', $value ) ? self::num( (float) $value ) : $value;
	}

	/**
	 * The radius of a border: one length for all corners, or an object by corner, as the list of four the model keeps.
	 *
	 * @param mixed $value Value.
	 * @return mixed
	 */
	private static function radius_from( $value ) {
		if ( self::is_map( $value ) ) {
			$out = array();
			foreach ( array( 'topLeft', 'topRight', 'bottomRight', 'bottomLeft' ) as $corner ) {
				$out[] = self::length_pt( $value[ $corner ] ?? 0 );
			}

			return $out;
		}
		if ( is_string( $value ) || is_int( $value ) || is_float( $value ) ) {
			$one = self::length_pt( $value );

			return array( $one, $one, $one, $one );
		}

		return $value;
	}

	/**
	 * A core layout object as what the model keeps: arrange, justify, align, columns.
	 *
	 * @param array<string, mixed> $layout Layout.
	 * @return array<string, mixed>
	 */
	private static function arrange_from( array $layout ) {
		$horizontal = array(
			'left'          => 'start',
			'center'        => 'center',
			'right'         => 'end',
			'space-between' => 'between',
		);
		$vertical   = array(
			'top'           => 'start',
			'center'        => 'center',
			'bottom'        => 'end',
			'space-between' => 'between',
		);
		$out        = array();
		if ( 'grid' === ( $layout['type'] ?? '' ) ) {
			$out['arrange'] = 'grid';
			if ( isset( $layout['columnCount'] ) ) {
				$out['columns'] = $layout['columnCount'];
			}

			return $out;
		}
		$is_vertical    = 'vertical' === ( $layout['orientation'] ?? '' );
		$out['arrange'] = $is_vertical ? 'stack' : 'row';
		$across         = isset( $layout['justifyContent'] ) ? ( $horizontal[ (string) $layout['justifyContent'] ] ?? $layout['justifyContent'] ) : null;
		$along          = isset( $layout['verticalAlignment'] ) ? ( $vertical[ (string) $layout['verticalAlignment'] ] ?? $layout['verticalAlignment'] ) : null;
		// The main axis of a row is horizontal and of a stack vertical: `justify` is the main one, `align` the other.
		$justify = $is_vertical ? $along : $across;
		$align   = $is_vertical ? $across : $along;
		if ( null !== $justify ) {
			$out['justify'] = $justify;
		}
		if ( null !== $align ) {
			$out['align'] = $align;
		}

		return $out;
	}

	/**
	 * The typography and color of a part of the verification (`code`, `hint`), as the model keeps them.
	 *
	 * @param mixed $value Value.
	 * @return mixed
	 */
	private static function part_from( $value ) {
		if ( ! self::is_map( $value ) || ( ! self::is_map( $value['typography'] ?? null ) && ! self::is_map( $value['color'] ?? null ) ) ) {
			return $value;
		}
		$typography = self::is_map( $value['typography'] ?? null ) ? $value['typography'] : array();
		$out        = array();
		if ( isset( $typography['fontSize'] ) ) {
			$out['size'] = self::size_from( $typography['fontSize'] );
		}
		if ( isset( $typography['fontWeight'] ) ) {
			$out['weight'] = self::numeric( $typography['fontWeight'] );
		}
		if ( self::is_map( $value['color'] ?? null ) && isset( $value['color']['text'] ) ) {
			$out['color'] = $value['color']['text'];
		}
		if ( isset( $typography['lineHeight'] ) ) {
			$out['lineHeight'] = self::numeric( $typography['lineHeight'] );
		}

		return $out;
	}

	/**
	 * The family the file says for a font: the slug of a font the host has gives its name; any other value is the name as written.
	 *
	 * @param mixed                   $value Value.
	 * @param array<int, string>|null $fonts The families the host has.
	 * @return mixed
	 */
	private static function family_from( $value, $fonts ) {
		if ( ! is_string( $value ) || ! is_array( $fonts ) ) {
			return $value;
		}
		foreach ( $fonts as $name ) {
			if ( self::slug( $name ) === $value ) {
				return $name;
			}
		}

		return $value;
	}

	/**
	 * The attributes of a block read as the names the file used before; what is not in the new form passes through.
	 *
	 * @param array<string, mixed>    $attrs Attributes.
	 * @param string                  $type  The type of the element ('' when it is not known).
	 * @param array<int, string>|null $fonts The families the host has.
	 * @return array<string, mixed>
	 */
	public static function from_core( array $attrs, $type = '', $fonts = null ) {
		$out        = $attrs;
		$style      = self::is_map( $out['style'] ?? null ) ? $out['style'] : array();
		unset( $out['style'] );
		$typography = self::is_map( $style['typography'] ?? null ) ? $style['typography'] : array();
		$color      = self::is_map( $style['color'] ?? null ) ? $style['color'] : array();
		$border     = self::is_map( $style['border'] ?? null ) ? $style['border'] : array();
		$spacing    = self::is_map( $style['spacing'] ?? null ) ? $style['spacing'] : array();
		$dimensions = self::is_map( $style['dimensions'] ?? null ) ? $style['dimensions'] : array();
		$set        = static function ( $key, $value ) use ( &$out ) {
			if ( null !== $value ) {
				$out[ $key ] = $value;
			}
		};

		// An image's `id` is its attachment (a number); the id of the element is its `anchor`.
		if ( 'image' === $type && ( is_int( $out['id'] ?? null ) || is_float( $out['id'] ?? null ) ) ) {
			$out['attachment'] = $out['id'];
			unset( $out['id'] );
		}

		// What every element has.
		if ( self::is_map( $out['lock'] ?? null ) ) {
			$lock = $out['lock'];
			unset( $out['lock'] );
			if ( ! empty( $lock['move'] ) || ! empty( $lock['remove'] ) ) {
				$out['locked'] = true;
			}
		}
		if ( isset( $dimensions['aspectRatio'] ) ) {
			$set( 'lock', self::numeric( $dimensions['aspectRatio'] ) );
		}
		if ( self::is_map( $out['metadata'] ?? null ) ) {
			$metadata = $out['metadata'];
			unset( $out['metadata'] );
			$set( 'name', $metadata['name'] ?? null );
			if ( false === ( $metadata['blockVisibility'] ?? null ) ) {
				$out['hidden'] = true;
			}
		}
		if ( isset( $out['anchor'] ) ) {
			$out['id'] = $out['anchor'];
			unset( $out['anchor'] );
		}
		foreach ( array( 'x', 'y', 'width', 'height', 'x1', 'y1', 'x2', 'y2', 'qr' ) as $key ) {
			if ( isset( $out[ $key ] ) ) {
				$out[ $key ] = self::length_pt( $out[ $key ] );
			}
		}
		if ( self::is_map( $out['layout'] ?? null ) && isset( $out['layout']['reference'] ) ) {
			$layout = array();
			foreach ( $out['layout'] as $key => $value ) {
				$layout['reference' === $key ? 'ref' : $key] = $value;
			}
			$out['layout'] = $layout;
		}

		// A text.
		if ( isset( $out['textVariants'] ) && is_array( $out['textVariants'] ) ) {
			$variants = array();
			foreach ( $out['textVariants'] as $group ) {
				if ( self::is_map( $group ) ) {
					$variants = array_merge( $variants, $group );
				}
			}
			unset( $out['textVariants'] );
			$out['variants'] = $variants;
		}
		if ( isset( $out['fontFamily'] ) ) {
			$out['font'] = self::family_from( $out['fontFamily'], $fonts );
			unset( $out['fontFamily'] );
		}
		$set( 'weight', isset( $typography['fontWeight'] ) ? self::numeric( $typography['fontWeight'] ) : null );
		$set( 'size', isset( $typography['fontSize'] ) ? self::size_from( $typography['fontSize'] ) : null );
		$set( 'lineHeight', isset( $typography['lineHeight'] ) ? self::numeric( $typography['lineHeight'] ) : null );
		$set( 'letterSpacing', isset( $typography['letterSpacing'] ) ? self::size_from( $typography['letterSpacing'] ) : null );
		$set( 'align', $typography['textAlign'] ?? null );
		if ( 'uppercase' === ( $typography['textTransform'] ?? null ) ) {
			$out['upper'] = true;
		}
		$set( 'color', $color['text'] ?? null );
		if ( isset( $out['fitText'] ) ) {
			$out['fit'] = $out['fitText'];
			unset( $out['fitText'] );
		}
		if ( isset( $out['minFontSize'] ) ) {
			$out['min'] = self::size_from( $out['minFontSize'] );
			unset( $out['minFontSize'] );
		}

		// A shape, a line.
		$set( 'fill', $color['background'] ?? null );
		$set( 'stroke', $border['color'] ?? null );
		if ( isset( $border['width'] ) ) {
			$out['strokeWidth'] = self::length_pt( $border['width'] );
		}
		$set( 'dash', $border['style'] ?? null );
		if ( isset( $border['radius'] ) ) {
			$out['radius'] = self::radius_from( $border['radius'] );
		}

		// A group and the verification.
		if ( self::is_map( $out['arrange'] ?? null ) ) {
			$layout = $out['arrange'];
			unset( $out['arrange'] );
			$out = array_merge( $out, self::arrange_from( $layout ) );
		}
		if ( 'verification' === $type && isset( $typography['textAlign'] ) ) {
			$out['align'] = $typography['textAlign'];
		}
		if ( isset( $spacing['blockGap'] ) ) {
			$out['gap'] = self::length_pt( $spacing['blockGap'] );
		}
		if ( isset( $spacing['padding'] ) ) {
			$out['padding'] = self::length_pt( $spacing['padding'] );
		}
		foreach ( array( 'code', 'hint' ) as $key ) {
			if ( isset( $out[ $key ] ) ) {
				$out[ $key ] = self::part_from( $out[ $key ] );
			}
		}

		// An image.
		if ( 'image' === $type ) {
			if ( isset( $out['url'] ) ) {
				$out['src'] = $out['url'];
				unset( $out['url'] );
			}
			if ( isset( $out['scale'] ) ) {
				$out['fit'] = $out['scale'];
				unset( $out['scale'] );
			}
			if ( isset( $out['aspectRatio'] ) ) {
				$out['ratio'] = $out['aspectRatio'];
				unset( $out['aspectRatio'] );
			}
		}

		return $out;
	}
}
