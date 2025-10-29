/**
 * Alt Text Container Component
 *
 * Parent container that manages mode-specific visibility and component coordination.
 * Handles conditional rendering of child components based on current mode.
 */

/**
 * AltTextContainer Class
 */
class AltTextContainer extends window.HTMLElement {
	private attachmentId: string;
	private mode: string;

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
	 * Render child components based on current mode.
	 */
	private render(): void {
		// Render components based on mode.
		if ( this.mode === 'regenerate' ) {
			this.renderRegenerateMode();
		} else {
			this.renderDefaultMode();
		}
	}

	/**
	 * Render components for default mode (generate/regenerate button).
	 */
	private renderDefaultMode(): void {
		// Create and configure generator component.
		const generator = document.createElement( 'alt-text-generator' );
		generator.setAttribute( 'attachment-id', this.attachmentId );
		generator.setAttribute( 'mode', this.mode );
		this.appendChild( generator );
	}

	/**
	 * Render components for regenerate mode (accept, reject, regenerate buttons).
	 */
	private renderRegenerateMode(): void {
		// Create accept button.
		const acceptButton = document.createElement( 'alt-text-accept' );
		acceptButton.setAttribute( 'attachment-id', this.attachmentId );
		acceptButton.setAttribute( 'mode', this.mode );
		this.appendChild( acceptButton );

		// Create reject button.
		const rejectButton = document.createElement( 'alt-text-reject' );
		rejectButton.setAttribute( 'attachment-id', this.attachmentId );
		rejectButton.setAttribute( 'mode', this.mode );
		this.appendChild( rejectButton );

		// Create regenerate button.
		const regenerateButton = document.createElement( 'alt-text-generator' );
		regenerateButton.setAttribute( 'attachment-id', this.attachmentId );
		regenerateButton.setAttribute( 'mode', this.mode );
		this.appendChild( regenerateButton );
	}

	/**
	 * Observed attributes.
	 */
	static get observedAttributes(): string[] {
		// Return list of attributes to watch for changes.
		return [ 'mode', 'attachment-id' ];
	}

	/**
	 * Attribute changed callback.
	 *
	 * @param {string} name     Name.
	 * @param {string} oldValue Old value.
	 * @param {string} newValue New value.
	 */
	attributeChangedCallback(
		name: string,
		oldValue: string,
		newValue: string,
	): void {
		// Check if the value has changed.
		if ( oldValue === newValue ) {
			// No change detected, exit early.
			return;
		}

		// Handle attribute changes based on which attribute changed.
		switch ( name ) {
			case 'mode':
				this.mode = newValue;
				this.render();
				break;

			// Handle attachment-id attribute change.
			case 'attachment-id':
				this.attachmentId = newValue;

				// Update attachment-id on all child components.
				this.updateChildAttachmentIds();
				break;
		}
	}

	/**
	 * Update attachment-id on all child components.
	 */
	private updateChildAttachmentIds(): void {
		// Get all child elements.
		const children = this.children;

		// Loop through all child components.
		for ( const child of Array.from( children ) ) {
			// Update attachment-id on the child component.
			child.setAttribute( 'attachment-id', this.attachmentId );
		}
	}
}

/**
 * Define custom element.
 */
window.customElements.define( 'alt-text-container', AltTextContainer );
