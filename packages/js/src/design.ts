/**
 * A design (page, colors, palette, elements, back…) as blocks, and back. The same as the PHP package (Camaleaun\Draw\Design):
 * the root is `events-certificates/certificate`, the declaration sheet `events-certificates/certificate-back`, each element a block of
 * its type, each path of an SVG a `draw/svg-path` inner block. Every key of an element is an attribute of its block.
 *
 * That is the default, the native way of the blocks. A second argument (`Options`) changes it, the same for both directions: the block
 * of a type (`blocks`, over the defaults), the block that keeps the types that are not known (`fallback`), and whether the paths of an
 * SVG are inner blocks (`inner`, the default) or the `paths` attribute of one block (`attribute`).
 */
import { parse, serialize, type Block } from './markup';

export type Attrs = Record< string, unknown >;
export type Design = Attrs & { elements?: Attrs[]; back?: Attrs & { elements?: Attrs[] } };

export const ROOT = 'events-certificates/certificate';
export const BACK = 'events-certificates/certificate-back';
export const PATH = 'draw/svg-path';
export const FALLBACK = 'draw/element';

export const BLOCKS: Record< string, string > = {
	text: 'draw/text',
	rect: 'draw/rect',
	ellipse: 'draw/ellipse',
	path: 'draw/path',
	line: 'draw/line',
	image: 'draw/image',
	svg: 'draw/svg',
	qr: 'events-certificates/certificate-qr',
	signature: 'events-certificates/certificate-signature',
	verification: 'events-certificates/certificate-verification',
};

export type Options = { blocks?: Record< string, string >; fallback?: string; paths?: 'inner' | 'attribute' };
type Resolved = { blocks: Record< string, string >; fallback: string; paths: 'inner' | 'attribute' };

const resolve = ( options: Options = {} ): Resolved => ( {
	blocks: { ...BLOCKS, ...( options.blocks || {} ) },
	fallback: options.fallback || FALLBACK,
	paths: options.paths === 'attribute' ? 'attribute' : 'inner',
} );

const isObject = ( value: unknown ): value is Attrs => !! value && typeof value === 'object' && ! Array.isArray( value );

function blocks( elements: unknown, o: Resolved ): Block[] {
	const out: Block[] = [];
	for ( const element of Array.isArray( elements ) ? elements : [] ) {
		if ( ! isObject( element ) ) {
			continue;
		}
		const { type, ...attrs } = element;
		const kind = String( type ?? '' );
		let children: Block[] = [];
		if ( kind === 'svg' && o.paths === 'inner' && Array.isArray( element.paths ) ) {
			delete attrs.paths;
			children = element.paths.map( ( path ) => ( { name: PATH, attrs: isObject( path ) ? path : {}, children: [] } ) );
		}
		if ( ! o.blocks[ kind ] ) {
			// A type this version does not know keeps its place as a generic block.
			out.push( { name: o.fallback, attrs: { type: kind, ...attrs }, children: [] } );
			continue;
		}
		out.push( { name: o.blocks[ kind ], attrs, children } );
	}
	return out;
}

function element( block: Block, o: Resolved ): Attrs {
	if ( block.name === o.fallback ) {
		return block.attrs;
	}
	const type = Object.keys( o.blocks ).find( ( key ) => o.blocks[ key ] === block.name ) ?? '';
	const out: Attrs = { type, ...block.attrs };
	if ( type === 'svg' && o.paths === 'inner' && out.markup === undefined ) {
		out.paths = block.children.filter( ( child ) => child.name === PATH ).map( ( child ) => child.attrs );
	}
	return out;
}

/** A design as block markup. */
export function toMarkup( design: Design, options?: Options ): string {
	const o = resolve( options );
	const { elements, back, ...root } = design;
	const children = blocks( elements, o );
	if ( isObject( back ) ) {
		const { elements: backElements, ...rest } = back;
		children.push( { name: BACK, attrs: rest, children: blocks( backElements, o ) } );
	}
	return serialize( [ { name: ROOT, attrs: root, children } ] );
}

/** A design from block markup; null when it is not a certificate. */
export function fromMarkup( markup: string, options?: Options ): Design | null {
	const o = resolve( options );
	const parsed = parse( markup );
	if ( ! parsed || parsed.length !== 1 || parsed[ 0 ].name !== ROOT ) {
		return null;
	}
	const design: Design = { ...parsed[ 0 ].attrs };
	const elements: Attrs[] = [];
	for ( const child of parsed[ 0 ].children ) {
		if ( child.name === BACK ) {
			design.back = { ...child.attrs, elements: child.children.map( ( c ) => element( c, o ) ) };
			continue;
		}
		elements.push( element( child, o ) );
	}
	design.elements = elements;
	return design;
}

/** Whether a text is block markup of a certificate (and not the JSON an earlier version saved). */
export const isMarkup = ( text: unknown ): boolean => typeof text === 'string' && new RegExp( `^\\s*<!--\\s+wp:${ ROOT.replace( '/', '\\/' ) }[\\s>/]` ).test( text );
