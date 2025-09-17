<?php
/**
 * Namespace functions.
 *
 * @package trav-ai
 */

namespace TravAI;

use WordPress\AiClient\AiClient;
use WordPress\AiClient\ProviderImplementations\OpenAi\OpenAiProvider;
use WordPress\AiClient\Providers\Http\DTO\ApiKeyRequestAuthentication;
use Exception;
use WP_CLI;

use function TravAI\Admin\get_default_settings;

/**
 * Bootstrap plugin.
 *
 * @return void
 */
function bootstrap(): void {
	// Auto-generate alt text on new image upload when none exists.
	add_action( 'add_attachment', __NAMESPACE__ . '\\maybe_generate_alt_text_on_upload', 20 );

	// Register WP CLI commands.
	if ( defined( 'WP_CLI' ) && true === WP_CLI && class_exists( 'WP_CLI' ) ) {
		require_once __DIR__ . '/wp-cli/class-generate-alt-text.php';

		// Register commands.
		WP_CLI::add_command( 'travai alt-text', __NAMESPACE__ . '\\WP_CLI\\Generate_Alt_Text' );
	}
}

/**
 * Generate alt text for an uploaded image if missing.
 *
 * @param int $attachment_id Attachment ID.
 *
 * @return void
 */
function maybe_generate_alt_text_on_upload( int $attachment_id ): void {
	// Validate attachment is an image and plugin is enabled.
	if (
			! wp_attachment_is_image( $attachment_id )
			|| ! get_ai_setting( 'ai_alt_text_enabled', false )
	) {
		return;
	}

	// Generate alt text for the uploaded image.
	generate_alt_text_for_attachment( $attachment_id );
}

/**
 * Get AI setting value.
 *
 * @param string $key           Setting key.
 * @param mixed  $default_value Default value if setting not found.
 *
 * @return mixed Setting value or default.
 */
function get_ai_setting( string $key, mixed $default_value = null ): mixed {
	// Fetch settings with default fallback.
	$settings = get_option( 'travai_settings', get_default_settings() );

	// Ensure settings is an array.
	if ( ! is_array( $settings ) ) {
		$settings = get_default_settings();
	}

	// Return.
	return $settings[ $key ] ?? $default_value;
}

/**
 * Generate alt text for any image attachment.
 *
 * @param int $attachment_id Attachment ID.
 *
 * @return array{success: bool, alt_text?: string, error?: string}
 */
function generate_alt_text_for_attachment( int $attachment_id ): array {
	// Early validation checks.
	if ( ! function_exists( 'wp_attachment_is_image' ) || ! wp_attachment_is_image( $attachment_id ) ) {
		return [
			'success'  => false,
			'alt_text' => '',
			'error'    => __( 'Invalid attachment ID or not an image', 'trav-ai' ),
		];
	}

	// Check if AI alt text generation is enabled.
	if ( ! get_ai_setting( 'ai_alt_text_enabled', false ) ) {
		return [
			'success'  => false,
			'alt_text' => '',
			'error'    => __( 'AI alt text generation is not enabled', 'trav-ai' ),
		];
	}

	// Get the AI prompt from settings.
	$ai_prompt = get_ai_setting( 'ai_alt_text_prompt', '' );

	// Validate prompt is configured.
	if ( empty( $ai_prompt ) || ! is_string( $ai_prompt ) ) {
		return [
			'success'  => false,
			'alt_text' => '',
			'error'    => __( 'AI prompt is not configured', 'trav-ai' ),
		];
	}

	// Ensure AI client is available.
	if ( ! class_exists( '\\WordPress\\AiClient\\AiClient' ) ) {
		return [
			'alt_text' => '',
			'success'  => false,
			'error'    => __( 'AI Client not available', 'trav-ai' ),
		];
	}

	// Ensure ApiKeyRequestAuthentication is available.
	if ( ! class_exists( '\\WordPress\\AiClient\\Providers\\Http\\DTO\\ApiKeyRequestAuthentication' ) ) {
		return [
			'alt_text' => '',
			'success'  => false,
			'error'    => __( 'AI Client authentication class not available', 'trav-ai' ),
		];
	}

	// Get the file path.
	$file_path = get_attached_file( $attachment_id, true );

	// Validate file path exists.
	if ( empty( $file_path ) || ! file_exists( $file_path ) ) {
		return [
			'success'  => false,
			'alt_text' => '',
			'error'    => __( 'Image file not found', 'trav-ai' ),
		];
	}

	// Default options for the generation.
	$default_options = [
		'model'           => 'gpt-4o-mini',
		'temperature'     => 0.1,
		'max_length'      => 120,
		'prompt'          => $ai_prompt,
		'include_context' => true,
	];

	/**
	 * Filter the generation options.
	 *
	 * @param array $default_options The generation options.
	 * @param int   $attachment_id The attachment ID.
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
	 * @param string $prompt The prompt.
	 * @param int $attachment_id The attachment ID.
	 */
	$prompt = apply_filters( 'trav_ai_alt_text_prompt', $prompt, $attachment_id );

	// Initialize context.
	$context = '';

	// Build context from metadata if requested.
	if ( true === $options['include_context'] ) {
		$context_parts = [];
		$file_name     = wp_basename( $file_path );
		$title         = get_the_title( $attachment_id );

		// Add file name to context.
		if ( $file_name ) {
			$context_parts[] = sprintf(
				/* translators: %s: filename */
				__( 'filename: %s', 'trav-ai' ),
				$file_name
			);
		}

		// Add title to context.
		if ( $title ) {
			$context_parts[] = sprintf(
				/* translators: %s: title */
				__( 'title: %s', 'trav-ai' ),
				$title
			);
		}

		// Join context parts with a semicolon.
		$context = implode( '; ', $context_parts );
	}

	// Add context to prompt if requested.
	if ( $context ) {
		$prompt .= sprintf(
			/* translators: %s: context */
			__( ' Additional context: %s', 'trav-ai' ),
			$context
		);
	}

	// Start AI generation process.
	try {
		// Check API key availability.
		if ( ! defined( 'OPENAI_API_KEY' ) && ! getenv( 'OPENAI_API_KEY' ) ) {
			return [
				'success'  => false,
				'alt_text' => '',
				'error'    => __( 'OpenAI API key not configured', 'trav-ai' ),
			];
		}

		// Get the API key.
		$api_key = defined( 'OPENAI_API_KEY' ) ? OPENAI_API_KEY : getenv( 'OPENAI_API_KEY' );

		// Validate API key is not empty.
		if ( empty( $api_key ) ) {
			return [
				'success'  => false,
				'alt_text' => '',
				'error'    => __( 'OpenAI API key is empty', 'trav-ai' ),
			];
		}

		// Set up authentication for OpenAI provider.
		$registry = AiClient::defaultRegistry();
		$auth     = new ApiKeyRequestAuthentication( $api_key );
		$registry->setProviderRequestAuthentication( OpenAiProvider::class, $auth );

		// Get actual image URL for the attachment.
		$image_url = wp_get_attachment_url( $attachment_id );

		// Filter the image URL if needed.
		$image_url = apply_filters( 'trav_ai_image_url', $image_url, $attachment_id );

		// Validate image URL exists.
		if ( ! $image_url || ! is_string( $image_url ) ) {
			return [
				'success'  => false,
				'alt_text' => '',
				'error'    => __( 'Could not get image URL or is not a string.', 'trav-ai' ),
			];
		}

		// Append image URL to prompt.
		$prompt .= sprintf(
			/* translators: %s: image URL */
			__( ' Image: %s', 'trav-ai' ),
			$image_url
		);

		// Generate AI response.
		$generated = AiClient::prompt( $prompt )
			->usingModel( OpenAiProvider::model( strval( $options['model'] ) ) )
			->usingTemperature( floatval( $options['temperature'] ) )
			->generateText();

		// Process and validate generated text.
		$generated = sanitize_text_field( trim( wp_strip_all_tags( strval( $generated ) ) ) );

		// Validate generated text is not empty.
		if ( empty( $generated ) ) {
			return [
				'success'  => false,
				'alt_text' => '',
				'error'    => __( 'AI generated empty response', 'trav-ai' ),
			];
		}

		// Save generated alt text to database.
		update_post_meta( $attachment_id, '_wp_attachment_image_alt', $generated );

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
				__( 'AI generation failed: %s', 'trav-ai' ),
				$e->getMessage()
			),
		];
	}
}

/**
 * Plugin activation hook handler.
 *
 * This function is called when the plugin is activated.
 * It can be used to set up initial options, create database tables,
 * or perform any other setup tasks required for the plugin.
 *
 * @return void
 */
function activate_plugin(): void {
	// Initialize default settings if they don't exist.
	$default_settings = [
		'ai_alt_text_enabled' => false,
		'ai_prompt'           => __( 'Describe this image in a concise, informative way for alt text. Focus on the main subject and important details that would help someone understand what is in the image.', 'trav-ai' ),
	];

	// Only set defaults if no settings exist yet.
	if ( false === get_option( 'travai_settings' ) ) {
		add_option( 'travai_settings', $default_settings );
	}
}
