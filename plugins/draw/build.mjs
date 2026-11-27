// Builds assets/editor.js: the blocks' editor, with the WordPress packages left to the page (wp.*) and the draw packages of this
// repository bundled in. Run from the repository root: npm run build:plugin
import * as esbuild from 'esbuild';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const here = path.dirname( fileURLToPath( import.meta.url ) );
const root = path.join( here, '..', '..' );
const globals = { 'element': 'wp.element', 'block-editor': 'wp.blockEditor', 'blocks': 'wp.blocks', 'components': 'wp.components', 'i18n': 'wp.i18n', 'data': 'wp.data' };

const wordpress = {
	name: 'wordpress-externals',
	setup( build ) {
		build.onResolve( { filter: /^@wordpress\// }, ( args ) => ( { path: args.path, namespace: 'wp-global' } ) );
		build.onLoad( { filter: /.*/, namespace: 'wp-global' }, ( args ) => {
			const global = globals[ args.path.replace( '@wordpress/', '' ) ];
			return { contents: global ? `module.exports = window.${ global };` : 'module.exports = {};', loader: 'js' };
		} );
	},
};

await esbuild.build( {
	entryPoints: [ path.join( here, 'src/index.tsx' ) ],
	outfile: path.join( here, 'assets/editor.js' ),
	bundle: true,
	format: 'iife',
	target: 'es2019',
	jsx: 'transform',
	jsxFactory: 'createElement',
	minify: false,
	alias: {
		'@camaleaun/draw': path.join( root, 'packages/js/src/index.ts' ),
		'@camaleaun/draw-editor': path.join( root, 'packages/editor/src/index.ts' ),
	},
	plugins: [ wordpress ],
} );
console.log( 'assets/editor.js built' );
