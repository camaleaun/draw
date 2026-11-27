import { test } from 'node:test';
import assert from 'node:assert/strict';
import { parseLen, isLen, refBox, resolve, placed, layoutFrom, lockedRatio, rectPath, svgCodeAllowed, isPathData, isFill } from '../dist/index.js';

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
