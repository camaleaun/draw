<?php
/**
 * A drawing as one SVG: the elements of a frame (rectangles, ellipses, lines, paths, SVG groups, images and text) drawn at their place,
 * in points, with every value escaped. For a page that shows a drawing as it is; a renderer for paper (PDF) has its own needs.
 *
 * @package Camaleaun\Draw
 */

namespace Camaleaun\Draw;

final class Svg {

	/**
	 * A corner radius in points: a number is points, "25%" is that share of the shorter side (0 to 50%: 50% makes a pill or a circle); anything else is 0.
	 * The same as radiusPt() of the TypeScript package.
	 *
	 * @param mixed $value Radius.
	 * @param float $w     The box's width.
	 * @param float $h     The box's height.
	 * @return float
	 */
	public static function radius( $value, $w, $h ) {
		if ( is_string( $value ) ) {
			if ( 1 === preg_match( '/^\s*(-?\d*\.?\d+)\s*%\s*$/', $value, $m ) ) {
				return max( 0.0, min( (float) $m[1], 50.0 ) ) / 100 * min( $w, $h );
			}

			return 0.0;
		}

		return is_numeric( $value ) ? (float) $value : 0.0;
	}

	/**
	 * The drawing of a frame.
	 *
	 * @param array<int, array<string, mixed>> $elements Elements (each with `type`, as Design reads them).
	 * @param float                            $width    Width of the frame.
	 * @param float                            $height   Height of the frame.
	 * @param array<string, string>            $colors   Variable (without `$`) => color, for fills like `$primary`.
	 * @param callable|null                    $image    Gives the address of a picture: `fn( array $element ): string` (default: its `src`).
	 * @return string
	 */
	public static function render( array $elements, $width, $height, array $colors = array(), $image = null ) {
		$width  = max( 1.0, (float) $width );
		$height = max( 1.0, (float) $height );
		$out    = sprintf( '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 %s %s" width="%s" height="%s" role="img">', self::n( $width ), self::n( $height ), self::n( $width ), self::n( $height ) );
		foreach ( $elements as $element ) {
			if ( is_array( $element ) && empty( $element['hidden'] ) ) {
				$out .= self::element( $element, $colors, $image );
			}
		}

		return $out . '</svg>';
	}

	/**
	 * A number as text, with no more than 3 decimals.
	 *
	 * @param mixed $value Number.
	 * @return string
	 */
	private static function n( $value ) {
		return rtrim( rtrim( number_format( (float) $value, 3, '.', '' ), '0' ), '.' );
	}

	/**
	 * Escapes an attribute.
	 *
	 * @param string $value Text.
	 * @return string
	 */
	private static function e( $value ) {
		return htmlspecialchars( (string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8' );
	}

	/**
	 * A color for an attribute: a variable looked up in $colors, or a hex/rgb/hsl color as it is; `none` when it is anything else.
	 *
	 * @param mixed                 $value  Value.
	 * @param array<string, string> $colors Variables.
	 * @param string                $other  What to give for nothing.
	 * @return string
	 */
	private static function color( $value, array $colors, $other = 'none' ) {
		$value = is_string( $value ) ? trim( $value ) : '';
		if ( '' === $value ) {
			return $other;
		}
		if ( '$' === $value[0] ) {
			$found = $colors[ substr( $value, 1 ) ] ?? '';

			return '' === $found ? $other : self::color( $found, array(), $other );
		}
		if ( preg_match( '/^(#[0-9a-f]{3,8}|none|transparent|(rgb|hsl)a?\([0-9.,%\s\/deg-]+\))$/i', $value ) ) {
			return $value;
		}

		return $other;
	}

	/**
	 * One element, drawn: the drawing of a single shape block (a frame draws its shapes by drawing each).
	 *
	 * @param array<string, mixed>  $el     Element.
	 * @param array<string, string> $colors Variables.
	 * @param callable|null         $image  Address of a picture.
	 * @return string
	 */
	public static function element( array $el, array $colors = array(), $image = null ) {
		$type   = (string) ( $el['type'] ?? '' );
		$x      = (float) ( $el['x'] ?? 0 );
		$y      = (float) ( $el['y'] ?? 0 );
		$w      = (float) ( $el['w'] ?? 0 );
		$h      = (float) ( $el['h'] ?? 0 );
		$stroke = self::color( $el['stroke'] ?? '', $colors );
		$sw     = (float) ( $el['strokeWidth'] ?? 0 );
		$attrs  = ' fill="' . self::e( self::color( $el['fill'] ?? '', $colors ) ) . '"';
		if ( 'none' !== $stroke && $sw > 0 ) {
			$attrs .= ' stroke="' . self::e( $stroke ) . '" stroke-width="' . self::n( $sw ) . '"';
		}
		$group = '';
		if ( isset( $el['opacity'] ) && (float) $el['opacity'] < 1 ) {
			$group .= ' opacity="' . self::n( max( 0, (float) $el['opacity'] ) ) . '"';
		}
		if ( ! empty( $el['rotation'] ) ) {
			$group .= sprintf( ' transform="rotate(%s %s %s)"', self::n( $el['rotation'] ), self::n( $x + $w / 2 ), self::n( $y + $h / 2 ) );
		}
		switch ( $type ) {
			case 'rect':
			case 'group':
				$r      = is_array( $el['radius'] ?? null ) ? self::radius( $el['radius'][0] ?? 0, (float) $w, (float) $h ) : 0.0;
				$inside = sprintf( '<rect x="%s" y="%s" width="%s" height="%s"%s%s/>', self::n( $x ), self::n( $y ), self::n( $w ), self::n( $h ), $r > 0 ? ' rx="' . self::n( $r ) . '"' : '', $attrs );
				if ( 'group' === $type ) {
					// What is in a group is placed from its corner (a nested SVG moves the origin there) and shown even where it goes out of its box, unless the group clips (`clip`).
					$inner = '';
					if ( isset( $el['inner'] ) && is_string( $el['inner'] ) ) {
						$inner = $el['inner'];
					} else {
						foreach ( self::place_children( $el ) as $child ) {
							$inner .= empty( $child['hidden'] ) ? self::element( $child, $colors, $image ) : '';
						}
					}
					$inside .= sprintf( '<svg x="%s" y="%s" width="%s" height="%s" overflow="%s">%s</svg>', self::n( $x ), self::n( $y ), self::n( $w ), self::n( $h ), ! empty( $el['clip'] ) ? 'hidden' : 'visible', $inner );
				}
				break;
			case 'ellipse':
				$inside = sprintf( '<ellipse cx="%s" cy="%s" rx="%s" ry="%s"%s/>', self::n( $x + $w / 2 ), self::n( $y + $h / 2 ), self::n( $w / 2 ), self::n( $h / 2 ), $attrs );
				break;
			case 'line':
				$inside = sprintf( '<line x1="%s" y1="%s" x2="%s" y2="%s" stroke="%s" stroke-width="%s"/>', self::n( $el['x1'] ?? 0 ), self::n( $el['y1'] ?? 0 ), self::n( $el['x2'] ?? 0 ), self::n( $el['y2'] ?? 0 ), self::e( 'none' === $stroke ? 'currentColor' : $stroke ), self::n( $sw > 0 ? $sw : 1 ) );
				break;
			case 'path':
				$d = (string) ( $el['d'] ?? '' );
				if ( ! Paths::is_data( $d ) ) {
					return '';
				}
				$vw     = max( 1.0, (float) ( $el['vw'] ?? 100 ) );
				$vh     = max( 1.0, (float) ( $el['vh'] ?? 100 ) );
				$inside = sprintf( '<svg x="%s" y="%s" width="%s" height="%s" viewBox="0 0 %s %s" overflow="hidden"><path d="%s"%s/></svg>', self::n( $x ), self::n( $y ), self::n( $w ), self::n( $h ), self::n( $vw ), self::n( $vh ), self::e( $d ), $attrs );
				break;
			case 'svg':
				$vw    = max( 1.0, (float) ( $el['vw'] ?? 100 ) );
				$vh    = max( 1.0, (float) ( $el['vh'] ?? 100 ) );
				$paths = '';
				foreach ( (array) ( $el['paths'] ?? array() ) as $path ) {
					if ( is_array( $path ) && Paths::is_data( $path['d'] ?? '' ) ) {
						$paths .= sprintf( '<path d="%s" fill="%s"%s/>', self::e( $path['d'] ), self::e( self::color( $path['fill'] ?? '', $colors, self::color( $el['color'] ?? '', $colors, 'currentColor' ) ) ), 'evenodd' === ( $path['rule'] ?? '' ) ? ' fill-rule="evenodd"' : '' );
					}
				}
				$inside = sprintf( '<svg x="%s" y="%s" width="%s" height="%s" viewBox="0 0 %s %s" overflow="hidden">%s</svg>', self::n( $x ), self::n( $y ), self::n( $w ), self::n( $h ), self::n( $vw ), self::n( $vh ), $paths );
				break;
			case 'image':
				$src = is_callable( $image ) ? (string) call_user_func( $image, $el ) : (string) ( $el['src'] ?? '' );
				if ( '' === $src || ! preg_match( '#^https?://#i', $src ) ) {
					return '';
				}
				$inside = sprintf( '<image x="%s" y="%s" width="%s" height="%s" href="%s" preserveAspectRatio="%s"/>', self::n( $x ), self::n( $y ), self::n( $w ), self::n( $h ), self::e( $src ), 'cover' === ( $el['fit'] ?? '' ) ? 'xMidYMid slice' : 'xMidYMid meet' );
				break;
			case 'text':
				$text = '';
				foreach ( (array) ( $el['runs'] ?? array() ) as $run ) {
					$text .= is_array( $run ) && isset( $run['text'] ) ? (string) $run['text'] : '';
				}
				if ( '' === $text && isset( $el['text'] ) && is_string( $el['text'] ) ) {
					$text = $el['text'];
				}
				$size   = max( 1.0, (float) ( $el['size'] ?? 14 ) );
				$align  = (string) ( $el['align'] ?? 'left' );
				$anchor = 'center' === $align ? 'middle' : ( 'right' === $align ? 'end' : 'start' );
				$tx     = 'center' === $align ? $x + $w / 2 : ( 'right' === $align ? $x + $w : $x );
				$inside = sprintf( '<text x="%s" y="%s" font-size="%s" text-anchor="%s" fill="%s"%s>%s</text>', self::n( $tx ), self::n( $y + $size ), self::n( $size ), $anchor, self::e( self::color( $el['color'] ?? '', $colors, 'currentColor' ) ), isset( $el['weight'] ) ? ' font-weight="' . (int) $el['weight'] . '"' : '', self::e( $text ) );
				break;
			default:
				return '';
		}

		return '' === $group ? $inside : '<g' . $group . '>' . $inside . '</g>';
	}

	/**
	 * What is in a group, with its places: in a row or a stack the flow places them (what is hidden takes no room); in a free group each is where it says.
	 *
	 * @param array<string, mixed> $group Group.
	 * @return array<int, array<string, mixed>>
	 */
	public static function place_children( array $group ) {
		$children = array_values( array_filter( (array) ( $group['children'] ?? array() ), 'is_array' ) );
		if ( ! in_array( $group['arrange'] ?? '', array( 'row', 'stack', 'grid' ), true ) ) {
			return $children;
		}
		$shown = array_keys( array_filter( $children, static function ( $c ) {
			return empty( $c['hidden'] );
		} ) );
		$boxes = Flow::boxes( $group, array_map( static function ( $i ) use ( $children ) {
			return $children[ $i ];
		}, $shown ) );
		foreach ( $shown as $at => $i ) {
			$children[ $i ] = array_merge( $children[ $i ], $boxes[ $at ] );
		}

		return $children;
	}

	/**
	 * A nested SVG at the place of an element, around what is already drawn inside it (the paths of an SVG group).
	 *
	 * @param array<string, mixed> $el    Element (x, y, w, h, vw, vh).
	 * @param string               $inner SVG already drawn.
	 * @return string
	 */
	public static function nested( array $el, $inner ) {
		return sprintf(
			'<svg x="%s" y="%s" width="%s" height="%s" viewBox="0 0 %s %s" overflow="hidden">%s</svg>',
			self::n( $el['x'] ?? 0 ),
			self::n( $el['y'] ?? 0 ),
			self::n( $el['w'] ?? 0 ),
			self::n( $el['h'] ?? 0 ),
			self::n( max( 1.0, (float) ( $el['vw'] ?? 100 ) ) ),
			self::n( max( 1.0, (float) ( $el['vh'] ?? 100 ) ) ),
			$inner
		);
	}

	/**
	 * One path of an SVG group, drawn.
	 *
	 * @param array<string, mixed>  $path   Path (d, fill, rule).
	 * @param array<string, string> $colors Variables.
	 * @param string                $other  Fill when the path has none.
	 * @return string
	 */
	public static function path( array $path, array $colors = array(), $other = 'currentColor' ) {
		$d = (string) ( $path['d'] ?? '' );
		if ( ! Paths::is_data( $d ) ) {
			return '';
		}

		return sprintf( '<path d="%s" fill="%s"%s/>', self::e( $d ), self::e( self::color( $path['fill'] ?? '', $colors, $other ) ), 'evenodd' === ( $path['rule'] ?? '' ) ? ' fill-rule="evenodd"' : '' );
	}

	/**
	 * The frame: the SVG around what its shapes drew.
	 *
	 * @param float  $width  Width.
	 * @param float  $height Height.
	 * @param string $inner  The shapes, drawn.
	 * @return string
	 */
	public static function frame( $width, $height, $inner ) {
		$width  = max( 1.0, (float) $width );
		$height = max( 1.0, (float) $height );

		return sprintf( '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 %s %s" width="%s" height="%s" role="img">%s</svg>', self::n( $width ), self::n( $height ), self::n( $width ), self::n( $height ), $inner );
	}
}
