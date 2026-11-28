<?php
/**
 * A design (the array the editor and the PDF read: page, colors, palette, elements, back…) as blocks, and back.
 *
 * The root is `events-certificates/certificate` and the declaration sheet is `events-certificates/certificate-back`; each element is a
 * block of its type (`draw/rect`, `draw/text`…), an SVG's paths are `draw/svg-path` inner blocks. Every key an element has is an attribute
 * of its block, so what goes in comes out the same.
 *
 * A text is written as text (`text`: `Dear [name], *welcome*`; `Runs`), and read back as runs: the keys that are fields come as the `fields` option.
 *
 * That is the default, the native way of the blocks. A second argument changes it (the same for both directions):
 *   - `blocks`:   the block of a type, over the defaults (`array( 'svg' => 'events-certificates/certificate-svg' )`);
 *   - `fallback`: the block that keeps the elements of a type this version does not know (`draw/element` by default);
 *   - `paths`:    `inner` (default: one `draw/svg-path` inner block for each path) or `attribute` (the SVG is one block with the
 *   - `names`:    the default names a host adds (by type or `type:slot`), not written in a file (`array( 'signature' => 'Signature' )`);
 *                 `paths` attribute, `{"paths":[{"d":"…"},{"d":"…"}]}`, and no inner blocks).
 *
 * @package Camaleaun\Draw
 */

namespace Camaleaun\Draw;

/**
 * Design <-> blocks.
 */
final class Design {

	const ROOT = 'events-certificates/certificate';
	const BACK = 'events-certificates/certificate-back';
	const PATH = 'draw/svg-path';

	/**
	 * The block that keeps an element of a type that is not known.
	 */
	const FALLBACK = 'draw/element';

	/**
	 * The block of each element type.
	 */
	const BLOCKS = array(
		'text'         => 'draw/text',
		'rect'         => 'draw/rect',
		'ellipse'      => 'draw/ellipse',
		'path'         => 'draw/path',
		'line'         => 'draw/line',
		'image'        => 'draw/image',
		'svg'          => 'draw/svg',
		'group'        => 'draw/group',
		'qr'           => 'events-certificates/certificate-qr',
		'verification' => 'events-certificates/certificate-verification',
	);

	/**
	 * The options with their defaults.
	 *
	 * @param array<string, mixed> $options Options (see the class).
	 * @return array{fields: array<int, string>|null, blocks: array<string, string>, fallback: string, paths: string, names: array<string, string>}
	 */
	private static function options( array $options ) {
		return array(
			'fields'   => is_array( $options['fields'] ?? null ) ? array_values( array_map( 'strval', $options['fields'] ) ) : null,
			'blocks'   => array_merge( self::BLOCKS, is_array( $options['blocks'] ?? null ) ? $options['blocks'] : array() ),
			'fallback' => is_string( $options['fallback'] ?? null ) ? $options['fallback'] : self::FALLBACK,
			'paths'    => 'attribute' === ( $options['paths'] ?? '' ) ? 'attribute' : 'inner',
			'names'    => is_array( $options['names'] ?? null ) ? $options['names'] : array(),
		);
	}

	/**
	 * A design as block markup.
	 *
	 * @param array<string, mixed> $design  The design.
	 * @param array<string, mixed> $options Options (see the class).
	 * @return string
	 */
	public static function to_markup( array $design, array $options = array() ) {
		$options  = self::options( $options );
		$elements = is_array( $design['elements'] ?? null ) ? $design['elements'] : array();
		$root     = $design;
		unset( $root['elements'], $root['back'] );
		$children = self::blocks( $elements, $options );
		if ( array_key_exists( 'back', $design ) && is_array( $design['back'] ) ) {
			$back       = $design['back'];
			$back_items = is_array( $back['elements'] ?? null ) ? $back['elements'] : array();
			unset( $back['elements'] );
			$children[] = array(
				'name'     => self::BACK,
				'attrs'    => $back,
				'children' => self::blocks( $back_items, $options ),
			);
		}

		return Markup::serialize(
			array(
				array(
					'name'     => self::ROOT,
					'attrs'    => $root,
					'children' => $children,
				),
			)
		);
	}

	/**
	 * A design from block markup.
	 *
	 * @param string               $markup  Markup.
	 * @param array<string, mixed> $options Options (see the class).
	 * @return array<string, mixed>|null Null when it is not a certificate (no single root block, or not valid markup).
	 */
	public static function from_markup( $markup, array $options = array() ) {
		$options = self::options( $options );
		$blocks  = Markup::parse( $markup );
		if ( ! $blocks || 1 !== count( $blocks ) || self::ROOT !== $blocks[0]['name'] ) {
			return null;
		}
		$design   = $blocks[0]['attrs'];
		$elements = array();
		foreach ( $blocks[0]['children'] as $child ) {
			if ( self::BACK === $child['name'] ) {
				$design['back'] = $child['attrs'] + array( 'elements' => array() );
				$design['back']['elements'] = Names::with_ids( self::elements( $child['children'], $options ) );
				continue;
			}
			$elements[] = self::element( $child, $options );
		}
		$design['elements'] = Names::with_ids( $elements );

		return $design;
	}

	/**
	 * Whether a text is block markup of a certificate (and not the JSON an earlier version saved).
	 *
	 * @param string $text Text.
	 * @return bool
	 */
	public static function is_markup( $text ) {
		return is_string( $text ) && 1 === preg_match( '/^\s*<!--\s+wp:' . preg_quote( self::ROOT, '/' ) . '[\s>\/]/', $text );
	}

	/**
	 * Elements as blocks.
	 *
	 * @param array<int, mixed>    $elements Elements.
	 * @param array<string, mixed> $options  Options, with their defaults.
	 * @return array<int, array<string, mixed>>
	 */
	private static function blocks( array $elements, array $options ) {
		$blocks = array();
		foreach ( $elements as $element ) {
			if ( ! is_array( $element ) ) {
				continue;
			}
			$type  = (string) ( $element['type'] ?? '' );
			$attrs = Names::to_attrs( $element, $options['names'], $options['fields'] );
			unset( $attrs['type'] );
			$children = array();
			if ( 'group' === $type ) {
				// What is in a group is its inner blocks (blocks all the way down).
				unset( $attrs['children'] );
				$children = self::blocks( is_array( $element['children'] ?? null ) ? $element['children'] : array(), $options );
			}
			if ( 'svg' === $type && 'inner' === $options['paths'] && is_array( $element['paths'] ?? null ) ) {
				unset( $attrs['paths'] );
				foreach ( $element['paths'] as $path ) {
					$children[] = array(
						'name'     => self::PATH,
						'attrs'    => is_array( $path ) ? $path : array(),
						'children' => array(),
					);
				}
			}
			if ( ! isset( $options['blocks'][ $type ] ) ) {
				// A type this version does not know keeps its place as a generic block, so nothing is lost in the trip.
				$blocks[] = array(
					'name'     => $options['fallback'],
					'attrs'    => array( 'type' => $type ) + $attrs,
					'children' => array(),
				);
				continue;
			}
			$blocks[] = array(
				'name'     => $options['blocks'][ $type ],
				'attrs'    => $attrs,
				'children' => $children,
			);
		}

		return $blocks;
	}

	/**
	 * Blocks as elements.
	 *
	 * @param array<int, array<string, mixed>> $blocks  Blocks.
	 * @param array<string, mixed>             $options Options, with their defaults.
	 * @return array<int, array<string, mixed>>
	 */
	private static function elements( array $blocks, array $options ) {
		return array_map(
			static function ( $block ) use ( $options ) {
				return self::element( $block, $options );
			},
			$blocks
		);
	}

	/**
	 * One block as an element.
	 *
	 * @param array<string, mixed> $block   Block.
	 * @param array<string, mixed> $options Options, with their defaults.
	 * @return array<string, mixed>
	 */
	private static function element( array $block, array $options ) {
		if ( $options['fallback'] === $block['name'] ) {
			return Names::from_attrs( $block['attrs'], $options['fields'] );
		}
		$type    = (string) array_search( $block['name'], $options['blocks'], true );
		$element = array( 'type' => $type ) + Names::from_attrs( $block['attrs'], $options['fields'], 'text' === $type );
		if ( 'group' === $type ) {
			$element['children'] = self::elements( $block['children'], $options );
		}
		if ( 'svg' === $type && 'inner' === $options['paths'] && ! isset( $element['markup'] ) ) {
			$element['paths'] = array();
			foreach ( $block['children'] as $path ) {
				if ( self::PATH === $path['name'] ) {
					$element['paths'][] = $path['attrs'];
				}
			}
		}

		return $element;
	}
}
