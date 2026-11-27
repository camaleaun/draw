/**
 * The editor side of the blocks, with no build step: the frame holds the shapes as inner blocks, and each block has the fields of its
 * place (x, y, w, h in points) and its color in the sidebar. The page shows the drawing the server makes.
 */
( function ( wp ) {
	var el = wp.element.createElement;
	var InnerBlocks = wp.blockEditor.InnerBlocks;
	var InspectorControls = wp.blockEditor.InspectorControls;
	var useBlockProps = wp.blockEditor.useBlockProps;
	var PanelBody = wp.components.PanelBody;
	var TextControl = wp.components.TextControl;
	var NumberControl = wp.components.__experimentalNumberControl || wp.components.TextControl;
	var __ = wp.i18n.__;

	var SHAPES = [ 'draw/rect', 'draw/ellipse', 'draw/line', 'draw/path', 'draw/image', 'draw/text', 'draw/svg' ];

	function field( props, key, label ) {
		return el( TextControl, {
			key: key,
			label: label,
			value: props.attributes[ key ] === undefined ? '' : String( props.attributes[ key ] ),
			onChange: function ( value ) {
				var patch = {};
				patch[ key ] = value === '' ? undefined : ( /^[xywh]$/.test( key ) ? Number( value ) : value );
				props.setAttributes( patch );
			},
		} );
	}

	wp.blocks.registerBlockType( 'draw/frame', {
		edit: function ( props ) {
			return el(
				'div',
				useBlockProps( { className: 'draw-frame draw-frame--editor' } ),
				el(
					InspectorControls,
					null,
					el( PanelBody, { title: __( 'Frame', 'draw' ) }, field( props, 'width', __( 'Width (e.g. 600px)', 'draw' ) ), field( props, 'height', __( 'Height (e.g. 400px)', 'draw' ) ) )
				),
				el( InnerBlocks, { allowedBlocks: SHAPES } )
			);
		},
		save: function () {
			return el( InnerBlocks.Content );
		},
	} );

	SHAPES.forEach( function ( name ) {
		wp.blocks.registerBlockType( name, {
			edit: function ( props ) {
				var children = name === 'draw/svg' ? el( InnerBlocks, { allowedBlocks: [ 'draw/svg-path' ] } ) : null;
				return el(
					'div',
					useBlockProps( { className: 'draw-shape' } ),
					el(
						InspectorControls,
						null,
						el( PanelBody, { title: __( 'Place and color', 'draw' ) }, field( props, 'x', 'x' ), field( props, 'y', 'y' ), field( props, 'w', 'w' ), field( props, 'h', 'h' ), field( props, 'fill', __( 'Fill', 'draw' ) ) )
					),
					el( 'strong', null, name.replace( 'draw/', '' ) ),
					el( 'span', null, ' ' + [ props.attributes.x, props.attributes.y, props.attributes.w, props.attributes.h ].join( ' · ' ) ),
					children
				);
			},
			save: function () {
				return name === 'draw/svg' ? el( InnerBlocks.Content ) : null;
			},
		} );
	} );

	wp.blocks.registerBlockType( 'draw/svg-path', {
		edit: function ( props ) {
			return el(
				'div',
				useBlockProps( { className: 'draw-path' } ),
				el( TextControl, { label: __( 'Path data (d)', 'draw' ), value: props.attributes.d || '', onChange: function ( d ) { props.setAttributes( { d: d } ); } } )
			);
		},
		save: function () {
			return null;
		},
	} );
}( window.wp ) );
