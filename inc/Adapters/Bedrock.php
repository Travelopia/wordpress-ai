<?php
/**
 * Bedrock Adapter for Alt Text Generation.
 *
 * @package travelopia-wordpress-ai
 */

namespace Travelopia\WordPress_AI\Adapters;

use Aysnc\WordPress\PhpAiClientBedrock\AwsBedrockProvider;
use RuntimeException;
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
		// Bedrock API requires base64-encoded file data — it cannot accept URLs.
		// Resolve to a local path (standard WP uploads) or data URI (remote storage like S3/CDN).
		$file = static::resolve_file_for_bedrock( $image_url );

		return AiClient::prompt( $prompt )
			->usingModel( AwsBedrockProvider::model( $model ) )
			->usingTemperature( $temperature )
			->withFile( $file )
			->usingSystemInstruction( $system_instruction )
			->generateText();
	}

	/**
	 * Resolve an image URL to a format Bedrock can consume (local path or data URI).
	 *
	 * Tries the local upload path first to avoid an HTTP round-trip,
	 * then falls back to downloading and encoding as a data URI for
	 * remote storage backends (S3, CDN, etc.).
	 *
	 * Note: create_error() cannot be used here because the abstract call_ai_client()
	 * signature returns string only. RuntimeException is caught by the parent
	 * generate_alt_text() which converts it via create_error() with ERROR_CODE_FAILED.
	 *
	 * @param string $url The image URL.
	 *
	 * @return string Local file path or data URI string.
	 *
	 * @throws RuntimeException If the file cannot be resolved.
	 */
	protected static function resolve_file_for_bedrock( string $url ): string
	{
		// Try local file path first — avoids HTTP overhead for standard WP uploads.
		$local_path = static::resolve_local_path( $url );

		if ( null !== $local_path ) {
			return $local_path;
		}

		// Fall back to downloading for remote storage (S3, CDN, etc.).
		return static::download_to_data_uri( $url );
	}

	/**
	 * Attempt to map a URL to a local file path via the WordPress uploads directory.
	 *
	 * @param string $url The image URL.
	 *
	 * @return string|null Local file path if it exists, null otherwise.
	 */
	protected static function resolve_local_path( string $url ): ?string
	{
		$upload_dir = wp_get_upload_dir();
		$base_url   = $upload_dir['baseurl'] ?? '';

		if ( empty( $base_url ) || ! str_starts_with( $url, $base_url ) ) {
			return null;
		}

		$local_path = str_replace( $base_url, $upload_dir['basedir'], $url );

		if ( ! file_exists( $local_path ) || ! is_file( $local_path ) ) {
			return null;
		}

		return $local_path;
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

		if ( is_wp_error( $response ) ) {
			throw new RuntimeException(
				sprintf(
					/* translators: %s: error message */
					__( 'Failed to download image: %s', 'travelopia-wordpress-ai' ),
					$response->get_error_message(),
				),
			);
		}

		$status_code = wp_remote_retrieve_response_code( $response );

		if ( 200 !== $status_code ) {
			throw new RuntimeException(
				sprintf(
					/* translators: %d: HTTP status code */
					__( 'Failed to download image: HTTP %d', 'travelopia-wordpress-ai' ),
					$status_code,
				),
			);
		}

		$body = wp_remote_retrieve_body( $response );

		if ( empty( $body ) ) {
			throw new RuntimeException(
				__( 'Failed to download image: empty response body.', 'travelopia-wordpress-ai' ),
			);
		}

		$mime_type = wp_remote_retrieve_header( $response, 'content-type' );

		// Strip charset or boundary parameters (e.g. "image/jpeg; charset=utf-8").
		if ( is_string( $mime_type ) && str_contains( $mime_type, ';' ) ) {
			$mime_type = trim( explode( ';', $mime_type )[0] );
		}

		// Fallback mime type from URL extension if content-type header is missing.
		if ( empty( $mime_type ) || ! is_string( $mime_type ) ) {
			$mime_type = wp_check_filetype( $url )['type'] ?? 'application/octet-stream';
		}

		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- Required for Bedrock API.
		return sprintf( 'data:%s;base64,%s', $mime_type, base64_encode( $body ) );
	}
}
