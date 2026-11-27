<?php
namespace Camaleaun\Draw\Tests;

use Camaleaun\Draw\Design;
use Camaleaun\Draw\Markup;
use PHPUnit\Framework\TestCase;

/**
 * A design goes into blocks and comes out the same.
 */
final class DesignTest extends TestCase {

	/**
	 * Keys in order, so two designs are compared by what they say.
	 *
	 * @param mixed $value Value.
	 * @return mixed
	 */
	private function canon( $value ) {
		if ( is_array( $value ) ) {
			ksort( $value );
			return array_map( array( $this, 'canon' ), $value );
		}
		return $value;
	}

	public function golden() {
		$out = array();
		foreach ( glob( __DIR__ . '/../../../tests/golden/*.json' ) as $file ) {
			$out[ basename( $file ) ] = array( $file );
		}
		return $out;
	}

	/**
	 * @dataProvider golden
	 */
	public function test_a_golden_design_comes_out_the_same( $file ) {
		$design = json_decode( file_get_contents( $file ), true );
		$markup = Design::to_markup( $design );
		$this->assertTrue( Design::is_markup( $markup ) );
		$this->assertSame( json_encode( $this->canon( $design ) ), json_encode( $this->canon( Design::from_markup( $markup ) ) ) );
	}

	public function test_an_svg_keeps_its_paths_as_inner_blocks() {
		$markup = Design::to_markup(
			array(
				'page'     => array( 'w' => 10, 'h' => 10 ),
				'elements' => array(
					array( 'type' => 'svg', 'id' => 's', 'vw' => 24, 'vh' => 24, 'paths' => array( array( 'd' => 'M0 0L1 1Z', 'fill' => '$text' ), array( 'd' => 'M2 2L3 3Z' ) ) ),
				),
			)
		);
		$this->assertSame( 2, substr_count( $markup, 'wp:draw/svg-path' ) );
		$this->assertCount( 2, Design::from_markup( $markup )['elements'][0]['paths'] );
	}

	/**
	 * The options of a certificate: its own blocks, and an SVG that is one block with its paths as an attribute.
	 *
	 * @return array<string, mixed>
	 */
	private function certificate_options() {
		return array(
			'blocks'   => array(
				'text' => 'events-certificates/certificate-text',
				'svg'  => 'events-certificates/certificate-svg',
			),
			'fallback' => 'events-certificates/certificate-element',
			'paths'    => 'attribute',
		);
	}

	public function test_the_options_make_an_svg_one_block_with_its_paths_as_an_attribute() {
		$design  = array(
			'page'     => array( 'width' => '10px' ),
			'elements' => array(
				array( 'type' => 'svg', 'id' => 's', 'vw' => 24, 'vh' => 24, 'paths' => array( array( 'd' => 'M0 0L1 1Z', 'fill' => '$text' ), array( 'd' => 'M2 2L3 3Z' ) ) ),
				array( 'type' => 'text', 'id' => 't', 'x' => 1 ),
				array( 'type' => 'rect', 'id' => 'r' ),
				array( 'type' => 'star', 'id' => 'x' ),
			),
		);
		$options = $this->certificate_options();
		$markup  = Design::to_markup( $design, $options );
		$this->assertStringContainsString( '<!-- wp:events-certificates/certificate-svg {', $markup );
		$this->assertStringContainsString( '"paths":[{"d":"M0 0L1 1Z","fill":"$text"},{"d":"M2 2L3 3Z"}]', $markup );
		$this->assertStringContainsString( '<!-- wp:events-certificates/certificate-text {', $markup );
		$this->assertStringContainsString( '<!-- wp:events-certificates/certificate-element {', $markup );
		// What is not named in the options keeps its default block.
		$this->assertStringContainsString( '<!-- wp:draw/rect {', $markup );
		$this->assertStringNotContainsString( 'wp:draw/svg', $markup );
		$this->assertSame( $this->canon( $design ), $this->canon( Design::from_markup( $markup, $options ) ) );
	}

	public function test_the_options_are_not_needed_for_the_default() {
		$design = array( 'page' => array(), 'elements' => array( array( 'type' => 'svg', 'id' => 's', 'paths' => array( array( 'd' => 'M0 0Z' ) ) ) ) );
		$this->assertSame( Design::to_markup( $design ), Design::to_markup( $design, array() ) );
		$this->assertStringContainsString( 'wp:draw/svg-path', Design::to_markup( $design ) );
	}

	public function test_a_type_that_is_not_known_keeps_its_place() {
		$design = array( 'page' => array( 'w' => 1, 'h' => 1 ), 'elements' => array( array( 'type' => 'future', 'id' => 'f', 'x' => 1 ) ) );
		$this->assertSame( $design, Design::from_markup( Design::to_markup( $design ) ) );
	}

	public function test_the_characters_that_could_end_a_comment_are_escaped() {
		$design = array( 'page' => array( 'w' => 1, 'h' => 1 ), 'elements' => array( array( 'type' => 'text', 'id' => 't', 'runs' => array( array( 'text' => '--> <script>alert(1)</script> & "q"' ) ) ) ) );
		$markup = Design::to_markup( $design );
		$this->assertStringNotContainsString( '<script', $markup );
		$this->assertSame( $design, Design::from_markup( $markup ) );
	}

	public function test_markup_that_is_not_balanced_or_too_deep_is_refused() {
		$this->assertNull( Markup::parse( '<!-- wp:a/b -->' ) );
		$this->assertNull( Markup::parse( '<!-- wp:a/b --><!-- /wp:a/c -->' ) );
		$this->assertNull( Markup::parse( '<!-- wp:a/b {"x":} /-->' ) );
		$deep = str_repeat( '<!-- wp:a/b -->', 40 ) . str_repeat( '<!-- /wp:a/b -->', 40 );
		$this->assertNull( Markup::parse( $deep ) );
		$this->assertNull( Design::from_markup( '<!-- wp:draw/rect /-->' ), 'Not a certificate.' );
		$this->assertNull( Design::from_markup( '{"elements":[]}' ), 'JSON of an earlier version.' );
	}
}
