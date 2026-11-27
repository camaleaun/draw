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
}
