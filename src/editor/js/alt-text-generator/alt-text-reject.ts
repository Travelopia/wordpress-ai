/**
 * Alt Text Reject Component
 *
 * Handles Reject action with simple navigation.
 * Dispatches events for component communication.
 */

/**
 * AltTextReject Class
 */
class AltTextReject extends HTMLElement {
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
		this.mode = this.getAttribute( 'mode' ) || 'regenerate';
	}

	/**
	 * Called when element is connected to the DOM.
	 */
	connectedCallback(): void {
		// Render the component when connected.
		this.render();
	}

	/**
	 * Render the reject link.
	 */
	private render(): void {
		// Check if we have the necessary data.
		if ( ! window.travelopiaWpAi || ! window.travelopiaWpAi.urls || ! window.travelopiaWpAi.labels ) {
			// Exit early if required data is not available.
			return;
		}

		// Extract URLs and labels from global travAi object.
		const { urls, labels } = window.travelopiaWpAi;

		// Set the attributes.
		this.className = 'button button-primary';
		this.textContent = labels.reject || 'Reject';
		this.href = urls.reject || '#';

		// Add the click event listener.
		this.addEventListener( 'click', this.handleClick.bind( this ) );
	}

	/**
	 * Handle click event.
	 */
	private handleClick(): void {
		// Dispatch custom event before navigation.
		const customEvent = new CustomEvent( 'alttext:rejected', {
			bubbles: true,
			detail: {
				attachmentId: this.attachmentId,
				mode: this.mode,
				href: this.href || '#',
			},
		} );

		// Dispatch the custom event.
		this.dispatchEvent( customEvent );

		// Navigate to the reject URL.
		window.location.href = this.href;
	}
}

/**
 * Define custom element.
 */
window.customElements.define( 'alt-text-reject', AltTextReject );
