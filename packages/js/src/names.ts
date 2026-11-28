/**
 * The names of an element in a file, as the blocks say them. The model keeps short names (w, h, vw, vh, lh, ls, a layout's h and v, a run's b and i);
 * a block says them in full (width, height, viewBox, lineHeight, letterSpacing, horizontal and vertical, bold and italic), and does not say an id that
 * only counts the elements (`rect-2`): it is made again by position when the file is read. x and y, and the coordinates of a line, stay.
 *
 * The same as `Names` of the PHP package.
 */
import { fromCore } from './core';
import { parseRuns, runsToText, type Run } from './runs';

type Attrs = Record< string, unknown >;

/** An id that only counts: a type and a number (`rect-2`), or the editor's (`svg-mwzbwe9c-1`); inside a group it follows the id of the group (`group-2-rect-1`). */
export const GENERATED_ID = /^(?:[a-z]+-(?:[a-z0-9]{6,9}-)?\d+-)*[a-z]+-(?:[a-z0-9]{6,9}-)?\d+$/;

const TEXT: Record< string, string > = { lh: 'lineHeight', ls: 'letterSpacing' };
const AXES: Record< string, string > = { h: 'horizontal', v: 'vertical' };
const MARKS: Record< string, string > = { b: 'bold', i: 'italic' };

const flip = ( map: Record< string, string >, full: boolean ) => ( full ? map : Object.fromEntries( Object.entries( map ).map( ( [ k, v ] ) => [ v, k ] ) ) );
const rename = ( value: unknown, map: Record< string, string >, full: boolean ): unknown => {
	if ( ! value || typeof value !== 'object' || Array.isArray( value ) ) {
		return value;
	}
	const names = flip( map, full );
	return Object.fromEntries( Object.entries( value ).map( ( [ k, v ] ) => [ names[ k ] ?? k, v ] ) );
};

/** The runs of a text element as the text that is written (`text`, where `runs` was; each language of `variants` the same). */
function writeText( element: Attrs ): Attrs {
	let out = element;
	if ( out.variants && typeof out.variants === 'object' ) {
		out = { ...out, variants: Object.fromEntries( Object.entries( out.variants as Attrs ).map( ( [ locale, runs ] ) => [ locale, Array.isArray( runs ) ? runsToText( runs as Run[] ) : runs ] ) ) };
	}
	if ( ! Array.isArray( out.runs ) ) {
		return out;
	}
	const written: Attrs = {};
	for ( const [ key, value ] of Object.entries( out ) ) {
		if ( key === 'runs' ) {
			written.text = runsToText( value as Run[] );
		} else if ( key !== 'text' ) {
			written[ key ] = value;
		}
	}
	return written;
}

/** The text that is written as the runs of a text element (a list is what files wrote before, with `bold` and `italic`). */
function readText( element: Attrs, fields: string[] | undefined, isText: boolean ): Attrs {
	let out = element;
	if ( out.variants && typeof out.variants === 'object' ) {
		out = { ...out, variants: Object.fromEntries( Object.entries( out.variants as Attrs ).map( ( [ locale, runs ] ) => [ locale, typeof runs === 'string' ? parseRuns( runs, { fields } ) : Array.isArray( runs ) ? runs.map( ( run ) => rename( run, MARKS, false ) ) : runs ] ) ) };
	}
	if ( ! isText || typeof out.text !== 'string' || out.runs !== undefined ) {
		return out;
	}
	return Object.fromEntries( Object.entries( out ).map( ( [ key, value ] ) => ( key === 'text' ? [ 'runs', parseRuns( value as string, { fields } ) ] : [ key, value ] ) ) );
}

function names( element: Attrs, full: boolean, fields?: string[], isText = false ): Attrs {
	let out = rename( element, TEXT, full ) as Attrs;
	out = full ? writeText( out ) : readText( out, fields, isText );
	for ( const key of [ 'hint', 'code' ] ) {
		if ( out[ key ] ) {
			out[ key ] = rename( out[ key ], TEXT, full );
		}
	}
	if ( out.layout ) {
		out.layout = rename( out.layout, AXES, full );
	}
	if ( Array.isArray( out.runs ) ) {
		out.runs = out.runs.map( ( run ) => rename( run, MARKS, full ) );
	}
	return out;
}

/**
 * The name an element has when it is made, by type: it is not written, as the type already says it. A host adds its own (`names` option of `Design`),
 * by type or by `type:slot` (`image:logo`).
 */
export const DEFAULT_NAMES: Record< string, string > = { text: 'Text', rect: 'Rectangle', ellipse: 'Ellipse', line: 'Line', image: 'Image', svg: 'SVG', group: 'Group', qr: 'Authenticity', verification: 'Verification' };

/** The name an element has when it is made: the host's for its type and slot, else the type's. */
export function defaultName( element: Attrs, hostNames: Record< string, string > = {} ): string {
	const all = { ...DEFAULT_NAMES, ...hostNames };
	return all[ `${ String( element.type ?? '' ) }:${ String( element.slot ?? '' ) }` ] ?? all[ String( element.type ?? '' ) ] ?? '';
}

/** An element as the attributes of its block. */
export function toAttrs( element: Attrs, hostNames: Record< string, string > = {}, fields?: string[] ): Attrs {
	const fallback = defaultName( element, hostNames );
	const el = names( element, true, fields );
	const out: Attrs = {};
	for ( const [ key, value ] of Object.entries( el ) ) {
		if ( key === 'name' && fallback !== '' && value === fallback ) {
			continue;
		}
		// An id that only counts is not written, and a group never says one: nothing points at a group (what is in it is placed by `ref: 'parent'`), and it is made again by position.
		if ( key === 'id' && typeof value === 'string' && ( GENERATED_ID.test( value ) || element.type === 'group' ) ) {
			continue;
		}
		if ( key === 'w' ) {
			out.width = value;
		} else if ( key === 'h' ) {
			out.height = value;
		} else if ( key === 'vw' && el.vh !== undefined ) {
			out.viewBox = `0 0 ${ value } ${ el.vh }`;
		} else if ( key !== 'vh' || el.vw === undefined ) {
			out[ key ] = value;
		}
	}
	return out;
}

/** The attributes of a block as an element, with no id when the block said none. */
export function fromAttrs( attrs: Attrs, fields?: string[], isText = false, type = '', fonts?: string[] ): Attrs {
	const out: Attrs = {};
	// The attributes of a core block (`style.typography`, `fontFamily`, lengths with units) are read as the names the file used before: both forms come through the same code.
	for ( const [ key, value ] of Object.entries( names( fromCore( attrs, type || ( isText ? 'text' : '' ), fonts ), false, fields, isText ) ) ) {
		const box = typeof value === 'string' ? /^\s*[-\d.]+[\s,]+[-\d.]+[\s,]+([\d.]+)[\s,]+([\d.]+)\s*$/.exec( value ) : null;
		if ( key === 'width' ) {
			out.w = value;
		} else if ( key === 'height' ) {
			out.h = value;
		} else if ( key === 'viewBox' && box ) {
			out.vw = Number( box[ 1 ] );
			out.vh = Number( box[ 2 ] );
		} else {
			out[ key ] = value;
		}
	}
	return out;
}

/**
 * Gives an id (`<type>-<position>`) to the elements that have none; the ids that are there are kept. What is in a group (`children`) is given its
 * own, `<id of the group>-<type>-<position>`, so no id is on the page twice.
 */
export function withIds( elements: Attrs[], prefix = '' ): Attrs[] {
	const taken = new Set( elements.map( ( el ) => el.id ).filter( Boolean ) as string[] );
	return elements.map( ( el, i ) => {
		let out = el;
		if ( ! el.id ) {
			const type = String( el.type || 'element' );
			let n = i + 1;
			while ( taken.has( `${ prefix }${ type }-${ n }` ) ) {
				n++;
			}
			taken.add( `${ prefix }${ type }-${ n }` );
			out = { ...el, id: `${ prefix }${ type }-${ n }` };
		}
		return Array.isArray( out.children ) ? { ...out, children: withIds( out.children as Attrs[], `${ out.id }-` ) } : out;
	} );
}
