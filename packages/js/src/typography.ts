/**
 * The line height and the letter spacing of a text, which may have a unit. A number is what files wrote before units: the line height a multiplier of the font size (1.2),
 * the letter spacing points. A text is a length with its unit: `lh` (a multiplier, the line height's own), `%` (of the font size), px, pt, mm, cm, in; with no unit a letter
 * spacing is a percentage and a line height a multiplier. The same as `Text` in PHP.
 */
import { UNITS } from './geometry';

export type TextLength = number | string;
export const LINE_HEIGHT_UNITS = [ 'lh', '%', 'px', 'pt', 'mm', 'cm', 'in' ] as const;
export const LETTER_SPACING_UNITS = [ '%', 'px', 'pt', 'mm', 'cm', 'in' ] as const;

const PARSE = /^\s*(-?\d*\.?\d+)\s*(lh|%|px|pt|mm|cm|in)?\s*$/i;

/** A text length as a number and its unit ('' when it has none); null when it is not a length. */
export function parseTextLength( value: unknown ): { n: number; unit: string } | null {
	const m = typeof value === 'string' ? PARSE.exec( value ) : null;
	return m ? { n: Number( m[ 1 ] ), unit: ( m[ 2 ] || '' ).toLowerCase() } : null;
}

/** The line height as a multiplier of the font size: 1.2 for none (or what is not a length). */
export function lineHeightFactor( value: TextLength | undefined, size: number, fallback = 1.2 ): number {
	if ( typeof value === 'number' ) {
		return value > 0 ? value : fallback;
	}
	const p = parseTextLength( value );
	if ( ! p || p.n <= 0 || size <= 0 ) {
		return fallback;
	}
	if ( p.unit === '%' ) {
		return p.n / 100;
	}
	if ( p.unit === '' || p.unit === 'lh' ) {
		return p.n;
	}
	return ( p.n * ( UNITS[ p.unit ] || 1 ) ) / size;
}

/** The letter spacing in points: 0 for none (or what is not a length). */
export function letterSpacingPt( value: TextLength | undefined, size: number ): number {
	if ( typeof value === 'number' ) {
		return value;
	}
	const p = parseTextLength( value );
	if ( ! p ) {
		return 0;
	}
	if ( p.unit === '%' || p.unit === '' || p.unit === 'lh' ) {
		return ( p.n / 100 ) * size;
	}
	return p.n * ( UNITS[ p.unit ] || 1 );
}
