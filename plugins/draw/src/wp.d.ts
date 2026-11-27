declare module '@wordpress/block-editor';
declare module '@wordpress/blocks';
declare module '@wordpress/components';
declare module '@wordpress/i18n';
declare module '@wordpress/element' {
	import * as React from 'react';
	export const createElement: typeof React.createElement;
}
