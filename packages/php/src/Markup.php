<?php
/**
 * Block markup read and written without WordPress: `<!-- wp:name {"attr":1} /-->` for a block with no inner blocks and
 * `<!-- wp:name {…} -->…<!-- /wp:name -->` for one with. The format is the one the block editor saves, so WordPress reads it too.
 *
 * Only blocks are read: any other text between them (the HTML a static block would have) is ignored, since every block of a drawing is
 * dynamic and keeps its data in its attributes.
 *
 * @package Camaleaun\Draw
 */

namespace Camaleaun\Draw;

/**
 * Parses and serializes block trees: each block is `array( 'name' => string, 'attrs' => array, 'children' => array )`.
 */
final class Markup {

	/**
	 * Most bytes of markup that are read, and the deepest nesting.
	 */
	const MAX_BYTES = 4194304;
	const MAX_DEPTH = 12;

	/**
	 * Reads a block tree from markup.
	 *
	 * @param string $markup Markup.
	 * @return array<int, array<string, mixed>>|null The blocks at the top, or null when the markup is too big, too deep, not balanced or an
	 *                                              attribute is not valid JSON.
	 */
	public static function parse( $markup ) {
		if ( ! is_string( $markup ) || strlen( $markup ) > self::MAX_BYTES ) {
			return null;
		}
		$found = preg_match_all(
			'/<!--\s+(\/)?wp:([a-z][a-z0-9_-]*\/)?([a-z][a-z0-9_-]*)\s+(?:(\{(?:(?!\}\s+\/?-->).)*+\})\s+)?(\/)?-->/s',
			$markup,
			$tokens,
			PREG_SET_ORDER
		);
		$root  = array( 'children' => array() );
		$stack = array( &$root );
		for ( $i = 0; $i < $found; $i++ ) {
			$token   = $tokens[ $i ];
			$closing = '' !== $token[1];
			$leaf    = isset( $token[5] ) && '' !== $token[5];
			$name    = ( '' === $token[2] ? 'core/' : $token[2] ) . $token[3];
			if ( $closing ) {
				$top = array_pop( $stack );
				if ( count( $stack ) < 1 || $top['name'] !== $name ) {
					return null;
				}
				continue;
			}
			$attrs = array();
			if ( isset( $token[4] ) && '' !== $token[4] ) {
				$attrs = json_decode( $token[4], true, 32 );
				if ( ! is_array( $attrs ) ) {
					return null;
				}
			}
			$parent = &$stack[ count( $stack ) - 1 ];
			$parent['children'][] = array(
				'name'     => $name,
				'attrs'    => $attrs,
				'children' => array(),
			);
			if ( ! $leaf ) {
				if ( count( $stack ) > self::MAX_DEPTH ) {
					return null;
				}
				$index   = count( $parent['children'] ) - 1;
				$stack[] = &$parent['children'][ $index ];
			}
			unset( $parent );
		}

		return 1 === count( $stack ) ? $root['children'] : null;
	}

	/**
	 * Writes a block tree as markup, one block to a line, nested ones indented.
	 *
	 * @param array<int, array<string, mixed>> $blocks Blocks.
	 * @param int                              $level  Indent.
	 * @return string
	 */
	public static function serialize( array $blocks, $level = 0 ) {
		$out = '';
		foreach ( $blocks as $block ) {
			$pad   = str_repeat( "\t", $level );
			$attrs = self::attributes( $block['attrs'] ?? array() );
			$name  = self::short( (string) $block['name'] );
			if ( empty( $block['children'] ) ) {
				$out .= $pad . '<!-- wp:' . $name . $attrs . ' /-->' . "\n";
				continue;
			}
			$out .= $pad . '<!-- wp:' . $name . $attrs . ' -->' . "\n" . self::serialize( $block['children'], $level + 1 ) . $pad . '<!-- /wp:' . $name . ' -->' . "\n";
		}

		return $out;
	}

	/**
	 * The attributes of a block as the JSON in its comment: with the characters that could end the comment or be read as markup escaped
	 * the way the block editor escapes them.
	 *
	 * @param array<string, mixed> $attrs Attributes.
	 * @return string ' {…}', or '' when there are none.
	 */
	private static function attributes( array $attrs ) {
		if ( ! $attrs ) {
			return '';
		}
		$json = json_encode( $attrs, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR );
		$json = str_replace( array( '--', '<', '>', '&', '\\"' ), array( '\\u002d\\u002d', '\\u003c', '\\u003e', '\\u0026', '\\u0022' ), (string) $json );

		return ' ' . $json;
	}

	/**
	 * A block name as the comment writes it: no `core/` for a core block.
	 *
	 * @param string $name Name.
	 * @return string
	 */
	private static function short( $name ) {
		return 0 === strpos( $name, 'core/' ) ? substr( $name, 5 ) : $name;
	}
}
