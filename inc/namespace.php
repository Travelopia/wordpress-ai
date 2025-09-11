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
 * Generate alt text for any image attachment (manual or auto).
 *
 * @param int $attachment_id Attachment ID.
 *
 * @return string|false Generated alt text or false on failure.
 */
function generate_alt_text_for_attachment( int $attachment_id ) {
	// Early validation checks.
	if ( ! function_exists( 'wp_attachment_is_image' ) || ! wp_attachment_is_image( $attachment_id ) ) {
		return false;
	}

	// Check for existing alt text to avoid unnecessary API calls.
	$existing_alt = get_post_meta( $attachment_id, '_wp_attachment_image_alt', true );

	// Check if existing alt text exists.
	if ( ! empty( trim( $existing_alt ) ) ) {
		return $existing_alt;
	}

	// Ensure AI client is available.
	if ( ! \class_exists( '\\WordPress\\AiClient\\AiClient' ) ) {
		return false;
	}

	// Get the file path.
	$file_path = get_attached_file( $attachment_id, true );

	// Validate file path exists.
	if ( empty( $file_path ) || ! file_exists( $file_path ) ) {
		return false;
	}

	// Build optimized context from metadata.
	$context_parts = [];
	$file_name     = wp_basename( $file_path );
	$title         = get_the_title( $attachment_id );

	// Add filename to context if available.
	if ( $file_name ) {
		$context_parts[] = 'filename: ' . $file_name;
	}

	// Add title to context if available.
	if ( $title ) {
		$context_parts[] = 'title: ' . $title;
	}
	$context = implode( '; ', $context_parts );

	// Optimized prompt construction.
	$prompt = 'Analyze this image and provide a concise, objective, accessible alt text description (maximum 120 characters). Focus on the main subject, key visual elements, and important details that would help someone who cannot see the image understand its content.';

	// Add context to prompt if available.
	if ( $context ) {
		$prompt .= ' Additional context: ' . $context;
	}

	// Start AI generation process.
	try {
		// Check API key availability.
		if ( ! defined( 'GOOGLE_API_KEY' ) && ! getenv( 'GOOGLE_API_KEY' ) ) {
			return false;
		}

		// Get actual image URL for the attachment.
		$image_url = wp_get_attachment_url( $attachment_id );

		// Validate image URL exists.
		if ( ! $image_url ) {
			return false;
		}

		// Append image URL to prompt.
		$prompt .= ' Image: ' . $image_url;

		// Generate AI response.
		$generated = AiClient::prompt( $prompt )
			->usingModel( GoogleProvider::model( 'gemini-1.5-flash' ) )
			->usingTemperature( 0.1 )
			->generateText();

		// Process and validate generated text.
		$generated = trim( wp_strip_all_tags( strval( $generated ) ) );

		// Validate generated text is not empty.
		if ( empty( $generated ) ) {
			return false;
		}

		// Truncate text if too long.
		if ( strlen( $generated ) > 120 ) {
			$generated = substr( $generated, 0, 120 );
		}
		$generated = sanitize_text_field( $generated );

		// Save generated alt text to database.
		update_post_meta( $attachment_id, '_wp_attachment_image_alt', $generated );

		// Return the generated alt text.
		return $generated;
	} catch ( Exception $e ) {
		// Return false on any error.
		return false;
	}
}
