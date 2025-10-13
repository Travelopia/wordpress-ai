<?php
/**
 * Alt Text namespace functions.
 *
 * @package travelopia-wp-ai
 */

namespace Travelopia_WordPress_AI\Alt_Text;

use WP_Query;
use WP_Post;
use WP_Error;

use function Travelopia_WordPress_AI\generate_alt_text_for_attachment;
use function Travelopia_WordPress_AI\get_setting;

/**
 * Get the translated default AI prompt.
 *
 * @return string Translated default prompt.
 */
function get_default_ai_alt_text_prompt(): string {
	// Return the prompt.
	return __( 'Describe this image in a concise, informative way for alt text. Focus on the main subject and important details that would help someone understand what is in the image.', 'travelopia-wp-ai' );
}

/**
 * Create a standardized WP_Error for alt text operations.
 *
 * @param string               $error_code    Error code.
 * @param string               $error_message Error message.
 * @param array<string, mixed> $error_data    Additional error data.
 *
 * @return WP_Error
 */
function create_alt_text_error( string $error_code, string $error_message, array $error_data = [] ): WP_Error {
	// Create WP_Error instance.
	$error = new WP_Error( $error_code, $error_message, $error_data );

	// Fire action hook for error tracking.
	do_action( 'trav_ai_alt_text_error', $error_code, $error_message, $error_data );

	// Return the error instance.
	return $error;
}

/**
 * Handle alt text processing errors consistently.
 *
 * @param string               $error_code    Error code.
 * @param string               $error_message Error message.
 * @param array<string, mixed> $error_data    Additional error data.
 *
 * @return array<string, mixed>
 */
function handle_alt_text_error( string $error_code, string $error_message, array $error_data = [] ): array {
	// Create error and fire action hook.
	$error = create_alt_text_error( $error_code, $error_message, $error_data );

	// Return standardized error array.
	return [
		'success'    => false,
		'error'      => $error_message,
		'error_code' => $error_code,
	];
}

/**
 * Get images that need alt text generation.
 *
 * @param array<int> $image_ids    Array of image IDs to process.
 * @param bool       $missing_only Whether to only process images missing alt text.
 *
 * @return array<int> Image IDs to process.
 */
function get_images_to_process( array $image_ids = [], bool $missing_only = false ): array {
	// Build query arguments.
	$query_args = [
		'post_type'              => 'attachment',
		'post_mime_type'         => 'image',
		'post_status'            => 'inherit',
		'posts_per_page'         => -1,
		'fields'                 => 'ids',
		'post__in'               => $image_ids,
		'no_found_rows'          => true,
		'update_post_meta_cache' => false,
		'update_post_term_cache' => false,
	];

	// Filter for images missing alt text if requested.
	if ( $missing_only ) {
		// Add meta query to filter for missing alt text.
		$query_args['meta_query'] = [
			'relation' => 'OR',
			[
				'key'     => '_wp_attachment_image_alt',
				'compare' => 'NOT EXISTS',
			],
			[
				'key'     => '_wp_attachment_image_alt',
				'value'   => '',
				'compare' => '=',
			],
		];
	}

	// Get images.
	$images_query = new WP_Query( $query_args );

	// Return images.
	return array_map( 'absint', $images_query->posts );
}

/**
 * Get all images on the site.
 *
 * @param bool $missing_only Whether to only get images missing alt text.
 *
 * @return array<int> Array of image IDs.
 */
function get_all_images( bool $missing_only = false ): array {
	// Build query arguments for all images.
	$query_args = [
		'post_type'              => 'attachment',
		'post_mime_type'         => 'image',
		'post_status'            => 'inherit',
		'posts_per_page'         => -1,
		'fields'                 => 'ids',
		'no_found_rows'          => true, // Performance optimization.
		'update_post_meta_cache' => false, // Performance optimization.
		'update_post_term_cache' => false, // Performance optimization.
	];

	// Filter for images missing alt text if requested.
	if ( $missing_only ) {
		// Add meta query to filter for missing alt text.
		$query_args['meta_query'] = [
			'relation' => 'OR',
			[
				'key'     => '_wp_attachment_image_alt',
				'compare' => 'NOT EXISTS',
			],
			[
				'key'     => '_wp_attachment_image_alt',
				'value'   => '',
				'compare' => '=',
			],
		];
	}

	// Get all images.
	$images_query = new WP_Query( $query_args );

	// Return images.
	return array_map( 'absint', $images_query->posts );
}

/**
 * Validate AI configuration.
 *
 * @return array<string, mixed>
 */
function validate_ai_configuration(): array {
	// Check if AI alt text generation is enabled.
	$ai_enabled = get_setting( 'ai_alt_text_enabled', false );

	// Bail if AI is not enabled.
	if ( ! $ai_enabled ) {
		// Fire error action hook.
		do_action( 'trav_ai_alt_text_error', 'ai_not_enabled', __( 'AI alt text generation is not enabled. Please enable it in Settings > Travelopia WP AI.', 'travelopia-wp-ai' ) );

		// Return error if AI is not enabled.
		return [
			'valid'      => false,
			'error'      => __( 'AI alt text generation is not enabled. Please enable it in Settings > Travelopia WP AI.', 'travelopia-wp-ai' ),
			'error_code' => 'ai_not_enabled',
		];
	}

	// Get the AI prompt.
	$ai_prompt = get_setting( 'ai_alt_text_prompt', '' );

	// Bail if AI prompt is not configured.
	if ( empty( $ai_prompt ) ) {
		// Fire error action hook.
		do_action( 'trav_ai_alt_text_error', 'ai_prompt_not_configured', __( 'AI prompt is not configured. Please set it in Settings > Travelopia WP AI.', 'travelopia-wp-ai' ) );

		// Return error if AI prompt is not configured.
		return [
			'valid'      => false,
			'error'      => __( 'AI prompt is not configured. Please set it in Settings > Travelopia WP AI.', 'travelopia-wp-ai' ),
			'error_code' => 'ai_prompt_not_configured',
		];
	}

	// API key presence validation (constant or env).
	$api_key = defined( 'OPENAI_API_KEY' ) ? constant( 'OPENAI_API_KEY' ) : getenv( 'OPENAI_API_KEY' );

	// Validate API key presence.
	if ( false === $api_key || '' === $api_key ) {
		// Fire error action hook.
		do_action( 'trav_ai_alt_text_error', 'api_key_not_configured', __( 'OpenAI API key not configured. Please set OPENAI_API_KEY in wp-config.php or environment.', 'travelopia-wp-ai' ) );

		// Return error result.
		return [
			'valid'      => false,
			'error'      => __( 'OpenAI API key not configured. Please set OPENAI_API_KEY in wp-config.php or environment.', 'travelopia-wp-ai' ),
			'error_code' => 'api_key_not_configured',
		];
	}

	// Return validation result.
	return [ 'valid' => true ];
}

/**
 * Get AI configuration details.
 *
 * @return array<string, mixed>
 */
function get_ai_configuration(): array {
	// Get AI configuration settings.
	return [
		'enabled' => (bool) get_setting( 'ai_alt_text_enabled', false ),
		'prompt'  => strval( get_setting( 'ai_alt_text_prompt', '' ) ),
	];
}

/**
 * Get image details for display.
 *
 * @param int $image_id Image ID.
 *
 * @return array<string, mixed>
 */
function get_image_details( int $image_id ): array {
	// Get image post and alt text data.
	$image_post = get_post( $image_id );
	$alt_text   = get_post_meta( $image_id, '_wp_attachment_image_alt', true );

	// Return formatted image details.
	return [
		'id'           => $image_id,
		'title'        => $image_post instanceof WP_Post ? ( $image_post->post_title ?: __( '(no title)', 'travelopia-wp-ai' ) ) : __( '(invalid post)', 'travelopia-wp-ai' ),
		'has_alt_text' => ! empty( $alt_text ),
		'alt_text'     => strval( $alt_text ?: '' ),
	];
}

/**
 * Parse and validate CLI arguments consistently.
 *
 * @param array<string, mixed> $args_assoc WP CLI associative arguments.
 *
 * @return array<string, mixed>
 */
function parse_cli_arguments( array $args_assoc ): array {
	// Parse and validate command arguments.
	$options = wp_parse_args(
		$args_assoc,
		[
			'ids'        => [],
			'missing'    => false,
			'all'        => false,
			'batch-size' => 50,
		]
	);

	// Parse IDs if provided.
	$ids = [];

	// Validate IDs.
	if ( ! empty( $options['ids'] ) ) {
		// Convert comma-separated string to array of integers.
		$ids = array_map( 'absint', array_filter( array_map( 'trim', explode( ',', $options['ids'] ) ) ) );
	}

	// Parse other arguments.
	$missing    = (bool) $options['missing'];
	$all        = (bool) $options['all'];
	$batch_size = max( 1, absint( $options['batch-size'] ) );

	// Check for conflicting arguments.
	if ( ! empty( $ids ) && $all ) {
		return [
			'ids'                => $ids,
			'missing'            => $missing,
			'all'                => $all,
			'needs_confirmation' => false,
			'batch-size'         => $batch_size,
			'valid'              => false,
			'error'              => __( 'Cannot use --ids and --all together. Use --ids for specific images or --all for all images.', 'travelopia-wp-ai' ),
		];
	}

	// Validate that some operation is specified.
	if ( empty( $ids ) && ! $all && ! $missing ) {
		return [
			'ids'                => $ids,
			'missing'            => $missing,
			'all'                => $all,
			'needs_confirmation' => false,
			'batch-size'         => $batch_size,
			'valid'              => false,
			'error'              => __( 'You must provide --ids=<1,2,3>, --missing, or --all to specify which images to process.', 'travelopia-wp-ai' ),
		];
	}

	// Determine if confirmation is needed.
	$needs_confirmation = $all || ( $missing && empty( $ids ) );

	// Return parsed arguments.
	return [
		'ids'                => $ids,
		'missing'            => $missing,
		'all'                => $all,
		'needs_confirmation' => $needs_confirmation,
		'batch-size'         => $batch_size,
		'valid'              => true,
	];
}

/**
 * Get images to process based on CLI options.
 *
 * @param array<string, mixed> $options Options.
 *
 * @return array<int> Image IDs.
 */
function get_cli_images_to_process( array $options = [] ): array {
	// Handle specific IDs.
	if ( ! empty( $options['ids'] ) ) {
		// Filter specific IDs for missing alt text if requested.
		$ids     = is_array( $options['ids'] ) ? $options['ids'] : [];
		$missing = isset( $options['missing'] ) ? (bool) $options['missing'] : false;

		// Return images to process.
		return get_images_to_process( $ids, $missing );
	}

	// Use get_all_images for bulk operations.
	$missing = isset( $options['missing'] ) ? (bool) $options['missing'] : false;

	// Return images to process.
	return get_all_images( $missing );
}

/**
 * Process images and generate alt text for CLI with batching and timing.
 *
 * @param array<int>           $image_ids Image IDs.
 * @param array<string, mixed> $options   Options.
 *
 * @return array<string, mixed> Results.
 */
function process_cli_images( array $image_ids, array $options ): array {
	// Initialize options with defaults.
	$options = wp_parse_args(
		$options,
		[
			'ids'        => [],
			'all'        => false,
			'missing'    => false,
			'batch-size' => 50, // Default batch size.
		]
	);

	// Validate AI is enabled and configured.
	$validation_result = validate_ai_configuration();

	// Bail if validation fails.
	if ( ! $validation_result['valid'] ) {
		// Return error result for all images if validation fails.
		$error_message          = isset( $validation_result['error'] ) ? strval( $validation_result['error'] ) : 'Unknown validation error';
		$error_result           = create_error_result( $error_message );
		$error_result['images'] = array_fill_keys(
			$image_ids,
			[
				'id'      => 0,
				'success' => false,
				'error'   => $validation_result['error'] ?? 'Unknown validation error',
			]
		);

		// Add required timing fields for return type compatibility.
		$error_result['total_time']   = 0.0;
		$error_result['average_time'] = 0.0;

		// Return error result.
		return $error_result;
	}

	// Get images to process.
	$missing_only      = $options['missing'] ?? false;
	$images_to_process = get_images_to_process( $image_ids, $missing_only );

	// Bail if no images to process.
	if ( empty( $images_to_process ) ) {
		// Return empty result if no images to process.
		return [
			'success'       => true,
			'processed'     => 0,
			'success_count' => 0,
			'failed_count'  => 0,
			'images'        => [],
			'total_time'    => 0.0,
			'average_time'  => 0.0,
		];
	}

	// Process each image individually.
	$success_count = 0;
	$failed_count  = 0;
	$images        = [];
	$start_time    = microtime( true );

	// Process each image in the list.
	foreach ( $images_to_process as $image_id ) {
		$image_start_time = microtime( true );

		// Generate alt text for this image.
		$image_result = generate_alt_text_for_attachment( $image_id );

		// Calculate processing time for this image.
		$processing_time = microtime( true ) - $image_start_time;

		// Build result entry.
		$entry = [
			'id'              => absint( $image_id ),
			'success'         => (bool) $image_result['success'],
			'processing_time' => round( $processing_time, 3 ),
		];

		// Add alt text if it exists.
		if ( isset( $image_result['alt_text'] ) ) {
			$entry['alt_text'] = strval( $image_result['alt_text'] );
		}

		// Add error if it exists.
		if ( isset( $image_result['error'] ) ) {
			$entry['error'] = strval( $image_result['error'] );
		}

		// Store result with image ID as key.
		$images[ $image_id ] = $entry;

		// Update counters.
		if ( $image_result['success'] ) {
			++$success_count;
		} else {
			++$failed_count;
		}

		// Memory management for large batches.
		if ( ( $success_count + $failed_count ) % 100 === 0 ) {
			wp_cache_flush();
		}
	}

	// Calculate total time and average time.
	$total_time   = microtime( true ) - $start_time;
	$processed    = $success_count + $failed_count;
	$average_time = $processed > 0 ? $total_time / max( 1, $processed ) : 0.0;

	// Return results.
	return [
		'success'       => true,
		'processed'     => $processed,
		'success_count' => $success_count,
		'failed_count'  => $failed_count,
		'images'        => $images,
		'total_time'    => round( $total_time, 3 ),
		'average_time'  => round( $average_time, 3 ),
	];
}

/**
 * Create error result for CLI.
 *
 * @param string $error_message Error message.
 *
 * @return array<string, mixed> Error result.
 */
function create_error_result( string $error_message ): array {
	// Return error result structure.
	return [
		'success'       => false,
		'error'         => $error_message,
		'processed'     => 0,
		'success_count' => 0,
		'failed_count'  => 0,
		'images'        => [],
	];
}

/**
 * Format processing time in a human-readable format.
 *
 * @param float $seconds Time in seconds.
 *
 * @return string Formatted time string.
 */
function format_processing_time( float $seconds ): string {
	// Format time based on duration.
	if ( $seconds < 60 ) {
		return sprintf( '%.2fs', $seconds );
	} elseif ( $seconds < 3600 ) {
		$minutes           = floor( $seconds / 60 );
		$remaining_seconds = $seconds % 60;

		// Return formatted time.
		return sprintf( '%dm %.1fs', $minutes, $remaining_seconds );
	} else {
		$hours   = floor( $seconds / 3600 );
		$minutes = floor( ( $seconds % 3600 ) / 60 );

		// Return formatted time.
		return sprintf( '%dh %dm', $hours, $minutes );
	}
}
