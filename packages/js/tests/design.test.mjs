import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync, readdirSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import path from 'node:path';
import { toMarkup, fromMarkup, isMarkup, parse } from '../dist/index.js';

const golden = path.join( path.dirname( fileURLToPath( import.meta.url ) ), '..', '..', '..', 'tests', 'golden' );
const canon = ( value ) => Array.isArray( value ) ? value.map( canon ) : value && typeof value === 'object' ? Object.fromEntries( Object.keys( value ).sort().map( ( key ) => [ key, canon( value[ key ] ) ] ) ) : value;

for ( const file of readdirSync( golden ).filter( ( name ) => name.endsWith( '.json' ) ) ) {
	test( `a golden design comes out the same: ${ file }`, () => {
		const design = JSON.parse( readFileSync( path.join( golden, file ), 'utf8' ) );
		const markup = toMarkup( design );
		assert.ok( isMarkup( markup ) );
		assert.deepEqual( canon( fromMarkup( markup ) ), canon( design ) );
	} );
}

test( 'an svg keeps its paths as inner blocks', () => {
	const markup = toMarkup( { page: { w: 10, h: 10 }, elements: [ { type: 'svg', id: 's', vw: 24, vh: 24, paths: [ { d: 'M0 0L1 1Z', fill: '$text' }, { d: 'M2 2L3 3Z' } ] } ] } );
	assert.equal( ( markup.match( /wp:draw\/svg-path/g ) || [] ).length, 2 );
	assert.equal( fromMarkup( markup ).elements[ 0 ].paths.length, 2 );
} );

test( 'a type that is not known keeps its place', () => {
	const design = { page: { w: 1, h: 1 }, elements: [ { type: 'future', id: 'f', x: 1 } ] };
	assert.deepEqual( fromMarkup( toMarkup( design ) ), design );
} );

test( 'the characters that could end a comment are escaped', () => {
	const design = { page: { w: 1, h: 1 }, elements: [ { type: 'text', id: 't', runs: [ { text: '--> <script>alert(1)</script> & "q"' } ] } ] };
	const markup = toMarkup( design );
	assert.ok( ! markup.includes( '<script' ) );
	assert.deepEqual( fromMarkup( markup ), design );
} );

test( 'markup that is not balanced or too deep is refused', () => {
	assert.equal( parse( '<!-- wp:a/b -->' ), null );
	assert.equal( parse( '<!-- wp:a/b --><!-- /wp:a/c -->' ), null );
	assert.equal( parse( '<!-- wp:a/b {"x":} /-->' ), null );
	assert.equal( parse( '<!-- wp:a/b -->'.repeat( 40 ) + '<!-- /wp:a/b -->'.repeat( 40 ) ), null );
	assert.equal( fromMarkup( '<!-- wp:draw/rect /-->' ), null );
	assert.equal( fromMarkup( '{"elements":[]}' ), null );
} );

test( 'markup written by PHP is read the same (the escaping of -- and < is understood)', () => {
	const markup = '<!-- wp:events-certificates/certificate {"page":{"w":1,"h":1}} -->\n\t<!-- wp:draw/text {"id":"t","runs":[{"text":"\\u002d\\u002d\\u003e \\u003cb\\u003e"}]} /-->\n<!-- /wp:events-certificates/certificate -->\n';
	assert.equal( fromMarkup( markup ).elements[ 0 ].runs[ 0 ].text, '--> <b>' );
} );

test( 'the options make an svg one block with its paths as an attribute, and give the types their own blocks', () => {
	const options = { blocks: { text: 'events-certificates/certificate-text', svg: 'events-certificates/certificate-svg' }, fallback: 'events-certificates/certificate-element', paths: 'attribute' };
	const design = { page: { width: '10px' }, elements: [
		{ type: 'svg', id: 's', vw: 24, vh: 24, paths: [ { d: 'M0 0L1 1Z', fill: '$text' }, { d: 'M2 2L3 3Z' } ] },
		{ type: 'text', id: 't', x: 1 },
		{ type: 'rect', id: 'r' },
		{ type: 'star', id: 'x' },
	] };
	const markup = toMarkup( design, options );
	assert.ok( markup.includes( '<!-- wp:events-certificates/certificate-svg {' ) );
	assert.ok( markup.includes( '"paths":[{"d":"M0 0L1 1Z","fill":"$text"},{"d":"M2 2L3 3Z"}]' ) );
	assert.ok( markup.includes( '<!-- wp:events-certificates/certificate-text {' ) );
	assert.ok( markup.includes( '<!-- wp:events-certificates/certificate-element {' ) );
	assert.ok( markup.includes( '<!-- wp:draw/rect {' ) );
	assert.ok( ! markup.includes( 'wp:draw/svg' ) );
	assert.deepEqual( canon( fromMarkup( markup, options ) ), canon( design ) );
} );

test( 'the default is the native one: the options are not needed', () => {
	const design = { page: {}, elements: [ { type: 'svg', id: 's', paths: [ { d: 'M0 0Z' } ] } ] };
	assert.equal( toMarkup( design ), toMarkup( design, {} ) );
	assert.ok( toMarkup( design ).includes( 'wp:draw/svg-path' ) );
} );

test( 'an element is written with the names of a block, and read back with the model\'s', () => {
	const design = {
		page: { width: '100mm', height: '50mm' },
		elements: [
			{ id: 'rect-2', type: 'rect', x: 1, y: 2, w: 30, h: 20, layout: { h: { mode: 'stretch' }, v: { mode: 'start' }, ref: 'safe' } },
			{ id: 'svg-mwzbwe9c-1', type: 'svg', w: 96, h: 96, vw: 24, vh: 24, paths: [ { d: 'M0 0L1 1Z' } ] },
			{ id: 'title', type: 'text', lh: 1.2, ls: 0.5, runs: [ { text: 'a', b: true }, { field: 'name', i: true } ] },
			{ type: 'line', x1: 0, y1: 0, x2: 5, y2: 5 },
		],
	};
	const markup = toMarkup( design );
	assert.ok( markup.includes( '"width":30,"height":20' ) );
	assert.ok( markup.includes( '"layout":{"horizontal":{"mode":"stretch"},"vertical":{"mode":"start"},"ref":"safe"}' ) );
	assert.ok( markup.includes( '"viewBox":"0 0 24 24"' ) );
	assert.ok( markup.includes( '"lineHeight":1.2,"letterSpacing":0.5' ) );
	assert.ok( markup.includes( '"bold":true' ) && markup.includes( '"italic":true' ) );
	assert.ok( markup.includes( '"x1":0' ), 'the coordinates of a line stay' );
	assert.ok( markup.includes( '"id":"title"' ), 'an id that means something stays' );
	assert.equal( /"id":"(rect-2|svg-)/.test( markup ), false, 'an id that only counts is not written' );
	assert.equal( /"(w|h|vw|vh|lh|ls|b|i)":/.test( markup ), false, 'no short name' );
	const back = fromMarkup( markup );
	assert.deepEqual( back.elements.map( ( e ) => e.id ), [ 'rect-1', 'svg-2', 'title', 'line-4' ], 'made again by position' );
	const strip = ( e ) => { const { id, ...rest } = e; return rest; };
	assert.deepEqual( back.elements.map( strip ), design.elements.map( strip ) );
} );

