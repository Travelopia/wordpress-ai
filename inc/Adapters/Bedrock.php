<?php
/**
 * Bedrock Adapter for Alt Text Generation.
 *
 * @package travelopia-wordpress-ai
 */

namespace Travelopia\WordPress_AI\Adapters;

use Aysnc\WordPress\PhpAiClientBedrock\AwsBedrockProvider;
use WordPress\AiClient\AiClient;
use WP_Error;

/**
 * AWS Bedrock adapter class for generating alt text.
 */
class Bedrock extends AbstractAiAdapter
{
	/**
	 * Error action hook name.
	 *
	 * @var string
	 */
	protected const ERROR_ACTION = 'travelopia_wordpress_ai_bedrock_error';

	/**
	 * Error code for empty responses.
	 *
	 * @var string
	 */
	protected const ERROR_CODE_EMPTY = 'travelopia_wordpress_ai_bedrock_alt_text_empty_response';

	/**
	 * Error code for generation failures.
	 *
	 * @var string
	 */
	protected const ERROR_CODE_FAILED = 'travelopia_wordpress_ai_bedrock_alt_text_generation_failed';

	/**
	 * Validate AWS Bedrock API key availability.
	 *
	 * @return true|WP_Error True if valid, WP_Error if invalid.
	 */
	public static function validate_api_key(): true|WP_Error
	{
		// Check API key availability.
		if ( ! defined( 'AWS_BEDROCK_API_KEY' ) && ! getenv( 'AWS_BEDROCK_API_KEY' ) ) {
			return static::create_error(
				'api_key_not_configured',
				__( 'AWS Bedrock API key not configured', 'travelopia-wordpress-ai' ),
			);
		}

		// Get the API key.
		$api_key = defined( 'AWS_BEDROCK_API_KEY' ) ? constant( 'AWS_BEDROCK_API_KEY' ) : getenv( 'AWS_BEDROCK_API_KEY' );

		if ( empty( $api_key ) ) {
			return static::create_error(
				'api_key_empty',
				__( 'AWS Bedrock API key is empty', 'travelopia-wordpress-ai' ),
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
			'model'              => 'anthropic.claude-3-5-sonnet-20241022-v2:0',
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
	 * @return string The generated text.
	 */
	protected static function call_ai_client( string $prompt, string $model, float $temperature, string $image_url, string $system_instruction ): string
	{
		return AiClient::prompt( $prompt )
			->usingModel( AwsBedrockProvider::model( $model ) )
			->usingTemperature( $temperature )
			->withFile( $image_url )
			->usingSystemInstruction( $system_instruction )
			->generateText();
	}
}
