import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync, readdirSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import path from 'node:path';
import { toMarkup, fromMarkup, elementsToMarkup, elementsFromMarkup, isMarkup, parse, defaultName } from '../dist/index.js';

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

// The markup as 0.25 wrote it, kept in files: every later version reads it back to the same design (the formats that came after are read with the same readers).
for ( const file of readdirSync( path.join( golden, 'markup' ) ).filter( ( name ) => name.endsWith( '.old.html' ) ) ) {
	test( `the markup 0.25 wrote is read back to its design: ${ file }`, () => {
		const design = JSON.parse( readFileSync( path.join( golden, file.replace( '.old.html', '.json' ) ), 'utf8' ) );
		const markup = readFileSync( path.join( golden, 'markup', file ), 'utf8' );
		assert.ok( isMarkup( markup ) );
		assert.deepEqual( canon( fromMarkup( markup ) ), canon( unnamed( design ) ) );
	} );
}

// The format of 0.27 (the attributes a core block says: style.typography, fontFamily, lock, units on every length). The fixture is made by tests/tools/make-new-fixture.py from the
// table of docs/block-markup.md, not by the package: 0.26 reads it, 0.27 writes it.
for ( const file of readdirSync( path.join( golden, 'markup' ) ).filter( ( name ) => name.endsWith( '.new.html' ) ) ) {
	test( `the markup with the attributes of a core block is read back to its design: ${ file }`, () => {
		const design = JSON.parse( readFileSync( path.join( golden, file.replace( '.new.html', '.json' ) ), 'utf8' ) );
		const markup = readFileSync( path.join( golden, 'markup', file ), 'utf8' );
		assert.deepEqual( canon( fromMarkup( markup, { fonts: [ 'Inter', 'Lora' ] } ) ), canon( unnamed( design ) ) );
	} );
}

// What 0.27 writes is what the oracle says, block by block (the oracle is made from the table of docs/block-markup.md, not by the package).
for ( const file of readdirSync( path.join( golden, 'markup' ) ).filter( ( name ) => name.endsWith( '.new.html' ) ) ) {
	test( `the markup is written with the attributes of a core block: ${ file }`, () => {
		const design = JSON.parse( readFileSync( path.join( golden, file.replace( '.new.html', '.json' ) ), 'utf8' ) );
		const written = toMarkup( design, { fonts: [ 'Inter', 'Lora' ] } );
		const expected = readFileSync( path.join( golden, 'markup', file ), 'utf8' );
		assert.deepEqual( canon( parse( written ) ), canon( parse( expected ) ) );
	} );
}

test( 'the attributes of a core block, one by one', () => {
	const read = ( attrs, options ) => fromMarkup( `<!-- wp:events-certificates/certificate {"page":{"w":1,"h":1}} --><!-- wp:draw/text ${ JSON.stringify( attrs ) } /--><!-- /wp:events-certificates/certificate -->`, options ).elements[ 0 ];
	assert.equal( read( { style: { typography: { fontSize: '13px' } } } ).size, 13, 'px is the point' );
	assert.equal( read( { style: { typography: { fontSize: '13pt' } } } ).size, 13 );
	assert.equal( read( { style: { typography: { fontSize: '4.4mm' } } } ).size, '4.4mm', 'another unit is kept as a length' );
	assert.equal( read( { style: { typography: { fontSize: 13 } } } ).size, 13 );
	assert.equal( read( { size: 13 } ).size, 13, 'the form before is read as it was' );
	assert.equal( read( { size: 9, style: { typography: { fontSize: '13pt' } } } ).size, 13, 'the new form wins' );
	assert.equal( read( { align: 'left', style: { typography: { textAlign: 'center' } } } ).align, 'center' );
	assert.equal( read( { lock: { move: true } } ).locked, true );
	assert.equal( read( { lock: { remove: true } } ).locked, true );
	assert.equal( read( { lock: 1.5 } ).lock, 1.5, 'the number is the ratio an element keeps' );
	assert.equal( read( { style: { dimensions: { aspectRatio: '1.5' } } } ).lock, 1.5 );
	assert.equal( read( { x: '10mm', width: '2in' } ).x, 28.3465 );
	assert.equal( read( { width: '2in' } ).w, 144 );
	assert.equal( read( { fontFamily: 'cert-sans' }, { fonts: [ 'CertSans' ] } ).font, 'CertSans', 'a slug of a font the host has is its name' );
	assert.equal( read( { fontFamily: 'IBMPlexSans' }, { fonts: [ 'CertSans' ] } ).font, 'IBMPlexSans', 'any other value is the name as written' );
	assert.equal( read( { fontFamily: 'inter' } ).font, 'inter', 'with no list the value is kept' );
	assert.deepEqual( read( { textVariants: [ { pt_BR: 'Ola', es_ES: 'Hola' } ] } ).variants.pt_BR, [ { text: 'Ola' } ] );
	assert.deepEqual( read( { textVariants: [ { pt_BR: 'Ola' }, { es_ES: 'Hola' } ] } ).variants.es_ES, [ { text: 'Hola' } ], 'one object by language is read too' );
	assert.equal( read( { metadata: { name: 'Body', blockVisibility: false } } ).hidden, true );
	assert.equal( read( { anchor: 'title' } ).id, 'title' );
} );

test( 'some elements are block markup with no root, and read back', () => {
	const elements = [
		{ id: 'title', type: 'text', x: 1, y: 2, w: 100, h: 20, runs: [ { text: 'Hi ' }, { field: 'name', b: true } ], size: 13 },
		{ id: 'group-2', type: 'group', x: 0, y: 0, w: 50, h: 50, children: [ { id: 'group-2-rect-1', type: 'rect', x: 0, y: 0, w: 5, h: 5 } ] },
	];
	const markup = elementsToMarkup( elements );
	assert.ok( ! markup.includes( 'wp:events-certificates/certificate' ), 'no root block' );
	assert.equal( ( parse( markup ) || [] ).length, 2, 'a block for each element' );
	const back = elementsFromMarkup( markup );
	assert.deepEqual( back, elements );
	assert.equal( back[ 1 ].children[ 0 ].id, 'group-2-rect-1' );
	// A whole certificate gives its front elements only.
	const design = { page: { w: 100, h: 100 }, elements, back: { elements: [ { id: 'x', type: 'rect' } ] } };
	assert.deepEqual( elementsFromMarkup( toMarkup( design ) ), elements );
	// What is not ours is ignored, and nothing is none.
	assert.deepEqual( elementsFromMarkup( '<!-- wp:core/paragraph --><p>x</p><!-- /wp:core/paragraph -->' + markup ), elements );
	assert.equal( elementsFromMarkup( '<!-- wp:core/paragraph --><p>x</p><!-- /wp:core/paragraph -->' ), null );
	assert.equal( elementsFromMarkup( '' ), null );
	assert.equal( elementsFromMarkup( '<!-- wp:draw/text {"x":} /-->' ), null, 'markup that is not valid' );
	// The name a block says in `metadata` is the name of the element.
	assert.equal( elementsFromMarkup( '<!-- wp:draw/rect {"metadata":{"name":"Frame"}} /-->' )[ 0 ].name, 'Frame' );
} );

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
	assert.ok( markup.includes( '"width":"30pt","height":"20pt"' ) );
	assert.ok( markup.includes( '"layout":{"horizontal":{"mode":"stretch"},"vertical":{"mode":"start"},"reference":"safe"}' ) );
	assert.ok( markup.includes( '"viewBox":"0 0 24 24"' ) );
	assert.ok( markup.includes( '"typography":{"lineHeight":"1.2","letterSpacing":"0.5pt"}' ) );
	assert.ok( markup.includes( '"text":"**a***[[name]]*"' ) && ! markup.includes( '"runs"' ), 'A text is written as text.' );
	assert.ok( markup.includes( '"x1":"0pt"' ), 'the coordinates of a line are lengths with their unit' );
	assert.ok( markup.includes( '"anchor":"title"' ), 'an id that means something is the anchor' );
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
	assert.match( markup, /<!-- wp:draw\/rect \{"width":"5pt","height":"5pt"\} \/-->/, 'its children are inner blocks' );
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
	assert.ok( markup.includes( '"text":"Held in **[[location]]**"' ) && markup.includes( '"textVariants":[{"pt_BR":"Em *[[location]]*"}]' ) );
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
	assert.ok( markup.includes( 'wp:a/date-of-emission {"anchor":"d","text":"[[issued_date]]"}' ), markup );
	assert.ok( markup.includes( '"slot":"other"' ), 'Another slot stays an attribute of the text.' );
	assert.deepEqual( fromMarkup( markup, options ), design );
} );

test( 'a group says no id in a file (nothing points at it), whatever its id looks like; an element that means something keeps its own', () => {
	const design = {
		page: { width: '100mm', height: '50mm' },
		elements: [
			{ id: '0503d4b3-a2fc-431d-9417-5b874edb1ead', type: 'group', x: 0, y: 0, w: 50, h: 20, children: [
				{ id: '0503d4b3-a2fc-431d-9417-5b874edb1ead-group-1', type: 'group', x: 0, y: 0, w: 10, h: 10, children: [ { id: 'signature', type: 'signature', x: 0, y: 0, w: 10, h: 10 } ] },
				{ id: 'name', type: 'text', x: 0, y: 12, w: 40, h: 8, runs: [ { text: 'a' } ] },
			] },
		],
	};
	const markup = toMarkup( design );
	assert.equal( /certificate-group[^>]*"id"|draw\/group[^>]*"id"/.test( markup ), false, 'no id on a group: ' + markup );
	assert.ok( markup.includes( '"anchor":"signature"' ) && markup.includes( '"anchor":"name"' ), 'what means something stays' );
	const back = fromMarkup( markup );
	assert.equal( back.elements[ 0 ].id, 'group-1', 'made again by position' );
	assert.equal( back.elements[ 0 ].children[ 0 ].id, 'group-1-group-1' );
} );
