<?php
/**
 * AI Adapter Interface.
 *
 * @package travelopia-wordpress-ai
 */

namespace Travelopia\WordPress_AI\Adapters;

use WP_Error;

/**
 * Interface for AI adapters.
 */
interface AiAdapter
{
	/**
	 * Generate alt text for an image.
	 *
	 * @param string               $image_url Image URL.
	 * @param array<string, mixed> $options   Generation options.
	 *
	 * @return string|WP_Error Generated alt text on success, WP_Error on failure.
	 */
	public static function generate_alt_text( string $image_url, array $options ): string|WP_Error;

	/**
	 * Get default generation options.
	 *
	 * @return array<string, mixed> Default options.
	 */
	public static function get_default_options(): array;

	/**
	 * Validate API key availability.
	 *
	 * @return true|WP_Error True if valid, WP_Error if invalid.
	 */
	public static function validate_api_key(): true|WP_Error;
}
