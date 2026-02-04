/**
 * Alt Text Generator Component
 *
 * Simple Web Component for generating alt text on attachment edit screen.
 * Makes AJAX call to WordPress admin-ajax.php endpoint.
 */

/**
 * Global data provided by WordPress via wp_localize_script.
 */
declare global {
	interface Window {
		travelopiaWpAi?: {
			attachmentId: number;
			currentAltText: string;
			restUrl: string;
			nonce: string;
			labels: {
				generate: string;
				regenerate: string;
				saving: string;
			};
		};
	}
}

/**
 * Alt Text Generator Web Component
 */
class AltTextGenerator extends HTMLElement {
	private attachmentId: number = 0;
	private currentAltText: string = '';
	private restUrl: string = '';
	private restNonce: string = '';
	private labels: { generate: string; regenerate: string; saving: string } = {
		generate: 'Generate Alt Text',
		regenerate: 'Regenerate Alt Text',
		saving: 'Generating...',
	};
	private textarea: HTMLTextAreaElement | null = null;

	/**
	 * Constructor
	 */
	constructor() {
		super();

		// Get data from global object
		const data = window.travelopiaWpAi;
		if ( data ) {
			this.attachmentId = data.attachmentId;
			this.currentAltText = data.currentAltText;
			this.restUrl = data.restUrl;
			this.restNonce = data.nonce;
			this.labels = data.labels;
		}
	}

	/**
	 * Called when element is connected to the DOM
	 */
	connectedCallback(): void {
		// Find the WordPress alt text textarea
		this.textarea = document.querySelector< HTMLTextAreaElement >(
			'textarea[name="_wp_attachment_image_alt"]#attachment_alt'
		);

		if ( ! this.textarea ) {
			return;
		}

		// Render the button
		this.render();
	}

	/**
	 * Render the generate button
	 */
	private render(): void {
		// Clear existing content
		this.innerHTML = '';

		// Determine button text based on whether alt text exists
		const hasAltText =
			this.currentAltText && this.currentAltText.trim() !== '';
		const buttonText = hasAltText
			? this.labels.regenerate
			: this.labels.generate;

		// Create button
		const button = document.createElement( 'button' );
		button.type = 'button';
		button.className = 'button button-primary';
		button.textContent = buttonText;
		button.addEventListener( 'click', this.handleClick.bind( this ) );

		// Add button to component
		this.appendChild( button );
	}

	/**
	 * Handle button click
	 *
	 * @param {Event} event Click event.
	 */
	private async handleClick( event: Event ): Promise< void > {
		event.preventDefault();

		const button = event.target as HTMLButtonElement;
		const originalText = button.textContent || '';

		// Disable button and show loading state
		button.disabled = true;
		button.textContent = this.labels.saving;

		try {
			// Make REST API request
			const response = await fetch( this.restUrl, {
				method: 'POST',
				headers: {
					'Content-Type': 'application/json',
					'X-WP-Nonce': this.restNonce,
				},
				body: JSON.stringify( {
					post_id: this.attachmentId,
				} ),
			} );

			const data = await response.json();

			if ( data.success && data.alt_text ) {
				// Update textarea with generated alt text
				if ( this.textarea ) {
					this.textarea.value = data.alt_text;

					// Trigger change event so WordPress knows the field changed
					this.textarea.dispatchEvent( new Event( 'change' ) );
				}

				// Update button text to "Regenerate" since we now have alt text
				button.textContent = this.labels.regenerate;
				button.disabled = false;

				// Show success message briefly
				this.showMessage(
					data.message || 'Alt text generated successfully!',
					'success'
				);
			} else {
				// Handle error
				const errorMessage =
					data.message || 'Failed to generate alt text';
				this.showMessage( errorMessage, 'error' );

				// Re-enable button with original text
				button.disabled = false;
				button.textContent = originalText;
			}
		} catch ( error ) {
			// Handle network error
			this.showMessage( 'Network error. Please try again.', 'error' );

			// Re-enable button with original text
			button.disabled = false;
			button.textContent = originalText;
		}
	}

	/**
	 * Show a temporary message
	 *
	 * @param {string}            message Message text.
	 * @param {'success'|'error'} type    Message type.
	 */
	private showMessage( message: string, type: 'success' | 'error' ): void {
		// Create message element
		const messageEl = document.createElement( 'div' );
		messageEl.className = `notice notice-${ type } is-dismissible`;
		messageEl.style.marginTop = '10px';
		messageEl.innerHTML = `<p>${ message }</p>`;

		// Add message after button
		this.appendChild( messageEl );

		// Remove message after 3 seconds
		setTimeout( () => {
			messageEl.remove();
		}, 3000 );
	}
}

/**
 * Initialize on DOM ready
 */
window.addEventListener( 'DOMContentLoaded', () => {
	// Check if we have the necessary data
	if ( ! window.travelopiaWpAi ) {
		return;
	}

	// Find the WordPress alt text textarea
	const textarea = document.querySelector< HTMLTextAreaElement >(
		'textarea[name="_wp_attachment_image_alt"]#attachment_alt'
	);

	if ( ! textarea ) {
		return;
	}

	// Create wrapper for textarea and button
	const wrapper = document.createElement( 'div' );
	wrapper.className = 'alt-text-generator-wrapper';
	wrapper.style.marginTop = '10px';

	// Get the textarea's parent element
	const parent = textarea.parentElement;

	if ( ! parent ) {
		return;
	}

	// Insert wrapper after textarea
	parent.insertBefore( wrapper, textarea.nextSibling );

	// Register custom element
	if ( ! window.customElements.get( 'alt-text-generator' ) ) {
		window.customElements.define( 'alt-text-generator', AltTextGenerator );
	}

	// Create and append component
	const generator = document.createElement( 'alt-text-generator' );
	wrapper.appendChild( generator );
} );

export {};
