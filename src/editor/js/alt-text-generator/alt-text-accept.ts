/**
 * Alt Text Accept Component
 *
 * Handles Accept action with REST API integration.
 * Manages API calls, loading states, and URL redirects.
 */

/**
 * AltTextAccept Class
 */
class AltTextAccept extends HTMLElement {
	private attachmentId: string;

	/**
	 * Constructor.
	 */
	constructor() {
		// Initialize parent class.
		super();

		// Get initial attributes.
		this.attachmentId = this.getAttribute( 'attachment-id' ) || '';
	}

	/**
	 * Called when element is connected to the DOM.
	 */
	connectedCallback(): void {
		// Render the component when connected.
		this.render();
	}

	/**
	 * Render the accept button.
	 */
	private render(): void {
		// Check if we have the necessary data.
		if ( ! window.travelopiaWpAi || ! window.travelopiaWpAi.labels ) {
			// Exit early if data is not available.
			return;
		}

		// Extract labels from global travelopiaWpAi object.
		const { labels } = window.travelopiaWpAi;

		// Create the accept button.
		this.className = 'button button-primary';
		this.textContent = labels.accept || 'Accept';

		// Add click event listener.
		this.addEventListener( 'click', this.handleClick.bind( this ) );
	}

	/**
	 * Handle accept button click.
	 *
	 * @param {Event} event Click event.
	 */
	private async handleClick( event: Event ): Promise< void > {
		// Prevent default anchor behavior.
		event.preventDefault();

		// Get the attachment ID from URL params as fallback.
		const urlAttachmentId =
			new URLSearchParams( window.location.search ).get( 'post' ) || '';
		const attachmentId = this.attachmentId || urlAttachmentId;

		// Validate attachment ID exists.
		if ( ! attachmentId ) {
			this.dispatchErrorEvent( 'No attachment ID found' );

			// Exit early if no attachment ID.
			return;
		}

		// Get alt text from the WordPress textarea.
		const textarea = document.querySelector< HTMLTextAreaElement >(
			'textarea[name="_wp_attachment_image_alt"]#attachment_alt'
		);
		const altText = textarea?.value || '';

		// Get labels for button text.
		const savingText = window.travelopiaWpAi?.labels?.saving || 'Saving...';
		const acceptText = window.travelopiaWpAi?.labels?.accept || 'Accept';

		// Disable button during request.
		this.textContent = savingText;

		// Get REST API nonce.
		const restNonce = window.travelopiaWpAi?.nonces?.rest || '';

		// Validate REST API nonce exists.
		if ( ! restNonce ) {
			// Re-enable button.
			this.textContent = acceptText;
			this.dispatchErrorEvent( 'No REST API nonce found' );

			// Exit early if no REST nonce.
			return;
		}

		// Attempt to save alt text via REST API.
		try {
			// Use WordPress Media REST API.
			const restUrl = window.travelopiaWpAi?.api?.root || '/wp-json/';
			const mediaEndpoint = `${ restUrl }wp/v2/media/${ attachmentId }`;

			// Make REST API request.
			const response = await fetch( mediaEndpoint, {
				method: 'POST',
				headers: {
					'Content-Type': 'application/json',
					'X-WP-Nonce': restNonce,
				},
				body: JSON.stringify( {
					alt_text: altText,
				} ),
				credentials: 'same-origin',
			} );

			// Check if the response is successful.
			if ( response.ok ) {
				// Get response data for debugging.
				const responseData = await response.json();

				// Dispatch success event.
				this.dispatchSuccessEvent( responseData );

				// Success - redirect to clean edit URL without any special parameters.
				const currentUrl = new URL( window.location.href );
				const cleanUrl = `${ currentUrl.origin }${ currentUrl.pathname }?post=${ attachmentId }&action=edit`;

				// Use replace to avoid back button issues and force reload.
				window.location.replace( cleanUrl );
			} else {
				// Handle REST API error.
				let errorData;

				// Try to parse the response as JSON, if it fails, parse it as text.
				try {
					errorData = await response.json();
				} catch ( e ) {
					errorData = await response.text();
				}

				// Re-enable button on failure.
				this.textContent = acceptText;

				// Dispatch error event with API error details.
				this.dispatchErrorEvent(
					`API Error: ${ response.status }`,
					errorData
				);
			}
		} catch ( error ) {
			// Re-enable button on error.
			this.textContent = acceptText;

			// Dispatch error event with network error details.
			this.dispatchErrorEvent( 'Network error', error );
		}
	}

	/**
	 * Dispatch success event.
	 *
	 * @param {any} responseData Response data from API.
	 */
	private dispatchSuccessEvent( responseData: any ): void {
		// Create custom event for successful alt text acceptance.
		const customEvent = new CustomEvent( 'alttext:accepted', {
			bubbles: true,
			detail: {
				attachmentId: this.attachmentId,
				responseData,
			},
		} );

		// Dispatch the success event.
		this.dispatchEvent( customEvent );
	}

	/**
	 * Dispatch error event.
	 *
	 * @param {string} message Error message.
	 * @param {any}    data    Error data.
	 */
	private dispatchErrorEvent( message: string, data?: any ): void {
		// Create custom error event with error details.
		const customEvent = new CustomEvent( 'alttext:error', {
			bubbles: true,
			detail: {
				attachmentId: this.attachmentId,
				message,
				data,
				source: 'accept-button',
			},
		} );

		// Dispatch the error event.
		this.dispatchEvent( customEvent );
	}
}

/**
 * Define custom element.
 */
window.customElements.define( 'alt-text-accept', AltTextAccept );
