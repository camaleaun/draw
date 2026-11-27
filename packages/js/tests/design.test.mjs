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
