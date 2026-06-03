<?php
/**
 * Bedrock Adapter for Alt Text Generation.
 *
 * @package travelopia-wordpress-ai
 */

namespace Travelopia\WordPress_AI\Adapters;

use RuntimeException;
use Travelopia\WordPress_AI\Dependencies\Aysnc\WordPress\PhpAiClientBedrock\AwsBedrockProvider;
use Travelopia\WordPress_AI\Dependencies\WordPress\AiClient\AiClient;
use WP_Error;

/**
 * AWS Bedrock adapter class for generating alt text.
 */
class Bedrock extends AbstractAiAdapter
{
	/**
	 * Boot the Bedrock adapter.
	 *
	 * Registers the AWS Bedrock provider with AiClient since it is not
	 * built-in and comes from the aysnc/wordpress-php-ai-client-bedrock package.
	 *
	 * @return void
	 */
	public static function boot(): void
	{
		AiClient::defaultRegistry()->registerProvider( AwsBedrockProvider::class );
	}

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
			'model'              => 'anthropic.claude-sonnet-4-6',
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
		// Bedrock API requires base64-encoded file data — download and encode as data URI.
		$data_uri = static::download_to_data_uri( $image_url );

		return AiClient::prompt( $prompt )
			->usingModel( AwsBedrockProvider::model( $model ) )
			->usingTemperature( $temperature )
			->withFile( $data_uri )
			->usingSystemInstruction( $system_instruction )
			->generateText();
	}

	/**
	 * Download a remote URL and convert it to a data URI.
	 *
	 * @param string $url The URL to download.
	 *
	 * @return string Data URI (e.g. data:image/jpeg;base64,...).
	 *
	 * @throws RuntimeException If the download fails.
	 */
	protected static function download_to_data_uri( string $url ): string
	{
		$response = wp_remote_get(
			$url,
			[
				'timeout' => 30,
			],
		);

		if ( $response instanceof WP_Error ) {
			throw new RuntimeException(
				sprintf(
					/* translators: %s: error message */
					esc_html__( 'Failed to download image: %s', 'travelopia-wordpress-ai' ),
					esc_html( $response->get_error_message() ),
				),
			);
		}

		$status_code = wp_remote_retrieve_response_code( $response );

		if ( 200 !== $status_code ) {
			throw new RuntimeException(
				sprintf(
					/* translators: %s: HTTP status code */
					esc_html__( 'Failed to download image: HTTP %s', 'travelopia-wordpress-ai' ),
					esc_html( (string) $status_code ),
				),
			);
		}

		$body = wp_remote_retrieve_body( $response );

		if ( empty( $body ) ) {
			throw new RuntimeException(
				esc_html__( 'Failed to download image: empty response body.', 'travelopia-wordpress-ai' ),
			);
		}

		$mime_type = wp_remote_retrieve_header( $response, 'content-type' );

		// Strip charset or boundary parameters (e.g. "image/jpeg; charset=utf-8").
		if ( is_string( $mime_type ) && str_contains( $mime_type, ';' ) ) {
			$mime_type = trim( explode( ';', $mime_type )[0] );
		}

		// Fallback mime type from URL extension if content-type header is missing.
		if ( empty( $mime_type ) || ! is_string( $mime_type ) ) {
			$mime_type = (string) ( wp_check_filetype( $url )['type'] ?? 'application/octet-stream' );
		}

		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- Required for Bedrock API.
		return sprintf( 'data:%s;base64,%s', $mime_type, base64_encode( $body ) );
	}
}
