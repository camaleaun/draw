/**
 * The shapes of a drawing as SVG for the canvas, from the attributes of their blocks (the same model the PHP renderer draws).
 */
import { createElement } from '@wordpress/element';
import { isPathData, rectPath } from '@camaleaun/draw';

export type Attrs = Record< string, any >;
export type ShapeBlock = { clientId: string; name: string; attributes: Attrs; innerBlocks?: ShapeBlock[] };

/** A fill or stroke: a variable of the frame (`$primary`), a hex/rgb/hsl color, or none. */
export function paint( value: unknown, colors: Record< string, string >, other = 'none' ): string {
	const v = typeof value === 'string' ? value.trim() : '';
	if ( ! v ) {
		return other;
	}
	if ( v[ 0 ] === '$' ) {
		return colors[ v.slice( 1 ) ] ? paint( colors[ v.slice( 1 ) ], {}, other ) : other;
	}
	return /^(#[0-9a-f]{3,8}|none|transparent|(rgb|hsl)a?\([0-9.,%\s/deg-]+\))$/i.test( v ) ? v : other;
}

/** The box of a shape (a line has its end points, not a box). */
export function boxOf( a: Attrs ) {
	return { x: Number( a.x ) || 0, y: Number( a.y ) || 0, w: Number( a.width ) || 0, h: Number( a.height ) || 0 };
}

/** One shape, drawn. Nothing that is not a color or a path reaches the SVG. */
export function Shape( { block, colors }: { block: ShapeBlock; colors: Record< string, string > } ) {
	const a = block.attributes;
	const { x, y, w, h } = boxOf( a );
	const stroke = paint( a.stroke, colors );
	const common = { fill: paint( a.fill, colors ), stroke: stroke === 'none' || ! a.strokeWidth ? undefined : stroke, strokeWidth: a.strokeWidth || undefined, opacity: a.opacity };
	let inside: any = null;
	switch ( block.name ) {
		case 'draw/rect':
			inside = <path d={ rectPath( x, y, w, h, Array.isArray( a.radius ) ? a.radius : [], a.corner ) } { ...common } />;
			break;
		case 'draw/ellipse':
			inside = <ellipse cx={ x + w / 2 } cy={ y + h / 2 } rx={ w / 2 } ry={ h / 2 } { ...common } />;
			break;
		case 'draw/line':
			inside = <line x1={ a.x1 || 0 } y1={ a.y1 || 0 } x2={ a.x2 || 0 } y2={ a.y2 || 0 } stroke={ stroke === 'none' ? 'currentColor' : stroke } strokeWidth={ a.strokeWidth || 1 } />;
			break;
		case 'draw/path':
			inside = isPathData( a.d || '' ) ? (
				<svg x={ x } y={ y } width={ w } height={ h } viewBox={ String( a.viewBox || '0 0 100 100' ) } overflow="hidden">
					<path d={ a.d } { ...common } />
				</svg>
			) : null;
			break;
		case 'draw/svg':
			inside = (
				<svg x={ x } y={ y } width={ w } height={ h } viewBox={ String( a.viewBox || '0 0 24 24' ) } overflow="hidden">
					{ ( block.innerBlocks || [] )
						.filter( ( inner ) => inner.name === 'draw/svg-path' && isPathData( inner.attributes.d || '' ) )
						.map( ( inner ) => (
							<path key={ inner.clientId } d={ inner.attributes.d } fill={ paint( inner.attributes.fill, colors, paint( a.color, colors, 'currentColor' ) ) } fillRule={ inner.attributes.rule === 'evenodd' ? 'evenodd' : undefined } />
						) ) }
				</svg>
			);
			break;
		case 'draw/image':
			inside = /^https?:\/\//i.test( a.src || '' ) ? <image x={ x } y={ y } width={ w } height={ h } href={ a.src } preserveAspectRatio={ a.fit === 'cover' ? 'xMidYMid slice' : 'xMidYMid meet' } /> : <rect x={ x } y={ y } width={ w } height={ h } fill="none" stroke="#999" strokeDasharray="4 3" />;
			break;
		case 'draw/text': {
			const text = ( Array.isArray( a.runs ) ? a.runs.map( ( r: Attrs ) => r.text || '' ).join( '' ) : '' ) || a.text || '';
			const size = Math.max( 1, Number( a.size ) || 14 );
			const align = a.align === 'center' ? 'middle' : a.align === 'right' ? 'end' : 'start';
			const tx = a.align === 'center' ? x + w / 2 : a.align === 'right' ? x + w : x;
			inside = (
				<text x={ tx } y={ y + size } fontSize={ size } textAnchor={ align } fill={ paint( a.color, colors, 'currentColor' ) } fontWeight={ a.weight || undefined }>
					{ String( text ) }
				</text>
			);
			break;
		}
		default:
			return null;
	}
	return a.rotation ? <g transform={ `rotate(${ a.rotation } ${ x + w / 2 } ${ y + h / 2 })` }>{ inside }</g> : inside;
}
