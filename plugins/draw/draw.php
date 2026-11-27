<?php
/**
 * Plugin Name:       Draw
 * Plugin URI:        https://github.com/camaleaun/draw
 * Description:       A drawing as blocks: a frame with rectangles, ellipses, lines, paths, SVG groups, images and text inside, drawn on the page as one SVG.
 * Version:           0.1.0
 * Requires at least: 6.4
 * Requires PHP:      7.4
 * Author:            camaleaun
 * License:           GPL-2.0-or-later
 * Text Domain:       draw
 *
 * @package Draw
 */

namespace Camaleaun\DrawPlugin;

defined( 'ABSPATH' ) || exit;

// The package of this repository (packages/php) when the plugin runs from it, or the copy in lib/ when it is built.
spl_autoload_register(
	static function ( $class ) {
		$prefix = 'Camaleaun\\Draw\\';
		if ( 0 !== strpos( $class, $prefix ) ) {
			return;
		}
		foreach ( array( dirname( __DIR__, 2 ) . '/packages/php/src/', __DIR__ . '/lib/' ) as $dir ) {
			$file = $dir . str_replace( '\\', '/', substr( $class, strlen( $prefix ) ) ) . '.php';
			if ( is_readable( $file ) ) {
				require_once $file;
				return;
			}
		}
	}
);

require_once __DIR__ . '/includes/blocks.php';
