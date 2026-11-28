/**
 * The text of an element as it is written: `Dear [name], *welcome* to _[event]_.` The same as `Runs` of the PHP package (the two read the same
 * fixtures: `packages/fixtures/runs.json`).
 *
 *   - `[key]` or `[key:format]` is a field (only a key the host says is a field, when it says; `{key}`, what files wrote before, is read as one too);
 *   - `*…*` is bold (a run of asterisks is one switch, so the `**…**` of before is bold), `_…_` is italic (not inside a word: `snake_case` is text);
 *   - a switch with no partner is text (`5 * 3`), a line break is a line break;
 *   - `\` before one of `\ * _ [ ] {` makes it text.
 *
 * The model keeps runs (`{ text, b, i }` and `{ field, format, b, i }`): this is how a file and the clipboard say them.
 */
export type Run = { text: string; b?: true; i?: true } | { field: string; format?: string; b?: true; i?: true };

/** The most characters read (what a file may hold is cleaned elsewhere; this only bounds the work). */
export const MAX_TEXT = 16000;

const KEY = /^[A-Za-z0-9_-]{1,40}$/;
const ESCAPABLE = '\\*_[]{';
const word = ( c: string | undefined ) => c !== undefined && /[\p{L}\p{N}]/u.test( c );

type Token = { kind: 'text'; text: string } | { kind: 'field'; field: string; format?: string; raw: string } | { kind: 'b' | 'i'; raw: string; on?: boolean };

/** Whether `key` is a field: any valid key when the host gave no list. */
const known = ( key: string, fields?: string[] ) => KEY.test( key ) && ( ! fields || fields.includes( key ) );

function tokens( source: string, fields?: string[] ): Token[] {
	const chars = Array.from( source.slice( 0, MAX_TEXT ) );
	const out: Token[] = [];
	const push = ( text: string ) => {
		const last = out[ out.length - 1 ];
		if ( last && last.kind === 'text' ) {
			last.text += text;
		} else {
			out.push( { kind: 'text', text } );
		}
	};
	for ( let i = 0; i < chars.length; i++ ) {
		const c = chars[ i ];
		if ( c === '\\' && i + 1 < chars.length && ESCAPABLE.includes( chars[ i + 1 ] ) ) {
			push( chars[ ++i ] );
		} else if ( c === '*' ) {
			let n = i;
			while ( chars[ n + 1 ] === '*' ) {
				n++;
			}
			out.push( { kind: 'b', raw: chars.slice( i, n + 1 ).join( '' ) } );
			i = n;
		} else if ( c === '_' && chars[ i - 1 ] !== '_' && chars[ i + 1 ] !== '_' ) {
			out.push( { kind: 'i', raw: '_' } );
		} else if ( c === '[' || c === '{' ) {
			const end = chars.indexOf( c === '[' ? ']' : '}', i + 1 );
			const body = end > i ? chars.slice( i + 1, end ).join( '' ) : '';
			const [ key, format, ...rest ] = body.split( ':' );
			if ( end > i && ! rest.length && known( key, fields ) && ( format === undefined || ( c === '[' && KEY.test( format ) ) ) ) {
				out.push( { kind: 'field', field: key, ...( format && format !== 'site' ? { format } : {} ), raw: chars.slice( i, end + 1 ).join( '' ) } );
				i = end;
			} else {
				push( c );
			}
		} else {
			push( c );
		}
	}
	return out;
}

/** Decides which switches are real: they pair in order; the rest (and a `_` inside a word) are text. */
function pair( list: Token[] ): void {
	const before = ( index: number ) => {
		const t = list[ index - 1 ];
		return t && t.kind === 'text' ? Array.from( t.text ).pop() : undefined;
	};
	const after = ( index: number ) => {
		const t = list[ index + 1 ];
		return t && t.kind === 'text' ? Array.from( t.text )[ 0 ] : undefined;
	};
	for ( const kind of [ 'b', 'i' ] as const ) {
		let open = -1;
		list.forEach( ( t, index ) => {
			if ( t.kind !== kind ) {
				return;
			}
			if ( open < 0 ) {
				// An italic opens before a word, not inside it.
				if ( kind === 'i' && word( before( index ) ) ) {
					return;
				}
				open = index;
			} else if ( kind === 'b' || ! word( after( index ) ) ) {
				( list[ open ] as { on?: boolean } ).on = true;
				( t as { on?: boolean } ).on = true;
				open = -1;
			}
		} );
	}
}

/** Text as runs. */
export function parseRuns( source: string, options: { fields?: string[] } = {} ): Run[] {
	const list = tokens( String( source ?? '' ), options.fields );
	pair( list );
	const runs: Run[] = [];
	let b = false;
	let i = false;
	const add = ( run: Run ) => {
		const last = runs[ runs.length - 1 ] as { text?: string; b?: true; i?: true } | undefined;
		if ( 'text' in run && last && 'text' in last && !! last.b === !! run.b && !! last.i === !! run.i ) {
			last.text += run.text;
			return;
		}
		runs.push( run );
	};
	for ( const t of list ) {
		if ( ( t.kind === 'b' || t.kind === 'i' ) && t.on ) {
			if ( t.kind === 'b' ) {
				b = ! b;
			} else {
				i = ! i;
			}
			continue;
		}
		const marks = { ...( b ? { b: true as const } : {} ), ...( i ? { i: true as const } : {} ) };
		if ( t.kind === 'field' ) {
			add( { field: t.field, ...( t.format ? { format: t.format } : {} ), ...marks } );
		} else {
			add( { text: t.kind === 'text' ? t.text : t.raw, ...marks } );
		}
	}
	return runs.filter( ( run ) => ! ( 'text' in run ) || run.text !== '' );
}

/** The runs a text of runs is the same as: no empty text, no false marks, neighbours with the same marks joined. */
export function normalizeRuns( runs: Run[] ): Run[] {
	const out: Run[] = [];
	for ( const run of runs ) {
		const r = run as { text?: string; field?: string; format?: string; b?: boolean; i?: boolean };
		const marks = { ...( r.b ? { b: true as const } : {} ), ...( r.i ? { i: true as const } : {} ) };
		const next: Run | null = typeof r.field === 'string' && r.field !== ''
			? { field: r.field, ...( r.format && r.format !== 'site' ? { format: r.format } : {} ), ...marks }
			: typeof r.text === 'string' && r.text !== '' ? { text: r.text, ...marks } : null;
		if ( ! next ) {
			continue;
		}
		const last = out[ out.length - 1 ] as { text?: string; b?: true; i?: true } | undefined;
		if ( 'text' in next && last && 'text' in last && !! last.b === !! next.b && !! last.i === !! next.i ) {
			last.text += next.text;
		} else {
			out.push( next );
		}
	}
	return out;
}

/** Runs as text: what `parseRuns` reads back as the same runs. */
export function runsToText( runs: Run[] ): string {
	let out = '';
	let b = false;
	let i = false;
	for ( const run of normalizeRuns( runs ) ) {
		const nb = !! run.b;
		const ni = !! run.i;
		// Closes before it opens.
		if ( i && ! ni ) {
			out += '_';
		}
		if ( b && ! nb ) {
			out += '*';
		}
		if ( ! b && nb ) {
			out += '*';
		}
		if ( ! i && ni ) {
			out += '_';
		}
		b = nb;
		i = ni;
		if ( 'field' in run ) {
			out += `[${ run.field }${ run.format ? ':' + run.format : '' }]`;
		} else {
			const chars = Array.from( run.text );
			out += chars
				.map( ( c, n ) => ( c === '_' ? ( word( chars[ n - 1 ] ) && word( chars[ n + 1 ] ) ? '_' : '\\_' ) : '\\*[{'.includes( c ) ? `\\${ c }` : c ) )
				.join( '' );
		}
	}
	return out + ( i ? '_' : '' ) + ( b ? '*' : '' );
}
