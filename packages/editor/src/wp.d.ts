// The WordPress packages come from the page (wp.*) at run time; here they are only named.
declare module '@wordpress/data';
declare module '@wordpress/block-editor';
declare module '@wordpress/blocks';
declare module '@wordpress/components';
declare module '@wordpress/i18n';
declare module '@wordpress/element' {
	import * as React from 'react';
	export const createElement: typeof React.createElement;
	export const useRef: typeof React.useRef;
	export const useState: typeof React.useState;
}
