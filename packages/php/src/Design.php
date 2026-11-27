<?php
/**
 * A design (the array the editor and the PDF read: page, colors, palette, elements, back…) as blocks, and back.
 *
 * The root is `events-certificates/certificate` and the declaration sheet is `events-certificates/certificate-back`; each element is a
 * block of its type (`draw/rect`, `draw/text`…), an SVG's paths are `draw/svg-path` inner blocks. Every key an element has is an attribute
 * of its block, so what goes in comes out the same.
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
		'qr'           => 'events-certificates/certificate-qr',
		'verification' => 'events-certificates/certificate-verification',
	);

	/**
	 * A design as block markup.
	 *
	 * @param array<string, mixed> $design The design.
	 * @return string
	 */
	public static function to_markup( array $design ) {
		$elements = is_array( $design['elements'] ?? null ) ? $design['elements'] : array();
		$root     = $design;
		unset( $root['elements'], $root['back'] );
		$children = self::blocks( $elements );
		if ( array_key_exists( 'back', $design ) && is_array( $design['back'] ) ) {
			$back       = $design['back'];
			$back_items = is_array( $back['elements'] ?? null ) ? $back['elements'] : array();
			unset( $back['elements'] );
			$children[] = array(
				'name'     => self::BACK,
				'attrs'    => $back,
				'children' => self::blocks( $back_items ),
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
	 * @param string $markup Markup.
	 * @return array<string, mixed>|null Null when it is not a certificate (no single root block, or not valid markup).
	 */
	public static function from_markup( $markup ) {
		$blocks = Markup::parse( $markup );
		if ( ! $blocks || 1 !== count( $blocks ) || self::ROOT !== $blocks[0]['name'] ) {
			return null;
		}
		$design   = $blocks[0]['attrs'];
		$elements = array();
		foreach ( $blocks[0]['children'] as $child ) {
			if ( self::BACK === $child['name'] ) {
				$design['back'] = $child['attrs'] + array( 'elements' => array() );
				$design['back']['elements'] = self::elements( $child['children'] );
				continue;
			}
			$elements[] = self::element( $child );
		}
		$design['elements'] = $elements;

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
	 * @param array<int, mixed> $elements Elements.
	 * @return array<int, array<string, mixed>>
	 */
	private static function blocks( array $elements ) {
		$blocks = array();
		foreach ( $elements as $element ) {
			if ( ! is_array( $element ) ) {
				continue;
			}
			$type  = (string) ( $element['type'] ?? '' );
			$attrs = $element;
			unset( $attrs['type'] );
			$children = array();
			if ( 'svg' === $type && is_array( $element['paths'] ?? null ) ) {
				unset( $attrs['paths'] );
				foreach ( $element['paths'] as $path ) {
					$children[] = array(
						'name'     => self::PATH,
						'attrs'    => is_array( $path ) ? $path : array(),
						'children' => array(),
					);
				}
			}
			if ( ! isset( self::BLOCKS[ $type ] ) ) {
				// A type this version does not know keeps its place as a generic block, so nothing is lost in the trip.
				$blocks[] = array(
					'name'     => 'draw/element',
					'attrs'    => array( 'type' => $type ) + $attrs,
					'children' => array(),
				);
				continue;
			}
			$blocks[] = array(
				'name'     => self::BLOCKS[ $type ],
				'attrs'    => $attrs,
				'children' => $children,
			);
		}

		return $blocks;
	}

	/**
	 * Blocks as elements.
	 *
	 * @param array<int, array<string, mixed>> $blocks Blocks.
	 * @return array<int, array<string, mixed>>
	 */
	private static function elements( array $blocks ) {
		return array_map( array( __CLASS__, 'element' ), $blocks );
	}

	/**
	 * One block as an element.
	 *
	 * @param array<string, mixed> $block Block.
	 * @return array<string, mixed>
	 */
	private static function element( array $block ) {
		if ( 'draw/element' === $block['name'] ) {
			return $block['attrs'];
		}
		$type    = (string) array_search( $block['name'], self::BLOCKS, true );
		$element = array( 'type' => $type ) + $block['attrs'];
		if ( 'svg' === $type && ! isset( $element['markup'] ) ) {
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
