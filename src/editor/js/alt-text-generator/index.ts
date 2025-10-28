/**
 * Alt Text Components Entry Point
 *
 * Imports all components and initializes the alt text UI.
 */

// Import all individual components.
import './alt-text-container';
import './alt-text-generator';
import './alt-text-accept';
import './alt-text-reject';

/**
 * Initialize alt text action buttons on DOM ready.
 */
window.addEventListener( 'DOMContentLoaded', () => {
	// Get the travelopiaWpAi object from window.
	const { travelopiaWpAi } = window;

	// Check if we have the necessary data.
	if ( ! travelopiaWpAi?.attachment ) {
		// Exit early if attachment data is not available.
		return;
	}

	// Find the WordPress alt text textarea.
	const textarea = document.querySelector<HTMLTextAreaElement>(
		'textarea[name="_wp_attachment_image_alt"]#attachment_alt',
	);

	// Exit early if textarea is not found.
	if ( ! textarea ) {
		// Textarea element not found in DOM.
		return;
	}

	// Extract attachment data from travelopiaWpAi object.
	const { id, altText, mode } = travelopiaWpAi.attachment;

	// Update textarea if needed.
	if ( altText && textarea.value !== altText ) {
		textarea.value = altText;
	}

	// Create wrapper for textarea and buttons.
	const wrapper = document.createElement( 'div' );
	wrapper.className = 'alt-text-wrapper';

	// Get the textarea's parent element.
	const parent = textarea.parentElement;

	// Check if parent element is found.
	if ( ! parent ) {
		// Exit early if parent element not found.
		return;
	}

	// Insert wrapper before textarea.
	parent.insertBefore( wrapper, textarea );
	wrapper.appendChild( textarea );

	// Create and append container component.
	const container = document.createElement( 'alt-text-container' );
	container.setAttribute( 'mode', mode );
	container.setAttribute( 'attachment-id', id.toString() );
	wrapper.appendChild( container );
} );
