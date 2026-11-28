<?php
namespace Camaleaun\Draw\Tests;

use Camaleaun\Draw\Svg;
use PHPUnit\Framework\TestCase;

/**
 * A frame drawn as SVG: every shape at its place, and nothing a value can say reaches the markup as code.
 */
final class SvgTest extends TestCase {

	public function test_the_shapes_are_drawn_at_their_place() {
		$svg = Svg::render(
			array(
				array( 'type' => 'rect', 'x' => 1, 'y' => 2, 'w' => 30, 'h' => 10, 'fill' => '$primary', 'radius' => array( 3, 3, 3, 3 ) ), // phpcs:ignore
				array( 'type' => 'ellipse', 'x' => 0, 'y' => 0, 'w' => 20, 'h' => 10, 'fill' => '#ff0000' ), // phpcs:ignore
				array( 'type' => 'svg', 'x' => 5, 'y' => 5, 'w' => 24, 'h' => 24, 'vw' => 24, 'vh' => 24, 'paths' => array( array( 'd' => 'M0 0L24 24Z' ) ) ), // phpcs:ignore
				array( 'type' => 'text', 'x' => 0, 'y' => 0, 'w' => 100, 'h' => 20, 'size' => 12, 'align' => 'center', 'runs' => array( array( 'text' => 'Hi' ) ) ), // phpcs:ignore
			),
			200,
			100,
			array( 'primary' => '#112233' )
		);
		$this->assertStringContainsString( 'viewBox="0 0 200 100"', $svg );
		$this->assertStringContainsString( '<rect x="1" y="2" width="30" height="10" rx="3" fill="#112233"/>', $svg );
		$this->assertStringContainsString( '<ellipse cx="10" cy="5" rx="10" ry="5" fill="#ff0000"/>', $svg );
		$this->assertStringContainsString( '<path d="M0 0L24 24Z" fill="currentColor"/>', $svg );
		$this->assertStringContainsString( 'text-anchor="middle"', $svg );
	}

	public function test_nothing_a_value_says_reaches_the_markup_as_code() {
		$svg = Svg::render(
			array(
				array( 'type' => 'rect', 'x' => 0, 'y' => 0, 'w' => 5, 'h' => 5, 'fill' => 'red" onload="alert(1)', 'stroke' => 'url(javascript:1)', 'strokeWidth' => 2 ), // phpcs:ignore
				array( 'type' => 'path', 'x' => 0, 'y' => 0, 'w' => 5, 'h' => 5, 'd' => 'M0 0<script>alert(1)</script>' ), // phpcs:ignore
				array( 'type' => 'image', 'x' => 0, 'y' => 0, 'w' => 5, 'h' => 5, 'src' => 'javascript:alert(1)' ), // phpcs:ignore
				array( 'type' => 'text', 'x' => 0, 'y' => 0, 'w' => 5, 'h' => 5, 'runs' => array( array( 'text' => '</text><script>alert(1)</script>' ) ) ), // phpcs:ignore
				array( 'type' => 'script', 'x' => 0, 'y' => 0, 'w' => 5, 'h' => 5 ), // phpcs:ignore
			),
			10,
			10
		);
		foreach ( array( '<script', 'onload', 'javascript:', 'url(' ) as $bad ) {
			$this->assertStringNotContainsString( $bad, $svg, $bad );
		}
		$this->assertSame( 1, substr_count( $svg, '<svg ' ) );
	}

	public function test_a_shape_a_path_a_group_and_a_frame_are_drawn_on_their_own() {
		$this->assertSame( '<rect x="1" y="2" width="3" height="4" fill="#fff"/>', Svg::element( array( 'type' => 'rect', 'x' => 1, 'y' => 2, 'w' => 3, 'h' => 4, 'fill' => '#fff' ) ) ); // phpcs:ignore
		$this->assertSame( '<path d="M0 0L5 5Z" fill="#112233" fill-rule="evenodd"/>', Svg::path( array( 'd' => 'M0 0L5 5Z', 'fill' => '$a', 'rule' => 'evenodd' ), array( 'a' => '#112233' ) ) );
		$this->assertSame( '', Svg::path( array( 'd' => 'M0 0<script>' ) ) );
		$group = Svg::nested( array( 'x' => 1, 'y' => 2, 'w' => 10, 'h' => 10, 'vw' => 24, 'vh' => 24 ), '<path d="M0 0L1 1Z" fill="currentColor"/>' ); // phpcs:ignore
		$this->assertStringContainsString( 'viewBox="0 0 24 24"', $group );
		$frame = Svg::frame( 300, 120, $group );
		$this->assertStringStartsWith( '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 300 120"', $frame );
		$this->assertSame( 2, substr_count( $frame, '<svg ' ) );
	}

	public function test_a_radius_is_points_or_a_percentage_of_the_shorter_side() {
		$this->assertSame( 12.0, Svg::radius( 12, 100.0, 40.0 ) );
		$this->assertSame( 10.0, Svg::radius( '25%', 100.0, 40.0 ) );
		$this->assertSame( 20.0, Svg::radius( '50%', 100.0, 40.0 ) );
		$this->assertSame( 20.0, Svg::radius( '80%', 100.0, 40.0 ) );
		$this->assertSame( 0.0, Svg::radius( 'abc', 100.0, 40.0 ) );
	}

	public function test_a_group_draws_its_box_and_what_is_in_it_from_its_corner() {
		$svg = Svg::element(
			array(
				'type'     => 'group',
				'x'        => 100,
				'y'        => 50,
				'w'        => 200,
				'h'        => 80,
				'fill'     => '#eeeeee',
				'children' => array( array( 'type' => 'rect', 'x' => 10, 'y' => 5, 'w' => 20, 'h' => 10, 'fill' => '#ff0000' ) ),
			)
		);
		$this->assertStringContainsString( '<rect x="100" y="50" width="200" height="80" fill="#eeeeee"/>', $svg );
		$this->assertStringContainsString( '<svg x="100" y="50" width="200" height="80" overflow="visible"><rect x="10" y="5" width="20" height="10" fill="#ff0000"/></svg>', $svg );
		$clipped = Svg::element( array( 'type' => 'group', 'x' => 0, 'y' => 0, 'w' => 10, 'h' => 10, 'clip' => true, 'children' => array() ) );
		$this->assertStringContainsString( 'overflow="hidden"', $clipped );
	}

	public function test_the_flow_of_a_row_is_the_same_as_the_typescript_one() {
		$items = array( array( 'w' => 40, 'h' => 10 ), array( 'w' => 60, 'h' => 20 ), array( 'w' => 20, 'h' => 30 ) );
		$row   = array( 'w' => 300, 'h' => 100, 'arrange' => 'row', 'gap' => 10, 'padding' => 5 );
		$boxes = \Camaleaun\Draw\Flow::boxes( $row, $items );
		$this->assertEquals( array( array( 5, 5, 40, 10 ), array( 55, 5, 60, 20 ), array( 125, 5, 20, 30 ) ), array_map( 'array_values', $boxes ) );
		$center = \Camaleaun\Draw\Flow::boxes( $row + array( 'justify' => 'center', 'align' => 'center' ), $items );
		$this->assertEqualsWithDelta( 80.0, $center[0]['x'], 0.001 );
		$this->assertEqualsWithDelta( 45.0, $center[0]['y'], 0.001 );
		$grown = \Camaleaun\Draw\Flow::boxes( array( 'w' => 200, 'h' => 50, 'arrange' => 'row', 'gap' => 10 ), array( array( 'w' => 40, 'h' => 10 ), array( 'w' => 20, 'h' => 10, 'grow' => 1 ), array( 'w' => 20, 'h' => 10, 'grow' => 3 ) ) );
		$this->assertEquals( array( array( 0, 40 ), array( 50, 45 ), array( 105, 95 ) ), array_map( static function ( $b ) { return array( $b['x'], $b['w'] ); }, $grown ) );
		$grid = \Camaleaun\Draw\Flow::boxes( array( 'w' => 210, 'h' => 200, 'arrange' => 'grid', 'columns' => 2, 'gap' => 10, 'padding' => 5 ), array( array( 'w' => 1, 'h' => 20 ), array( 'w' => 1, 'h' => 30 ), array( 'w' => 1, 'h' => 10 ) ) );
		$this->assertEquals( array( array( 5, 5, 95, 20 ), array( 110, 5, 95, 30 ), array( 5, 45, 95, 10 ) ), array_map( 'array_values', $grid ) );
		$stack = \Camaleaun\Draw\Flow::boxes( array( 'w' => 100, 'h' => 300, 'arrange' => 'stack', 'gap' => 10 ), $items );
		$this->assertEquals( array( array( 0, 0, 40, 10 ), array( 0, 20, 60, 20 ), array( 0, 50, 20, 30 ) ), array_map( 'array_values', $stack ) );
	}

	public function test_a_row_group_draws_what_is_in_it_one_after_the_other() {
		$svg = Svg::element(
			array(
				'type'     => 'group',
				'x'        => 0,
				'y'        => 0,
				'w'        => 200,
				'h'        => 50,
				'arrange'  => 'row',
				'gap'      => 10,
				'children' => array(
					array( 'type' => 'rect', 'w' => 40, 'h' => 10, 'fill' => '#111111' ),
					array( 'type' => 'rect', 'w' => 20, 'h' => 10, 'fill' => '#222222', 'hidden' => true ),
					array( 'type' => 'rect', 'w' => 30, 'h' => 10, 'fill' => '#333333' ),
				),
			)
		);
		$this->assertStringContainsString( '<rect x="0" y="0" width="40" height="10" fill="#111111"/>', $svg );
		$this->assertStringContainsString( '<rect x="50" y="0" width="30" height="10" fill="#333333"/>', $svg );
	}

	public function test_a_line_height_and_a_letter_spacing_are_read_with_their_unit() {
		$this->assertEqualsWithDelta( 1.2, \Camaleaun\Draw\Text::line_height( null, 10 ), 0.0001 );
		$this->assertEqualsWithDelta( 1.4, \Camaleaun\Draw\Text::line_height( 1.4, 10 ), 0.0001 );
		$this->assertEqualsWithDelta( 1.5, \Camaleaun\Draw\Text::line_height( '150%', 10 ), 0.0001 );
		$this->assertEqualsWithDelta( 1.6, \Camaleaun\Draw\Text::line_height( '16px', 10 ), 0.0001 );
		$this->assertEqualsWithDelta( 2.0, \Camaleaun\Draw\Text::line_height( '2lh', 10 ), 0.0001 );
		$this->assertEqualsWithDelta( 0.5, \Camaleaun\Draw\Text::letter_spacing( 0.5, 10 ), 0.0001, 'A number is points.' );
		$this->assertEqualsWithDelta( 0.5, \Camaleaun\Draw\Text::letter_spacing( '5%', 10 ), 0.0001, 'A percentage of the size.' );
		$this->assertEqualsWithDelta( 0.5, \Camaleaun\Draw\Text::letter_spacing( '5', 10 ), 0.0001, 'With no unit, a percentage.' );
		$this->assertEqualsWithDelta( 3.0, \Camaleaun\Draw\Text::letter_spacing( '3pt', 10 ), 0.0001 );
		$this->assertEqualsWithDelta( 28.3465, \Camaleaun\Draw\Text::letter_spacing( '10mm', 10 ), 0.0001 );
	}
}
