<?php
/**
 * Namespace functions.
 *
 * @package travelopia-ai
 */

namespace Travelopia\AI;

use WordPress\AiClient\AiClient;
use WordPress\AiClient\ProviderImplementations\Google\GoogleProvider;
use WP_Error;
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
	generate_alt_text_for_attachment( $attachment_id, false );
}

/**
 * Generate alt text for any image attachment (manual or auto).
 *
 * @param int  $attachment_id Attachment ID.
 * @return string|false Generated alt text or false on failure.
 */
function generate_alt_text_for_attachment( int $attachment_id ) {
	if ( ! function_exists( '\\wp_attachment_is_image' ) ) {
		return false;
	}

	if ( ! wp_attachment_is_image( $attachment_id ) ) {
		return false;
	}

	// Ensure AI client is available.
	if ( ! \class_exists( '\\WordPress\\AiClient\\AiClient' ) ) {
		return false;
	}

	$file_path = (string) get_attached_file( $attachment_id, true );
	if ( '' === $file_path || ! file_exists( $file_path ) ) {
		error_log( "Travelopia AI: Image file not found for attachment {$attachment_id}" ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		return false;
	}

	$file_name = wp_basename( $file_path );
	$title     = (string) get_the_title( $attachment_id );

	// Build context from metadata.
	$context_parts = array();
	if ( '' !== $file_name ) {
		$context_parts[] = 'filename: ' . $file_name;
	}
	if ( '' !== $title ) {
		$context_parts[] = 'title: ' . $title;
	}
	$context = implode( '; ', $context_parts );

	// Create a prompt that asks the AI to analyze the actual image content.
	$prompt = 'Analyze this image and provide a concise, objective, accessible alt text description (maximum 120 characters). Focus on the main subject, key visual elements, and important details that would help someone who cannot see the image understand its content.';
	if ( '' !== $context ) {
		$prompt .= ' Additional context: ' . $context;
	}

	try {
		// Check if Google API key is available.
		if ( ! defined( 'GOOGLE_API_KEY' ) ) {
			return new WP_Error( 'travelopia_ai_google_api_key_not_configured', 'Google API key not configured. Set GOOGLE_API_KEY environment variable.' );
		}

		// @todo: Get the public URL for the image.
		// $image_url = wp_get_attachment_url( $attachment_id );
		$image_url = "https://sunsail-website.s3.amazonaws.com/uploads/2025/05/LOGO_Dee-Caffari-e1748266875143.jpg";
		if ( empty( $image_url ) ) {
			return new WP_Error( 'travelopia_ai_image_url_not_found', 'Image URL not found for attachment: ' . $attachment_id );
		}

		$prompt .= ' Image: ' . $image_url;

		$generated = AiClient::prompt( $prompt )
			->usingModel( GoogleProvider::model( 'gemini-2.5-flash' ) )
			->usingTemperature( 0.1 )
			->generateText();

		$generated = (string) $generated;
		$generated = trim( wp_strip_all_tags( $generated ) );
		if ( '' === $generated ) {
			return false;
		}

		// Enforce 140 char limit and sanitize.
		if ( strlen( $generated ) > 140 ) {
			$generated = substr( $generated, 0, 140 );
		}
		$generated = sanitize_text_field( $generated );

		update_post_meta( $attachment_id, '_wp_attachment_image_alt', $generated );

		return $generated;
	} catch ( Exception $e ) {
		return new WP_Error( 'travelopia_ai_failed_to_generate_alt_text', 'Failed to generate alt text for attachment: ' . $attachment_id . ' - ' . $e->getMessage() );
	}
}
