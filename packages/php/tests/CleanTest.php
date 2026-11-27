<?php
namespace Camaleaun\Draw\Tests;

use Camaleaun\Draw\Clean;
use Camaleaun\Draw\Page;
use Camaleaun\Draw\Paths;
use PHPUnit\Framework\TestCase;

/**
 * The strict cleaning: designs that are right come out the same, designs made to do harm come out clean.
 */
final class CleanTest extends TestCase {

	private function clean(): Clean {
		return new Clean(
			array(
				'upload_url'          => static function ( $url ) {
					return 0 === strpos( $url, 'https://example.test/uploads/' ) && ! preg_match( '#\.(php|svg|html?|js)$#i', $url );
				},
				'attachment_is_image' => static function ( $id ) {
					return 7 === $id;
				},
			)
		);
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
	public function test_a_golden_design_is_kept_as_it_is( $file ) {
		$design = json_decode( file_get_contents( $file ), true );
		$notes  = array();
		$clean  = $this->clean()->design( $design, $notes );
		$this->assertSame( array(), $notes );
		$this->assertArrayNotHasKey( 'version', $clean, 'A page with units says what it is.' );
		$back = Page::in_points( $clean );
		$this->assertEqualsWithDelta( $design['page']['safe'], $back['page']['safe'], 0.01 );
		unset( $design['page'], $design['version'], $back['page'], $back['version'] );
		$this->assertEquals( $design, $back );
	}

	public function test_a_type_the_host_adds_has_only_the_colors_it_names() {
		$design = array(
			'page'     => array( 'width' => '100px', 'height' => '100px' ),
			'elements' => array(
				array( 'id' => 'signature', 'type' => 'signature', 'x' => 1, 'y' => 2, 'w' => 120, 'h' => 40, 'color' => '$text', 'fill' => '#fff', 'paths' => array( array( 'd' => 'M0 0L1 1Z' ) ), 'onclick' => 'x' ),
			),
		);
		// By default there is no such type.
		$this->assertSame( array(), $this->clean()->design( $design )['elements'] );
		$host  = new Clean( array( 'types' => array( 'signature' => array( 'color' ) ) ) );
		$clean = $host->design( $design );
		$this->assertSame( 'signature', $clean['elements'][0]['type'] );
		$this->assertSame( '$text', $clean['elements'][0]['color'] );
		$this->assertSame( 120.0, (float) $clean['elements'][0]['w'] );
		foreach ( array( 'fill', 'paths', 'onclick' ) as $key ) {
			$this->assertArrayNotHasKey( $key, $clean['elements'][0], $key );
		}
	}

	public function test_path_data_is_commands_and_numbers_only() {
		foreach ( array( 'M0 0L100 0L100 40L0 40Z', 'M0 0 10 10 20 0z', 'M10 10a2 2 0 011-1.5', 'M.5.5L1e2 3.5e-1', "M0,0 L10,10\n20,20" ) as $good ) {
			$this->assertTrue( Paths::is_data( $good ), $good );
		}
		foreach ( array( '', 'L0 0', 'M0 0<script>alert(1)</script>', 'M0 0 onload=alert(1)', 'M0 0L1e999 5', 'M0 0L999999 5', 'M0 0L10', 'M0 0X5 5', 'M0 0L5 5 url(#x)', 'M0 0A1 1 0 2 1 5 5', 'M0 0Z5', str_repeat( 'M0 0L1 1', 3000 ), 'M0 0L' . str_repeat( '9', 40 ) . ' 5' ) as $bad ) {
			$this->assertFalse( Paths::is_data( $bad ), substr( $bad, 0, 40 ) );
		}
	}

	public function test_a_drawing_keeps_only_valid_paths_and_a_preferred_size() {
		$d = Paths::drawing( array( 'vw' => 24, 'vh' => 24, 'w' => 512, 'h' => 512, 'paths' => array( array( 'd' => 'M0 0L1 1Z' ), array( 'd' => 'M0 0<script>' ), array( 'd' => 'M0 0L5 5Z', 'fill' => 'url(javascript:1)' ), array( 'd' => 'M0 0L5 5Z', 'fill' => '$text', 'rule' => 'evenodd' ) ) ) ); // phpcs:ignore
		$this->assertCount( 3, $d['paths'] );
		$this->assertArrayNotHasKey( 'fill', $d['paths'][1] );
		$this->assertSame( '$text', $d['paths'][2]['fill'] );
		$this->assertEquals( 512, $d['w'] );
		$this->assertArrayNotHasKey( 'w', Paths::drawing( array( 'vw' => 24, 'vh' => 24, 'w' => 24, 'h' => 24, 'paths' => array( array( 'd' => 'M0 0L1 1Z' ) ) ) ) );
		$this->assertNull( Paths::drawing( array( 'vw' => 99999, 'vh' => 4, 'paths' => array( array( 'd' => 'M0 0L1 1Z' ) ) ) ) );
	}

	public function test_a_design_made_to_do_harm_is_cleaned() {
		$img = static function ( $id, $src ) {
			return array( 'id' => $id, 'type' => 'image', 'x' => 0, 'y' => 0, 'w' => 10, 'h' => 10, 'src' => $src ); // phpcs:ignore
		};
		$design = array(
			'page'     => array( 'w' => 841.89, 'h' => 595.28, 'bg' => 'url(javascript:alert(1))', 'safe' => 28, 'bleed' => 0 ),
			'colors'   => array( 'text' => array( 'name' => '<b>Text</b>', 'light' => '#1B1F2E' ), 'evil' => array( 'name' => 'x', 'light' => 'red;background:url(//evil)' ) ),
			'onload'   => 'alert(1)',
			'elements' => array(
				array( 'id' => 'a', 'type' => 'script', 'x' => 0, 'y' => 0, 'w' => 5, 'h' => 5 ),
				array( 'id' => 'b', 'type' => 'text', 'x' => '1e999', 'y' => 'abc', 'w' => 100, 'h' => 20, 'onclick' => 'alert(1)', 'style' => 'background:url(javascript:alert(1))', 'font' => "Arial';}</style><script>alert(1)</script>", 'color' => 'expression(alert(1))', 'weight' => 99999, 'size' => 14, 'runs' => array( array( 'text' => '<script>alert(1)</script>Hello <img src=x onerror=alert(1)>, ' ), array( 'field' => 'name"><script>' ), array( 'field' => 'name' ) ) ), // phpcs:ignore
				$img( 'c', 'javascript:alert(1)' ),
				$img( 'd', 'data:image/png;base64,AAAA' ),
				$img( 'e', 'https://evil.example/x.png' ),
				$img( 'f', 'https://example.test/uploads/shell.php' ),
				$img( 'g', 'https://example.test/uploads/logo.png' ),
				array( 'id' => 'h', 'type' => 'svg', 'x' => 0, 'y' => 0, 'w' => 10, 'h' => 10, 'vw' => 100, 'vh' => 100, 'markup' => '<svg onload=alert(1)>', 'paths' => array( array( 'd' => 'M0 0L5 5Z' ), array( 'd' => 'M0 0<script>' ) ) ), // phpcs:ignore
			),
		);
		$notes = array();
		$clean = $this->clean()->design( $design, $notes );
		$json  = json_encode( $clean );
		$this->assertArrayNotHasKey( 'onload', $clean );
		$this->assertSame( '', $clean['page']['backgroundColor'] );
		$this->assertSame( array( 'text' ), array_keys( $clean['colors'] ) );
		$this->assertSame( 'Text', $clean['colors']['text']['name'] );
		foreach ( array( '<script', 'onerror', 'onclick', 'javascript:', 'expression(', 'url(', 'onload', 'evil.example', 'data:image', 'shell.php' ) as $forbidden ) {
			$this->assertStringNotContainsString( $forbidden, $json, $forbidden );
		}
		$by = array_column( $clean['elements'], null, 'id' );
		$this->assertArrayNotHasKey( 'a', $by );
		$this->assertArrayNotHasKey( 'x', $by['b'] );
		$this->assertSame( 900, $by['b']['weight'] );
		$this->assertSame( 'Hello , ', $by['b']['runs'][0]['text'] );
		foreach ( array( 'c', 'd', 'e', 'f' ) as $id ) {
			$this->assertSame( '', $by[ $id ]['src'], $id );
		}
		$this->assertSame( 'https://example.test/uploads/logo.png', $by['g']['src'] );
		$this->assertCount( 1, $by['h']['paths'] );
		$this->assertArrayNotHasKey( 'markup', $by['h'] );
		$this->assertSame( 1, $notes['elements'] );
		$this->assertSame( 4, $notes['pictures'] );
		$this->assertSame( 1, $notes['paths'] );
	}

	public function test_what_is_not_a_design_is_refused_and_a_big_one_is_cut() {
		$this->assertNull( $this->clean()->design( 'text' ) );
		$this->assertNull( $this->clean()->design( array( 'elements' => array() ) ) );
		$big = array( 'page' => array( 'w' => 100, 'h' => 100 ), 'elements' => array() ); // phpcs:ignore
		for ( $i = 0; $i < 400; $i++ ) {
			$big['elements'][] = array( 'id' => 'r' . $i, 'type' => 'rect', 'x' => $i, 'y' => 0, 'w' => 1, 'h' => 1 ); // phpcs:ignore
		}
		$this->assertCount( Clean::MAX_ELEMENTS, $this->clean()->design( $big )['elements'] );
		$big['elements'][0]['name'] = str_repeat( 'x', 3 * 1048576 );
		$this->assertNull( $this->clean()->design( $big ) );
	}

	public function test_the_page_is_kept_with_units() {
		$old   = array( 'version' => 1, 'page' => array( 'w' => 841.89, 'h' => 595.28, 'bg' => '$background', 'safe' => 28.35, 'bleed' => 0 ), 'elements' => array() ); // phpcs:ignore
		$clean = $this->clean()->design( $old );
		$this->assertSame( array( 'width' => '297mm', 'height' => '210mm', 'backgroundColor' => '$background', 'safe' => '10mm' ), $clean['page'] ); // phpcs:ignore
		$this->assertArrayNotHasKey( 'version', $clean );
		$this->assertSame( $clean['page'], $this->clean()->design( $clean )['page'] );
		// What an earlier version wrote (a point as px) is read as it was.
		$earlier = $this->clean()->design( array( 'page' => array( 'width' => '841.89px', 'height' => '595.28px' ), 'elements' => array() ) );
		$this->assertSame( '297mm', $earlier['page']['width'] );
		// No safe margin written when there is none, and it is read as 0.
		$none = $this->clean()->design( array( 'page' => array( 'w' => 841.89, 'h' => 595.28, 'bg' => '$background', 'safe' => 0, 'bleed' => 0 ), 'elements' => array() ) );
		$this->assertSame( array( 'width' => '297mm', 'height' => '210mm', 'backgroundColor' => '$background' ), $none['page'] ); // phpcs:ignore
		$this->assertSame( 0.0, (float) Page::in_points( $none )['page']['safe'] );
		// Read back in points to the hundredth: 297mm by 210mm is 841.89 by 595.28.
		$points = Page::in_points( $clean )['page'];
		$this->assertSame( 841.89, $points['w'] );
		$this->assertSame( 595.28, $points['h'] );
	}

	public function test_the_size_of_a_page_is_in_the_unit_that_makes_it_round() {
		$this->assertSame( array( '297mm', '210mm' ), Page::size( 841.89, 595.28 ), 'An A4.' );
		$this->assertSame( array( '210mm', '297mm' ), Page::size( 595.28, 841.89 ), 'An A4, upright.' );
		$this->assertSame( array( '11in', '8.5in' ), Page::size( 792, 612 ), 'A letter.' );
		$this->assertSame( array( '148mm', '210mm' ), Page::size( 419.53, 595.28 ), 'An A5.' );
		$this->assertSame( array( '100mm', '100mm' ), Page::size( 283.47, 283.47 ), 'A custom one in mm.' );
		$this->assertSame( array( '800pt', '600pt' ), Page::size( 800, 600 ), 'None of the units is round: points.' );
		$this->assertSame( array( '812.3pt', '600pt' ), Page::size( 812.3, 600 ) );
	}
}
