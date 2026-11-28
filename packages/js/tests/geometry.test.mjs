import { test } from 'node:test';
import assert from 'node:assert/strict';
import { lineHeightFactor, letterSpacingPt, placeChildren, flowBoxes, radiusPt, pageSize, parseLen, isLen, refBox, resolve, placed, layoutFrom, lockedRatio, rectPath, svgCodeAllowed, isPathData, isFill } from '../dist/index.js';

const page = { w: 800, h: 600, safe: 28.3465 };

test( 'lengths are read in points', () => {
	assert.equal( parseLen( '20pt', 0 ), 20 );
	assert.equal( parseLen( '10mm', 0 ), 28.3465 );
	assert.equal( parseLen( '50%', 200 ), 100 );
	assert.equal( parseLen( 'calc(100% - 40pt)', 200 ), 160 );
	assert.equal( parseLen( 7, 0 ), 7 );
	assert.equal( parseLen( '', 0 ), 0 );
	assert.ok( isLen( '20pt' ) && isLen( 'calc(100% - 40pt)' ) && ! isLen( 'abc' ) );
} );

test( 'an anchored element resolves to a box and goes back to the same anchors', () => {
	const el = { type: 'rect', layout: { h: { mode: 'stretch', start: '10pt', end: '10pt' }, v: { mode: 'start', start: '5pt', size: '50pt' } } };
	const r = resolve( el, page );
	assert.deepEqual( [ r.x, r.w, r.y, r.h ], [ 10, 780, 5, 50 ] );
	const moved = placed( el, { x: 30, y: 25, w: 740, h: 50 }, page );
	assert.deepEqual( moved.layout.h, { mode: 'stretch', start: '30pt', end: '30pt' } );
	assert.equal( moved.layout.v.start, '25pt' );
} );

test( 'the safe margin is a box of its own', () => {
	assert.deepEqual( refBox( page, 'safe' ), { x: 28.3465, y: 28.3465, w: 800 - 2 * 28.3465, h: 600 - 2 * 28.3465 } );
	assert.deepEqual( refBox( page ), { x: 0, y: 0, w: 800, h: 600 } );
} );

test( 'a kept ratio is the image ratio or the lock', () => {
	assert.equal( lockedRatio( { type: 'image', ratio: '4/3' } ), 4 / 3 );
	assert.equal( lockedRatio( { type: 'rect', lock: 2 } ), 2 );
	assert.equal( lockedRatio( { type: 'rect' } ), undefined );
} );

test( 'a rectangle is a path, with the corner of its shape', () => {
	assert.equal( rectPath( 0, 0, 10, 10 ), 'M0 0L10 0L10 10L0 10Z' );
	assert.match( rectPath( 0, 0, 10, 10, [ 2, 2, 2, 2 ], 'bevel' ), /^M0 2L2 0/ );
	assert.match( rectPath( 0, 0, 10, 10, [ 2, 2, 2, 2 ] ), /A2 2 0 0 1/ );
} );

test( 'only plain paths of commands and numbers may be read', () => {
	assert.ok( isPathData( 'M0 0L10 10Z' ) && ! isPathData( 'L0 0' ) && ! isPathData( 'M0 0<script>' ) );
	assert.ok( isFill( '#fff' ) && isFill( '$text' ) && ! isFill( 'url(x)' ) );
	assert.ok( svgCodeAllowed( '<svg><path d="M0 0"/></svg>' ) && ! svgCodeAllowed( '<svg onload="x"/>' ) && ! svgCodeAllowed( '<!DOCTYPE svg>' ) );
} );

import { snap, resizeBox } from '../dist/index.js';

test( 'a moving box snaps to the page and to the others, and says which guide', () => {
	const page = { w: 800, h: 600, safe: 28 };
	const near = snap( { x: 29.5, y: 100, w: 50, h: 20 }, page, [], 5 );
	assert.equal( near.dx, -1.5 );
	assert.deepEqual( near.guides[ 0 ], { axis: 'x', at: 28 } );
	const far = snap( { x: 300, y: 250, w: 50, h: 20 }, page, [], 5 );
	assert.deepEqual( [ far.dx, far.dy, far.guides.length ], [ 0, 0, 0 ] );
	const withOther = snap( { x: 200, y: 203, w: 50, h: 20 }, page, [ { x: 100, y: 200, w: 50, h: 20 } ], 5 );
	assert.equal( withOther.dy, -3 );
} );

test( 'resizing by a handle keeps the opposite side, the ratio and the centre when asked', () => {
	const box = { x: 100, y: 100, w: 200, h: 100 };
	assert.deepEqual( resizeBox( box, 'e', 50, 0, false ), { x: 100, y: 100, w: 250, h: 100 } );
	assert.deepEqual( resizeBox( box, 'nw', 20, 10, false ), { x: 120, y: 110, w: 180, h: 90 } );
	assert.deepEqual( resizeBox( box, 'se', 40, 0, true ), { x: 100, y: 100, w: 240, h: 120 } );
	assert.deepEqual( resizeBox( box, 'e', 20, 0, false, true ), { x: 80, y: 100, w: 240, h: 100 } );
	assert.equal( resizeBox( box, 'e', -500, 0, false ).w, 4, 'never smaller than 4' );
} );

test( 'the size of a page is in the unit that makes it round', () => {
	assert.deepEqual( pageSize( 841.89, 595.28 ), [ '297mm', '210mm' ] );
	assert.deepEqual( pageSize( 595.28, 841.89 ), [ '210mm', '297mm' ] );
	assert.deepEqual( pageSize( 792, 612 ), [ '11in', '8.5in' ] );
	assert.deepEqual( pageSize( 419.53, 595.28 ), [ '148mm', '210mm' ] );
	assert.deepEqual( pageSize( 283.47, 283.47 ), [ '100mm', '100mm' ] );
	assert.deepEqual( pageSize( 800, 600 ), [ '800pt', '600pt' ] );
	assert.deepEqual( pageSize( 812.3, 600 ), [ '812.3pt', '600pt' ] );
} );

test( 'a length is written back in the unit it was written in, to two decimals (three in inches)', () => {
	const page = { w: 842, h: 595, safe: 0 };
	const box = { x: 28.3465, y: 56.693, w: 85.0395, h: 14.17325 };
	const mm = layoutFrom( { h: { mode: 'start', start: '1mm', size: '1mm' }, v: { mode: 'start', start: '1mm', size: '1mm' } }, box, page );
	assert.deepEqual( [ mm.h.start, mm.h.size, mm.v.start, mm.v.size ], [ '10mm', '30mm', '20mm', '5mm' ] );
	const inches = layoutFrom( { h: { mode: 'start', start: '0in', size: '1in' }, v: { mode: 'start', start: '0in', size: '1in' } }, { x: 36, y: 18, w: 90, h: 9 }, page );
	assert.deepEqual( [ inches.h.start, inches.h.size, inches.v.start, inches.v.size ], [ '0.5in', '1.25in', '0.25in', '0.125in' ] );
	const px = layoutFrom( { h: { mode: 'start', start: '0px', size: '0px' }, v: { mode: 'start', start: '0', size: '0' } }, { x: 12.345, y: 5, w: 100, h: 20 }, page );
	assert.deepEqual( [ px.h.start, px.h.size, px.v.start ], [ '12.35px', '100px', '5pt' ], 'px is kept; no unit is pt' );
	const percent = layoutFrom( { h: { mode: 'start', start: '0%', size: '0%' }, v: { mode: 'end', end: '0pt', size: '0pt' } }, { x: 84.2, y: 0, w: 421, h: 100 }, page );
	assert.deepEqual( [ percent.h.start, percent.h.size ], [ '10%', '50%' ] );
	const calc = layoutFrom( { h: { mode: 'start', start: 'calc(1pt + 1pt)', size: '1pt' }, v: { mode: 'start' } }, { x: 10, y: 0, w: 5, h: 5 }, page );
	assert.equal( calc.h.start, '10pt', 'a calc() becomes pt' );
} );

test( 'a corner radius is points or a percentage of the shorter side, 0 to 50%', () => {
	assert.equal( radiusPt( 12, 100, 40 ), 12 );
	assert.equal( radiusPt( '25%', 100, 40 ), 10 );
	assert.equal( radiusPt( '50%', 100, 40 ), 20, '50% makes a pill' );
	assert.equal( radiusPt( '80%', 100, 40 ), 20, 'no more than 50%' );
	assert.equal( radiusPt( 'abc', 100, 40 ), 0 );
	assert.equal( radiusPt( undefined, 100, 40 ), 0 );
	assert.equal( rectPath( 0, 0, 100, 40, [ '50%', 0, 0, '50%' ] ), rectPath( 0, 0, 100, 40, [ 20, 0, 0, 20 ] ), 'the same path as the radius in points' );
} );

test( 'inside a group an element is placed from the corner of the group, and anchored to its size', () => {
	const group = { x: 100, y: 50, w: 200, h: 100 };
	const el = { type: 'rect', layout: { ref: 'parent', h: { mode: 'stretch', start: '10pt', end: '10pt' }, v: { mode: 'start', start: '5pt', size: '40pt' } } };
	const r = resolve( el, page, group );
	assert.deepEqual( [ r.x, r.w, r.y, r.h ], [ 10, 180, 5, 40 ], 'relative to the group, not the page' );
	assert.deepEqual( refBox( page, 'parent', group ), { x: 0, y: 0, w: 200, h: 100 } );
	const half = { type: 'rect', layout: { ref: 'parent', h: { mode: 'start', start: '50%', size: '25%' }, v: { mode: 'center', start: '0pt', size: '50%' } } };
	const q = resolve( half, page, group );
	assert.deepEqual( [ q.x, q.w, q.y, q.h ], [ 100, 50, 25, 50 ] );
	const moved = placed( el, { x: 20, y: 10, w: 160, h: 40 }, page, group );
	assert.equal( moved.layout.h.start, '20pt' );
	assert.equal( moved.layout.h.end, '20pt' );
	// With no group the parent is the page.
	assert.deepEqual( refBox( page, 'parent' ), { x: 0, y: 0, w: 800, h: 600 } );
} );


test( 'a row and a stack place what is in them one after the other, justified and aligned', () => {
	const items = [ { w: 40, h: 10 }, { w: 60, h: 20 }, { w: 20, h: 30 } ];
	const row = { w: 300, h: 100, arrange: 'row', gap: 10, padding: 5 };
	const start = flowBoxes( row, items );
	assert.deepEqual( start.map( ( b ) => [ b.x, b.y, b.w, b.h ] ), [ [ 5, 5, 40, 10 ], [ 55, 5, 60, 20 ], [ 125, 5, 20, 30 ] ] );
	const centered = flowBoxes( { ...row, justify: 'center', align: 'center' }, items );
	assert.equal( centered[ 0 ].x, 5 + ( 290 - 140 ) / 2, 'the room left (290 less 120 of widths and 20 of gaps) is shared in two' );
	assert.equal( centered[ 0 ].y, 5 + ( 90 - 10 ) / 2 );
	const end = flowBoxes( { ...row, justify: 'end', align: 'end' }, items );
	assert.equal( end[ 2 ].x + end[ 2 ].w, 295 );
	assert.equal( end[ 0 ].y + end[ 0 ].h, 95 );
	const between = flowBoxes( { ...row, justify: 'between' }, items );
	assert.deepEqual( [ between[ 0 ].x, between[ 2 ].x + between[ 2 ].w ], [ 5, 295 ] );
	const stretch = flowBoxes( { ...row, align: 'stretch' }, items );
	assert.deepEqual( stretch.map( ( b ) => b.h ), [ 90, 90, 90 ] );
	const stack = flowBoxes( { w: 100, h: 300, arrange: 'stack', gap: 10 }, items );
	assert.deepEqual( stack.map( ( b ) => [ b.x, b.y, b.w, b.h ] ), [ [ 0, 0, 40, 10 ], [ 0, 20, 60, 20 ], [ 0, 50, 20, 30 ] ] );
} );

test( 'what grows shares the room that is left, and a grid has columns of one width', () => {
	const grown = flowBoxes( { w: 200, h: 50, arrange: 'row', gap: 10 }, [ { w: 40, h: 10 }, { w: 20, h: 10, grow: 1 }, { w: 20, h: 10, grow: 3 } ] );
	// 200 - 40 - 20 - 20 - 2 gaps (20) = 100 left: 25 and 75 more.
	assert.deepEqual( grown.map( ( b ) => [ b.x, b.w ] ), [ [ 0, 40 ], [ 50, 45 ], [ 105, 95 ] ] );
	const grid = flowBoxes( { w: 210, h: 200, arrange: 'grid', columns: 2, gap: 10, padding: 5 }, [ { w: 1, h: 20 }, { w: 1, h: 30 }, { w: 1, h: 10 } ] );
	// (210 - 10 padding - 10 gap) / 2 = 95 for each cell; the second row is under the highest of the first (30) and a gap.
	assert.deepEqual( grid.map( ( b ) => [ b.x, b.y, b.w, b.h ] ), [ [ 5, 5, 95, 20 ], [ 110, 5, 95, 30 ], [ 5, 45, 95, 10 ] ] );
	assert.deepEqual( flowBoxes( { w: 210, h: 200, arrange: 'grid', columns: 2, align: 'stretch' }, [ { w: 1, h: 20 }, { w: 1, h: 30 } ] ).map( ( b ) => b.h ), [ 30, 30 ] );
	assert.deepEqual( flowBoxes( { w: 100, h: 100, arrange: 'row' }, [] ), [] );
} );

test( 'what is in a group is placed by the flow in a row, and by its own anchors in a free group', () => {
	const kids = [ { type: 'rect', w: 40, h: 10 }, { type: 'rect', w: 20, h: 10, hidden: true }, { type: 'rect', w: 30, h: 10 } ];
	const row = placeChildren( { w: 200, h: 50, arrange: 'row', gap: 10 }, kids );
	assert.deepEqual( [ row[ 0 ].x, row[ 2 ].x ], [ 0, 50 ], 'the hidden one takes no room' );
	assert.equal( row[ 1 ].x, undefined );
	const free = placeChildren( { w: 200, h: 50 }, [ { type: 'rect', layout: { ref: 'parent', h: { mode: 'stretch', start: '5pt', end: '5pt' }, v: { mode: 'start', start: '0pt', size: '10pt' } } } ] );
	assert.deepEqual( [ free[ 0 ].x, free[ 0 ].w ], [ 5, 190 ] );
} );


test( 'a drawing that says no size on an axis is as big as its viewBox', () => {
	const svg = { type: 'svg', vw: 24, vh: 12, layout: { h: { mode: 'start', start: '10pt' }, v: { mode: 'center', start: '0pt' } } };
	const r = resolve( svg, page );
	assert.deepEqual( [ r.x, r.w, r.h ], [ 10, 24, 12 ] );
	assert.equal( r.y, ( 600 - 12 ) / 2, 'centered with its own height' );
	const said = resolve( { ...svg, layout: { h: { mode: 'start', start: '10pt', size: '50pt' }, v: { mode: 'start', start: '0pt', size: '8pt' } } }, page );
	assert.deepEqual( [ said.w, said.h ], [ 50, 8 ], 'a size that is said wins' );
	const other = resolve( { type: 'rect', vw: 24, layout: { h: { mode: 'start', start: '0pt' }, v: { mode: 'start', start: '0pt' } } }, page );
	assert.equal( other.w, 0, 'only a drawing has a size of its own' );
} );

test( 'a drawing with no size on an axis keeps it so when it is moved, and has one once it is resized', () => {
	const svg = { type: 'svg', vw: 24, vh: 12, layout: { h: { mode: 'start', start: '10pt' }, v: { mode: 'start', start: '20pt' } } };
	const moved = placed( svg, { x: 30, y: 40, w: 24, h: 12 }, page );
	assert.deepEqual( [ moved.layout.h.start, moved.layout.v.start ], [ '30pt', '40pt' ] );
	assert.equal( moved.layout.h.size, undefined, 'still no size' );
	assert.equal( moved.layout.v.size, undefined );
	const resized = placed( svg, { x: 10, y: 20, w: 48, h: 12 }, page );
	assert.equal( resized.layout.h.size, '48pt', 'the width was said' );
	assert.equal( resized.layout.v.size, undefined, 'the height was not' );
} );

test( 'a line height and a letter spacing are read with their unit, and a number is what files wrote before', () => {
	assert.equal( lineHeightFactor( undefined, 10 ), 1.2 );
	assert.equal( lineHeightFactor( 1.4, 10 ), 1.4 );
	assert.equal( lineHeightFactor( '150%', 10 ), 1.5 );
	assert.equal( lineHeightFactor( '16px', 10 ), 1.6 );
	assert.equal( lineHeightFactor( '2lh', 10 ), 2 );
	assert.equal( lineHeightFactor( '1.4em', 10 ), 1.4 );
	assert.equal( letterSpacingPt( '0.05em', 20 ), 1 );
	assert.equal( letterSpacingPt( '-0.1em', 10 ), -1 );
	assert.equal( lineHeightFactor( 'nope', 10 ), 1.2 );
	assert.equal( letterSpacingPt( 0.5, 10 ), 0.5, 'a number is points' );
	assert.equal( letterSpacingPt( '5%', 10 ), 0.5 );
	assert.equal( letterSpacingPt( '5', 10 ), 0.5, 'no unit: a percentage' );
	assert.equal( letterSpacingPt( '3pt', 10 ), 3 );
	assert.ok( Math.abs( letterSpacingPt( '10mm', 10 ) - 28.3465 ) < 0.0001 );
	assert.equal( letterSpacingPt( undefined, 10 ), 0 );
} );

