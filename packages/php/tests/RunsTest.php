<?php
namespace Camaleaun\Draw\Tests;

use Camaleaun\Draw\Runs;
use PHPUnit\Framework\TestCase;

/**
 * Text <-> runs: the same fixtures as the JavaScript package.
 */
final class RunsTest extends TestCase {

	private function cases() {
		return json_decode( (string) file_get_contents( __DIR__ . '/../../fixtures/runs.json' ), true );
	}

	public function test_text_is_read_as_runs() {
		$cases = $this->cases();
		foreach ( array_merge( $cases['roundtrip'], $cases['parse'] ) as $case ) {
			$fields = array_key_exists( 'fields', $case ) ? $case['fields'] : $cases['fields'];
			$this->assertSame( $case['runs'], Runs::parse( $case['text'], $fields ), wp_json( $case['text'] ) );
		}
	}

	public function test_runs_are_written_as_the_text_that_reads_back_as_them() {
		$cases = $this->cases();
		foreach ( $cases['roundtrip'] as $case ) {
			$this->assertSame( $case['text'], Runs::to_text( $case['runs'] ), wp_json( $case['text'] ) );
			$this->assertSame( Runs::normalize( $case['runs'] ), Runs::parse( Runs::to_text( $case['runs'] ), $cases['fields'] ) );
		}
	}

	public function test_runs_with_empty_text_false_marks_and_the_default_format_are_the_same_as_without() {
		$this->assertSame(
			'ab[[issued_date]]',
			Runs::to_text(
				array(
					array( 'text' => '' ),
					array(
						'text' => 'a',
						'b'    => false,
					),
					array( 'text' => 'b' ),
					array(
						'field'  => 'issued_date',
						'format' => 'site',
					),
				)
			)
		);
	}
}

function wp_json( $text ) {
	return (string) json_encode( $text );
}
