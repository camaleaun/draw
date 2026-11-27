<?php
/**
 * The blocks: draw/frame holds the drawing, and its inner blocks are the shapes. The frame draws all of them as one SVG; a shape on
 * its own has nothing to show.
 *
 * @package Draw
 */

namespace Camaleaun\DrawPlugin;

use Camaleaun\Draw\Clean;
use Camaleaun\Draw\Page;
use Camaleaun\Draw\Svg;

defined( 'ABSPATH' ) || exit;

/**
 * The shapes a frame may hold, with the type each is in the package's model.
 */
const SHAPES = array(
	'draw/rect'    => 'rect',
	'draw/ellipse' => 'ellipse',
	'draw/line'    => 'line',
	'draw/path'    => 'path',
	'draw/image'   => 'image',
	'draw/text'    => 'text',
	'draw/svg'     => 'svg',
);

/**
 * A block as an element of the package's model: its attributes, with the type, and an SVG's paths from its inner blocks.
 *
 * @param array<string, mixed> $block A parsed block.
 * @return array<string, mixed>|null Null when it is not a shape.
 */
function element( array $block ) {
	$type = SHAPES[ $block['blockName'] ?? '' ] ?? '';
	if ( '' === $type ) {
		return null;
	}
	$element = array( 'type' => $type ) + (array) ( $block['attrs'] ?? array() );
	if ( 'svg' === $type ) {
		$element['paths'] = array();
		foreach ( (array) ( $block['innerBlocks'] ?? array() ) as $inner ) {
			if ( 'draw/svg-path' === ( $inner['blockName'] ?? '' ) ) {
				$element['paths'][] = (array) ( $inner['attrs'] ?? array() );
			}
		}
	}

	return $element;
}

/**
 * The frame: the drawing as one SVG. The shapes are cleaned like a design is, so what the blocks say cannot become code.
 *
 * @param array<string, mixed> $attributes The block's attributes.
 * @param string               $content    Inner content (not used: the frame draws its shapes itself).
 * @param \WP_Block            $block      The block.
 * @return string
 */
function render_frame( $attributes, $content, $block ) {
	$elements = array();
	foreach ( (array) ( $block->parsed_block['innerBlocks'] ?? array() ) as $inner ) {
		$element = element( $inner );
		if ( $element ) {
			$elements[] = $element;
		}
	}
	$width  = Page::length( $attributes['width'] ?? '600px' );
	$height = Page::length( $attributes['height'] ?? '400px' );
	$clean  = ( new Clean(
		array(
			'upload_url'          => static function ( $url ) {
				return 0 === strpos( preg_replace( '#^https?:#', '', $url ), preg_replace( '#^https?:#', '', wp_get_upload_dir()['baseurl'] ) . '/' ) && ! preg_match( '#\.(php|phtml|phar|svg|html?|js)(\?|$)#i', $url );
			},
			'attachment_is_image' => static function ( $id ) {
				return (bool) wp_attachment_is_image( $id );
			},
		)
	) )->design(
		array(
			'page'     => array(
				'w' => max( 10, $width ),
				'h' => max( 10, $height ),
			),
			'elements' => array_map(
				static function ( $element, $i ) {
					return $element + array( 'id' => 'e' . $i );
				},
				$elements,
				array_keys( $elements )
			),
		)
	);
	$colors = array();
	foreach ( (array) ( $attributes['colors'] ?? array() ) as $name => $value ) {
		if ( is_string( $value ) ) {
			$colors[ (string) $name ] = $value;
		}
	}
	if ( ! $clean ) {
		return '';
	}

	return sprintf( '<div %s>%s</div>', get_block_wrapper_attributes( array( 'class' => 'draw-frame' ) ), Svg::render( $clean['elements'], max( 10, $width ), max( 10, $height ), $colors ) );
}

add_action(
	'init',
	static function () {
		register_block_type(
			'draw/frame',
			array(
				'api_version'     => 3,
				'title'           => __( 'Drawing', 'draw' ),
				'description'     => __( 'A frame to draw in: rectangles, ellipses, lines, paths, SVG, images and text, placed in points.', 'draw' ),
				'category'        => 'design',
				'icon'            => 'art',
				'attributes'      => array(
					'width'  => array(
						'type'    => 'string',
						'default' => '600px',
					),
					'height' => array(
						'type'    => 'string',
						'default' => '400px',
					),
					'colors' => array(
						'type'    => 'object',
						'default' => array(),
					),
				),
				'supports'        => array( 'html' => false ),
				'editor_script'   => 'draw-editor',
				'render_callback' => __NAMESPACE__ . '\render_frame',
			)
		);
		foreach ( array_keys( SHAPES ) as $name ) {
			register_block_type(
				$name,
				array(
					'api_version'     => 3,
					'title'           => ucfirst( substr( $name, 5 ) ),
					'category'        => 'design',
					'parent'          => 'draw/frame' === $name ? array() : array( 'draw/frame' ),
					'attributes'      => array(
						'x'    => array( 'type' => 'number' ),
						'y'    => array( 'type' => 'number' ),
						'w'    => array( 'type' => 'number' ),
						'h'    => array( 'type' => 'number' ),
						'fill' => array( 'type' => 'string' ),
					),
					'editor_script'   => 'draw-editor',
					'render_callback' => static function () {
						return ''; // The frame draws its shapes.
					},
				)
			);
		}
		register_block_type(
			'draw/svg-path',
			array(
				'api_version'     => 3,
				'title'           => __( 'Path', 'draw' ),
				'category'        => 'design',
				'parent'          => array( 'draw/svg' ),
				'attributes'      => array(
					'd'    => array( 'type' => 'string' ),
					'fill' => array( 'type' => 'string' ),
					'rule' => array( 'type' => 'string' ),
				),
				'editor_script'   => 'draw-editor',
				'render_callback' => static function () {
					return '';
				},
			)
		);
		wp_register_script( 'draw-editor', plugins_url( 'assets/editor.js', dirname( __DIR__ ) . '/draw.php' ), array( 'wp-blocks', 'wp-element', 'wp-block-editor', 'wp-components', 'wp-i18n', 'wp-data' ), '0.1.0', true );
	}
);
