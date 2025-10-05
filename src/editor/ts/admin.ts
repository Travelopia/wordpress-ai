/**
 * Travelopia WordPress AI Admin TypeScript
 */

// On DOM ready.
document.addEventListener( 'DOMContentLoaded', (): void => {
	// Get references to the checkbox and textarea elements.
	const enableCheckbox = document.getElementById( 'ai-alt-text-enabled' ) as HTMLInputElement | null;
	const promptTextarea = document.getElementById( 'ai-alt-text-prompt' ) as HTMLTextAreaElement | null;

	// Toggles the disabled state of the prompt textarea based on the checkbox state.
	function togglePromptField(): void {
		// Toggle.
		if ( promptTextarea ) {
			promptTextarea.disabled = ! enableCheckbox?.checked;
		}
	}

	// Initialize the prompt textarea state.
	if ( enableCheckbox && promptTextarea ) {
		enableCheckbox.addEventListener( 'change', togglePromptField );
		togglePromptField(); // Set initial state
	}
} );
