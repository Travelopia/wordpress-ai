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
 * @return void
 */
function maybe_generate_alt_text_on_upload( int $attachment_id ): void {
	generate_alt_text_for_attachment( $attachment_id );
}

/**
 * Generate alt text for any image attachment (manual or auto).
 *
 * @param int  $attachment_id Attachment ID.
 * @return string|false Generated alt text or false on failure.
 */
function generate_alt_text_for_attachment( int $attachment_id ) {
	// Early validation checks
	if ( ! function_exists( 'wp_attachment_is_image' ) || ! wp_attachment_is_image( $attachment_id ) ) {
		return false;
	}

	// Check for existing alt text to avoid unnecessary API calls
	$existing_alt = get_post_meta( $attachment_id, '_wp_attachment_image_alt', true );
	if ( ! empty( trim( $existing_alt ) ) ) {
		return $existing_alt;
	}

	// Ensure AI client is available.
	if ( ! \class_exists( '\\WordPress\\AiClient\\AiClient' ) ) {
		return false;
	}

	// Get the file path.
	$file_path = get_attached_file( $attachment_id, true );
	if ( empty( $file_path ) || ! file_exists( $file_path ) ) {
		return false;
	}

	// Build optimized context from metadata
	$context_parts = array();
	$file_name = wp_basename( $file_path );
	$title = get_the_title( $attachment_id );

	if ( $file_name ) {
		$context_parts[] = 'filename: ' . $file_name;
	}
	if ( $title ) {
		$context_parts[] = 'title: ' . $title;
	}
	$context = implode( '; ', $context_parts );

	// Optimized prompt construction
	$prompt = 'Analyze this image and provide a concise, objective, accessible alt text description (maximum 120 characters). Focus on the main subject, key visual elements, and important details that would help someone who cannot see the image understand its content.';
	if ( $context ) {
		$prompt .= ' Additional context: ' . $context;
	}

	try {
		// Check API key availability
		if ( ! defined( 'GOOGLE_API_KEY' ) && ! getenv( 'GOOGLE_API_KEY' ) ) {
			return false;
		}

		// Get actual image URL for the attachment
		$image_url = wp_get_attachment_url( $attachment_id );

		// If the image URL is not found, return false.
		if ( ! $image_url ) {
			return false;
		}

		$prompt .= ' Image: ' . $image_url;

		$generated = AiClient::prompt( $prompt )
			->usingModel( GoogleProvider::model( 'gemini-1.5-flash' ) )
			->usingTemperature( 0.1 )
			->generateText();

		// Process and validate generated text
		$generated = trim( wp_strip_all_tags( (string) $generated ) );
		if ( empty( $generated ) ) {
			return false;
		}

		// Enforce character limit and sanitize
		if ( strlen( $generated ) > 120 ) {
			$generated = substr( $generated, 0, 120 );
		}
		$generated = sanitize_text_field( $generated );

		update_post_meta( $attachment_id, '_wp_attachment_image_alt', $generated );

		return $generated;
	} catch ( Exception $e ) {
		return false;
	}
}
