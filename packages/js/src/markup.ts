/**
 * Block markup read and written without WordPress: `<!-- wp:name {"attr":1} /-->` for a block with no inner blocks and
 * `<!-- wp:name {…} -->…<!-- /wp:name -->` for one with. The same as the PHP package (Camaleaun\Draw\Markup).
 */
export type Block = { name: string; attrs: Record< string, unknown >; children: Block[] };

export const MAX_BYTES = 4194304;
export const MAX_DEPTH = 12;

const TOKEN = /<!--\s+(\/)?wp:([a-z][a-z0-9_-]*\/)?([a-z][a-z0-9_-]*)\s+(?:(\{(?:(?!\}\s+\/?-->)[\s\S])*\})\s+)?(\/)?-->/g;

/** Reads a block tree; null when the markup is too big, too deep, not balanced or an attribute is not valid JSON. */
export function parse( markup: string ): Block[] | null {
	if ( typeof markup !== 'string' || markup.length > MAX_BYTES ) {
		return null;
	}
	const root: Block = { name: '', attrs: {}, children: [] };
	const stack: Block[] = [ root ];
	for ( const token of markup.matchAll( TOKEN ) ) {
		const name = ( token[ 2 ] || 'core/' ) + token[ 3 ];
		if ( token[ 1 ] ) {
			const top = stack.pop();
			if ( ! top || stack.length < 1 || top.name !== name ) {
				return null;
			}
			continue;
		}
		let attrs: Record< string, unknown > = {};
		if ( token[ 4 ] ) {
			try {
				const decoded = JSON.parse( token[ 4 ] );
				if ( ! decoded || typeof decoded !== 'object' || Array.isArray( decoded ) ) {
					return null;
				}
				attrs = decoded;
			} catch ( e ) {
				return null;
			}
		}
		const block: Block = { name, attrs, children: [] };
		stack[ stack.length - 1 ].children.push( block );
		if ( ! token[ 5 ] ) {
			if ( stack.length > MAX_DEPTH ) {
				return null;
			}
			stack.push( block );
		}
	}
	return stack.length === 1 ? root.children : null;
}

const attributes = ( attrs: Record< string, unknown > ): string => {
	if ( ! Object.keys( attrs ).length ) {
		return '';
	}
	// The characters that could end the comment or be read as markup are escaped, as the block editor does.
	const json = JSON.stringify( attrs )
		.replace( /--/g, '\\u002d\\u002d' )
		.replace( /</g, '\\u003c' )
		.replace( />/g, '\\u003e' )
		.replace( /&/g, '\\u0026' );
	return ' ' + json;
};

/** Writes a block tree as markup, one block to a line, nested ones indented. */
export function serialize( blocks: Block[], level = 0 ): string {
	let out = '';
	for ( const block of blocks ) {
		const pad = '\t'.repeat( level );
		const name = block.name.startsWith( 'core/' ) ? block.name.slice( 5 ) : block.name;
		const attrs = attributes( block.attrs || {} );
		if ( ! block.children?.length ) {
			out += `${ pad }<!-- wp:${ name }${ attrs } /-->\n`;
			continue;
		}
		out += `${ pad }<!-- wp:${ name }${ attrs } -->\n${ serialize( block.children, level + 1 ) }${ pad }<!-- /wp:${ name } -->\n`;
	}
	return out;
}
