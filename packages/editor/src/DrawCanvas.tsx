/**
 * The canvas of a drawing, on the block editor's store: the shapes are the inner blocks of a frame, and what is done here is done to
 * their attributes (select, move, resize, nudge, delete, insert), so the editor's List View, Inspector and undo see the same drawing.
 */
import { createElement, useRef, useState } from '@wordpress/element';
import { useDispatch, useSelect } from '@wordpress/data';
import { store as blockEditorStore } from '@wordpress/block-editor';
import { createBlock } from '@wordpress/blocks';
import { Button, ButtonGroup } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { resizeBox, snap, type Guide } from '@camaleaun/draw';
import { Shape, boxOf, type ShapeBlock } from './shapes';

const HANDLES = [ 'nw', 'n', 'ne', 'e', 'se', 's', 'sw', 'w' ];
const SNAP = 5;

type Drag = { kind: 'move' | 'resize'; id: string; handle?: string; startX: number; startY: number; origin: { x: number; y: number; w: number; h: number } } | null;

const SHAPES: Array< [ string, string, Record< string, unknown > ] > = [
	[ 'draw/rect', __( 'Rectangle', 'draw' ), { w: 120, h: 80, fill: '#dcdcde' } ],
	[ 'draw/ellipse', __( 'Ellipse', 'draw' ), { w: 100, h: 100, fill: '#dcdcde' } ],
	[ 'draw/text', __( 'Text', 'draw' ), { w: 160, h: 24, size: 18, runs: [ { text: __( 'Text', 'draw' ) } ] } ],
	[ 'draw/svg', __( 'SVG', 'draw' ), { w: 64, h: 64, vw: 24, vh: 24 } ],
];

export function DrawCanvas( { clientId, width, height, colors = {} }: { clientId: string; width: number; height: number; colors?: Record< string, string > } ) {
	const { blocks, selected } = useSelect(
		( select: any ) => {
			const store = select( blockEditorStore );
			return { blocks: store.getBlocks( clientId ) as ShapeBlock[], selected: store.getSelectedBlockClientId() as string | null };
		},
		[ clientId ]
	) as { blocks: ShapeBlock[]; selected: string | null };
	const { updateBlockAttributes, selectBlock, removeBlocks, insertBlocks } = useDispatch( blockEditorStore );
	const svg = useRef< SVGSVGElement >( null );
	const [ drag, setDrag ] = useState< Drag >( null );
	const [ guides, setGuides ] = useState< Guide[] >( [] );

	const scale = () => ( svg.current ? svg.current.getBoundingClientRect().width / width : 1 );
	const shapes = blocks.filter( ( b ) => b.name !== 'draw/svg-path' );
	const current = shapes.find( ( b ) => b.clientId === selected );
	const others = ( id: string ) => shapes.filter( ( b ) => b.clientId !== id && b.attributes.w ).map( ( b ) => boxOf( b.attributes ) );

	const begin = ( event: React.PointerEvent, block: ShapeBlock, kind: 'move' | 'resize', handle?: string ) => {
		event.stopPropagation();
		( event.currentTarget as Element ).setPointerCapture?.( event.pointerId );
		selectBlock( block.clientId );
		setDrag( { kind, id: block.clientId, handle, startX: event.clientX, startY: event.clientY, origin: boxOf( block.attributes ) } );
	};
	const move = ( event: React.PointerEvent ) => {
		if ( ! drag ) {
			return;
		}
		const k = scale() || 1;
		const dx = ( event.clientX - drag.startX ) / k;
		const dy = ( event.clientY - drag.startY ) / k;
		let next = drag.origin;
		if ( drag.kind === 'move' ) {
			next = { ...drag.origin, x: drag.origin.x + dx, y: drag.origin.y + dy };
			const found = snap( next, { w: width, h: height, safe: 0 }, others( drag.id ), SNAP / k );
			next = { ...next, x: next.x + found.dx, y: next.y + found.dy };
			setGuides( found.guides );
		} else {
			next = resizeBox( drag.origin, drag.handle || 'se', dx, dy, event.shiftKey, event.altKey );
		}
		// Every move is an update of the same attributes of the same block, which the editor's history keeps as one step.
		updateBlockAttributes( drag.id, { x: round( next.x ), y: round( next.y ), w: round( next.w ), h: round( next.h ) } );
	};
	const end = () => {
		setDrag( null );
		setGuides( [] );
	};
	const keys = ( event: React.KeyboardEvent ) => {
		if ( ! current ) {
			return;
		}
		const step = event.shiftKey ? 10 : 1;
		const a = current.attributes;
		const patch: Record< string, number > | null = { ArrowLeft: { x: ( a.x || 0 ) - step }, ArrowRight: { x: ( a.x || 0 ) + step }, ArrowUp: { y: ( a.y || 0 ) - step }, ArrowDown: { y: ( a.y || 0 ) + step } }[ event.key ] as never;
		if ( patch ) {
			event.preventDefault();
			updateBlockAttributes( current.clientId, patch );
		} else if ( event.key === 'Delete' || event.key === 'Backspace' ) {
			event.preventDefault();
			removeBlocks( current.clientId );
		}
	};
	const add = ( name: string, attrs: Record< string, unknown > ) => insertBlocks( createBlock( name, { x: 20, y: 20, ...attrs } ), undefined, clientId );

	const cur = current ? boxOf( current.attributes ) : null;
	const hs = 6 / ( scale() || 1 );
	return (
		<div className="draw-canvas" onKeyDown={ keys } tabIndex={ 0 }>
			<ButtonGroup className="draw-canvas__tools">
				{ SHAPES.map( ( [ name, label, attrs ] ) => (
					<Button key={ name } variant="secondary" size="small" onClick={ () => add( name, attrs ) }>
						{ label }
					</Button>
				) ) }
			</ButtonGroup>
			<svg ref={ svg } className="draw-canvas__page" viewBox={ `0 0 ${ width } ${ height }` } onPointerMove={ move } onPointerUp={ end } onPointerCancel={ end } onPointerDown={ () => selectBlock( clientId ) } style={ { width: '100%', background: '#fff', touchAction: 'none' } }>
				{ shapes.map( ( block ) => (
					<g key={ block.clientId } onPointerDown={ ( event ) => begin( event, block, 'move' ) } style={ { cursor: 'move' } }>
						<Shape block={ block } colors={ colors } />
					</g>
				) ) }
				{ guides.map( ( g, i ) => ( g.kind === 'gap' ? null : g.axis === 'x' ? <line key={ i } x1={ g.at } x2={ g.at } y1={ 0 } y2={ height } stroke="#d63638" strokeWidth={ 1 / ( scale() || 1 ) } /> : <line key={ i } y1={ g.at } y2={ g.at } x1={ 0 } x2={ width } stroke="#d63638" strokeWidth={ 1 / ( scale() || 1 ) } /> ) ) }
				{ current && cur && cur.w > 0 && (
					<g>
						<rect x={ cur.x } y={ cur.y } width={ cur.w } height={ cur.h } fill="none" stroke="#3858e9" strokeWidth={ 1 / ( scale() || 1 ) } pointerEvents="none" />
						{ HANDLES.map( ( handle ) => {
							const hx = handle.includes( 'w' ) ? cur.x : handle.includes( 'e' ) ? cur.x + cur.w : cur.x + cur.w / 2;
							const hy = handle.includes( 'n' ) ? cur.y : handle.includes( 's' ) ? cur.y + cur.h : cur.y + cur.h / 2;
							return <rect key={ handle } x={ hx - hs / 2 } y={ hy - hs / 2 } width={ hs } height={ hs } fill="#fff" stroke="#3858e9" strokeWidth={ 1 / ( scale() || 1 ) } style={ { cursor: `${ handle }-resize` } } onPointerDown={ ( event ) => begin( event, current, 'resize', handle ) } />;
						} ) }
					</g>
				) }
			</svg>
		</div>
	);
}

const round = ( n: number ) => Math.round( n * 100 ) / 100;
