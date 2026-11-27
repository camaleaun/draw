/**
 * The pure geometry and checks of a drawing, with no editor and no WordPress: lengths with units, anchors (layout) and the boxes they
 * resolve to, the path of a rectangle with its corner shapes, and what SVG code may be read into paths. Shared by every editor of a
 * drawing (the certificate editor and the Draw plugin).
 */

export type Box = { x: number; y: number; w: number; h: number };

/** What the anchors need of the page: its size and its safe margin, in points. */
export type PageBox = { w: number; h: number; safe: number };

/** What the anchors need of an element: its kind, its place and its anchors. A line has no place of its own. */
export type Placeable = { type: string; x?: number; y?: number; w?: number; h?: number; layout?: Layout; lock?: number; ratio?: string };

export type CornerShape = 'round' | 'scoop' | 'bevel' | 'notch' | 'squircle';
export type SvgPath = { d: string; fill?: string; rule?: 'nonzero' | 'evenodd' };

export type AxisMode = 'start' | 'center' | 'end' | 'stretch';
export type Axis = { mode: AxisMode; start?: string; end?: string; size?: string };
export type Layout = { h: Axis; v: Axis; ref?: 'page' | 'safe' };

const UNITS: Record< string, number > = { pt: 1, px: 1, mm: 2.83465, cm: 28.3465, in: 72 };

/** A length in pt: "20pt", "10mm", "5%", "calc(100% - 40pt)" (terms joined by + or -), or a bare number (pt). */
export function parseLen( value: string | number | undefined, total: number ): number {
	if ( value === undefined || value === '' ) {
		return 0;
	}
	if ( typeof value === 'number' ) {
		return value;
	}
	let expr = value.trim().toLowerCase();
	const calc = /^calc\((.*)\)$/.exec( expr );
	if ( calc ) {
		expr = calc[ 1 ];
	}
	let sum = 0;
	const re = /([+-]?)\s*(-?\d*\.?\d+)\s*(pt|px|mm|cm|in|%)?/g;
	let m: RegExpExecArray | null;
	let matched = false;
	while ( ( m = re.exec( expr ) ) ) {
		matched = true;
		const n = parseFloat( m[ 2 ] ) * ( m[ 1 ] === '-' ? -1 : 1 );
		sum += m[ 3 ] === '%' ? ( n * total ) / 100 : n * ( UNITS[ m[ 3 ] || 'pt' ] || 1 );
	}
	return matched ? sum : 0;
}

/** Whether a length is valid open text. */
export function isLen( value: string ): boolean {
	const v = value.trim().toLowerCase();
	return /^-?\d*\.?\d+\s*(pt|px|mm|cm|in|%)?$/.test( v ) || /^calc\(\s*-?\d*\.?\d+\s*(pt|px|mm|cm|in|%)?(\s*[+-]\s*\d*\.?\d+\s*(pt|px|mm|cm|in|%)?)*\s*\)$/.test( v );
}

/** The box an element is anchored to. */
export function refBox( page: PageBox, ref?: 'page' | 'safe' ): Box {
	return ref === 'safe' ? { x: page.safe, y: page.safe, w: page.w - page.safe * 2, h: page.h - page.safe * 2 } : { x: 0, y: 0, w: page.w, h: page.h };
}

function resolveAxis( axis: Axis, origin: number, total: number, forced?: number ): [ number, number ] {
	const start = parseLen( axis.start, total );
	const end = parseLen( axis.end, total );
	const size = forced !== undefined ? forced : parseLen( axis.size, total );
	switch ( axis.mode ) {
		case 'end':
			return [ origin + total - end - size, size ];
		case 'center':
			return [ origin + ( total - size ) / 2 + start, size ];
		case 'stretch':
			return [ origin + start, Math.max( 1, total - start - end ) ];
		default:
			return [ origin + start, size ];
	}
}

/** An element with its anchors resolved to x, y, w, h in pt (elements without anchors are returned as they are). */
export function resolve< T extends Placeable >( el: T, page: PageBox ): T {
	const layout = ( el as { layout?: Layout } ).layout;
	if ( ! layout || el.type === 'line' ) {
		return el;
	}
	const r = refBox( page, layout.ref );
	let [ x, w ] = resolveAxis( layout.h, r.x, r.w );
	let [ y, h ] = resolveAxis( layout.v, r.y, r.h );
	const ratio = lockedRatio( el );
	const side = ratio ? lockedSide( layout ) : null;
	if ( ratio && side === 'w' ) {
		[ x, w ] = resolveAxis( layout.h, r.x, r.w, h * ratio );
	} else if ( ratio && side === 'h' ) {
		[ y, h ] = resolveAxis( layout.v, r.y, r.h, w / ratio );
	}
	return { ...el, x, y, w, h };
}

/** The width ÷ height an element keeps, if any: an image's aspect ratio setting, or the lock of other elements. */
export function lockedRatio( el: Placeable ): number | undefined {
	if ( el.type === 'image' ) {
		const parts = ( ( el as { ratio?: string } ).ratio || 'auto' ).split( '/' ).map( Number );
		return parts.length === 2 && parts[ 0 ] > 0 && parts[ 1 ] > 0 ? parts[ 0 ] / parts[ 1 ] : undefined;
	}
	const lock = ( el as { lock?: number } ).lock;
	return lock && lock > 0 ? lock : undefined;
}

/**
 * Which size follows the other when the ratio is kept: the height, unless only
 * the height fills (stretch), then the width. Filling both axes keeps both.
 */
export function lockedSide( layout: Layout ): 'w' | 'h' | null {
	const h = layout.h.mode === 'stretch';
	const v = layout.v.mode === 'stretch';
	if ( h && v ) {
		return null;
	}
	return v ? 'w' : 'h';
}

/** Writes a length back, in the unit it was written in (% stays %, anything else becomes pt). */
function lenLike( previous: string | undefined, pt: number, total: number ): string {
	const round = ( n: number ) => Math.round( n * 100 ) / 100;
	if ( previous && /%\s*$/.test( previous ) && ! /calc/i.test( previous ) ) {
		return `${ round( ( pt / total ) * 100 ) }%`;
	}
	return `${ round( pt ) }pt`;
}

function axisFrom( axis: Axis, pos: number, size: number, origin: number, total: number ): Axis {
	const offset = pos - origin;
	switch ( axis.mode ) {
		case 'end':
			return { ...axis, end: lenLike( axis.end, total - offset - size, total ), size: lenLike( axis.size, size, total ) };
		case 'center':
			return { ...axis, start: lenLike( axis.start, offset - ( total - size ) / 2, total ), size: lenLike( axis.size, size, total ) };
		case 'stretch':
			return { ...axis, start: lenLike( axis.start, offset, total ), end: lenLike( axis.end, total - offset - size, total ) };
		default:
			return { ...axis, start: lenLike( axis.start, offset, total ), size: lenLike( axis.size, size, total ) };
	}
}

/** The anchors that put an element at a box (after dragging or resizing on the canvas). */
export function layoutFrom( layout: Layout, b: Box, page: PageBox ): Layout {
	const r = refBox( page, layout.ref );
	return { ...layout, h: axisFrom( layout.h, b.x, b.w, r.x, r.w ), v: axisFrom( layout.v, b.y, b.h, r.y, r.h ) };
}

/** An element placed at a box: absolute elements take the numbers, anchored ones update their anchors. */
export function placed< T extends Placeable >( stored: T, b: Box, page: PageBox ): T {
	const layout = ( stored as { layout?: Layout } ).layout;
	if ( stored.type === 'line' ) {
		return stored;
	}
	return layout ? { ...stored, layout: layoutFrom( layout, b, page ) } : { ...stored, x: b.x, y: b.y, w: b.w, h: b.h };
}


/**
 * A rectangle's outline as an SVG path, with each corner drawn in a shape like
 * CSS corner-shape (round, scoop, bevel, notch, squircle). A path, so the PDF
 * draws exactly what the editor shows. Radii: [top-left, top-right,
 * bottom-right, bottom-left], clamped to half the shorter side.
 */
export function rectPath( x: number, y: number, w: number, h: number, radius: number[] = [], shape: CornerShape = 'round' ): string {
	const r = [ 0, 1, 2, 3 ].map( ( i ) => Math.max( 0, Math.min( radius[ i ] || 0, w / 2, h / 2 ) ) );
	const f = ( n: number ) => Math.round( n * 100 ) / 100;
	// Corner points and the directions along the edges into and out of each corner.
	const corners = [
		{ c: [ x, y ], in: [ 0, 1 ], out: [ 1, 0 ] }, // top-left: coming up the left edge, leaving right.
		{ c: [ x + w, y ], in: [ -1, 0 ], out: [ 0, 1 ] },
		{ c: [ x + w, y + h ], in: [ 0, -1 ], out: [ -1, 0 ] },
		{ c: [ x, y + h ], in: [ 1, 0 ], out: [ 0, -1 ] },
	];
	let d = '';
	corners.forEach( ( { c, in: din, out }, i ) => {
		const rr = r[ i ];
		const start = [ c[ 0 ] + din[ 0 ] * rr, c[ 1 ] + din[ 1 ] * rr ];
		const end = [ c[ 0 ] + out[ 0 ] * rr, c[ 1 ] + out[ 1 ] * rr ];
		d += ( i === 0 ? 'M' : 'L' ) + `${ f( start[ 0 ] ) } ${ f( start[ 1 ] ) }`;
		if ( ! rr ) {
			return;
		}
		switch ( shape ) {
			case 'scoop':
				d += `A${ f( rr ) } ${ f( rr ) } 0 0 0 ${ f( end[ 0 ] ) } ${ f( end[ 1 ] ) }`;
				break;
			case 'bevel':
				d += `L${ f( end[ 0 ] ) } ${ f( end[ 1 ] ) }`;
				break;
			case 'notch': {
				const inner = [ start[ 0 ] + end[ 0 ] - c[ 0 ], start[ 1 ] + end[ 1 ] - c[ 1 ] ];
				d += `L${ f( inner[ 0 ] ) } ${ f( inner[ 1 ] ) }L${ f( end[ 0 ] ) } ${ f( end[ 1 ] ) }`;
				break;
			}
			case 'squircle': {
				const k = 0.18;
				const c1 = [ c[ 0 ] + ( start[ 0 ] - c[ 0 ] ) * k, c[ 1 ] + ( start[ 1 ] - c[ 1 ] ) * k ];
				const c2 = [ c[ 0 ] + ( end[ 0 ] - c[ 0 ] ) * k, c[ 1 ] + ( end[ 1 ] - c[ 1 ] ) * k ];
				d += `C${ f( c1[ 0 ] ) } ${ f( c1[ 1 ] ) } ${ f( c2[ 0 ] ) } ${ f( c2[ 1 ] ) } ${ f( end[ 0 ] ) } ${ f( end[ 1 ] ) }`;
				break;
			}
			default:
				d += `A${ f( rr ) } ${ f( rr ) } 0 0 1 ${ f( end[ 0 ] ) } ${ f( end[ 1 ] ) }`;
		}
	} );
	return d + 'Z';
}

/**
 * SVG is kept as fields, like the wp_kses() lists themes use for their icons
 * but stricter: a viewBox size and paths with path data, a fill and a fill
 * rule. Pasted code is read into those fields; groups are unwrapped (their
 * fill goes to their paths); strokes, other shapes, styles, scripts, links
 * and embedded content are dropped. The server checks the fields again.
 */

/** What an SVG may be: the numbers of includes/signer.php (SVG_MAX_*), kept in step with it. */
export const SVG_LIMITS = { bytes: 524288, paths: 50, pathData: 20000, viewBox: 5000 };

/**
 * Whether SVG code may even be read: not big, and with no DTD or entity (expansion bombs), style sheet instruction, script or
 * event handler. None of these is ever read (only paths are), a file that carries them is not taken for a signature at all.
 */
export function svgCodeAllowed( code: string ): boolean {
	return code.length <= SVG_LIMITS.bytes && ! /<!(DOCTYPE|ENTITY)|<\?xml-stylesheet|<\s*script|\son[a-z]+\s*=/i.test( code );
}

/** Path data: only path commands and numbers, starting with a move. */
export function isPathData( d: string ): boolean {
	return /^[MmLlHhVvCcSsQqTtAaZz0-9eE.,\s+-]*$/.test( d ) && /^\s*[Mm]/.test( d );
}

/** A fill: a hex/rgb color, none, currentColor or a $variable. */
export function isFill( value: string ): boolean {
	return /^(none|currentcolor|#[0-9a-f]{3,8}|rgba?\([\d\s.,%]+\)|\$[a-z0-9_-]+)$/i.test( value.trim() );
}

/** Reads SVG code into fields; returns the viewBox size and the paths, or null. */
export function sanitizeSvg( code: string ): { vw: number; vh: number; w?: number; h?: number; paths: SvgPath[] } | null {
	const text = code.trim();
	if ( ! svgCodeAllowed( text ) ) {
		return null;
	}
	// A DOMParser document is inert: no script runs, nothing loads, it is never attached to the page. Only paths are read from it.
	const doc = new DOMParser().parseFromString( /^<svg[\s>]/i.test( text ) ? text : `<svg xmlns="http://www.w3.org/2000/svg">${ text }</svg>`, 'image/svg+xml' );
	const root = doc.documentElement;
	if ( ! root || root.nodeName.toLowerCase() !== 'svg' || doc.querySelector( 'parsererror' ) ) {
		return null;
	}
	const inherited = ( node: globalThis.Element, name: string ): string | null => {
		for ( let n: globalThis.Element | null = node; n; n = n.parentElement ) {
			if ( n.getAttribute( name ) ) {
				return n.getAttribute( name );
			}
		}
		return null;
	};
	const paths: SvgPath[] = [];
	for ( const node of Array.from( root.getElementsByTagName( 'path' ) ) ) {
		if ( paths.length >= SVG_LIMITS.paths ) {
			break;
		}
		const d = ( node.getAttribute( 'd' ) || '' ).trim().replace( /\s+/g, ' ' );
		if ( ! d || d.length > SVG_LIMITS.pathData || ! isPathData( d ) ) {
			continue;
		}
		const fill = inherited( node, 'fill' );
		const rule = inherited( node, 'fill-rule' );
		paths.push( { d, ...( fill && isFill( fill ) && fill.toLowerCase() !== 'currentcolor' ? { fill } : {} ), ...( rule === 'evenodd' ? { rule: 'evenodd' as const } : {} ) } );
	}
	if ( ! paths.length ) {
		return null;
	}
	const box = ( root.getAttribute( 'viewBox' ) || '' ).split( /[\s,]+/ ).map( Number );
	const vw = box.length === 4 && box[ 2 ] > 0 ? box[ 2 ] : Number( root.getAttribute( 'width' ) ) || 100;
	const vh = box.length === 4 && box[ 3 ] > 0 ? box[ 3 ] : Number( root.getAttribute( 'height' ) ) || 100;
	// A drawing of any size is not a signature: the server refuses it too.
	if ( ! ( vw > 0 && vh > 0 && vw <= SVG_LIMITS.viewBox && vh <= SVG_LIMITS.viewBox ) ) {
		return null;
	}
	// The size the code says it is shown at (its width and height, in pixels or with no unit) is kept when it is not the viewBox itself.
	const size = ( name: string ) => {
		const given = /^\s*([\d.]+)(px)?\s*$/.exec( root.getAttribute( name ) || '' );
		return given ? Number( given[ 1 ] ) : 0;
	};
	const w = size( 'width' );
	const h = size( 'height' );
	const shown = w > 0 && h > 0 && w <= SVG_LIMITS.viewBox && h <= SVG_LIMITS.viewBox && ( w !== vw || h !== vh );
	return { vw, vh, ...( shown ? { w, h } : {} ), paths };
}

