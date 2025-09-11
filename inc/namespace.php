<?php
/**
 * Namespace functions.
 *
 * @package trav-ai
 */

namespace TravAI;

use WordPress\AiClient\AiClient;
use WordPress\AiClient\ProviderImplementations\Google\GoogleProvider;
use Exception;

/**
 * Bootstrap plugin.
 *
 * @return void
 */
function bootstrap(): void {
	// Auto-generate alt text on new image upload when none exists.
	add_action( 'add_attachment', __NAMESPACE__ . '\\maybe_generate_alt_text_on_upload', 20 );
}

/**
 * Generate alt text for an uploaded image if missing.
 *
 * @param int $attachment_id Attachment ID.
 *
 * @return void
 */
function maybe_generate_alt_text_on_upload( int $attachment_id ): void {
	// Generate alt text for the uploaded image.
	generate_alt_text_for_attachment( $attachment_id );
}

/**
 * Generate alt text for any image attachment.
 *
 * @param int $attachment_id Attachment ID.
 *
 * @return array{success: bool, alt_text?: string, error?: string}
 */
function generate_alt_text_for_attachment( int $attachment_id ) {
	// Early validation checks.
	if ( ! function_exists( 'wp_attachment_is_image' ) || ! wp_attachment_is_image( $attachment_id ) ) {
		return [
			'success' => false,
			'error'   => 'Invalid attachment ID or not an image',
		];
	}

	// Check for existing alt text to avoid unnecessary API calls.
	$existing_alt = get_post_meta( $attachment_id, '_wp_attachment_image_alt', true );

	// Check if existing alt text exists.
	if ( ! empty( trim( $existing_alt ) ) ) {
		return $existing_alt;
	}

	// Ensure AI client is available.
	if ( ! \class_exists( '\\WordPress\\AiClient\\AiClient' ) ) {
		return [
			'success' => false,
			'error'   => 'AI Client not available',
		];
	}

	// Get the file path.
	$file_path = get_attached_file( $attachment_id, true );

	// Validate file path exists.
	if ( empty( $file_path ) || ! file_exists( $file_path ) ) {
		return [
			'success' => false,
			'error'   => 'Image file not found',
		];
	}

	// Default options for the generation.
	$options = [
		'model'           => 'gemini-1.5-flash',
		'temperature'     => 0.1,
		'max_length'      => 120,
		'prompt'          => 'Analyze this image and provide a concise, objective, accessible alt text description (maximum 120 characters). Focus on the main subject, key visual elements, and important details that would help someone who cannot see the image understand its content.',
		'include_context' => true,
	];

	// Initialize context.
	$context = '';

	// Build context from metadata if requested.
	if ( $options['include_context'] ) {
		$context_parts = [];
		$file_name     = wp_basename( $file_path );
		$title         = get_the_title( $attachment_id );

		// Add file name to context.
		if ( $file_name ) {
			$context_parts[] = 'filename: ' . $file_name;
		}

		// Add title to context.
		if ( $title ) {
			$context_parts[] = 'title: ' . $title;
		}

		// Join context parts with a semicolon.
		$context = implode( '; ', $context_parts );
	}

	// Build final prompt.
	$prompt = $options['prompt'];

	// Add context to prompt if requested.
	if ( $context ) {
		$prompt .= ' Additional context: ' . $context;
	}

	/**
	 * Filter the prompt.
	 *
	 * @param string $prompt The prompt.
	 * @param int $attachment_id The attachment ID.
	 */
	$prompt = apply_filters( 'trav_ai_alt_text_prompt', $prompt, $attachment_id );

	/**
	 * Filter the generation options.
	 *
	 * @param array $options The generation options.
	 * @param int $attachment_id The attachment ID.
	 */
	$options = apply_filters( 'trav_ai_generation_options', $options, $attachment_id );

	// Start AI generation process.
	try {
		// Check API key availability.
		if ( ! defined( 'GOOGLE_API_KEY' ) && ! getenv( 'GOOGLE_API_KEY' ) ) {
			return [
				'success' => false,
				'error'   => 'Google API key not configured',
			];
		}

		// Get actual image URL for the attachment.
		$image_url = wp_get_attachment_url( $attachment_id );

		// Validate image URL exists.
		if ( ! $image_url ) {
			return [
				'success' => false,
				'error'   => 'Could not get image URL',
			];
		}

		// Append image URL to prompt.
		$prompt .= ' Image: ' . $image_url;

		// Generate AI response.
		$generated = AiClient::prompt( $prompt )
			->usingModel( GoogleProvider::model( $options['model'] ) )
			->usingTemperature( $options['temperature'] )
			->generateText();

		// Process and validate generated text.
		$generated = trim( wp_strip_all_tags( strval( $generated ) ) );

		// Validate generated text is not empty.
		if ( empty( $generated ) ) {
			return [
				'success' => false,
				'error'   => 'AI generated empty response',
			];
		}

		// Truncate text if too long.
		if ( strlen( $generated ) > $options['max_length'] ) {
			$generated = substr( $generated, 0, $options['max_length'] );
		}
		$generated = sanitize_text_field( $generated );

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
			'success' => false,
			'error'   => 'AI generation failed: ' . $e->getMessage(),
		];
	}
}
