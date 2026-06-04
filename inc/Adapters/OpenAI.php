<?php
/**
 * OpenAI Adapter for Alt Text Generation.
 *
 * @package travelopia-wordpress-ai
 */

namespace Travelopia\WordPress_AI\Adapters;

use WP_Error;

/**
 * OpenAI adapter class for generating alt text.
 */
class OpenAI extends AbstractAiAdapter
{
	/**
	 * Error action hook name.
	 *
	 * @var string
	 */
	protected const ERROR_ACTION = 'travelopia_wordpress_ai_open_ai_error';

	/**
	 * Error code for empty responses.
	 *
	 * @var string
	 */
	protected const ERROR_CODE_EMPTY = 'travelopia_wordpress_ai_open_ai_alt_text_empty_response';

	/**
	 * Error code for generation failures.
	 *
	 * @var string
	 */
	protected const ERROR_CODE_FAILED = 'travelopia_wordpress_ai_open_ai_alt_text_generation_failed';

	/**
	 * Validate OpenAI API key availability.
	 *
	 * @return true|WP_Error True if valid, WP_Error if invalid.
	 */
	public static function validate_api_key(): true|WP_Error
	{
		// Check API key availability.
		if ( ! defined( 'OPENAI_API_KEY' ) && ! getenv( 'OPENAI_API_KEY' ) ) {
			return static::create_error(
				'api_key_not_configured',
				__( 'OpenAI API key not configured', 'travelopia-wordpress-ai' ),
			);
		}

		// Get the API key.
		$api_key = defined( 'OPENAI_API_KEY' ) ? constant( 'OPENAI_API_KEY' ) : getenv( 'OPENAI_API_KEY' );

		if ( empty( $api_key ) ) {
			return static::create_error(
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
	 * Call the AI client to generate text.
	 *
	 * @param string $prompt             The prompt.
	 * @param string $model              The model ID.
	 * @param float  $temperature        The temperature.
	 * @param string $image_url          The image URL.
	 * @param string $system_instruction The system instruction.
	 *
	 * WordPress core's bundled AI client ships no concrete OpenAI provider yet, and
	 * the upstream OpenAI provider package still targets php-ai-client 0.4. Until a
	 * compatible provider exists this adapter cannot generate, so it returns an empty
	 * string and callers surface the standard empty-response error. Bedrock is the
	 * supported provider (see Adapters\Bedrock).
	 *
	 * @return string The generated text.
	 */
	protected static function call_ai_client( string $prompt, string $model, float $temperature, string $image_url, string $system_instruction ): string
	{
		return '';
	}
}
