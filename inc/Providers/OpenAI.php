<?php
/**
 * OpenAI Provider for Alt Text Generation.
 *
 * @package travelopia-wordpress-ai
 */

namespace Travelopia\WordPress_AI\Providers;

use Exception;
use WordPress\AiClient\AiClient;
use WordPress\AiClient\ProviderImplementations\OpenAi\OpenAiProvider;
use WP_Error;

/**
 * OpenAI provider class for generating alt text.
 */
class OpenAI
{
	/**
	 * Generate alt text for an image using OpenAI.
	 *
	 * @param string               $image_url Image URL.
	 * @param array<string, mixed> $options   Generation options.
	 *
	 * @return string|WP_Error Generated alt text on success, WP_Error on failure.
	 */
	public static function generate_alt_text( string $image_url = '', array $options = [] ): WP_Error|string
	{
		// Validate API key.
		$api_key_error = self::validate_api_key();

		if ( $api_key_error instanceof WP_Error ) {
			return $api_key_error;
		}

		// Generate AI response.
		try {
			$prompt_value       = $options['prompt'] ?? '';
			$prompt             = is_string( $prompt_value ) ? $prompt_value : '';
			$model              = (string) ( $options['model'] ?? 'gpt-4o-mini' );
			$temp_value         = $options['temperature'] ?? 0.1;
			$temperature        = is_numeric( $temp_value ) ? floatval( $temp_value ) : 0.1;
			$system_instruction = (string) ( $options['system_instruction'] ?? '' );

			$alt_text = AiClient::prompt( $prompt )
				->usingModel( OpenAiProvider::model( $model ) )
				->usingTemperature( $temperature )
				->withFile( $image_url )
				->usingSystemInstruction( $system_instruction )
				->generateText();

			// Process and validate generated text.
			$alt_text = sanitize_text_field( trim( wp_strip_all_tags( $alt_text ) ) );

			// Validate generated text is not empty.
			if ( empty( $alt_text ) ) {
				return self::create_error(
					'travelopia_wordpress_ai_open_ai_alt_text_empty_response',
					__( 'AI generated empty response', 'travelopia-wordpress-ai' ),
				);
			}

			return $alt_text;
		} catch ( Exception $e ) {
			return self::create_error(
				'travelopia_wordpress_ai_open_ai_alt_text_generation_failed',
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
	 * Validate OpenAI API key availability.
	 *
	 * @return true|WP_Error True if valid, WP_Error if invalid.
	 */
	public static function validate_api_key(): true|WP_Error
	{
		// Check API key availability.
		if ( ! defined( 'OPENAI_API_KEY' ) && ! getenv( 'OPENAI_API_KEY' ) ) {
			return self::create_error(
				'api_key_not_configured',
				__( 'OpenAI API key not configured', 'travelopia-wordpress-ai' ),
			);
		}

		// Get the API key.
		$api_key = defined( 'OPENAI_API_KEY' ) ? constant( 'OPENAI_API_KEY' ) : getenv( 'OPENAI_API_KEY' );

		if ( empty( $api_key ) ) {
			return self::create_error(
				'api_key_empty',
				__( 'OpenAI API key is empty', 'travelopia-wordpress-ai' ),
			);
		}

		return true;
	}

	/**
	 * Get default generation options.
	 *
	 * @return array<string, mixed> Default options.
	 */
	public static function get_default_options(): array
	{
		return [
			'model'              => 'gpt-4o-mini',
			'temperature'        => 0.1,
			'system_instruction' => 'You are an accessibility tool.',
		];
	}

	/**
	 * Create a standardized WP_Error for OpenAI operations.
	 *
	 * @param string               $error_code    Error code.
	 * @param string               $error_message Error message.
	 * @param array<string, mixed> $error_data    Additional error data.
	 *
	 * @return WP_Error
	 */
	private static function create_error( string $error_code = '', string $error_message = '', array $error_data = [] ): WP_Error
	{
		$error = new WP_Error( $error_code, $error_message, $error_data );
		do_action( 'travelopia_wordpress_ai_open_ai_error', $error_code, $error_message, $error_data );
		return $error;
	}
}
