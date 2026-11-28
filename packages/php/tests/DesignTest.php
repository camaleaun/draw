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

	public function written_before() {
		$out = array();
		foreach ( glob( __DIR__ . '/../../../tests/golden/markup/*.old.html' ) as $file ) {
			$out[ basename( $file ) ] = array( $file );
		}
		return $out;
	}

	/**
	 * The markup 0.25 wrote, kept in files: every later version reads it back to the same design.
	 *
	 * @dataProvider written_before
	 */
	public function test_the_markup_0_25_wrote_is_read_back_to_its_design( $file ) {
		$design = json_decode( file_get_contents( str_replace( array( '/markup/', '.old.html' ), array( '/', '.json' ), $file ) ), true );
		$unnamed = static function ( $sheet ) {
			foreach ( $sheet['elements'] ?? array() as $i => $element ) {
				if ( isset( $element['name'] ) && Names::default_name( $element ) === $element['name'] ) {
					unset( $sheet['elements'][ $i ]['name'] );
				}
			}

			return $sheet;
		};
		$design = $unnamed( $design );
		if ( isset( $design['back'] ) ) {
			$design['back'] = $unnamed( $design['back'] );
		}
		$markup = file_get_contents( $file );
		$this->assertTrue( Design::is_markup( $markup ) );
		$this->assertSame( json_encode( $this->canon( $design ) ), json_encode( $this->canon( Design::from_markup( $markup ) ) ) );
	}

	public function written_with_core_attributes() {
		$out = array();
		foreach ( glob( __DIR__ . '/../../../tests/golden/markup/*.new.html' ) as $file ) {
			$out[ basename( $file ) ] = array( $file );
		}
		return $out;
	}

	/**
	 * The markup with the attributes of a core block (made by tests/tools/make-new-fixture.py from the table of docs/block-markup.md, not by the package) is read back to its design.
	 *
	 * @dataProvider written_with_core_attributes
	 */
	public function test_the_markup_with_the_attributes_of_a_core_block_is_read_back_to_its_design( $file ) {
		$design  = json_decode( file_get_contents( str_replace( array( '/markup/', '.new.html' ), array( '/', '.json' ), $file ) ), true );
		$unnamed = static function ( $sheet ) {
			foreach ( $sheet['elements'] ?? array() as $i => $element ) {
				if ( isset( $element['name'] ) && Names::default_name( $element ) === $element['name'] ) {
					unset( $sheet['elements'][ $i ]['name'] );
				}
			}

			return $sheet;
		};
		$design = $unnamed( $design );
		$this->assertSame( json_encode( $this->canon( $design ) ), json_encode( $this->canon( Design::from_markup( file_get_contents( $file ), array( 'fonts' => array( 'Inter', 'Lora' ) ) ) ) ) );
	}

	/**
	 * What 0.27 writes is what the oracle says, block by block.
	 *
	 * @dataProvider written_with_core_attributes
	 */
	public function test_the_markup_is_written_with_the_attributes_of_a_core_block( $file ) {
		$design  = json_decode( file_get_contents( str_replace( array( '/markup/', '.new.html' ), array( '/', '.json' ), $file ) ), true );
		$written = Design::to_markup( $design, array( 'fonts' => array( 'Inter', 'Lora' ) ) );
		$this->assertSame( json_encode( $this->canon_blocks( Markup::parse( file_get_contents( $file ) ) ) ), json_encode( $this->canon_blocks( Markup::parse( $written ) ) ) );
	}

	/**
	 * Blocks with their attributes in a fixed order, to compare them.
	 *
	 * @param array<int, array<string, mixed>> $blocks Blocks.
	 * @return array<int, array<string, mixed>>
	 */
	private function canon_blocks( array $blocks ) {
		return array_map(
			function ( $block ) {
				return array(
					'name'     => $block['name'],
					'attrs'    => $this->canon( $block['attrs'] ),
					'children' => $this->canon_blocks( $block['children'] ),
				);
			},
			$blocks
		);
	}

	/**
	 * A line height in lh is written with no unit: a number with none is the multiplier already.
	 */
	public function test_a_line_height_in_lh_is_written_with_no_unit() {
		$write = static function ( $element ) {
			return Design::to_markup( array( 'page' => array( 'w' => 1, 'h' => 1 ), 'elements' => array( array_merge( array( 'type' => 'text', 'id' => 't' ), $element ) ) ) );
		};
		$this->assertStringContainsString( '"lineHeight":"1.4"', $write( array( 'lh' => '1.4lh' ) ) );
		$this->assertStringNotContainsString( 'lh"', $write( array( 'lh' => '1.4lh' ) ) );
		$this->assertStringContainsString( '"lineHeight":"1.4"', $write( array( 'lh' => 1.4 ) ) );
		$this->assertStringContainsString( '"lineHeight":"1.4em"', $write( array( 'lh' => '1.4em' ) ) );
		$this->assertStringContainsString( '"lineHeight":"120%"', $write( array( 'lh' => '120%' ) ) );
		$this->assertStringContainsString( '"lineHeight":"1.3"', $write( array( 'hint' => array( 'size' => 6, 'lh' => '1.3lh' ) ) ) );
		$back = Design::from_markup( $write( array( 'lh' => '1.4lh' ) ) )['elements'][0]['lh'];
		$this->assertSame( 1.4, $back );
		$this->assertSame( \Camaleaun\Draw\Text::line_height( '1.4lh', 10 ), \Camaleaun\Draw\Text::line_height( $back, 10 ) );
	}

	/**
	 * The attributes of a core block, one by one (the same cases as the TypeScript test).
	 */
	public function test_the_attributes_of_a_core_block_one_by_one() {
		$read = static function ( $attrs, $options = array() ) {
			return Design::from_markup( '<!-- wp:events-certificates/certificate {"page":{"w":1,"h":1}} --><!-- wp:draw/text ' . json_encode( $attrs ) . ' /--><!-- /wp:events-certificates/certificate -->', $options )['elements'][0];
		};
		$this->assertSame( 13, $read( array( 'style' => array( 'typography' => array( 'fontSize' => '13px' ) ) ) )['size'] );
		$this->assertSame( 13, $read( array( 'style' => array( 'typography' => array( 'fontSize' => '13pt' ) ) ) )['size'] );
		$this->assertSame( '4.4mm', $read( array( 'style' => array( 'typography' => array( 'fontSize' => '4.4mm' ) ) ) )['size'] );
		$this->assertSame( 13, $read( array( 'size' => 13 ) )['size'] );
		$this->assertSame( 13, $read( array( 'size' => 9, 'style' => array( 'typography' => array( 'fontSize' => '13pt' ) ) ) )['size'], 'The new form wins.' );
		$this->assertSame( 'center', $read( array( 'align' => 'left', 'style' => array( 'typography' => array( 'textAlign' => 'center' ) ) ) )['align'] );
		$this->assertTrue( $read( array( 'lock' => array( 'move' => true ) ) )['locked'] );
		$this->assertTrue( $read( array( 'lock' => array( 'remove' => true ) ) )['locked'] );
		$this->assertSame( 1.5, $read( array( 'lock' => 1.5 ) )['lock'] );
		$this->assertSame( 1.5, $read( array( 'style' => array( 'dimensions' => array( 'aspectRatio' => '1.5' ) ) ) )['lock'] );
		$this->assertSame( 28.3465, $read( array( 'x' => '10mm' ) )['x'] );
		$this->assertSame( 144, $read( array( 'width' => '2in' ) )['w'] );
		$this->assertSame( 'CertSans', $read( array( 'fontFamily' => 'cert-sans' ), array( 'fonts' => array( 'CertSans' ) ) )['font'] );
		$this->assertSame( 'IBMPlexSans', $read( array( 'fontFamily' => 'IBMPlexSans' ), array( 'fonts' => array( 'CertSans' ) ) )['font'] );
		$this->assertSame( 'inter', $read( array( 'fontFamily' => 'inter' ) )['font'] );
		$this->assertSame( array( array( 'text' => 'Ola' ) ), $read( array( 'textVariants' => array( array( 'pt_BR' => 'Ola', 'es_ES' => 'Hola' ) ) ) )['variants']['pt_BR'] );
		$this->assertSame( array( array( 'text' => 'Hola' ) ), $read( array( 'textVariants' => array( array( 'pt_BR' => 'Ola' ), array( 'es_ES' => 'Hola' ) ) ) )['variants']['es_ES'] );
		$this->assertTrue( $read( array( 'metadata' => array( 'name' => 'Body', 'blockVisibility' => false ) ) )['hidden'] );
		$this->assertSame( 'title', $read( array( 'anchor' => 'title' ) )['id'] );
	}

	/**
	 * Some elements are block markup with no root, and are read back.
	 */
	public function test_some_elements_are_block_markup_with_no_root_and_read_back() {
		$elements = array(
			array( 'id' => 'title', 'type' => 'text', 'x' => 1, 'y' => 2, 'w' => 100, 'h' => 20, 'runs' => array( array( 'text' => 'Hi ' ), array( 'field' => 'name', 'b' => true ) ), 'size' => 13 ),
			array( 'id' => 'group-2', 'type' => 'group', 'x' => 0, 'y' => 0, 'w' => 50, 'h' => 50, 'children' => array( array( 'id' => 'group-2-rect-1', 'type' => 'rect', 'x' => 0, 'y' => 0, 'w' => 5, 'h' => 5 ) ) ),
		);
		$markup = Design::elements_to_markup( $elements );
		$this->assertStringNotContainsString( 'wp:events-certificates/certificate', $markup );
		$this->assertCount( 2, Markup::parse( $markup ) );
		$this->assertEquals( $elements, Design::elements_from_markup( $markup ) );
		$design = array( 'page' => array( 'w' => 100, 'h' => 100 ), 'elements' => $elements, 'back' => array( 'elements' => array( array( 'id' => 'x', 'type' => 'rect' ) ) ) );
		$this->assertEquals( $elements, Design::elements_from_markup( Design::to_markup( $design ) ) );
		$paragraph = '<!-- wp:core/paragraph --><p>x</p><!-- /wp:core/paragraph -->';
		$this->assertEquals( $elements, Design::elements_from_markup( $paragraph . $markup ) );
		$this->assertNull( Design::elements_from_markup( $paragraph ) );
		$this->assertNull( Design::elements_from_markup( '' ) );
		$this->assertNull( Design::elements_from_markup( '<!-- wp:draw/text {"x":} /-->' ) );
		$this->assertSame( 'Frame', Design::elements_from_markup( '<!-- wp:draw/rect {"metadata":{"name":"Frame"}} /-->' )[0]['name'] );
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

	public function test_a_text_is_written_as_text_and_read_as_runs_in_every_language() {
		$design = array(
			'page'     => array( 'w' => 1, 'h' => 1 ),
			'elements' => array(
				array(
					'type'     => 'text',
					'id'       => 't',
					'runs'     => array( array( 'text' => 'Held in ' ), array( 'field' => 'location', 'b' => true ) ),
					'variants' => array( 'pt_BR' => array( array( 'text' => 'Em ' ), array( 'field' => 'location', 'i' => true ) ) ),
				),
			),
		);
		$markup = Design::to_markup( $design );
		$this->assertStringContainsString( '"text":"Held in **[[location]]**"', $markup );
		$this->assertStringContainsString( '"textVariants":[{"pt_BR":"Em *[[location]]*"}]', $markup );
		$this->assertSame( $design, Design::from_markup( $markup ) );
	}

	public function test_a_text_of_an_earlier_file_is_read() {
		$old = '<!-- wp:events-certificates/certificate {"page":{"w":1,"h":1}} --><!-- wp:draw/text {"id":"t","runs":[{"text":"a"},{"field":"name","bold":true}],"variants":{"pt_BR":[{"text":"b","bold":true,"i":true}]}} /--><!-- wp:draw/text {"id":"u","text":"{name} and **bold**"} /--><!-- /wp:events-certificates/certificate -->';
		$design = Design::from_markup( $old );
		$this->assertSame( array( array( 'text' => 'a' ), array( 'field' => 'name', 'b' => true ) ), $design['elements'][0]['runs'] );
		$this->assertSame( array( 'pt_BR' => array( array( 'text' => 'b', 'b' => true, 'i' => true ) ) ), $design['elements'][0]['variants'] );
		$this->assertSame( array( array( 'field' => 'name' ), array( 'text' => ' and ' ), array( 'text' => 'bold', 'b' => true ) ), $design['elements'][1]['runs'] );
	}

	public function test_only_the_fields_the_host_says_are_fields() {
		$markup = '<!-- wp:events-certificates/certificate {"page":{"w":1,"h":1}} --><!-- wp:draw/text {"id":"t","text":"[[name]] [[other]]"} /--><!-- /wp:events-certificates/certificate -->';
		$design = Design::from_markup( $markup, array( 'fields' => array( 'name' ) ) );
		$this->assertSame( array( array( 'field' => 'name' ), array( 'text' => ' [[other]]' ) ), $design['elements'][0]['runs'] );
	}

	public function test_a_type_with_a_slot_can_have_a_block_of_its_own() {
		$options = array( 'blocks' => array( 'text:date-of-emission' => 'a/date-of-emission' ) );
		$design  = array(
			'page'     => array( 'w' => 1, 'h' => 1 ),
			'elements' => array(
				array( 'type' => 'text', 'id' => 'd', 'runs' => array( array( 'field' => 'issued_date' ) ), 'slot' => 'date-of-emission' ),
				array( 'type' => 'text', 'id' => 't', 'slot' => 'other', 'runs' => array() ),
			),
		);
		$markup  = Design::to_markup( $design, $options );
		$this->assertStringContainsString( 'wp:a/date-of-emission {"anchor":"d","text":"[[issued_date]]"}', $markup );
		$this->assertStringContainsString( '"slot":"other"', $markup, 'Another slot stays an attribute of the text.' );
		$this->assertSame( $design, Design::from_markup( $markup, $options ) );
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

	public function test_a_group_says_no_id_in_a_file() {
		$design = array(
			'page'     => array( 'width' => '100mm', 'height' => '50mm' ),
			'elements' => array(
				array(
					'id' => '0503d4b3-a2fc-431d-9417-5b874edb1ead', 'type' => 'group', 'x' => 0, 'y' => 0, 'w' => 50, 'h' => 20,
					'children' => array(
						array( 'id' => '0503d4b3-a2fc-431d-9417-5b874edb1ead-group-1', 'type' => 'group', 'x' => 0, 'y' => 0, 'w' => 10, 'h' => 10, 'children' => array( array( 'id' => 'signature', 'type' => 'signature', 'x' => 0, 'y' => 0, 'w' => 10, 'h' => 10 ) ) ),
					),
				),
			),
		);
		$markup = Design::to_markup( $design );

		$this->assertSame( 0, preg_match( '/draw\/group \{[^}]*"id"/', $markup ), 'No id on a group: ' . $markup );
		$this->assertStringContainsString( '"anchor":"signature"', $markup, 'What means something stays.' );
		$back = Design::from_markup( $markup );
		$this->assertSame( 'group-1', $back['elements'][0]['id'], 'Made again by position.' );
		$this->assertSame( 'group-1-group-1', $back['elements'][0]['children'][0]['id'] );
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

		$this->assertStringContainsString( '"width":"30pt","height":"20pt"', $markup );
		$this->assertStringContainsString( '"layout":{"horizontal":{"mode":"stretch"},"vertical":{"mode":"start"},"reference":"safe"}', $markup );
		$this->assertStringContainsString( '"viewBox":"0 0 24 24"', $markup );
		$this->assertStringContainsString( '"typography":{"lineHeight":"1.2","letterSpacing":"0.5pt"}', $markup );
		$this->assertStringContainsString( '"text":"**a***[[name]]*"', $markup, 'A text is written as text.' );
		$this->assertStringNotContainsString( '"runs"', $markup );
		$this->assertStringContainsString( '"x1":"0pt"', $markup, 'The coordinates of a line are lengths with their unit.' );
		$this->assertStringContainsString( '"anchor":"title"', $markup, 'An id that means something is the anchor.' );
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

	public function test_a_group_is_a_block_with_its_children_as_inner_blocks() {
		$design = array(
			'page'     => array( 'width' => '297mm', 'height' => '210mm' ),
			'elements' => array(
				array( 'type' => 'rect', 'w' => 10, 'h' => 10 ),
				array(
					'type'     => 'group',
					'w'        => 200,
					'h'        => 100,
					'children' => array(
						array( 'type' => 'rect', 'w' => 5, 'h' => 5 ),
						array( 'type' => 'group', 'w' => 50, 'h' => 50, 'children' => array( array( 'type' => 'rect', 'w' => 1, 'h' => 1 ) ) ),
					),
				),
			),
		);
		$markup = Design::to_markup( $design );
		$this->assertStringContainsString( '<!-- wp:draw/group ', $markup );
		$this->assertStringContainsString( '<!-- wp:draw/rect {"width":"5pt","height":"5pt"} /-->', $markup );
		$this->assertStringNotContainsString( '"children"', $markup );
		$back = Design::from_markup( $markup );
		$this->assertSame( 'group', $back['elements'][1]['type'] );
		$this->assertSame( 2, count( $back['elements'][1]['children'] ) );
		$this->assertSame( 'group-2-group-2-rect-1', $back['elements'][1]['children'][1]['children'][0]['id'], 'Inside a group the id follows the one of the group.' );
		$ids  = array();
		$walk = static function ( $list ) use ( &$walk, &$ids ) {
			foreach ( $list as $el ) {
				$ids[] = $el['id'];
				$walk( $el['children'] ?? array() );
			}
		};
		$walk( $back['elements'] );
		$this->assertSame( count( $ids ), count( array_unique( $ids ) ), implode( ',', $ids ) );
		$this->assertEquals( $back, Design::from_markup( Design::to_markup( $back ) ) );
	}
}
