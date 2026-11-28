<?php
/**
 * The text of an element as it is written: `Dear [name], *welcome* to _[event]_.` The same as `runs.ts` of the JavaScript package (the two read the
 * same fixtures: `packages/fixtures/runs.json`).
 *
 *   - `[key]` or `[key:format]` is a field (only a key the host says is a field, when it says; `{key}`, what files wrote before, is read as one too);
 *   - `*…*` is bold (a run of asterisks is one switch, so the `**…**` of before is bold), `_…_` is italic (not inside a word: `snake_case` is text);
 *   - a switch with no partner is text (`5 * 3`), a line break is a line break;
 *   - `\` before one of `\ * _ [ ] {` makes it text.
 *
 * The model keeps runs (`array( 'text' => …, 'b' => true )` and `array( 'field' => …, 'format' => …, 'b' => true )`): this is how a file and the
 * clipboard say them.
 *
 * @package Camaleaun\Draw
 */

namespace Camaleaun\Draw;

/**
 * Text <-> runs.
 */
final class Runs {

	/**
	 * The most characters read (what a file may hold is cleaned elsewhere; this only bounds the work).
	 */
	const MAX_TEXT = 16000;

	const KEY = '/^[A-Za-z0-9_-]{1,40}$/';

	const ESCAPABLE = '\\*_[]{';

	/**
	 * Whether a character is part of a word.
	 *
	 * @param string|null $char Character.
	 * @return bool
	 */
	private static function word( $char ) {
		return null !== $char && 1 === preg_match( '/^[\p{L}\p{N}]$/u', $char );
	}

	/**
	 * Whether a key is a field: any valid key when the host gave no list.
	 *
	 * @param string             $key    Key.
	 * @param array<int, string>|null $fields Fields.
	 * @return bool
	 */
	private static function known( $key, $fields ) {
		return 1 === preg_match( self::KEY, $key ) && ( null === $fields || in_array( $key, $fields, true ) );
	}

	/**
	 * Characters of a text.
	 *
	 * @param string $text Text.
	 * @return array<int, string>
	 */
	private static function chars( $text ) {
		$chars = preg_split( '//u', $text, -1, PREG_SPLIT_NO_EMPTY );
		return is_array( $chars ) ? $chars : array();
	}

	/**
	 * The tokens of a text.
	 *
	 * @param string                  $source Text.
	 * @param array<int, string>|null $fields Fields.
	 * @return array<int, array<string, mixed>>
	 */
	private static function tokens( $source, $fields ) {
		$chars = self::chars( mb_substr( $source, 0, self::MAX_TEXT ) );
		$count = count( $chars );
		$out   = array();
		$push  = static function ( $text ) use ( &$out ) {
			$last = count( $out ) - 1;
			if ( $last >= 0 && 'text' === $out[ $last ]['kind'] ) {
				$out[ $last ]['text'] .= $text;
			} else {
				$out[] = array(
					'kind' => 'text',
					'text' => $text,
				);
			}
		};
		for ( $i = 0; $i < $count; $i++ ) {
			$c = $chars[ $i ];
			if ( '\\' === $c && $i + 1 < $count && false !== strpos( self::ESCAPABLE, $chars[ $i + 1 ] ) ) {
				$push( $chars[ ++$i ] );
			} elseif ( '*' === $c ) {
				$n = $i;
				while ( ( $chars[ $n + 1 ] ?? '' ) === '*' ) {
					++$n;
				}
				$out[] = array(
					'kind' => 'b',
					'raw'  => implode( '', array_slice( $chars, $i, $n - $i + 1 ) ),
				);
				$i     = $n;
			} elseif ( '_' === $c && ( $chars[ $i - 1 ] ?? '' ) !== '_' && ( $chars[ $i + 1 ] ?? '' ) !== '_' ) {
				$out[] = array(
					'kind' => 'i',
					'raw'  => '_',
				);
			} elseif ( '[' === $c || '{' === $c ) {
				$close = array_search( '[' === $c ? ']' : '}', array_slice( $chars, $i + 1 ), true );
				$end   = false === $close ? -1 : $i + 1 + $close;
				$body  = $end > $i ? implode( '', array_slice( $chars, $i + 1, $end - $i - 1 ) ) : '';
				$parts = explode( ':', $body );
				$key   = $parts[0];
				$fmt   = $parts[1] ?? null;
				if ( $end > $i && count( $parts ) <= 2 && self::known( $key, $fields ) && ( null === $fmt || ( '[' === $c && 1 === preg_match( self::KEY, $fmt ) ) ) ) {
					$token = array(
						'kind'  => 'field',
						'field' => $key,
					);
					if ( null !== $fmt && '' !== $fmt && 'site' !== $fmt ) {
						$token['format'] = $fmt;
					}
					$out[] = $token;
					$i     = $end;
				} else {
					$push( $c );
				}
			} else {
				$push( $c );
			}
		}

		return $out;
	}

	/**
	 * Decides which switches are real: they pair in order; the rest (and a `_` inside a word) are text.
	 *
	 * @param array<int, array<string, mixed>> $list Tokens.
	 * @return array<int, array<string, mixed>>
	 */
	private static function pair( array $list ) {
		$before = static function ( $index ) use ( &$list ) {
			$t = $list[ $index - 1 ] ?? null;
			if ( ! $t || 'text' !== $t['kind'] ) {
				return null;
			}
			$chars = self::chars( $t['text'] );
			return $chars ? end( $chars ) : null;
		};
		$after  = static function ( $index ) use ( &$list ) {
			$t = $list[ $index + 1 ] ?? null;
			if ( ! $t || 'text' !== $t['kind'] ) {
				return null;
			}
			$chars = self::chars( $t['text'] );
			return $chars ? $chars[0] : null;
		};
		foreach ( array( 'b', 'i' ) as $kind ) {
			$open = -1;
			foreach ( $list as $index => $t ) {
				if ( $kind !== $t['kind'] ) {
					continue;
				}
				if ( $open < 0 ) {
					// An italic opens before a word, not inside it.
					if ( 'i' === $kind && self::word( $before( $index ) ) ) {
						continue;
					}
					$open = $index;
				} elseif ( 'b' === $kind || ! self::word( $after( $index ) ) ) {
					$list[ $open ]['on'] = true;
					$list[ $index ]['on'] = true;
					$open                = -1;
				}
			}
		}

		return $list;
	}

	/**
	 * The marks of a run, in order.
	 *
	 * @param bool $b Bold.
	 * @param bool $i Italic.
	 * @return array<string, bool>
	 */
	private static function marks( $b, $i ) {
		return array_merge( $b ? array( 'b' => true ) : array(), $i ? array( 'i' => true ) : array() );
	}

	/**
	 * Text as runs.
	 *
	 * @param mixed                   $source Text.
	 * @param array<int, string>|null $fields The keys that are fields (null: any valid key).
	 * @return array<int, array<string, mixed>>
	 */
	public static function parse( $source, $fields = null ) {
		$list = self::pair( self::tokens( is_string( $source ) ? $source : '', $fields ) );
		$runs = array();
		$b    = false;
		$i    = false;
		$add  = static function ( array $run ) use ( &$runs ) {
			$last = count( $runs ) - 1;
			if ( isset( $run['text'] ) && $last >= 0 && isset( $runs[ $last ]['text'] ) && ! empty( $runs[ $last ]['b'] ) === ! empty( $run['b'] ) && ! empty( $runs[ $last ]['i'] ) === ! empty( $run['i'] ) ) {
				$runs[ $last ]['text'] .= $run['text'];
				return;
			}
			$runs[] = $run;
		};
		foreach ( $list as $t ) {
			if ( in_array( $t['kind'], array( 'b', 'i' ), true ) && ! empty( $t['on'] ) ) {
				if ( 'b' === $t['kind'] ) {
					$b = ! $b;
				} else {
					$i = ! $i;
				}
				continue;
			}
			$marks = self::marks( $b, $i );
			if ( 'field' === $t['kind'] ) {
				$add( array( 'field' => $t['field'] ) + ( isset( $t['format'] ) ? array( 'format' => $t['format'] ) : array() ) + $marks );
			} else {
				$add( array( 'text' => 'text' === $t['kind'] ? $t['text'] : $t['raw'] ) + $marks );
			}
		}

		return array_values(
			array_filter(
				$runs,
				static function ( $run ) {
					return ! isset( $run['text'] ) || '' !== $run['text'];
				}
			)
		);
	}

	/**
	 * The runs a text of runs is the same as: no empty text, no false marks, neighbours with the same marks joined.
	 *
	 * @param array<int, mixed> $runs Runs.
	 * @return array<int, array<string, mixed>>
	 */
	public static function normalize( array $runs ) {
		$out = array();
		foreach ( $runs as $run ) {
			if ( ! is_array( $run ) ) {
				continue;
			}
			$marks = self::marks( ! empty( $run['b'] ), ! empty( $run['i'] ) );
			if ( isset( $run['field'] ) && is_string( $run['field'] ) && '' !== $run['field'] ) {
				$format = isset( $run['format'] ) && is_string( $run['format'] ) && '' !== $run['format'] && 'site' !== $run['format'] ? array( 'format' => $run['format'] ) : array();
				$next   = array( 'field' => $run['field'] ) + $format + $marks;
			} elseif ( isset( $run['text'] ) && is_string( $run['text'] ) && '' !== $run['text'] ) {
				$next = array( 'text' => $run['text'] ) + $marks;
			} else {
				continue;
			}
			$last = count( $out ) - 1;
			if ( isset( $next['text'] ) && $last >= 0 && isset( $out[ $last ]['text'] ) && ! empty( $out[ $last ]['b'] ) === ! empty( $next['b'] ) && ! empty( $out[ $last ]['i'] ) === ! empty( $next['i'] ) ) {
				$out[ $last ]['text'] .= $next['text'];
			} else {
				$out[] = $next;
			}
		}

		return $out;
	}

	/**
	 * Runs as text: what `parse` reads back as the same runs.
	 *
	 * @param array<int, mixed> $runs Runs.
	 * @return string
	 */
	public static function to_text( array $runs ) {
		$out = '';
		$b   = false;
		$i   = false;
		foreach ( self::normalize( $runs ) as $run ) {
			$nb = ! empty( $run['b'] );
			$ni = ! empty( $run['i'] );
			// Closes before it opens.
			if ( $i && ! $ni ) {
				$out .= '_';
			}
			if ( $b !== $nb ) {
				$out .= '*';
			}
			if ( ! $i && $ni ) {
				$out .= '_';
			}
			$b = $nb;
			$i = $ni;
			if ( isset( $run['field'] ) ) {
				$out .= '[' . $run['field'] . ( isset( $run['format'] ) ? ':' . $run['format'] : '' ) . ']';
				continue;
			}
			$chars = self::chars( $run['text'] );
			foreach ( $chars as $n => $c ) {
				if ( '_' === $c ) {
					$out .= self::word( $chars[ $n - 1 ] ?? null ) && self::word( $chars[ $n + 1 ] ?? null ) ? '_' : '\\_';
				} elseif ( false !== strpos( '\\*[{', $c ) ) {
					$out .= '\\' . $c;
				} else {
					$out .= $c;
				}
			}
		}

		return $out . ( $i ? '_' : '' ) . ( $b ? '*' : '' );
	}
}
