/**
 * Type declarations for WordPress AI components.
 */

/**
 * Global travAi object provided by WordPress localization.
 */
declare global {
	interface Window {
		travelopiaWpAi?: {
			api: {
				root: string;
				nonce: string;
			};
			nonces: {
				rest: string;
			};
			attachment: {
				id: number;
				altText: string;
				mode: 'default' | 'regenerate';
			};
			urls: {
				generate: string;
				regenerate: string;
				reject: string;
			};
			labels: {
				generateAltText: string;
				regenerateAltText: string;
				accept: string;
				reject: string;
				regenerate: string;
				saving: string;
			};
		};
		HTMLElement: typeof HTMLElement;
	}
}

// Export the global interface.
export {};
