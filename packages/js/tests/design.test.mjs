import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync, readdirSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import path from 'node:path';
import { toMarkup, fromMarkup, isMarkup, parse, defaultName } from '../dist/index.js';

const golden = path.join( path.dirname( fileURLToPath( import.meta.url ) ), '..', '..', '..', 'tests', 'golden' );
// The default name of a type is not written in a file, so it is not read back.
const unnamed = ( design ) => ( { ...design, elements: ( design.elements || [] ).map( ( el ) => { const { name, ...rest } = el; return name === defaultName( el ) ? rest : el; } ), ...( design.back ? { back: unnamed( design.back ) } : {} ) } );
const canon = ( value ) => Array.isArray( value ) ? value.map( canon ) : value && typeof value === 'object' ? Object.fromEntries( Object.keys( value ).sort().map( ( key ) => [ key, canon( value[ key ] ) ] ) ) : value;

for ( const file of readdirSync( golden ).filter( ( name ) => name.endsWith( '.json' ) ) ) {
	test( `a golden design comes out the same: ${ file }`, () => {
		const design = JSON.parse( readFileSync( path.join( golden, file ), 'utf8' ) );
		const markup = toMarkup( design );
		assert.ok( isMarkup( markup ) );
		assert.deepEqual( canon( fromMarkup( markup ) ), canon( unnamed( design ) ) );
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
	assert.ok( markup.includes( '"text":"**a***[[name]]*"' ) && ! markup.includes( '"runs"' ), 'A text is written as text.' );
	assert.ok( markup.includes( '"x1":0' ), 'the coordinates of a line stay' );
	assert.ok( markup.includes( '"id":"title"' ), 'an id that means something stays' );
	assert.equal( /"id":"(rect-2|svg-)/.test( markup ), false, 'an id that only counts is not written' );
	assert.equal( /"(w|h|vw|vh|lh|ls|b|i)":/.test( markup ), false, 'no short name' );
	const back = fromMarkup( markup );
	assert.deepEqual( back.elements.map( ( e ) => e.id ), [ 'rect-1', 'svg-2', 'title', 'line-4' ], 'made again by position' );
	const strip = ( e ) => { const { id, ...rest } = e; return rest; };
	assert.deepEqual( back.elements.map( strip ), design.elements.map( strip ) );
} );

test( 'the default name of an element is not written, the name a host adds either', () => {
	const design = { page: { width: '100mm', height: '50mm' }, elements: [ { type: 'rect', name: 'Rectangle' }, { type: 'rect', name: 'Band' }, { type: 'svg', name: 'SVG' }, { type: 'image', slot: 'logo', name: 'Community logo' }, { type: 'signature', name: 'Signature' } ] };
	const without = toMarkup( design );
	assert.equal( without.includes( '"name":"Rectangle"' ) || without.includes( '"name":"SVG"' ), false );
	assert.ok( without.includes( '"name":"Band"' ) && without.includes( '"name":"Community logo"' ) && without.includes( '"name":"Signature"' ) );
	const withHost = toMarkup( design, { names: { signature: 'Signature', 'image:logo': 'Community logo' } } );
	assert.equal( withHost.includes( '"name":"Community logo"' ) || withHost.includes( '"name":"Signature"' ), false );
	assert.ok( withHost.includes( '"name":"Band"' ) );
} );

test( 'a group keeps what is in it as inner blocks, down to the last level, and every id is its own', () => {
	const design = {
		page: { width: '297mm', height: '210mm' },
		elements: [
			{ type: 'rect', w: 10, h: 10 },
			{ type: 'group', fill: '#fff', w: 200, h: 100, children: [ { type: 'rect', w: 5, h: 5 }, { type: 'group', w: 50, h: 50, children: [ { type: 'rect', w: 1, h: 1 } ] } ] },
		],
	};
	const markup = toMarkup( design );
	assert.match( markup, /<!-- wp:draw\/group /, 'the group is a block' );
	assert.match( markup, /<!-- wp:draw\/rect \{"width":5,"height":5\} \/-->/, 'its children are inner blocks' );
	assert.ok( ! markup.includes( '"children"' ), 'children is not an attribute' );
	const back = fromMarkup( markup );
	const group = back.elements[ 1 ];
	assert.equal( group.type, 'group' );
	assert.equal( group.children.length, 2 );
	assert.equal( group.children[ 1 ].children[ 0 ].w, 1 );
	const ids = [];
	const walk = ( list ) => list.forEach( ( el ) => { ids.push( el.id ); walk( el.children || [] ); } );
	walk( back.elements );
	assert.equal( new Set( ids ).size, ids.length, 'no id twice: ' + ids.join( ',' ) );
	assert.deepEqual( fromMarkup( toMarkup( back ) ), back, 'the trip is the same' );
} );


test( 'a text is written as text and read as runs, in every language', () => {
	const design = { page: { w: 1, h: 1 }, elements: [ { type: 'text', id: 't', runs: [ { text: 'Held in ' }, { field: 'location', b: true } ], variants: { pt_BR: [ { text: 'Em ' }, { field: 'location', i: true } ] } } ] };
	const markup = toMarkup( design );
	assert.ok( markup.includes( '"text":"Held in **[[location]]**"' ) && markup.includes( '"variants":{"pt_BR":"Em *[[location]]*"}' ) );
	assert.deepEqual( fromMarkup( markup ), design );
} );

test( 'a text of an earlier file is read', () => {
	const old = '<!-- wp:events-certificates/certificate {"page":{"w":1,"h":1}} --><!-- wp:draw/text {"id":"t","runs":[{"text":"a"},{"field":"name","bold":true}],"variants":{"pt_BR":[{"text":"b","bold":true,"i":true}]}} /--><!-- wp:draw/text {"id":"u","text":"{name} and **bold**"} /--><!-- /wp:events-certificates/certificate -->';
	const design = fromMarkup( old );
	assert.deepEqual( design.elements[ 0 ].runs, [ { text: 'a' }, { field: 'name', b: true } ] );
	assert.deepEqual( design.elements[ 0 ].variants, { pt_BR: [ { text: 'b', b: true, i: true } ] } );
	assert.deepEqual( design.elements[ 1 ].runs, [ { field: 'name' }, { text: ' and ' }, { text: 'bold', b: true } ] );
} );

test( 'only the fields the host says are fields', () => {
	const markup = '<!-- wp:events-certificates/certificate {"page":{"w":1,"h":1}} --><!-- wp:draw/text {"id":"t","text":"[[name]] [[other]]"} /--><!-- /wp:events-certificates/certificate -->';
	assert.deepEqual( fromMarkup( markup, { fields: [ 'name' ] } ).elements[ 0 ].runs, [ { field: 'name' }, { text: ' [[other]]' } ] );
} );

test( 'a type with a slot can have a block of its own, and the slot is what the block says', () => {
	const options = { blocks: { 'text:date-of-emission': 'a/date-of-emission' } };
	const design = { page: { w: 1, h: 1 }, elements: [ { type: 'text', id: 'd', slot: 'date-of-emission', runs: [ { field: 'issued_date' } ] }, { type: 'text', id: 't', slot: 'other', runs: [] } ] };
	const markup = toMarkup( design, options );
	assert.ok( markup.includes( 'wp:a/date-of-emission {"id":"d","text":"[[issued_date]]"}' ), markup );
	assert.ok( markup.includes( '"slot":"other"' ), 'Another slot stays an attribute of the text.' );
	assert.deepEqual( fromMarkup( markup, options ), design );
} );
