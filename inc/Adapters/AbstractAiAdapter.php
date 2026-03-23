<?php
/**
 * Abstract AI Adapter.
 *
 * @package travelopia-wordpress-ai
 */

namespace Travelopia\WordPress_AI\Adapters;

use Exception;
use WP_Error;

/**
 * Abstract base class for AI adapters.
 */
abstract class AbstractAiAdapter
{
	/**
	 * Boot the adapter. Called once during registration.
	 *
	 * Subclasses can override this to perform one-time setup such as
	 * registering external AiClient providers.
	 *
	 * @return void
	 */
	public static function boot(): void
	{
	}

	/**
	 * Error action hook prefix. Subclasses should override this.
	 *
	 * @var string
	 */
	protected const ERROR_ACTION = '';

	/**
	 * Error code prefix for empty responses. Subclasses should override this.
	 *
	 * @var string
	 */
	protected const ERROR_CODE_EMPTY = '';

	/**
	 * Error code prefix for generation failures. Subclasses should override this.
	 *
	 * @var string
	 */
	protected const ERROR_CODE_FAILED = '';

	/**
	 * Generate alt text for an image.
	 *
	 * @param string               $image_url Image URL.
	 * @param array<string, mixed> $options   Generation options.
	 *
	 * @return string|WP_Error Generated alt text on success, WP_Error on failure.
	 */
	public static function generate_alt_text( string $image_url = '', array $options = [] ): WP_Error|string
	{
		// Validate API key.
		$api_key_error = static::validate_api_key();

		if ( $api_key_error instanceof WP_Error ) {
			return $api_key_error;
		}

		// Generate AI response.
		try {
			$defaults           = static::get_default_options();
			$prompt_value       = $options['prompt'] ?? '';
			$prompt             = is_string( $prompt_value ) ? $prompt_value : '';
			$model              = (string) ( $options['model'] ?? $defaults['model'] ?? '' );
			$temp_value         = $options['temperature'] ?? $defaults['temperature'] ?? 0.1;
			$temperature        = is_numeric( $temp_value ) ? floatval( $temp_value ) : 0.1;
			$system_instruction = (string) ( $options['system_instruction'] ?? $defaults['system_instruction'] ?? '' );

			$alt_text = static::call_ai_client( $prompt, $model, $temperature, $image_url, $system_instruction );

			// Process and validate generated text.
			$alt_text = sanitize_text_field( trim( wp_strip_all_tags( $alt_text ) ) );

			// Validate generated text is not empty.
			if ( empty( $alt_text ) ) {
				return static::create_error(
					static::ERROR_CODE_EMPTY,
					__( 'AI generated empty response', 'travelopia-wordpress-ai' ),
				);
			}

			return $alt_text;
		} catch ( Exception $e ) {
			return static::create_error(
				static::ERROR_CODE_FAILED,
				sprintf(
					/* translators: %s: error message */
					__( 'AI generation failed: %s', 'travelopia-wordpress-ai' ),
					$e->getMessage(),
				),
				[ 'exception' => $e->getMessage() ],
			);
		}
	}

	/**
	 * Get default generation options.
	 *
	 * @return array<string, mixed> Default options.
	 */
	abstract public static function get_default_options(): array;

	/**
	 * Validate API key availability.
	 *
	 * @return true|WP_Error True if valid, WP_Error if invalid.
	 */
	abstract public static function validate_api_key(): true|WP_Error;

	/**
	 * Call the AI client to generate text.
	 *
	 * @param string $prompt             The prompt.
	 * @param string $model              The model ID.
	 * @param float  $temperature        The temperature.
	 * @param string $image_url          The image URL.
	 * @param string $system_instruction The system instruction.
	 *
	 * @return string The generated text.
	 */
	abstract protected static function call_ai_client( string $prompt, string $model, float $temperature, string $image_url, string $system_instruction ): string;

	/**
	 * Create a standardized WP_Error.
	 *
	 * @param string               $error_code    Error code.
	 * @param string               $error_message Error message.
	 * @param array<string, mixed> $error_data    Additional error data.
	 *
	 * @return WP_Error
	 */
	protected static function create_error( string $error_code = '', string $error_message = '', array $error_data = [] ): WP_Error
	{
		$error = new WP_Error( $error_code, $error_message, $error_data );
		do_action( static::ERROR_ACTION, $error_code, $error_message, $error_data );
		return $error;
	}
}
