/**
 * Alt Text Generator Component
 *
 * Handles Generate and Regenerate actions for default mode.
 * Renders as an anchor tag with appropriate href based on alt text existence.
 */

/**
 * AltTextGenerator Class
 */
class AltTextGenerator extends HTMLElement {
	private attachmentId: string;
	private mode: string;
	private href: string = '';

	/**
	 * Constructor.
	 */
	constructor() {
		// Initialize parent class.
		super();

		// Get initial attributes.
		this.attachmentId = this.getAttribute( 'attachment-id' ) || '';
		this.mode = this.getAttribute( 'mode' ) || 'default';
	}

	/**
	 * Called when element is connected to the DOM.
	 */
	connectedCallback(): void {
		// Render the component when connected.
		this.render();
	}

	/**
	 * Render the generate/regenerate link.
	 */
	private render(): void {
		// Clear existing content.
		this.innerHTML = '';

		// Check if we have the necessary data.
		if (
			! window.travelopiaWpAi ||
			! window.travelopiaWpAi.attachment ||
			! window.travelopiaWpAi.urls
		) {
			// Exit early if required data is not available.
			return;
		}

		// Extract data from global travelopiaWpAi object.
		const { attachment, urls, labels } = window.travelopiaWpAi;
		const { altText } = attachment;
		const isEmpty = ! altText || altText.trim() === '';

		// Create the link element.
		this.className = 'button button-primary';
		this.href = isEmpty ? urls.generate : urls.regenerate;
		this.textContent = isEmpty
			? labels.generateAltText
			: labels.regenerateAltText;

		// Add click event listener.
		this.addEventListener( 'click', this.handleClick.bind( this ) );
	}

	/**
	 * Handle click event.
	 *
	 * @param {Event} _event Click event.
	 */
	private handleClick( _event: Event ): void {
		// Dispatch custom event before navigation.
		const customEvent = new CustomEvent( 'alttext:generate', {
			bubbles: true,
			detail: {
				attachmentId: this.attachmentId,
				mode: this.mode,
				href: this.href || '#',
			},
		} );

		// Dispatch the custom event.
		this.dispatchEvent( customEvent );

		// Navigate to the generate/regenerate URL.
		window.location.href = this.href;
	}
}

/**
 * Define custom element.
 */
window.customElements.define( 'alt-text-generator', AltTextGenerator );
