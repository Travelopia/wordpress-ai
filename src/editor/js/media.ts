/**
 * MediaEditor class.
 */
class MediaEditor {
	private acceptButton: HTMLButtonElement | null;

	/**
	 * Construct the MediaEditor class.
	 *
	 * @return {void}
	 */
	constructor() {
		// DOM Elements.
		this.acceptButton = document.querySelector( 'button[value="accept"]' );

		// Events.
		if ( this.acceptButton ) {
			// Add event listener to the accept button.
			this.acceptButton?.addEventListener(
				'click',
				this.handleAcceptButtonClick.bind( this ),
			);
		}
	}

	/**
	 * Accept the new alt text and update it in the database via AJAX.
	 *
	 * @return {void}
	 */
	private handleAcceptButtonClick() {
		// Get the attachment ID from the URL params.
		const attachmentId = new URLSearchParams( window.location.search ).get(
			'post',
		);

		// Get the alt text value from the textarea.
		const altTextElement = document.querySelector(
			'textarea[name="_wp_attachment_image_alt"][id="attachment_alt"]',
		) as HTMLTextAreaElement;

		// Get the alt text value from the textarea.
		const altText = altTextElement?.value || '';

		// Check if the attachment ID is valid.
		if ( ! attachmentId ) {
			// Bail early.
			return;
		}

		// Disable the button during the request.
		if ( this.acceptButton ) {
			this.acceptButton.disabled = true;
			this.acceptButton.textContent = 'Saving...';
		}

		// Get AJAX URL and nonce (with fallbacks).
		const ajaxUrl = travAi?.ajax?.url || '/wp-admin/admin-ajax.php';
		const nonce = travAi?.nonces?.modifyAltText || '';

		// Check if the nonce is valid.
		if ( ! nonce ) {
			// Re-enable the button.
			if ( this.acceptButton ) {
				this.acceptButton.disabled = false;
				this.acceptButton.textContent = 'Accept';
			}

			// Bail.
			return;
		}

		// Prepare form data for the AJAX request.
		const formData = new FormData();
		formData.append( 'action', 'modify_attachment_alt_text' );
		formData.append( 'attachment_id', attachmentId );
		formData.append( 'alt_text', altText );
		formData.append( 'nonce', nonce );

		// Make the AJAX request.
		fetch( ajaxUrl, {
			method: 'POST',
			body: formData,
			credentials: 'same-origin',
		} )
			.then( ( response ) => response.json() )
			.then( ( data ) => {
				// Check if the request was successful.
				if ( data.success ) {
					// Success - redirect to clean URL without regeneration params.
					const cleanUrl = new URL( window.location.href );
					cleanUrl.searchParams.delete( 'tp_regenerate_alt_text' );
					cleanUrl.searchParams.delete( 'tp_nonce' );

					// Redirect to clean URL.
					window.location.href = cleanUrl.toString();
				} else if ( this.acceptButton ) {
					this.acceptButton.disabled = false;
					this.acceptButton.textContent = 'Accept';
				}
			} )
			.catch( () => {
				// Re-enable the button.
				if ( this.acceptButton ) {
					this.acceptButton.disabled = false;
					this.acceptButton.textContent = 'Accept';
				}
			} );
	}
}

// Export the MediaEditor class.
export default MediaEditor;
