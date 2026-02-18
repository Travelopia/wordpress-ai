/**
 * Settings page entry point.
 */
import domReady from '@wordpress/dom-ready';
import { createRoot } from '@wordpress/element';

import { SettingsPage } from './components';

import './index.css';

domReady( () => {
	const container = document.getElementById( 'travelopia-wp-ai-settings' );

	if ( container ) {
		const root = createRoot( container );
		root.render( <SettingsPage /> );
	}
} );
