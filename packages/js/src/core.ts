/**
 * The attributes of an element as a core block says them: `style.typography` (fontSize, lineHeight, letterSpacing, fontWeight, textAlign, textTransform), `style.color` (text, background),
 * `style.border`, `style.spacing`, `fontFamily`, `lock: { move, remove }`, `metadata`, `anchor`, and every length with its unit (`"13pt"`, `"0pt"`). This reads them as the names the file used
 * before (`names.ts` takes it from there), so both forms are read with the same code; a key of the new form wins over the old one.
 *
 * The same as `Core` of the PHP package.
 */
import { UNITS } from './geometry';

type Attrs = Record< string, unknown >;

const isObject = ( value: unknown ): value is Attrs => !! value && typeof value === 'object' && ! Array.isArray( value );
const round8 = ( n: number ) => Math.round( n * 1e8 ) / 1e8;
const LENGTH = /^\s*(-?\d*\.?\d+)\s*(px|pt|mm|cm|in)?\s*$/i;

/** The kebab case core gives a name (`CertSans` is `cert-sans`): the slug of a font family. */
export const slug = ( name: string ): string => name.replace( /([a-z0-9])([A-Z])/g, '$1-$2' ).toLowerCase().replace( /[^a-z0-9]+/g, '-' ).replace( /^-+|-+$/g, '' );

/** A length in points: a number is points; a text is a length with its unit (px, pt, mm, cm, in; none is points). What is not a plain length (`50%`, `calc()`) is kept. */
export function lengthPt( value: unknown ): unknown {
	const m = typeof value === 'string' ? LENGTH.exec( value ) : null;
	return m ? round8( Number( m[ 1 ] ) * ( UNITS[ ( m[ 2 ] || 'pt' ).toLowerCase() ] || 1 ) ) : value;
}

/** A font size or a minimum: points as a number ("13pt", "13px", "13"), any other unit as the length it is ("4.4mm"). */
function sizeFrom( value: unknown ): unknown {
	const m = typeof value === 'string' ? LENGTH.exec( value ) : null;
	return m && ( ! m[ 2 ] || [ 'pt', 'px' ].includes( m[ 2 ].toLowerCase() ) ) ? Number( m[ 1 ] ) : value;
}

/** A number written as text ("1.4", "400") as the number. */
const numeric = ( value: unknown ): unknown => ( typeof value === 'string' && /^\s*-?\d*\.?\d+\s*$/.test( value ) ? Number( value ) : value );

/** The letter spacing: points as a number ("2pt"), a percentage or em as written. */
const spacingFrom = ( value: unknown ): unknown => sizeFrom( value );

/** The radius of a border: one length for all corners, or an object by corner, as the list of four the model keeps. */
function radiusFrom( value: unknown ): unknown {
	if ( isObject( value ) ) {
		return [ 'topLeft', 'topRight', 'bottomRight', 'bottomLeft' ].map( ( corner ) => lengthPt( value[ corner ] ?? 0 ) );
	}
	return typeof value === 'string' || typeof value === 'number' ? [ 0, 1, 2, 3 ].map( () => lengthPt( value ) ) : value;
}

const HORIZONTAL: Record< string, string > = { left: 'start', center: 'center', right: 'end', 'space-between': 'between' };
const VERTICAL: Record< string, string > = { top: 'start', center: 'center', bottom: 'end', 'space-between': 'between' };

/** A core layout object (`{ type: 'flex', orientation, justifyContent, verticalAlignment }`, `{ type: 'grid', columnCount }`) as what the model keeps: arrange, justify, align, columns. */
function arrangeFrom( layout: Attrs ): Attrs {
	const out: Attrs = {};
	{
		const grid = layout.type === 'grid';
		const vertical = layout.orientation === 'vertical';
		out.arrange = grid ? 'grid' : vertical ? 'stack' : 'row';
		if ( grid && layout.columnCount !== undefined ) {
			out.columns = layout.columnCount;
		}
		const across = layout.justifyContent !== undefined ? HORIZONTAL[ String( layout.justifyContent ) ] ?? layout.justifyContent : undefined;
		const along = layout.verticalAlignment !== undefined ? VERTICAL[ String( layout.verticalAlignment ) ] ?? layout.verticalAlignment : undefined;
		// The main axis of a row is horizontal and of a stack vertical: `justify` is the main one, `align` the other.
		const [ justify, align ] = vertical && ! grid ? [ along, across ] : [ across, along ];
		if ( justify !== undefined ) {
			out.justify = justify;
		}
		if ( align !== undefined ) {
			out.align = align;
		}
	}
	return out;
}

/** The typography and color of a part of the verification (`code`, `hint`), as the model keeps them. */
function partFrom( value: unknown ): unknown {
	if ( ! isObject( value ) || ( ! isObject( value.typography ) && ! isObject( value.color ) ) ) {
		return value;
	}
	const typography = isObject( value.typography ) ? value.typography : {};
	const out: Attrs = {};
	if ( typography.fontSize !== undefined ) {
		out.size = sizeFrom( typography.fontSize );
	}
	if ( typography.fontWeight !== undefined ) {
		out.weight = numeric( typography.fontWeight );
	}
	if ( isObject( value.color ) && value.color.text !== undefined ) {
		out.color = value.color.text;
	}
	if ( typography.lineHeight !== undefined ) {
		out.lineHeight = numeric( typography.lineHeight );
	}
	return out;
}

/** The family the file says for a font: the slug of a font the host has gives its name; any other value is the name as written. */
function familyFrom( value: unknown, fonts?: string[] ): unknown {
	return typeof value === 'string' && fonts ? fonts.find( ( name ) => slug( name ) === value ) ?? value : value;
}

/** The attributes of a block read as the names the file used before; what is not in the new form passes through. */
export function fromCore( attrs: Attrs, type: string, fonts?: string[] ): Attrs {
	const out: Attrs = { ...attrs };
	const set = ( key: string, value: unknown ) => {
		if ( value !== undefined ) {
			out[ key ] = value;
		}
	};
	const style = isObject( out.style ) ? out.style : {};
	delete out.style;
	const typography = isObject( style.typography ) ? style.typography : {};
	const color = isObject( style.color ) ? style.color : {};
	const border = isObject( style.border ) ? style.border : {};
	const spacing = isObject( style.spacing ) ? style.spacing : {};
	const dimensions = isObject( style.dimensions ) ? style.dimensions : {};

	// An image's `id` is its attachment (a number); the id of the element is its `anchor`.
	if ( type === 'image' && typeof out.id === 'number' ) {
		out.attachment = out.id;
		delete out.id;
	}

	// What every element has.
	if ( isObject( out.lock ) ) {
		const lock = out.lock;
		delete out.lock;
		if ( lock.move || lock.remove ) {
			out.locked = true;
		}
	}
	if ( dimensions.aspectRatio !== undefined ) {
		set( 'lock', numeric( dimensions.aspectRatio ) );
	}
	if ( isObject( out.metadata ) ) {
		const metadata = out.metadata;
		delete out.metadata;
		set( 'name', metadata.name );
		if ( metadata.blockVisibility === false ) {
			out.hidden = true;
		}
	}
	if ( out.anchor !== undefined ) {
		set( 'id', out.anchor );
		delete out.anchor;
	}
	for ( const key of [ 'x', 'y', 'width', 'height', 'x1', 'y1', 'x2', 'y2', 'qr' ] ) {
		if ( out[ key ] !== undefined ) {
			out[ key ] = lengthPt( out[ key ] );
		}
	}
	if ( isObject( out.layout ) && out.layout.reference !== undefined ) {
		const { reference, ...rest } = out.layout;
		out.layout = { ...rest, ref: reference };
	}

	// A text.
	if ( Array.isArray( out.textVariants ) ) {
		const variants: Attrs = {};
		for ( const group of out.textVariants ) {
			Object.assign( variants, isObject( group ) ? group : {} );
		}
		delete out.textVariants;
		out.variants = variants;
	}
	if ( out.fontFamily !== undefined ) {
		out.font = familyFrom( out.fontFamily, fonts );
		delete out.fontFamily;
	}
	set( 'weight', numeric( typography.fontWeight ) );
	set( 'size', sizeFrom( typography.fontSize ) );
	set( 'lineHeight', numeric( typography.lineHeight ) );
	set( 'letterSpacing', spacingFrom( typography.letterSpacing ) );
	set( 'align', typography.textAlign );
	if ( typography.textTransform === 'uppercase' ) {
		out.upper = true;
	}
	set( 'color', color.text );
	if ( out.fitText !== undefined ) {
		set( 'fit', out.fitText );
		delete out.fitText;
	}
	if ( out.minFontSize !== undefined ) {
		out.min = sizeFrom( out.minFontSize );
		delete out.minFontSize;
	}

	// A shape, a line.
	set( 'fill', color.background );
	set( 'stroke', border.color );
	if ( border.width !== undefined ) {
		out.strokeWidth = lengthPt( border.width );
	}
	set( 'dash', border.style );
	if ( border.radius !== undefined ) {
		out.radius = radiusFrom( border.radius );
	}

	// A group and the verification.
	if ( isObject( out.arrange ) ) {
		const layout = out.arrange;
		delete out.arrange;
		Object.assign( out, arrangeFrom( layout ) );
	}
	if ( type === 'verification' && typography.textAlign !== undefined ) {
		out.align = typography.textAlign;
	}
	if ( spacing.blockGap !== undefined ) {
		out.gap = lengthPt( spacing.blockGap );
	}
	if ( spacing.padding !== undefined ) {
		out.padding = lengthPt( spacing.padding );
	}
	for ( const key of [ 'code', 'hint' ] ) {
		if ( out[ key ] !== undefined ) {
			out[ key ] = partFrom( out[ key ] );
		}
	}

	// An image.
	if ( type === 'image' ) {
		if ( out.url !== undefined ) {
			out.src = out.url;
			delete out.url;
		}
		if ( out.scale !== undefined ) {
			out.fit = out.scale;
			delete out.scale;
		}
		if ( out.aspectRatio !== undefined ) {
			out.ratio = out.aspectRatio;
			delete out.aspectRatio;
		}
	}
	return out;
}


// ---------------------------------------------------------------------------------------------------------------------------------------------------------------------
// Writing: the element as the attributes of a core block, the way `fromCore` reads them.

/** A number of points as a length with its unit ("13pt"); what is not a number is as it is. */
const pt = ( value: unknown ): unknown => ( typeof value === 'number' ? `${ value }pt` : value );

const WRITE_HORIZONTAL: Record< string, string > = { start: 'left', center: 'center', end: 'right', between: 'space-between' };
const WRITE_VERTICAL: Record< string, string > = { start: 'top', center: 'center', end: 'bottom', between: 'space-between' };

/** `arrange`, `justify`, `align` and `columns` of a group as a core layout object. */
function arrangeTo( arrange: unknown, justify: unknown, align: unknown, columns: unknown ): Attrs {
	const out: Attrs = { type: arrange === 'grid' ? 'grid' : 'flex' };
	const vertical = arrange === 'stack';
	if ( arrange === 'row' || vertical ) {
		out.orientation = vertical ? 'vertical' : 'horizontal';
	}
	if ( arrange === 'grid' && columns !== undefined ) {
		out.columnCount = columns;
	}
	// The main axis of a row is horizontal and of a stack vertical.
	const [ across, along ] = vertical ? [ align, justify ] : [ justify, align ];
	if ( across !== undefined ) {
		out.justifyContent = WRITE_HORIZONTAL[ String( across ) ] ?? across;
	}
	if ( along !== undefined ) {
		out.verticalAlignment = WRITE_VERTICAL[ String( along ) ] ?? along;
	}
	return out;
}

/** The typography and color of a part of the verification (`code`, `hint`) as a core block says them. */
function partTo( value: unknown ): unknown {
	if ( ! isObject( value ) ) {
		return value;
	}
	const typography: Attrs = {};
	const out: Attrs = {};
	for ( const [ key, item ] of Object.entries( value ) ) {
		if ( key === 'size' ) {
			typography.fontSize = pt( item );
		} else if ( key === 'weight' ) {
			typography.fontWeight = String( item );
		} else if ( key === 'lineHeight' ) {
			typography.lineHeight = typeof item === 'number' ? String( item ) : item;
		} else if ( key === 'color' ) {
			out.color = { text: item };
		} else {
			out[ key ] = item;
		}
	}
	return { ...( Object.keys( typography ).length ? { typography } : {} ), ...out };
}

/** The attributes of an element (with the names a block says in full: `width`, `lineHeight`, `text`…) as the attributes of a core block; what has no core name is as it is. */
export function toCore( attrs: Attrs, type: string, fonts?: string[] ): Attrs {
	const out: Attrs = {};
	const typography: Attrs = {};
	const color: Attrs = {};
	const border: Attrs = {};
	const spacing: Attrs = {};
	const dimensions: Attrs = {};
	const metadata: Attrs = {};
	const group = type === 'group';
	for ( const [ key, value ] of Object.entries( attrs ) ) {
		if ( key === 'id' ) {
			out.anchor = value;
		} else if ( key === 'attachment' ) {
			out.id = value;
		} else if ( key === 'locked' ) {
			if ( value ) {
				out.lock = { move: true, remove: true };
			}
		} else if ( key === 'lock' ) {
			dimensions.aspectRatio = String( value );
		} else if ( key === 'name' ) {
			metadata.name = value;
		} else if ( key === 'hidden' ) {
			if ( value ) {
				metadata.blockVisibility = false;
			}
		} else if ( [ 'x', 'y', 'width', 'height', 'x1', 'y1', 'x2', 'y2', 'qr', 'minFontSize' ].includes( key ) ) {
			out[ key ] = pt( value );
		} else if ( key === 'min' ) {
			out.minFontSize = pt( value );
		} else if ( key === 'layout' && isObject( value ) ) {
			out.layout = Object.fromEntries( Object.entries( value ).map( ( [ k, v ] ) => [ k === 'ref' ? 'reference' : k, v ] ) );
		} else if ( key === 'variants' && isObject( value ) ) {
			out.textVariants = [ value ];
		} else if ( key === 'font' ) {
			out.fontFamily = typeof value === 'string' && fonts?.includes( value ) ? slug( value ) : value;
		} else if ( key === 'weight' ) {
			typography.fontWeight = String( value );
		} else if ( key === 'size' ) {
			typography.fontSize = pt( value );
		} else if ( key === 'lineHeight' ) {
			typography.lineHeight = typeof value === 'number' ? String( value ) : value;
		} else if ( key === 'letterSpacing' ) {
			typography.letterSpacing = pt( value );
		} else if ( key === 'align' && ! group ) {
			typography.textAlign = value;
		} else if ( key === 'upper' ) {
			if ( value ) {
				typography.textTransform = 'uppercase';
			}
		} else if ( key === 'color' ) {
			color.text = value;
		} else if ( key === 'fit' && type !== 'image' ) {
			out.fitText = value;
		} else if ( key === 'fill' ) {
			color.background = value;
		} else if ( key === 'stroke' ) {
			border.color = value;
		} else if ( key === 'strokeWidth' ) {
			border.width = pt( value );
		} else if ( key === 'dash' ) {
			border.style = value;
		} else if ( key === 'radius' && Array.isArray( value ) && value.length === 4 ) {
			border.radius = value.every( ( corner ) => corner === value[ 0 ] ) ? pt( value[ 0 ] ) : Object.fromEntries( [ 'topLeft', 'topRight', 'bottomRight', 'bottomLeft' ].map( ( name, i ) => [ name, typeof value[ i ] === 'number' ? `${ value[ i ] }pt` : value[ i ] ] ) );
		} else if ( key === 'arrange' && group ) {
			out.arrange = arrangeTo( value, attrs.justify, attrs.align, attrs.columns );
		} else if ( ( key === 'justify' || key === 'columns' || key === 'align' ) && group && attrs.arrange !== undefined ) {
			continue;
		} else if ( key === 'gap' ) {
			spacing.blockGap = pt( value );
		} else if ( key === 'padding' ) {
			spacing.padding = pt( value );
		} else if ( key === 'src' && type === 'image' ) {
			out.url = value;
		} else if ( key === 'ratio' && type === 'image' ) {
			out.aspectRatio = value;
		} else if ( key === 'fit' ) {
			out.scale = value;
		} else if ( ( key === 'code' || key === 'hint' ) ) {
			out[ key ] = partTo( value );
		} else {
			out[ key ] = value;
		}
	}
	const style: Attrs = {};
	for ( const [ name, part ] of [ [ 'typography', typography ], [ 'color', color ], [ 'border', border ], [ 'spacing', spacing ], [ 'dimensions', dimensions ] ] as const ) {
		if ( Object.keys( part ).length ) {
			style[ name ] = part;
		}
	}
	return {
		...out,
		...( Object.keys( metadata ).length ? { metadata } : {} ),
		...( Object.keys( style ).length ? { style } : {} ),
	};
}
