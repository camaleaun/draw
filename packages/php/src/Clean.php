<?php
/**
 * The strict cleaning of a design: what is saved or brought in is checked against a list of what a design may have, and the rest is
 * removed. A design is data that an editor and a renderer read, never markup; nothing in it may reach a page as code.
 *
 * Only the element types, the keys of each type and the values an editor makes are kept: numbers in ranges, colors that are a color or
 * a theme variable, texts with no markup, a font that is a family name, pictures that are files the host allows, and drawings that are
 * paths of commands and numbers.
 *
 * What depends on the host (WordPress) is given in `$env`: `upload_url( string ): bool` (whether an address is a picture the host
 * allows), `font_name( string ): string` (a font family name made safe) and `attachment_is_image( int ): bool`.
 *
 * @package Camaleaun\Draw
 */

namespace Camaleaun\Draw;

final class Clean {

	const MAX_ELEMENTS  = 300;
	const MAX_DEPTH     = 3;
	const MAX_BYTES     = 2097152;
	const MAX_COLORS    = 60;
	const MAX_RUNS      = 80;
	const SVG_PATH_DATA = 100000;

	/**
	 * What the host decides.
	 *
	 * @var array<string, mixed>
	 */
	private $env;

	/**
	 * How many elements may still be taken, all the groups counted (reset for each list of elements of a design).
	 *
	 * @var int
	 */
	private $budget = self::MAX_ELEMENTS;

	/**
	 * How many groups deep the cleaning is.
	 *
	 * @var int
	 */
	private $depth = 0;

	/**
	 * @param array<string, mixed> $env upload_url, font_name, attachment_is_image (callables, each optional: the default allows no picture,
	 *                                  keeps a font name of letters, digits, spaces and dashes, and knows no attachment) and types (an
	 *                                  array of the element types the host adds, each with the keys of its colors: `array( 'signature' =>
	 *                                  array( 'color' ) )`; by default there are none).
	 */
	public function __construct( array $env = array() ) {
		$this->env = $env + array(
			'upload_url'          => static function () {
				return false;
			},
			'font_name'           => static function ( $name ) {
				return trim( (string) preg_replace( '/[^\p{L}\p{N} _-]/u', '', $name ) );
			},
			'attachment_is_image' => static function () {
				return false;
			},
		);
	}

	/**
	 * A number in a range, or null when it is not a number. Whole numbers stay whole.
	 *
	 * @param mixed $value Value.
	 * @param float $min   Smallest.
	 * @param float $max   Largest.
	 * @return int|float|null
	 */
	private function clean_number( $value, $min, $max ) {
		if ( is_bool( $value ) || ! is_numeric( $value ) ) {
			return null;
		}
		$number = $value + 0;
		if ( is_float( $number ) && ( is_nan( $number ) || is_infinite( $number ) ) ) {
			return null;
		}
		$number = max( $min, min( $max, $number ) );

		return is_float( $number ) && floor( $number ) === $number && abs( $number ) < 1e12 ? (int) $number : $number;
	}

	/**
	 * A text with no markup and no control characters, up to a length.
	 *
	 * @param mixed $value  Value.
	 * @param int   $length Most characters.
	 * @return string
	 */
	private function clean_text( $value, $length ) {
		if ( ! is_string( $value ) ) {
			return '';
		}
		// No markup, but the spaces and line breaks stay: a run of a text is ", " or a new line, and that is part of the design.
		$value = (string) preg_replace( '@<(script|style)[^>]*?>.*?</\\1>@si', '', $value );
		$value = (string) preg_replace( '/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', strip_tags( $value ) );

		return mb_substr( $value, 0, $length );
	}

	/**
	 * A color: a hex color, rgb(a), hsl(a), none or transparent, or a theme variable ($text, $band…); '' when it is anything else.
	 *
	 * @param mixed $value        Value.
	 * @param bool  $with_variable Whether a variable of the theme is allowed.
	 * @return string
	 */
	private function clean_color( $value, $with_variable = true ) {
		$value = is_string( $value ) ? trim( $value ) : '';
		if ( '' === $value || strlen( $value ) > 80 ) {
			return '';
		}
		if ( $with_variable && preg_match( '/^\$[a-z0-9_-]{1,40}$/i', $value ) ) {
			return $value;
		}
		// The color of the text around: a shape that says it takes the color of the document's text (the host decides which).
		if ( 0 === strcasecmp( $value, 'currentColor' ) ) {
			return 'currentColor';
		}
		$number = '-?[0-9]*\.?[0-9]+%?';
		if ( preg_match( '/^(#[0-9a-f]{3,4}|#[0-9a-f]{6}|#[0-9a-f]{8}|none|transparent)$/i', $value )
			|| preg_match( '/^(rgb|hsl)a?\(\s*' . $number . '(deg)?\s*[,\s]\s*' . $number . '\s*[,\s]\s*' . $number . '(\s*[,\/]\s*' . $number . ')?\s*\)$/i', $value ) ) {
			return $value;
		}

		return '';
	}

	/**
	 * A name made of letters, digits, dashes and underscores (an id, a slot, a field, a variable).
	 *
	 * @param mixed $value  Value.
	 * @param int   $length Most characters.
	 * @return string
	 */
	private function clean_key( $value, $length = 64 ) {
		$value = is_string( $value ) ? $value : '';

		return preg_match( '/^[A-Za-z0-9_-]{1,' . (int) $length . '}$/', $value ) ? $value : '';
	}

	/**
	 * A length of a layout: numbers with units and the operators of calc(), as the editor writes them ("20pt", "calc(100% - 40pt)").
	 *
	 * @param mixed $value Value.
	 * @return string
	 */
	private function clean_length( $value ) {
		$value = is_string( $value ) ? trim( $value ) : '';

		return preg_match( '#^[0-9a-zA-Z%+*/().\s-]{0,80}$#', $value ) ? $value : '';
	}

	/**
	 * The runs of a text: plain text or a field, with bold and italic.
	 *
	 * @param mixed $runs Runs.
	 * @return array<int, array<string, mixed>>
	 */
	private function clean_runs( $runs ) {
		$out = array();
		foreach ( array_slice( is_array( $runs ) ? $runs : array(), 0, self::MAX_RUNS ) as $run ) {
			if ( ! is_array( $run ) ) {
				continue;
			}
			if ( isset( $run['field'] ) ) {
				$field = $this->clean_key( $run['field'], 40 );
				if ( '' === $field ) {
					continue;
				}
				$item = array( 'field' => $field );
				if ( isset( $run['format'] ) && '' !== $this->clean_key( $run['format'], 40 ) ) {
					$item['format'] = $run['format'];
				}
			} elseif ( isset( $run['text'] ) && is_string( $run['text'] ) ) {
				$item = array( 'text' => $this->clean_text( $run['text'], 2000 ) );
			} else {
				continue;
			}
			foreach ( array( 'b', 'i' ) as $mark ) {
				if ( ! empty( $run[ $mark ] ) ) {
					$item[ $mark ] = true;
				}
			}
			$out[] = $item;
		}

		return $out;
	}

	/**
	 * The layout of an element (anchors in each axis).
	 *
	 * @param mixed $layout Layout.
	 * @return array<string, mixed>
	 */
	private function clean_layout( $layout ) {
		$out = array();
		foreach ( array( 'h', 'v' ) as $axis ) {
			$given = is_array( $layout ) && is_array( $layout[ $axis ] ?? null ) ? $layout[ $axis ] : array();
			$mode  = in_array( $given['mode'] ?? '', array( 'start', 'center', 'end', 'stretch' ), true ) ? $given['mode'] : '';
			if ( '' === $mode ) {
				return array();
			}
			$out[ $axis ] = array( 'mode' => $mode );
			foreach ( array( 'start', 'end', 'size' ) as $key ) {
				if ( isset( $given[ $key ] ) && '' !== $this->clean_length( $given[ $key ] ) ) {
					$out[ $axis ][ $key ] = $this->clean_length( $given[ $key ] );
				}
			}
		}
		if ( in_array( $layout['ref'] ?? '', array( 'page', 'safe', 'parent' ), true ) ) {
			$out['ref'] = $layout['ref'];
		}

		return $out;
	}

	/**
	 * The paths of a drawing, through the strict cleaning of the paths (Events\clean_vector()).
	 *
	 * @param array<string, mixed> $element Element (an svg).
	 * @return array{vw: int|float, vh: int|float, paths: array<int, array<string, string>>}
	 */
	private function clean_drawing( array $element ) {
		$vw      = $this->clean_number( $element['vw'] ?? 100, 1, Paths::MAX_VIEWBOX );
		$vh      = $this->clean_number( $element['vh'] ?? 100, 1, Paths::MAX_VIEWBOX );
		$vw      = null === $vw ? 100 : $vw;
		$vh      = null === $vh ? 100 : $vh;
		$drawing = Paths::drawing(
			array(
				'vw'    => $vw,
				'vh'    => $vh,
				'paths' => is_array( $element['paths'] ?? null ) ? $element['paths'] : array(),
			),
			self::SVG_PATH_DATA
		);

		return array(
			'vw'    => $vw,
			'vh'    => $vh,
			'paths' => $drawing ? $drawing['paths'] : array(),
		);
	}

	/**
	 * One element, with only what its type may have; null when it is not an element a design may have.
	 *
	 * @param mixed                $element Element.
	 * @param array<string, mixed> $notes   What was removed, counted by kind (filled).
	 * @return array<string, mixed>|null
	 */
	public function element( $element, array &$notes = array() ) {
		$type = is_array( $element ) ? ( $element['type'] ?? '' ) : '';
		if ( ! in_array( $type, array( 'text', 'rect', 'ellipse', 'path', 'line', 'image', 'qr', 'svg', 'verification', 'group' ), true ) && ! isset( $this->env['types'][ $type ] ) ) {
			return null;
		}
		$id = $this->clean_key( $element['id'] ?? '', 64 );
		if ( '' === $id ) {
			return null;
		}
		$out = array(
			'id'   => $id,
			'type' => $type,
		);
		if ( isset( $element['name'] ) && '' !== $this->clean_text( $element['name'], 120 ) ) {
			$out['name'] = $this->clean_text( $element['name'], 120 );
		}
		if ( '' !== $this->clean_key( $element['slot'] ?? '', 32 ) ) {
			$out['slot'] = $element['slot'];
		}
		$numbers = array(
			'x'        => array( -20000, 20000 ),
			'y'        => array( -20000, 20000 ),
			'w'        => array( 0, 20000 ),
			'h'        => array( 0, 20000 ),
			'opacity'  => array( 0, 1 ),
			'rotation' => array( -360, 360 ),
			'lock'     => array( 0, 1000 ),
			'grow'     => array( 0, 100 ),
		);
		if ( 'line' === $type ) {
			foreach ( array( 'x1', 'y1', 'x2', 'y2' ) as $key ) {
				$numbers[ $key ] = array( -20000, 20000 );
			}
		}
		foreach ( $numbers as $key => $range ) {
			$number = $this->clean_number( $element[ $key ] ?? null, $range[0], $range[1] );
			if ( null !== $number ) {
				$out[ $key ] = $number;
			}
		}
		foreach ( array( 'locked', 'hidden' ) as $flag ) {
			if ( ! empty( $element[ $flag ] ) ) {
				$out[ $flag ] = true;
			}
		}
		if ( is_array( $element['layout'] ?? null ) && $this->clean_layout( $element['layout'] ) ) {
			$out['layout'] = $this->clean_layout( $element['layout'] );
		}
		if ( is_array( $element['views'] ?? null ) ) {
			foreach ( array( 'desktop', 'tablet', 'mobile' ) as $screen ) {
				$view = $element['views'][ $screen ] ?? null;
				if ( ! is_array( $view ) ) {
					continue;
				}
				$item = array();
				if ( is_array( $view['layout'] ?? null ) && $this->clean_layout( $view['layout'] ) ) {
					$item['layout'] = $this->clean_layout( $view['layout'] );
				}
				if ( ! empty( $view['hidden'] ) ) {
					$item['hidden'] = true;
				}
				if ( is_array( $view['line'] ?? null ) ) {
					foreach ( array( 'x1', 'y1', 'x2', 'y2' ) as $key ) {
						$number = $this->clean_number( $view['line'][ $key ] ?? null, -20000, 20000 );
						if ( null !== $number ) {
							$item['line'][ $key ] = $number;
						}
					}
				}
				if ( $item ) {
					$out['views'][ $screen ] = $item;
				}
			}
		}

		$color  = function ( $key, $with_variable = true ) use ( $element, &$out ) {
			if ( isset( $element[ $key ] ) && '' !== $this->clean_color( $element[ $key ], $with_variable ) ) {
				$out[ $key ] = $this->clean_color( $element[ $key ], $with_variable );
			}
		};
		// A line height or a letter spacing: a number (what files wrote before units) in its range, or a length with a unit (Text): % of the font size up to `$percent`,
		// lh as a multiplier up to `$max`, any other unit up to `$other` points.
		$length = function ( $key, $min, $max, $percent, $other ) use ( $element, &$out ) {
			$given = $element[ $key ] ?? null;
			$parsed = Text::parse( $given );
			if ( null === $parsed ) {
				$value = $this->clean_number( $given, $min, $max );
				if ( null !== $value ) {
					$out[ $key ] = $value;
				}
				return;
			}
			list( $n, $unit ) = $parsed;
			// No unit: a line height is a multiplier (a number), a letter spacing a percentage.
			if ( '' === $unit && 'lh' === $key ) {
				$value = $this->clean_number( $n, $min, $max );
				if ( null !== $value ) {
					$out[ $key ] = $value;
				}
				return;
			}
			// em is the font size: for a line height a multiplier (as lh), for a letter spacing 100% of the size at most.
			$multiplier = 'lh' === $unit || ( 'em' === $unit && 'lh' === $key );
			if ( 'em' === $unit && 'lh' !== $key ) {
				$limit = $percent / 100;
			} else {
				$limit = '%' === $unit || '' === $unit ? $percent : ( $multiplier ? $max : $other );
			}
			$low = $multiplier ? $min : -$limit;
			if ( $n >= $low && $n <= $limit ) {
				$out[ $key ] = ( round( $n, 3 ) + 0 ) . ( '' === $unit ? '%' : $unit );
			}
		};
		$number = function ( $key, $min, $max ) use ( $element, &$out ) {
			$value = $this->clean_number( $element[ $key ] ?? null, $min, $max );
			if ( null !== $value ) {
				$out[ $key ] = $value;
			}
		};

		// A type the host added (`types`): the element has only the colors it names, and what every element has.
		foreach ( (array) ( $this->env['types'][ $type ] ?? array() ) as $key ) {
			$color( $key );
		}
		switch ( $type ) {
			case 'text':
				if ( isset( $element['text'] ) && is_string( $element['text'] ) ) {
					$out['text'] = $this->clean_text( $element['text'], 4000 );
				}
				if ( isset( $element['runs'] ) ) {
					$out['runs'] = $this->clean_runs( $element['runs'] );
				}
				foreach ( is_array( $element['variants'] ?? null ) ? array_slice( $element['variants'], 0, 40, true ) : array() as $locale => $runs ) {
					if ( preg_match( '/^[a-z]{2,3}(_[A-Za-z0-9]{2,8}){0,2}$/', (string) $locale ) ) {
						$out['variants'][ $locale ] = $this->clean_runs( $runs );
					}
				}
				$out['font'] = call_user_func( $this->env['font_name'], (string) ( $element['font'] ?? '' ) );
				$number( 'weight', 100, 900 );
				$number( 'size', 1, 1000 );
				$color( 'color' );
				if ( in_array( $element['align'] ?? '', array( 'left', 'center', 'right' ), true ) ) {
					$out['align'] = $element['align'];
				}
				$length( 'lh', 0, 10, 1000, 500 );
				$length( 'ls', -50, 100, 100, 100 );
				$number( 'min', 1, 1000 );
				foreach ( array( 'upper', 'fit' ) as $flag ) {
					if ( ! empty( $element[ $flag ] ) ) {
						$out[ $flag ] = true;
					}
				}
				break;
			case 'rect':
			case 'group':
				$color( 'fill' );
				$color( 'stroke' );
				$number( 'strokeWidth', 0, 500 );
				if ( is_array( $element['radius'] ?? null ) ) {
					$out['radius'] = array();
					foreach ( array_slice( array_values( $element['radius'] ), 0, 4 ) as $radius ) {
						// Points, or a percentage of the shorter side ("25%", 0 to 50: 50% makes a pill).
						if ( is_string( $radius ) && 1 === preg_match( '/^\s*(\d*\.?\d+)\s*%\s*$/', $radius, $m ) ) {
							$out['radius'][] = (string) ( round( min( (float) $m[1], 50.0 ), 2 ) + 0 ) . '%';
							continue;
						}
						$out['radius'][] = $this->clean_number( $radius, 0, 5000 ) ?? 0;
					}
				}
				if ( in_array( $element['corner'] ?? '', array( 'round', 'scoop', 'bevel', 'notch', 'squircle' ), true ) ) {
					$out['corner'] = $element['corner'];
				}
				if ( 'group' === $type ) {
					// A group does not clip what is in it (it shows what goes out of its box), unless it says it does (only the exception is written).
					if ( ! empty( $element['clip'] ) ) {
						$out['clip'] = true;
					}
					// Row, stack or grid (the free group has none: each element has its own place). The gap and the padding are points, the room is as the core's flex layout.
					if ( in_array( $element['arrange'] ?? '', array( 'row', 'stack', 'grid' ), true ) ) {
						$out['arrange'] = $element['arrange'];
						$number( 'gap', 0, 5000 );
						$number( 'padding', 0, 5000 );
						if ( 'grid' === $element['arrange'] ) {
							$number( 'columns', 1, 24 );
						}
						foreach ( array(
							'justify' => array( 'start', 'center', 'end', 'between' ),
							'align'   => array( 'start', 'center', 'end', 'stretch' ),
						) as $key => $allowed ) {
							if ( in_array( $element[ $key ] ?? '', $allowed, true ) ) {
								$out[ $key ] = $element[ $key ];
							}
						}
					}
					$out['children'] = $this->clean_children( $element['children'] ?? array(), $notes );
				}
				break;
			case 'ellipse':
				$color( 'fill' );
				$color( 'stroke' );
				$number( 'strokeWidth', 0, 500 );
				break;
			case 'path':
				$data      = $this->clean_drawing(
					array(
						'vw'    => $element['vw'] ?? 100,
						'vh'    => $element['vh'] ?? 100,
						'paths' => array( array( 'd' => (string) ( $element['d'] ?? '' ) ) ),
					)
				);
				$out['d']  = $data['paths'] ? $data['paths'][0]['d'] : '';
				$out['vw'] = $data['vw'];
				$out['vh'] = $data['vh'];
				$color( 'fill' );
				$color( 'stroke' );
				$number( 'strokeWidth', 0, 500 );
				break;
			case 'line':
				$color( 'stroke' );
				$number( 'strokeWidth', 0, 500 );
				if ( in_array( $element['dash'] ?? '', array( 'solid', 'dashed', 'dotted' ), true ) ) {
					$out['dash'] = $element['dash'];
				}
				break;
			case 'svg':
				$drawing = $this->clean_drawing( $element );
				if ( ! empty( $element['paths'] ) && count( (array) $element['paths'] ) > count( $drawing['paths'] ) ) {
					$notes['paths'] = ( $notes['paths'] ?? 0 ) + count( (array) $element['paths'] ) - count( $drawing['paths'] );
				}
				$out['vw']    = $drawing['vw'];
				$out['vh']    = $drawing['vh'];
				$out['paths'] = $drawing['paths'];
				if ( isset( $element['title'] ) ) {
					$out['title'] = $this->clean_text( $element['title'], 200 );
				}
				$color( 'color' );
				$color( 'fill' );
				break;
			case 'image':
				$src = isset( $element['src'] ) && is_string( $element['src'] ) ? trim( $element['src'] ) : '';
				if ( '' !== $src && ! call_user_func( $this->env['upload_url'], $src ) ) {
					$notes['pictures'] = ( $notes['pictures'] ?? 0 ) + 1;
					$src               = '';
				}
				$out['src'] = $src;
				if ( in_array( $element['fit'] ?? '', array( 'contain', 'cover' ), true ) ) {
					$out['fit'] = $element['fit'];
				}
				if ( isset( $element['alt'] ) ) {
					$out['alt'] = $this->clean_text( $element['alt'], 200 );
				}
				$attachment = $this->clean_number( $element['attachment'] ?? null, 0, 4294967295 );
				if ( '' !== $src && $attachment && call_user_func( $this->env['attachment_is_image'], (int) $attachment ) ) {
					$out['attachment'] = (int) $attachment;
				}
				if ( isset( $element['ratio'] ) && is_string( $element['ratio'] ) && preg_match( '/^[0-9a-z:.\/ -]{1,16}$/i', $element['ratio'] ) ) {
					$out['ratio'] = $element['ratio'];
				}
				break;
			case 'verification':
				foreach ( array(
					'arrange' => array( 'stack', 'row' ),
					'align'   => array( 'left', 'center', 'right' ),
				) as $key => $allowed ) {
					if ( in_array( $element[ $key ] ?? '', $allowed, true ) ) {
						$out[ $key ] = $element[ $key ];
					}
				}
				$number( 'qr', 1, 1000 );
				$number( 'gap', 0, 1000 );
				if ( isset( $element['font'] ) ) {
					$out['font'] = call_user_func( $this->env['font_name'], (string) $element['font'] );
				}
				foreach ( array( 'code', 'hint' ) as $part ) {
					$given = is_array( $element[ $part ] ?? null ) ? $element[ $part ] : array();
					$item  = array();
					foreach ( array(
						'size'   => array( 1, 1000 ),
						'weight' => array( 100, 900 ),
						'lh'     => array( 0, 10 ),
					) as $key => $range ) {
						$value = $this->clean_number( $given[ $key ] ?? null, $range[0], $range[1] );
						if ( null !== $value && ( 'lh' !== $key || 'hint' === $part ) ) {
							$item[ $key ] = $value;
						}
					}
					if ( '' !== $this->clean_color( $given['color'] ?? '' ) ) {
						$item['color'] = $this->clean_color( $given['color'] );
					}
					if ( $item ) {
						$out[ $part ] = $item;
					}
				}
				break;
		}

		return $out;
	}

	/**
	 * A list of elements.
	 *
	 * @param mixed                $elements Elements.
	 * @param array<string, mixed> $notes    What was removed (filled).
	 * @return array<int, array<string, mixed>>
	 */
	private function clean_elements( $elements, array &$notes ) {
		// At most MAX_ELEMENTS in all, what is in groups too.
		$this->budget = self::MAX_ELEMENTS;
		$this->depth  = 0;

		return $this->clean_list( $elements, $notes, false );
	}

	/**
	 * What is in a group: elements, but not the ones that only have a place on the certificate itself (the authenticity code, the verification block, the
	 * signature), and not deeper than MAX_DEPTH groups.
	 *
	 * @param mixed                $elements Elements.
	 * @param array<string, mixed> $notes    What was removed (filled).
	 * @return array<int, array<string, mixed>>
	 */
	private function clean_children( $elements, array &$notes ) {
		if ( $this->depth >= self::MAX_DEPTH ) {
			$notes['elements'] = ( $notes['elements'] ?? 0 ) + ( is_array( $elements ) ? count( $elements ) : 0 );

			return array();
		}
		++$this->depth;
		$out = $this->clean_list( $elements, $notes, true );
		--$this->depth;

		return $out;
	}

	/**
	 * A list of elements, taking from what is left of the budget.
	 *
	 * @param mixed                $elements Elements.
	 * @param array<string, mixed> $notes    What was removed (filled).
	 * @param bool                 $inner    Whether they are in a group.
	 * @return array<int, array<string, mixed>>
	 */
	private function clean_list( $elements, array &$notes, $inner ) {
		$out = array();
		foreach ( is_array( $elements ) ? array_values( $elements ) : array() as $element ) {
			if ( $this->budget <= 0 ) {
				$notes['elements'] = ( $notes['elements'] ?? 0 ) + 1;
				continue;
			}
			$kind = is_array( $element ) ? ( $element['type'] ?? '' ) : '';
			if ( $inner && ( in_array( $kind, array( 'qr', 'verification' ), true ) || 'signature' === ( is_array( $element ) ? ( $element['slot'] ?? '' ) : '' ) ) ) {
				$notes['elements'] = ( $notes['elements'] ?? 0 ) + 1;
				continue;
			}
			--$this->budget;
			$clean = $this->element( $element, $notes );
			if ( null === $clean ) {
				$notes['elements'] = ( $notes['elements'] ?? 0 ) + 1;
				continue;
			}
			$out[] = $clean;
		}

		return $out;
	}

	/**
	 * A design, strictly: only what a design may have. Null when it is not a design (no page, no elements, or too big).
	 *
	 * @param mixed                $design Design as the editor sent it or a file brought it.
	 * @param array<string, mixed> $notes  What was removed, counted by kind: elements, pictures, paths (filled).
	 * @return array<string, mixed>|null
	 */
	public function design( $design, array &$notes = array() ) {
		if ( ! is_array( $design ) || ! is_array( $design['page'] ?? null ) || ! is_array( $design['elements'] ?? null ) ) {
			return null;
		}
		if ( strlen( (string) json_encode( $design ) ) > self::MAX_BYTES ) {
			return null;
		}
		// The page may come in either form (points, or lengths with a unit); here it is cleaned as points (and written with units by Page::stored()).
		$page = Page::in_points( $design )['page'];
		$out  = array(
			'page'    => array(
				'w'     => $this->clean_number( $page['w'] ?? 841.89, 10, 5000 ) ?? 841.89,
				'h'     => $this->clean_number( $page['h'] ?? 595.28, 10, 5000 ) ?? 595.28,
				'bg'    => '',
				'safe'  => $this->clean_number( $page['safe'] ?? 0, 0, 500 ) ?? 0,
				'bleed' => $this->clean_number( $page['bleed'] ?? 0, 0, 500 ) ?? 0,
			),
		);
		// The unit of a margin (anything but millimeters) is kept: Page::stored() writes the length in it.
		foreach ( array( 'safe', 'bleed' ) as $key ) {
			if ( in_array( $page[ $key . 'Unit' ] ?? '', array( 'pt', 'px', 'cm', 'in' ), true ) ) {
				$out['page'][ $key . 'Unit' ] = $page[ $key . 'Unit' ];
			}
		}
		if ( is_array( $page['bg'] ?? null ) ) {
			$out['page']['bg'] = array();
			foreach ( array( 'light', 'dark' ) as $mode ) {
				if ( isset( $page['bg'][ $mode ] ) && is_string( $page['bg'][ $mode ] ) ) {
					$out['page']['bg'][ $mode ] = $this->clean_color( $page['bg'][ $mode ] ); // '' is "none in this mode".
				}
			}
		} else {
			$out['page']['bg'] = $this->clean_color( $page['bg'] ?? '' );
		}

		$out['page'] = Page::stored( $out['page'] );

		$out['colors'] = array();
		foreach ( array_slice( is_array( $design['colors'] ?? null ) ? $design['colors'] : array(), 0, self::MAX_COLORS, true ) as $key => $value ) {
			// A color may have no Light value ("none": Clear); one with a value that is not a color is dropped. A value may point at another color
			// ("$base": the Theme's), which is how a color picked from the Theme is kept.
			$light = is_array( $value ) ? (string) ( $value['light'] ?? '' ) : '';
			if ( '' === $this->clean_key( (string) $key, 40 ) || ! is_array( $value ) || ( '' !== trim( $light ) && '' === $this->clean_color( $light ) ) ) {
				continue;
			}
			$item = array(
				'name'  => $this->clean_text( $value['name'] ?? '', 80 ),
				'light' => $this->clean_color( $light ),
			);
			if ( '' !== $this->clean_color( $value['dark'] ?? '' ) ) {
				$item['dark'] = $this->clean_color( $value['dark'] );
			}
			$out['colors'][ (string) $key ] = $item;
		}
		if ( is_array( $design['palette'] ?? null ) ) {
			$out['palette'] = array();
			foreach ( array_slice( array_values( $design['palette'] ), 0, self::MAX_COLORS ) as $swatch ) {
				if ( ! is_array( $swatch ) || '' === $this->clean_color( $swatch['color'] ?? '', false ) ) {
					continue;
				}
				$item = array( 'color' => $this->clean_color( $swatch['color'], false ) );
				if ( '' !== $this->clean_key( $swatch['slug'] ?? '', 40 ) ) {
					$item = array( 'slug' => $swatch['slug'] ) + $item; // Older designs name a swatch only by its color.
				}
				if ( isset( $swatch['name'] ) && '' !== $this->clean_text( $swatch['name'], 80 ) ) {
					$item['name'] = $this->clean_text( $swatch['name'], 80 );
				}
				$out['palette'][] = $item;
			}
		}
		$out['elements'] = $this->clean_elements( $design['elements'], $notes );
		if ( is_array( $design['sample'] ?? null ) ) {
			$out['sample'] = array();
			foreach ( array_slice( $design['sample'], 0, 40, true ) as $key => $value ) {
				if ( '' !== $this->clean_key( (string) $key, 40 ) && is_string( $value ) ) {
					$out['sample'][ (string) $key ] = $this->clean_text( $value, 500 );
				}
			}
		}
		if ( in_array( $design['verifyFirstPage'] ?? '', array( 'always', 'without-declaration' ), true ) ) {
			$out['verifyFirstPage'] = $design['verifyFirstPage'];
		}
		if ( is_array( $design['back'] ?? null ) ) {
			$out['back'] = array( 'elements' => $this->clean_elements( $design['back']['elements'] ?? array(), $notes ) );
		}

		return $out;
	}
}
