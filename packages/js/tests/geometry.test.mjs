import { test } from 'node:test';
import assert from 'node:assert/strict';
import { pageSize, parseLen, isLen, refBox, resolve, placed, layoutFrom, lockedRatio, rectPath, svgCodeAllowed, isPathData, isFill } from '../dist/index.js';

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
