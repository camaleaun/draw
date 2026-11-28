/**
 * The pure geometry and checks of a drawing, with no editor and no WordPress: lengths with units, anchors (layout) and the boxes they
 * resolve to, the path of a rectangle with its corner shapes, and what SVG code may be read into paths. Shared by every editor of a
 * drawing (the certificate editor and the Draw plugin).
 */

export type Box = { x: number; y: number; w: number; h: number };

/** What the anchors need of the page: its size and its safe margin, in points. */
export type PageBox = { w: number; h: number; safe: number };

/** What the anchors need of an element: its kind, its place and its anchors. A line has no place of its own. */
export type Placeable = { type: string; x?: number; y?: number; w?: number; h?: number; vw?: number; vh?: number; layout?: Layout; lock?: number; ratio?: string };

export type CornerShape = 'round' | 'scoop' | 'bevel' | 'notch' | 'squircle';
export type SvgPath = { d: string; fill?: string; rule?: 'nonzero' | 'evenodd' };

export type AxisMode = 'start' | 'center' | 'end' | 'stretch';
export type Axis = { mode: AxisMode; start?: string; end?: string; size?: string };
/** The box an element is anchored to: the page, its safe margin, or (inside a group) the group's own box, whose corner is 0,0. */
export type Layout = { h: Axis; v: Axis; ref?: 'page' | 'safe' | 'parent' };

export const UNITS: Record< string, number > = { pt: 1, px: 1, mm: 2.83465, cm: 28.3465, in: 72 };

/**
 * The width and height of a page as lengths in the unit that makes both round: inches, millimeters or, when neither does, points. An A4 is
 * "297mm" by "210mm" (in points it would be 841.89 by 595.28), a letter "11in" by "8.5in". Of the two units that are exact, the one with the fewest
 * decimals wins, and a tie is in; points are for a page that is round in neither.
 * The same as Page::size() in PHP.
 */
export function pageSize( w: number, h: number ): [ string, string ] {
	const round = ( n: number, d: number ) => Math.round( n * 10 ** d ) / 10 ** d;
	const text = ( n: number ) => String( round( n, 2 ) );
	let best: { digits: number; parts: [ string, string ] } | null = null;
	for ( const unit of [ 'in', 'mm' ] ) {
		const factor = UNITS[ unit ];
		const parts: string[] = [];
		let digits = 0;
		let ok = true;
		for ( const points of [ w, h ] ) {
			let found: [ number, number ] | null = null;
			for ( let d = 0; d <= 2; d++ ) {
				const value = round( points / factor, d );
				if ( Math.abs( value * factor - points ) < 0.006 ) {
					found = [ d, value ];
					break;
				}
			}
			if ( ! found ) {
				ok = false;
				break;
			}
			digits = Math.max( digits, found[ 0 ] );
			parts.push( text( found[ 1 ] ) + unit );
		}
		if ( ok && ( ! best || digits < best.digits ) ) {
			best = { digits, parts: parts as [ string, string ] };
		}
	}
	return best ? best.parts : [ `${ text( w ) }pt`, `${ text( h ) }pt` ];
}

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

/**
 * The box an element is anchored to. Inside a group `parent` is the group's box with its corner at 0,0 (what is in a group is placed from the group's
 * top-left corner); with no parent (an element that is not in a group) it is the page.
 */
export function refBox( page: PageBox, ref?: 'page' | 'safe' | 'parent', parent?: Box ): Box {
	if ( ref === 'parent' && parent ) {
		return { x: 0, y: 0, w: parent.w, h: parent.h };
	}
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

/** An element with its anchors resolved to x, y, w, h in pt (elements without anchors are returned as they are); `parent` is the box of the group it is in. */
export function resolve< T extends Placeable >( el: T, page: PageBox, parent?: Box ): T {
	const layout = ( el as { layout?: Layout } ).layout;
	if ( ! layout || el.type === 'line' ) {
		return el;
	}
	const r = refBox( page, layout.ref, parent );
	// A drawing (svg) that says no size on an axis is as big as its viewBox (a unit of it is a point): the size is not written, only the anchors.
	const own = ( axis: Axis, size?: number ) => ( el.type === 'svg' && axis.mode !== 'stretch' && axis.size === undefined && size ? size : undefined );
	let [ x, w ] = resolveAxis( layout.h, r.x, r.w, own( layout.h, el.vw ) );
	let [ y, h ] = resolveAxis( layout.v, r.y, r.h, own( layout.v, el.vh ) );
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

/**
 * Writes a length back, in the unit it was written in (pt, px, mm, cm, in or %; a calc() or what has no unit becomes pt), to two decimals in that unit
 * (three in inches). px is the point (UNITS.px), so a value in px is written in px and means the same.
 */
function lenLike( previous: string | undefined, pt: number, total: number ): string {
	const unit = previous && ! /calc/i.test( previous ) ? /(pt|px|mm|cm|in|%)\s*$/i.exec( previous )?.[ 1 ].toLowerCase() : undefined;
	const round = ( n: number, digits = 2 ) => Math.round( n * 10 ** digits ) / 10 ** digits;
	if ( unit === '%' ) {
		return `${ round( ( pt / total ) * 100 ) }%`;
	}
	if ( unit && unit !== 'pt' && UNITS[ unit ] ) {
		return `${ round( pt / UNITS[ unit ], unit === 'in' ? 3 : 2 ) }${ unit }`;
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
export function layoutFrom( layout: Layout, b: Box, page: PageBox, parent?: Box ): Layout {
	const r = refBox( page, layout.ref, parent );
	return { ...layout, h: axisFrom( layout.h, b.x, b.w, r.x, r.w ), v: axisFrom( layout.v, b.y, b.h, r.y, r.h ) };
}

/** An element placed at a box: absolute elements take the numbers, anchored ones update their anchors. */
export function placed< T extends Placeable >( stored: T, b: Box, page: PageBox, parent?: Box ): T {
	const layout = ( stored as { layout?: Layout } ).layout;
	if ( stored.type === 'line' ) {
		return stored;
	}
	if ( ! layout ) {
		return { ...stored, x: b.x, y: b.y, w: b.w, h: b.h };
	}
	const next = layoutFrom( layout, b, page, parent );
	// A drawing that says no size on an axis keeps it so when it is only moved: the size it has is still its viewBox's.
	if ( stored.type === 'svg' ) {
		( [ [ 'h', 'w', stored.vw ], [ 'v', 'h', stored.vh ] ] as const ).forEach( ( [ axis, side, own ] ) => {
			if ( layout[ axis ].size === undefined && layout[ axis ].mode !== 'stretch' && own && Math.abs( b[ side ] - own ) < 0.005 ) {
				delete next[ axis ].size;
			}
		} );
	}
	return { ...stored, layout: next };
}


/**
 * A corner radius in points: a number is points, "25%" is that share of the shorter side (0 to 50%: 50% makes a pill or a circle). Anything else is 0.
 * The same as Svg::radius() in PHP.
 */
export function radiusPt( value: number | string | undefined, w: number, h: number ): number {
	if ( typeof value === 'string' ) {
		const m = /^\s*(-?\d*\.?\d+)\s*%\s*$/.exec( value );
		return m ? ( Math.max( 0, Math.min( Number( m[ 1 ] ), 50 ) ) / 100 ) * Math.min( w, h ) : 0;
	}
	return typeof value === 'number' && Number.isFinite( value ) ? value : 0;
}

/**
 * A rectangle's outline as an SVG path, with each corner drawn in a shape like
 * CSS corner-shape (round, scoop, bevel, notch, squircle). A path, so the PDF
 * draws exactly what the editor shows. Radii: [top-left, top-right,
 * bottom-right, bottom-left], clamped to half the shorter side.
 */
export function rectPath( x: number, y: number, w: number, h: number, radius: Array< number | string > = [], shape: CornerShape = 'round' ): string {
	const r = [ 0, 1, 2, 3 ].map( ( i ) => Math.max( 0, Math.min( radiusPt( radius[ i ], w, h ), w / 2, h / 2 ) ) );
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
export const SVG_LIMITS = { bytes: 524288, paths: 50, pathData: 100000, viewBox: 5000 };

/**
 * The most characters of a path's data in a picture (a signature, a community logo): what the server keeps for those (`Paths::MAX_DATA`). An SVG element of a
 * template takes `SVG_LIMITS.pathData` (`Clean::SVG_PATH_DATA`): a detailed logo is a path of tens of thousands of characters.
 */
export const PICTURE_PATH_DATA = 20000;

/** What to do with the data of a path: nothing (it has none), leave it out (too long, or not made of path commands and numbers) or keep it. */
export function pathVerdict( d: string, limit: number = SVG_LIMITS.pathData ): 'empty' | 'too-long' | 'not-data' | 'ok' {
	if ( ! d ) {
		return 'empty';
	}
	return d.length > limit ? 'too-long' : isPathData( d ) ? 'ok' : 'not-data';
}

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

/**
 * Reads SVG code into fields; returns the viewBox size and the paths, or null. A path that is too long (`options.pathData`, `SVG_LIMITS.pathData` by default), that is
 * not path data, or that is past the 50th, is left out: `skipped` says how many, so a drawing that lost some does not look whole.
 */
export function sanitizeSvg( code: string, options: { pathData?: number } = {} ): { vw: number; vh: number; w?: number; h?: number; paths: SvgPath[]; skipped?: number } | null {
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
	let skipped = 0;
	for ( const node of Array.from( root.getElementsByTagName( 'path' ) ) ) {
		const d = ( node.getAttribute( 'd' ) || '' ).trim().replace( /\s+/g, ' ' );
		const verdict = pathVerdict( d, options.pathData ?? SVG_LIMITS.pathData );
		if ( verdict === 'empty' ) {
			continue;
		}
		if ( verdict !== 'ok' || paths.length >= SVG_LIMITS.paths ) {
			skipped++;
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
	return { vw, vh, ...( shown ? { w, h } : {} ), paths, ...( skipped ? { skipped } : {} ) };
}


/** A guide the canvas draws while something moves: an alignment line, or the gap of an equal spacing. */
export type Guide =
	| { axis: 'x' | 'y'; at: number; kind?: 'align' }
	| { axis: 'x' | 'y'; kind: 'gap'; from: number; to: number; cross: number };

/**
 * Snaps a moving box to the page (its edges, safe margin and centre) and to the other boxes (edges, centres and equal spacing).
 *
 * @param moving    The box being moved.
 * @param page      The page.
 * @param others    The boxes it may snap to.
 * @param threshold How near it must be, in points.
 * @return The offset to apply and the guides to draw.
 */
export function snap( moving: Box, page: PageBox, others: Box[], threshold: number ): { dx: number; dy: number; guides: Guide[] } {
	const { w, h, safe } = page;
	const xs = [ 0, safe, w / 2, w - safe, w ];
	const ys = [ 0, safe, h / 2, h - safe, h ];
	for ( const b of others ) {
		xs.push( b.x, b.x + b.w / 2, b.x + b.w );
		ys.push( b.y, b.y + b.h / 2, b.y + b.h );
	}
	const best = ( targets: number[], edges: number[] ) => {
		let found: { delta: number; at: number } | null = null;
		for ( const edge of edges ) {
			for ( const t of targets ) {
				const delta = t - edge;
				if ( Math.abs( delta ) <= threshold && ( ! found || Math.abs( delta ) < Math.abs( found.delta ) ) ) {
					found = { delta, at: t };
				}
			}
		}
		return found;
	};

	// Equal spacing along one axis, against neighbours that overlap the moving box on the other axis: centred between the nearest one on
	// each side, or repeating the gap between the nearest neighbour and the one past it.
	const spacing = ( axis: 'x' | 'y' ) => {
		const pos = axis === 'x' ? 'x' : 'y';
		const size = axis === 'x' ? 'w' : 'h';
		const cpos = axis === 'x' ? 'y' : 'x';
		const csize = axis === 'x' ? 'h' : 'w';
		const overlaps = ( b: Box ) => b[ cpos ] < moving[ cpos ] + moving[ csize ] && b[ cpos ] + b[ csize ] > moving[ cpos ];
		const row = others.filter( overlaps );
		const before = row.filter( ( b ) => b[ pos ] + b[ size ] <= moving[ pos ] + threshold ).sort( ( a, b ) => b[ pos ] + b[ size ] - ( a[ pos ] + a[ size ] ) );
		const after = row.filter( ( b ) => b[ pos ] >= moving[ pos ] + moving[ size ] - threshold ).sort( ( a, b ) => a[ pos ] - b[ pos ] );
		const cross = ( b: Box ) => ( Math.max( b[ cpos ], moving[ cpos ] ) + Math.min( b[ cpos ] + b[ csize ], moving[ cpos ] + moving[ csize ] ) ) / 2;
		const candidates: { delta: number; gaps: Guide[] }[] = [];
		const L = before[ 0 ];
		const R = after[ 0 ];
		if ( L && R ) {
			const target = ( L[ pos ] + L[ size ] + R[ pos ] - moving[ size ] ) / 2;
			candidates.push( {
				delta: target - moving[ pos ],
				gaps: [
					{ axis, kind: 'gap', from: L[ pos ] + L[ size ], to: target, cross: cross( L ) },
					{ axis, kind: 'gap', from: target + moving[ size ], to: R[ pos ], cross: cross( R ) },
				],
			} );
		}
		if ( L && before[ 1 ] ) {
			const gap = L[ pos ] - ( before[ 1 ][ pos ] + before[ 1 ][ size ] );
			const target = L[ pos ] + L[ size ] + gap;
			candidates.push( {
				delta: target - moving[ pos ],
				gaps: [
					{ axis, kind: 'gap', from: before[ 1 ][ pos ] + before[ 1 ][ size ], to: L[ pos ], cross: cross( before[ 1 ] ) },
					{ axis, kind: 'gap', from: L[ pos ] + L[ size ], to: target, cross: cross( L ) },
				],
			} );
		}
		if ( R && after[ 1 ] ) {
			const gap = after[ 1 ][ pos ] - ( R[ pos ] + R[ size ] );
			const target = R[ pos ] - gap - moving[ size ];
			candidates.push( {
				delta: target - moving[ pos ],
				gaps: [
					{ axis, kind: 'gap', from: target + moving[ size ], to: R[ pos ], cross: cross( R ) },
					{ axis, kind: 'gap', from: R[ pos ] + R[ size ], to: after[ 1 ][ pos ], cross: cross( after[ 1 ] ) },
				],
			} );
		}
		return candidates.filter( ( c ) => Math.abs( c.delta ) <= threshold && c.gaps.every( ( g ) => 'to' in g && g.to - g.from >= 2 ) ).sort( ( a, b ) => Math.abs( a.delta ) - Math.abs( b.delta ) )[ 0 ] || null;
	};

	const fx = best( xs, [ moving.x, moving.x + moving.w / 2, moving.x + moving.w ] );
	const fy = best( ys, [ moving.y, moving.y + moving.h / 2, moving.y + moving.h ] );
	const sx = spacing( 'x' );
	const sy = spacing( 'y' );
	const guides: Guide[] = [];
	let dx = 0;
	let dy = 0;
	if ( sx && ( ! fx || Math.abs( sx.delta ) <= Math.abs( fx.delta ) ) ) {
		dx = sx.delta;
		guides.push( ...sx.gaps );
	} else if ( fx ) {
		dx = fx.delta;
		guides.push( { axis: 'x', at: fx.at } );
	}
	if ( sy && ( ! fy || Math.abs( sy.delta ) <= Math.abs( fy.delta ) ) ) {
		dy = sy.delta;
		guides.push( ...sy.gaps );
	} else if ( fy ) {
		dy = fy.delta;
		guides.push( { axis: 'y', at: fy.at } );
	}
	return { dx, dy, guides };
}

/**
 * A box resized by a handle (n, ne, e, se, s, sw, w, nw), like vector apps: `keepRatio` (Shift) keeps the proportions and `fromCenter`
 * (Alt/Option) resizes from the centre, the opposite side moving too. Never smaller than 4 points.
 */
export function resizeBox( origin: Box, handle: string, dx: number, dy: number, keepRatio: boolean, fromCenter = false ): Box {
	const k = fromCenter ? 2 : 1;
	let { x, y, w, h } = origin;
	if ( handle.includes( 'e' ) ) {
		w = Math.max( 4, w + dx * k );
	}
	if ( handle.includes( 's' ) ) {
		h = Math.max( 4, h + dy * k );
	}
	if ( handle.includes( 'w' ) ) {
		const nw = Math.max( 4, w - dx * k );
		x += w - nw;
		w = nw;
	}
	if ( handle.includes( 'n' ) ) {
		const nh = Math.max( 4, h - dy * k );
		y += h - nh;
		h = nh;
	}
	if ( keepRatio && origin.w && origin.h ) {
		const ratio = origin.w / origin.h;
		if ( handle === 'n' || handle === 's' ) {
			w = h * ratio;
		} else {
			h = w / ratio;
		}
		// The side opposite the handle stays put (or the centre, below).
		if ( handle.includes( 'n' ) ) {
			y = origin.y + origin.h - h;
		}
		if ( handle.includes( 'w' ) ) {
			x = origin.x + origin.w - w;
		}
		if ( handle === 'n' || handle === 's' ) {
			x = origin.x + ( origin.w - w ) / 2;
		} else if ( handle === 'e' || handle === 'w' ) {
			y = origin.y + ( origin.h - h ) / 2;
		}
	}
	if ( fromCenter ) {
		x = origin.x + ( origin.w - w ) / 2;
		y = origin.y + ( origin.h - h ) / 2;
	}
	return { x, y, w, h };
}

/** How a group places what is in it: free (each element by its own place and anchors), in a row, in a stack, or in a grid (as the core's Group variations). */
export type Arrange = 'free' | 'row' | 'stack' | 'grid';
export type FlowGroup = { w: number; h: number; arrange?: Arrange; gap?: number; padding?: number; justify?: 'start' | 'center' | 'end' | 'between'; align?: 'start' | 'center' | 'end' | 'stretch'; columns?: number };
export type FlowItem = { w: number; h: number; grow?: number };

/**
 * The boxes (x, y, w, h from the group's corner) of what is in a row, a stack or a grid.
 * Row and stack: one after the other along the main axis (the width of a row, the height of a stack) inside the padding, with `gap` between them. `justify`
 * spreads them along it (start, center, end, or between, with the room that is left between them); an element with `grow` shares the room that is left with the others
 * that grow, and then there is nothing to justify. `align` puts them on the cross axis (start, center, end, or stretch to the whole).
 * Grid: `columns` columns of the same width (the width inside the padding, less the gaps, shared), each element in the next cell, as wide as its cell and as high as
 * itself; a row of the grid is as high as its highest element. The group keeps its size.
 */
export function flowBoxes( group: FlowGroup, items: FlowItem[] ): Box[] {
	const pad = Math.max( 0, group.padding || 0 );
	const gap = Math.max( 0, group.gap || 0 );
	if ( group.arrange === 'grid' ) {
		const columns = Math.max( 1, Math.floor( group.columns || 2 ) );
		const cell = Math.max( 0, ( group.w - pad * 2 - gap * ( columns - 1 ) ) / columns );
		const out: Box[] = [];
		let y = pad;
		for ( let start = 0; start < items.length; start += columns ) {
			const row = items.slice( start, start + columns );
			const high = Math.max( 0, ...row.map( ( it ) => it.h ) );
			row.forEach( ( it, i ) => out.push( { x: pad + i * ( cell + gap ), y, w: cell, h: group.align === 'stretch' ? high : it.h } ) );
			y += high + gap;
		}
		return out;
	}
	const row = group.arrange !== 'stack';
	const main = Math.max( 0, ( row ? group.w : group.h ) - pad * 2 );
	const cross = Math.max( 0, ( row ? group.h : group.w ) - pad * 2 );
	const along = ( it: FlowItem ) => ( row ? it.w : it.h );
	const across = ( it: FlowItem ) => ( row ? it.h : it.w );
	const sum = items.reduce( ( n, it ) => n + along( it ), 0 ) + gap * Math.max( 0, items.length - 1 );
	const left = main - sum;
	const grows = items.reduce( ( n, it ) => n + Math.max( 0, it.grow || 0 ), 0 );
	const room = left > 0 && grows > 0 ? left : 0;
	const free = grows > 0 && left > 0 ? 0 : Math.max( 0, left );
	let at = group.justify === 'center' ? free / 2 : group.justify === 'end' ? free : 0;
	const between = group.justify === 'between' && items.length > 1 ? free / ( items.length - 1 ) : 0;
	return items.map( ( it ) => {
		const length = along( it ) + ( room ? ( room * Math.max( 0, it.grow || 0 ) ) / grows : 0 );
		const thick = group.align === 'stretch' ? cross : across( it );
		const place = group.align === 'center' ? ( cross - thick ) / 2 : group.align === 'end' ? cross - thick : 0;
		const m = pad + at;
		const c = pad + place;
		at += length + gap + between;
		return row ? { x: m, y: c, w: length, h: thick } : { x: c, y: m, w: thick, h: length };
	} );
}

/**
 * What is in a group with its places resolved (x, y, w, h from the group's corner): in a row or a stack the flow places them (hidden ones take no room), in a
 * free group each by its own place or anchors, to the size of the group.
 */
export function placeChildren< T extends Placeable & { hidden?: boolean; grow?: number } >( group: FlowGroup, children: T[] ): T[] {
	if ( group.arrange === 'row' || group.arrange === 'stack' || group.arrange === 'grid' ) {
		const shown = children.filter( ( el ) => ! el.hidden );
		const boxes = flowBoxes( group, shown.map( ( el ) => ( { w: el.w || 0, h: el.h || 0, grow: el.grow } ) ) );
		return children.map( ( el ) => {
			const at = shown.indexOf( el );
			return at < 0 ? el : { ...el, ...boxes[ at ] };
		} );
	}
	const inner = { w: group.w, h: group.h, safe: 0 };
	return children.map( ( el ) => resolve( el, inner, { x: 0, y: 0, w: group.w, h: group.h } ) );
}

