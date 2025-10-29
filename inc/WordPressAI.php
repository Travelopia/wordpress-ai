<?php
/**
 * Main WordPress AI module.
 *
 * @package travelopia-wordpress-ai
 */

namespace Travelopia\WordPress_AI;

use Exception;
use WordPress\AiClient\AiClient;
use WordPress\AiClient\ProviderImplementations\OpenAi\OpenAiProvider;

/**
 * WordPress AI class.
 */
class WordPressAI
{
	/**
	 * Bootstrap plugin.
	 *
	 * @return void
	 *
	 * @throws Exception
	 */
	public static function bootstrap(): void
	{
		// Hooks.
		add_action( 'add_attachment', [ __CLASS__, 'maybe_generate_alt_text_on_upload' ], 20 );
	}

	/**
	 * Generate alt text for an uploaded image if missing.
	 *
	 * @param int $attachment_id Attachment ID.
	 *
	 * @return void
	 */
	public static function maybe_generate_alt_text_on_upload( int $attachment_id = 0 ): void
	{
		if ( ! wp_attachment_is_image( $attachment_id ) || ! self::get_setting( 'alt_text_generation', false ) ) {
			return;
		}

		self::generate_alt_text_for_attachment( $attachment_id );
	}

	/**
	 * Get AI setting value.
	 *
	 * @param string $key           Setting key.
	 * @param mixed  $default_value Default value if setting not found.
	 *
	 * @return mixed Setting value or default.
	 */
	public static function get_setting( string $key = '', mixed $default_value = null ): mixed
	{
		$settings = get_option( 'travelopia_wp_ai_settings', Admin::get_default_settings() );

		if ( ! is_array( $settings ) ) {
			$settings = Admin::get_default_settings();
		}

		return $settings[ $key ] ?? $default_value;
	}

	/**
	 * Generate alt text for any image attachment.
	 *
	 * @param int     $attachment_id Attachment ID.
	 * @param boolean $update        Whether to update the alt text.
	 *
	 * @return array{success: bool, alt_text?: string, error?: string}
	 */
	public static function generate_alt_text_for_attachment( int $attachment_id = 0, bool $update = true ): array
	{
		// Early validation checks.
		if ( ! function_exists( 'wp_attachment_is_image' ) || ! wp_attachment_is_image( $attachment_id ) ) {
			return [
				'success'  => false,
				'alt_text' => '',
				'error'    => __( 'Invalid attachment ID or not an image', 'travelopia-wp-ai' ),
			];
		}

		// Check if AI alt text generation is enabled.
		if ( ! self::get_setting( 'alt_text_generation', false ) ) {
			return [
				'success'  => false,
				'alt_text' => '',
				'error'    => __( 'AI alt text generation is not enabled', 'travelopia-wp-ai' ),
			];
		}

		// Get the AI prompt from settings.
		$ai_prompt = self::get_setting( 'ai_alt_text_prompt', '' );

		// Validate prompt is configured.
		if ( empty( $ai_prompt ) || ! is_string( $ai_prompt ) ) {
			return [
				'success'  => false,
				'alt_text' => '',
				'error'    => __( 'AI prompt is not configured', 'travelopia-wp-ai' ),
			];
		}

		// Ensure AI client is available.
		if ( ! class_exists( '\\WordPress\\AiClient\\AiClient' ) ) {
			return [
				'alt_text' => '',
				'success'  => false,
				'error'    => __( 'AI Client not available', 'travelopia-wp-ai' ),
			];
		}

		// Default options for the generation.
		$default_options = [
			'model'              => 'gpt-4o-mini',
			'temperature'        => 0.1,
			'prompt'             => $ai_prompt,
			'include_context'    => true,
			'system_instruction' => 'You are an accessibility tool.',
		];

		/**
		 * Filter the generation options.
		 *
		 * @param array $default_options The generation options.
		 * @param int   $attachment_id   The attachment ID.
		 */
		$options = apply_filters( 'trav_ai_generation_options', $default_options, $attachment_id );

		// Ensure options is an array.
		if ( ! is_array( $options ) ) {
			$options = $default_options;
		}

		// Build final prompt.
		$prompt = $options['prompt'];

		/**
		 * Filter the prompt.
		 *
		 * @param string $prompt        The prompt.
		 * @param int    $attachment_id The attachment ID.
		 */
		$prompt = apply_filters( 'trav_ai_alt_text_prompt', $prompt, $attachment_id );

		// Validate prompt is a string after filtering.
		if ( ! is_string( $prompt ) ) {
			return [
				'success'  => false,
				'alt_text' => '',
				'error'    => __( 'Invalid prompt type after filtering', 'travelopia-wp-ai' ),
			];
		}

		// Initialize context.
		$context = '';

		// Build context from metadata if requested.
		if ( true === $options['include_context'] ) {
			$context_parts = [];
			$title         = get_the_title( $attachment_id );

			// Add title to context.
			if ( $title ) {
				$context_parts[] = sprintf(
					/* translators: %s: title */
					__( 'title: %s', 'travelopia-wp-ai' ),
					$title,
				);
			}

			// Join context parts with a semicolon.
			$context = implode( '; ', $context_parts );
		}

		// Add context to prompt if requested.
		if ( $context ) {
			$prompt .= sprintf(
				/* translators: %s: context */
				__( ' Additional context: %s', 'travelopia-wp-ai' ),
				$context,
			);
		}

		// Start AI generation process.
		try {
			// Check API key availability.
			if ( ! defined( 'OPENAI_API_KEY' ) && ! getenv( 'OPENAI_API_KEY' ) ) {
				return [
					'success'  => false,
					'alt_text' => '',
					'error'    => __( 'OpenAI API key not configured', 'travelopia-wp-ai' ),
				];
			}

			// Get the API key.
			$api_key = defined( 'OPENAI_API_KEY' ) ? constant( 'OPENAI_API_KEY' ) : getenv( 'OPENAI_API_KEY' );

			// Validate API key is not empty.
			if ( empty( $api_key ) ) {
				return [
					'success'  => false,
					'alt_text' => '',
					'error'    => __( 'OpenAI API key is empty', 'travelopia-wp-ai' ),
				];
			}

			// Get actual image URL for the attachment.
			$image_url = wp_get_attachment_url( $attachment_id );

			// Filter the image URL if needed.
			$image_url = apply_filters( 'trav_ai_image_url', $image_url, $attachment_id );

			// Validate image URL exists.
			if ( ! $image_url || ! is_string( $image_url ) ) {
				return [
					'success'  => false,
					'alt_text' => '',
					'error'    => __( 'Could not get image URL or is not a string.', 'travelopia-wp-ai' ),
				];
			}

			// Generate AI response.
			$generated = AiClient::prompt( $prompt )
				->usingModel( OpenAiProvider::model( strval( $options['model'] ) ) )
				->usingTemperature( floatval( $options['temperature'] ) )
				->withFile( $image_url )
				->usingSystemInstruction( $options['system_instruction'] )
				->generateText();

			// Process and validate generated text.
			$generated = sanitize_text_field( trim( wp_strip_all_tags( strval( $generated ) ) ) );

			// Validate generated text is not empty.
			if ( empty( $generated ) ) {
				return [
					'success'  => false,
					'alt_text' => '',
					'error'    => __( 'AI generated empty response', 'travelopia-wp-ai' ),
				];
			}

			// Save generated alt text to database.
			if ( $update ) {
				update_post_meta( $attachment_id, '_wp_attachment_image_alt', $generated );
			}

			// Fire action hook after successful generation.
			do_action( 'trav_ai_alt_text_generated', $attachment_id, $generated );

			// Return success with generated alt text.
			return [
				'success'  => true,
				'alt_text' => $generated,
			];
		} catch ( Exception $e ) {
			// Return error details.
			return [
				'success'  => false,
				'alt_text' => '',
				'error'    => sprintf(
					/* translators: %s: error message */
					__( 'AI generation failed: %s', 'travelopia-wp-ai' ),
					$e->getMessage(),
				),
			];
		}
	}
}
