<?php
/**
 * The blocks, all rendered on the server. `draw/frame` is the drawing: the SVG around its shapes, which it asks to draw themselves.
 * Each shape (`draw/rect`, `draw/ellipse`…) is a block that can only be inside a frame, and draws its own piece of SVG from its
 * attributes; `draw/svg` draws a nested SVG around its `draw/svg-path` blocks. What each block says is cleaned like a design is,
 * so no attribute can become code.
 *
 * @package Draw
 */

namespace Camaleaun\DrawPlugin;

use Camaleaun\Draw\Clean;
use Camaleaun\Draw\Names;
use Camaleaun\Draw\Page;
use Camaleaun\Draw\Paths;
use Camaleaun\Draw\Svg;

defined( 'ABSPATH' ) || exit;

/**
 * The shapes a frame holds, with the type each is in the package's model.
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
 * The cleaning of what WordPress decides: which addresses are files of the Media Library and which numbers are pictures.
 *
 * @return Clean
 */
function cleaner() {
	return new Clean(
		array(
			'upload_url'          => static function ( $url ) {
				$base = preg_replace( '#^https?:#', '', wp_get_upload_dir()['baseurl'] );

				return 0 === strpos( preg_replace( '#^https?:#', '', $url ), $base . '/' ) && ! preg_match( '#\.(php|phtml|phar|svg|html?|js)(\?|$)#i', $url );
			},
			'attachment_is_image' => static function ( $id ) {
				return (bool) wp_attachment_is_image( $id );
			},
		)
	);
}

/**
 * The colors a frame gives its shapes (`$primary` in a fill), from the context it provides.
 *
 * @param \WP_Block $block A shape.
 * @return array<string, string>
 */
function colors_of( $block ) {
	$colors = array();
	foreach ( (array) ( $block->context['draw/colors'] ?? array() ) as $name => $value ) {
		if ( is_string( $value ) ) {
			$colors[ (string) $name ] = $value;
		}
	}

	return $colors;
}

/**
 * Whether a shape is inside a frame: only a frame gives its shapes the context, so a shape on its own (a parent the editor enforces,
 * and the server does too) draws nothing.
 *
 * @param \WP_Block $block A shape.
 * @return bool
 */
function inside_frame( $block ) {
	return isset( $block->context['draw/colors'] );
}

/**
 * A shape block as a cleaned element of the package's model, or null when what it says is not a shape.
 *
 * @param string               $type       The type (rect, ellipse…).
 * @param array<string, mixed> $attributes The block's attributes.
 * @return array<string, mixed>|null
 */
function clean_shape( $type, array $attributes ) {
	return cleaner()->element( array( 'type' => $type, 'id' => 'shape' ) + Names::from_attrs( $attributes ) ); // phpcs:ignore Universal.Arrays.MultipleStatementAlignment
}

/**
 * Draws a shape: its own piece of SVG.
 *
 * @param string    $name       Block name.
 * @param array     $attributes Attributes.
 * @param string    $content    What its inner blocks drew.
 * @param \WP_Block $block      The block.
 * @return string
 */
function render_shape( $name, $attributes, $content, $block ) {
	if ( ! inside_frame( $block ) ) {
		return '';
	}
	$element = clean_shape( SHAPES[ $name ], (array) $attributes );
	if ( ! $element ) {
		return '';
	}
	if ( 'svg' === $element['type'] ) {
		return Svg::nested( $element, $content );
	}

	return Svg::element( $element, colors_of( $block ) );
}

/**
 * The frame: the SVG around what its shapes drew.
 *
 * @param array<string, mixed> $attributes The block's attributes.
 * @param string               $content    What the shapes drew.
 * @return string
 */
function render_frame( $attributes, $content ) {
	$width  = max( 10, Page::length( $attributes['width'] ?? '600px' ) );
	$height = max( 10, Page::length( $attributes['height'] ?? '400px' ) );

	return sprintf( '<div %s>%s</div>', get_block_wrapper_attributes( array( 'class' => 'draw-frame' ) ), Svg::frame( $width, $height, $content ) );
}

add_action(
	'init',
	static function () {
		register_block_type(
			'draw/frame',
			array(
				'api_version'      => 3,
				'title'            => __( 'Drawing', 'draw' ),
				'description'      => __( 'A frame to draw in: rectangles, ellipses, lines, paths, SVG, images and text, placed in points.', 'draw' ),
				'category'         => 'design',
				'icon'             => 'art',
				'attributes'       => array(
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
				'provides_context' => array( 'draw/colors' => 'colors' ),
				'supports'         => array( 'html' => false ),
				'editor_script'    => 'draw-editor',
				'render_callback'  => __NAMESPACE__ . '\render_frame',
			)
		);
		foreach ( array_keys( SHAPES ) as $name ) {
			register_block_type(
				$name,
				array(
					'api_version'     => 3,
					'title'           => ucfirst( substr( $name, 5 ) ),
					'category'        => 'design',
					'parent'          => array( 'draw/frame' ), // A shape is only drawn inside a frame.
					'uses_context'    => array( 'draw/colors' ),
					'attributes'      => array(
						'x'       => array( 'type' => 'number' ),
						'y'       => array( 'type' => 'number' ),
						'width'   => array( 'type' => 'number' ),
						'height'  => array( 'type' => 'number' ),
						'viewBox' => array( 'type' => 'string' ),
						'fill'    => array( 'type' => 'string' ),
					),
					'editor_script'   => 'draw-editor',
					'render_callback' => static function ( $attributes, $content, $block ) use ( $name ) {
						return render_shape( $name, $attributes, $content, $block );
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
				'uses_context'    => array( 'draw/colors' ),
				'attributes'      => array(
					'd'    => array( 'type' => 'string' ),
					'fill' => array( 'type' => 'string' ),
					'rule' => array( 'type' => 'string' ),
				),
				'editor_script'   => 'draw-editor',
				'render_callback' => static function ( $attributes, $content, $block ) {
					return inside_frame( $block ) && Paths::is_data( $attributes['d'] ?? '' ) ? Svg::path( (array) $attributes, colors_of( $block ) ) : '';
				},
			)
		);
		wp_register_script( 'draw-editor', plugins_url( 'assets/editor.js', dirname( __DIR__ ) . '/draw.php' ), array( 'wp-blocks', 'wp-element', 'wp-block-editor', 'wp-components', 'wp-i18n', 'wp-data' ), '0.1.0', true );
	}
);
