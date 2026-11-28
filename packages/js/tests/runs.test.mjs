import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { parseRuns, runsToText, normalizeRuns } from '../dist/index.js';

const cases = JSON.parse( readFileSync( new URL( '../../fixtures/runs.json', import.meta.url ), 'utf8' ) );

test( 'text is read as runs (the fixtures PHP reads too)', () => {
	for ( const c of [ ...cases.roundtrip, ...cases.parse ] ) {
		const fields = 'fields' in c ? c.fields ?? undefined : cases.fields;
		assert.deepEqual( parseRuns( c.text, { fields } ), c.runs, JSON.stringify( c.text ) );
	}
} );

test( 'runs are written as the text that reads back as them', () => {
	for ( const c of cases.roundtrip ) {
		assert.equal( runsToText( c.runs ), c.text, JSON.stringify( c.text ) );
		assert.deepEqual( parseRuns( runsToText( c.runs ), { fields: cases.fields } ), normalizeRuns( c.runs ) );
	}
} );

test( 'runs with empty text, false marks and the default format are the same as without', () => {
	assert.equal( runsToText( [ { text: '' }, { text: 'a', b: false }, { text: 'b' }, { field: 'issued_date', format: 'site' } ] ), 'ab[[issued_date]]' );
} );
