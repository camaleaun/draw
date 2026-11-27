<?php
namespace Camaleaun\Draw\Tests;

use Camaleaun\Draw\Design;
use Camaleaun\Draw\Markup;
use Camaleaun\Draw\Names;
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
		// The default name of a type is not written in a file, so it is not read back.
		$unnamed = static function ( $sheet ) {
			foreach ( $sheet['elements'] ?? array() as $i => $element ) {
				if ( isset( $element['name'] ) && Names::default_name( $element ) === $element['name'] ) {
					unset( $sheet['elements'][ $i ]['name'] );
				}
			}

			return $sheet;
		};
		$design  = $unnamed( $design );
		if ( isset( $design['back'] ) ) {
			$design['back'] = $unnamed( $design['back'] );
		}
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

	public function test_an_element_is_written_with_the_names_of_a_block() {
		$design = array(
			'page'     => array( 'width' => '100mm', 'height' => '50mm' ),
			'elements' => array(
				array(
					'id' => 'rect-2', 'type' => 'rect', 'x' => 1, 'y' => 2, 'w' => 30, 'h' => 20,
					'layout' => array( 'h' => array( 'mode' => 'stretch' ), 'v' => array( 'mode' => 'start' ), 'ref' => 'safe' ),
				),
				array( 'id' => 'svg-mwzbwe9c-1', 'type' => 'svg', 'w' => 96, 'h' => 96, 'vw' => 24, 'vh' => 24, 'paths' => array( array( 'd' => 'M0 0L1 1Z' ) ) ),
				array( 'id' => 'title', 'type' => 'text', 'lh' => 1.2, 'ls' => 0.5, 'runs' => array( array( 'text' => 'a', 'b' => true ), array( 'field' => 'name', 'i' => true ) ) ),
				array( 'type' => 'line', 'x1' => 0, 'y1' => 0, 'x2' => 5, 'y2' => 5 ),
			),
		);
		$markup = Design::to_markup( $design );

		$this->assertStringContainsString( '"width":30,"height":20', $markup );
		$this->assertStringContainsString( '"layout":{"horizontal":{"mode":"stretch"},"vertical":{"mode":"start"},"ref":"safe"}', $markup );
		$this->assertStringContainsString( '"viewBox":"0 0 24 24"', $markup );
		$this->assertStringContainsString( '"lineHeight":1.2,"letterSpacing":0.5', $markup );
		$this->assertStringContainsString( '"bold":true', $markup );
		$this->assertStringContainsString( '"italic":true', $markup );
		$this->assertStringContainsString( '"x1":0', $markup, 'The coordinates of a line stay.' );
		$this->assertStringContainsString( '"id":"title"', $markup, 'An id that means something stays.' );
		$this->assertSame( 0, preg_match( '/"id":"(rect-2|svg-)/', $markup ), 'An id that only counts is not written.' );
		$this->assertSame( 0, preg_match( '/"(w|h|vw|vh|lh|ls|b|i)":/', $markup ), 'No short name.' );

		$back = Design::from_markup( $markup );
		$this->assertSame( array( 'rect-1', 'svg-2', 'title', 'line-4' ), array_column( $back['elements'], 'id' ), 'Made again by position.' );
		unset( $back['elements'][0]['id'], $back['elements'][1]['id'], $back['elements'][3]['id'] );
		unset( $design['elements'][0]['id'], $design['elements'][1]['id'] );
		$this->assertEquals( $design['elements'], $back['elements'] );
	}

	public function test_the_default_name_of_an_element_is_not_written() {
		$design = array(
			'page'     => array( 'width' => '100mm', 'height' => '50mm' ),
			'elements' => array(
				array( 'type' => 'rect', 'name' => 'Rectangle', 'w' => 1, 'h' => 2 ),
				array( 'type' => 'rect', 'name' => 'Band', 'w' => 1, 'h' => 2 ),
				array( 'type' => 'svg', 'name' => 'SVG' ),
				array( 'type' => 'image', 'slot' => 'logo', 'name' => 'Community logo' ),
				array( 'type' => 'image', 'name' => 'Image' ),
				array( 'type' => 'signature', 'name' => 'Signature' ),
			),
		);
		$names  = array( 'signature' => 'Signature', 'image:logo' => 'Community logo' );

		$without = Design::to_markup( $design );
		$this->assertStringNotContainsString( '"name":"Rectangle"', $without );
		$this->assertStringContainsString( '"name":"Band"', $without );
		$this->assertStringNotContainsString( '"name":"SVG"', $without );
		$this->assertStringContainsString( '"name":"Community logo"', $without, 'A host name that the package does not know is written.' );
		$this->assertStringContainsString( '"name":"Signature"', $without );

		$with = Design::to_markup( $design, array( 'names' => $names ) );
		$this->assertStringNotContainsString( '"name":"Community logo"', $with );
		$this->assertStringNotContainsString( '"name":"Signature"', $with );
		$this->assertStringContainsString( '"name":"Band"', $with );
	}
}
