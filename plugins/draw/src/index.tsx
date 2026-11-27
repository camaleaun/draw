/**
 * The editor of the Draw blocks. The frame shows the canvas (the shapes are its inner blocks, kept in the editor's store, so the List
 * View and the undo are the editor's own); each shape has the fields of its place and color in the sidebar.
 */
import { createElement } from '@wordpress/element';
import { registerBlockType } from '@wordpress/blocks';
import { InnerBlocks, InspectorControls, useBlockProps, useInnerBlocksProps } from '@wordpress/block-editor';
import { PanelBody, TextControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { DrawCanvas } from '@camaleaun/draw-editor';
import { parseLen } from '@camaleaun/draw';

const SHAPES = [ 'draw/rect', 'draw/ellipse', 'draw/line', 'draw/path', 'draw/image', 'draw/text', 'draw/svg' ];
const NUMBERS = [ 'x', 'y', 'w', 'h' ];

function field( props: any, key: string, label: string ) {
	return (
		<TextControl
			key={ key }
			label={ label }
			value={ props.attributes[ key ] === undefined ? '' : String( props.attributes[ key ] ) }
			onChange={ ( value: string ) => props.setAttributes( { [ key ]: value === '' ? undefined : NUMBERS.includes( key ) ? Number( value ) : value } ) }
		/>
	);
}

registerBlockType( 'draw/frame', {
	edit: ( props: any ) => {
		const width = Math.max( 10, parseLen( props.attributes.width || '600px', 0 ) );
		const height = Math.max( 10, parseLen( props.attributes.height || '400px', 0 ) );
		// The shapes stay mounted (their sidebar fields work) but out of sight: the canvas is what draws them.
		const layers = useInnerBlocksProps( { style: { display: 'none' } }, { allowedBlocks: SHAPES, renderAppender: false } );
		return (
			<div { ...useBlockProps( { className: 'draw-frame draw-frame--editor' } ) }>
				<InspectorControls>
					<PanelBody title={ __( 'Frame', 'draw' ) }>
						{ field( props, 'width', __( 'Width (e.g. 600px)', 'draw' ) ) }
						{ field( props, 'height', __( 'Height (e.g. 400px)', 'draw' ) ) }
					</PanelBody>
				</InspectorControls>
				<DrawCanvas clientId={ props.clientId } width={ width } height={ height } colors={ props.attributes.colors || {} } />
				<div { ...layers } />
			</div>
		);
	},
	save: () => <InnerBlocks.Content />,
} as any );

SHAPES.forEach( ( name ) => {
	registerBlockType( name, {
		edit: ( props: any ) => (
			<div { ...useBlockProps( { className: 'draw-shape' } ) }>
				<InspectorControls>
					<PanelBody title={ __( 'Place and color', 'draw' ) }>
						{ field( props, 'x', 'x' ) }
						{ field( props, 'y', 'y' ) }
						{ field( props, 'w', 'w' ) }
						{ field( props, 'h', 'h' ) }
						{ field( props, 'fill', __( 'Fill', 'draw' ) ) }
					</PanelBody>
				</InspectorControls>
				<strong>{ name.replace( 'draw/', '' ) }</strong>
				{ name === 'draw/svg' ? <InnerBlocks allowedBlocks={ [ 'draw/svg-path' ] } /> : null }
			</div>
		),
		save: () => ( name === 'draw/svg' ? <InnerBlocks.Content /> : null ),
	} as any );
} );

registerBlockType( 'draw/svg-path', {
	edit: ( props: any ) => (
		<div { ...useBlockProps( { className: 'draw-path' } ) }>
			<TextControl label={ __( 'Path data (d)', 'draw' ) } value={ props.attributes.d || '' } onChange={ ( d: string ) => props.setAttributes( { d } ) } />
		</div>
	),
	save: () => null,
} as any );
