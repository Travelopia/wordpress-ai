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
 * Bootstrap alt text functionality.
 *
 * @return void
 */
function bootstrap(): void {
	// Alt text functionality is autoloaded, no additional setup needed.
}

/**
 * Process images in batches to prevent memory issues.
 *
 * @param array<int> $image_ids    Array of image IDs to process.
 * @param bool       $missing_only Whether to only process images missing alt text.
 * @param int        $batch_size   Number of images to process per batch.
 *
 * @return array{
 *   success: int,
 *   failed: int,
 *   results: array<int, array{
 *     success: bool,
 *     alt_text?: string,
 *     error?: string,
 *     processing_time?: float
 *   }>,
 *   total_time: float
 * }
 */
function generate_alt_text_for_images_batched( array $image_ids, bool $missing_only = false, int $batch_size = 50 ): array {
	// Validate AI is enabled and configured.
	$validation_result = validate_ai_configuration();

	// Bail if validation fails.
	if ( ! $validation_result['valid'] ) {
		// Return error result for all images if validation fails.
		return [
			'success'    => 0,
			'failed'     => count( $image_ids ),
			'results'    => array_fill_keys(
				$image_ids,
				[
					'success'  => false,
					'alt_text' => '',
					'error'    => $validation_result['error'] ?? 'Unknown validation error',
				]
			),
			'total_time' => 0.0,
		];
	}

	// Get images to process.
	$images_to_process = get_images_to_process( $image_ids, $missing_only );

	// Bail if no images to process.
	if ( empty( $images_to_process ) ) {
		// Return empty result if no images to process.
		return [
			'success'    => 0,
			'failed'     => 0,
			'results'    => [],
			'total_time' => 0.0,
		];
	}

	// Process images in batches.
	$success_count = 0;
	$failed_count  = 0;
	$results       = [];
	$start_time    = microtime( true );
	$batches       = array_chunk( $images_to_process, max( 1, $batch_size ) );

	// Process each batch.
	foreach ( $batches as $batch ) {
		// Process each batch.
		$batch_result = generate_alt_text_for_images( $batch, $missing_only );

		// Merge results.
		$success_count += $batch_result['success'];
		$failed_count  += $batch_result['failed'];
		$results        = array_merge( $results, $batch_result['results'] );

		// Memory management.
		wp_cache_flush();
	}

	// Calculate total processing time.
	$total_time = microtime( true ) - $start_time;

	// Return results.
	return [
		'success'    => $success_count,
		'failed'     => $failed_count,
		'results'    => $results,
		'total_time' => round( $total_time, 3 ),
	];
}

/**
 * Generate alt text for multiple images.
 *
 * @param array<int> $image_ids    Array of image IDs to process.
 * @param bool       $missing_only Whether to only process images missing alt text.
 *
 * @return array{
 *   success: int,
 *   failed: int,
 *   results: array<int, array{
 *     success: bool,
 *     alt_text?: string,
 *     error?: string,
 *     processing_time?: float
 *   }>,
 *   total_time: float
 * }
 */
function generate_alt_text_for_images( array $image_ids, bool $missing_only = false ): array {
	// Validate AI is enabled and configured.
	$validation_result = validate_ai_configuration();

	// Bail if validation fails.
	if ( ! $validation_result['valid'] ) {
		// Return error result for all images if validation fails.
		return [
			'success'    => 0,
			'failed'     => count( $image_ids ),
			'results'    => array_fill_keys(
				$image_ids,
				[
					'success'  => false,
					'alt_text' => '',
					'error'    => $validation_result['error'] ?? 'Unknown validation error',
				]
			),
			'total_time' => 0.0,
		];
	}

	// Get images to process.
	$images_to_process = get_images_to_process( $image_ids, $missing_only );

	// Bail if no images to process.
	if ( empty( $images_to_process ) ) {
		// Return empty result if no images to process.
		return [
			'success'    => 0,
			'failed'     => 0,
			'results'    => [],
			'total_time' => 0.0,
		];
	}

	// Process each image with timing.
	$success_count = 0;
	$failed_count  = 0;
	$results       = [];
	$start_time    = microtime( true );

	// Process each image with progress tracking.
	$total_images = count( $images_to_process );
	$processed    = 0;

	// Process each image individually.
	foreach ( $images_to_process as $image_id ) {
		// Process each image individually.
		$image_start_time = microtime( true );

		// Generate alt text for each image.
		$result = generate_alt_text_for_attachment( $image_id );

		// Calculate processing time for this image.
		$processing_time           = microtime( true ) - $image_start_time;
		$result['processing_time'] = round( $processing_time, 3 );
		$results[ $image_id ]      = $result;

		// Check if the result is successful.
		if ( $result['success'] ) {
			// Increment success counter.
			++$success_count;
		} else {
			// Increment failure counter.
			++$failed_count;
		}

		// Increment processed counter.
		++$processed;

		// Memory management for large batches.
		if ( 0 === $processed % 100 ) {
			// Clear any cached data to prevent memory issues.
			wp_cache_flush();
		}
	}

	// Calculate total processing time.
	$total_time = microtime( true ) - $start_time;

	// Return results.
	return [
		'success'    => $success_count,
		'failed'     => $failed_count,
		'results'    => $results,
		'total_time' => round( $total_time, 3 ),
	];
}

/**
 * Get images that need alt text generation.
 *
 * @param array<int> $image_ids    Array of image IDs to process.
 * @param bool       $missing_only Whether to only process images missing alt text.
 *
 * @return array<int> Array of image IDs to process.
 */
function get_images_to_process( array $image_ids, bool $missing_only = false ): array {
	// Build query arguments.
	$query_args = [
		'post_type'      => 'attachment',
		'post_mime_type' => 'image',
		'post_status'    => 'inherit',
		'posts_per_page' => -1,
		'fields'         => 'ids',
		'post__in'       => $image_ids,
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
 * @return array{valid: bool, error?: string}
 */
function validate_ai_configuration(): array {
	// Check if AI alt text generation is enabled.
	$ai_enabled = get_setting( 'ai_alt_text_enabled', false );

	// Bail if AI is not enabled.
	if ( ! $ai_enabled ) {
		// Return error if AI is not enabled.
		return [
			'valid' => false,
			'error' => __( 'AI alt text generation is not enabled. Please enable it in Settings > Travelopia WP AI.', 'travelopia-wp-ai' ),
		];
	}

	// Get the AI prompt.
	$ai_prompt = get_setting( 'ai_alt_text_prompt', '' );

	// Bail if AI prompt is not configured.
	if ( empty( $ai_prompt ) ) {
		// Return error if AI prompt is not configured.
		return [
			'valid' => false,
			'error' => __( 'AI prompt is not configured. Please set it in Settings > Travelopia WP AI.', 'travelopia-wp-ai' ),
		];
	}

	// Return validation result.
	return [ 'valid' => true ];
}

/**
 * Get AI configuration details.
 *
 * @return array{
 *   enabled: bool,
 *   prompt: string
 * }
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
 * @return array{
 *   id: int,
 *   title: string,
 *   has_alt_text: bool,
 *   alt_text: string
 * }
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
 * Generate alt text for images via CLI.
 *
 * @param array{ids?: array<int>, all?: bool, missing?: bool} $options CLI options.
 *
 * @return array{success: bool, processed: int, success_count: int, failed_count: int, images: array<int, array{id: int, success: bool, alt_text?: string, error?: string, skipped?: bool, reason?: string}>} Results.
 */
function cli_generate_alt_text( array $options = [] ): array {
	// Set default options.
	$options = wp_parse_args(
		$options,
		[
			'ids'     => [],
			'all'     => false,
			'missing' => false,
		]
	);

	// Validate AI configuration.
	if ( ! get_setting( 'ai_alt_text_enabled', false ) ) {
		// Return error if AI is not enabled.
		return create_error_result( __( 'AI alt text generation is not enabled.', 'travelopia-wp-ai' ) );
	}

	// Check if prompt is configured.
	if ( empty( get_setting( 'ai_alt_text_prompt', '' ) ) ) {
		// Return error if prompt is not configured.
		return create_error_result( __( 'AI prompt is not configured.', 'travelopia-wp-ai' ) );
	}

	// Get images to process.
	$image_ids = get_cli_images_to_process( $options );

	// Check if images were found.
	if ( empty( $image_ids ) ) {
		// Return error if no images found.
		return create_error_result( __( 'No images found matching the specified criteria.', 'travelopia-wp-ai' ) );
	}

	// Process images.
	return process_cli_images( $image_ids, $options );
}

/**
 * Get images to process based on CLI options.
 *
 * @param array{ids?: array<int>, all?: bool, missing?: bool} $options Options.
 *
 * @return array<int> Image IDs.
 */
function get_cli_images_to_process( array $options ): array {
	// Initialize options with defaults.
	$options = wp_parse_args(
		$options,
		[
			'ids'     => [],
			'all'     => false,
			'missing' => false,
		]
	);

	// Build query arguments.
	$query_args = [
		'post_type'      => 'attachment',
		'post_mime_type' => 'image',
		'post_status'    => 'inherit',
		'posts_per_page' => -1,
		'fields'         => 'ids',
	];

	// Handle specific IDs.
	if ( ! empty( $options['ids'] ) ) {
		$query_args['post__in'] = array_map( 'absint', $options['ids'] );
	} elseif ( ! $options['all'] && ! $options['missing'] ) {
		// Return empty array if no selection mode specified.
		return [];
	}

	// Filter for missing alt text if needed.
	if ( $options['missing'] ) {
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

	// Execute query.
	$query = new WP_Query( $query_args );

	// Return image IDs as integers.
	return array_map(
		function ( $post ) {
			return is_object( $post ) ? $post->ID : absint( $post );
		},
		$query->posts
	);
}

/**
 * Process images and generate alt text for CLI.
 *
 * @param array<int>                                          $image_ids Image IDs.
 * @param array{ids?: array<int>, all?: bool, missing?: bool} $options   Options.
 *
 * @return array{success: bool, processed: int, success_count: int, failed_count: int, images: array<int, array{id: int, success: bool, alt_text?: string, error?: string, skipped?: bool, reason?: string}>} Results.
 */
function process_cli_images( array $image_ids, array $options ): array {
	// Initialize options with defaults.
	$options = wp_parse_args(
		$options,
		[
			'ids'     => [],
			'all'     => false,
			'missing' => false,
		]
	);

	// Initialize counters.
	$success_count = 0;
	$failed_count  = 0;
	$images        = [];

	// Process each image.
	foreach ( $image_ids as $image_id ) {
		$result   = process_cli_single_image( $image_id, $options );
		$images[] = $result;

		// Update counters.
		if ( $result['success'] ) {
			++$success_count;
		} else {
			++$failed_count;
		}
	}

	// Return results.
	return [
		'success'       => true,
		'processed'     => count( $image_ids ),
		'success_count' => $success_count,
		'failed_count'  => $failed_count,
		'images'        => $images,
	];
}

/**
 * Process a single image for CLI.
 *
 * @param int                                                 $image_id Image ID.
 * @param array{ids?: array<int>, all?: bool, missing?: bool} $options  Options.
 *
 * @return array{id: int, success: bool, alt_text?: string, error?: string, skipped?: bool, reason?: string} Result.
 */
function process_cli_single_image( int $image_id, array $options ): array {
	// Initialize options with defaults.
	$options = wp_parse_args(
		$options,
		[
			'ids'     => [],
			'all'     => false,
			'missing' => false,
		]
	);

	// Get image post.
	$image_post = get_post( $image_id );

	// Validate image post.
	if ( ! $image_post instanceof WP_Post ) {
		return [
			'id'      => $image_id,
			'success' => false,
			'error'   => __( 'Invalid image post.', 'travelopia-wp-ai' ),
		];
	}

	// Skip if alt text exists (unless processing all or specific IDs without --missing).
	$should_skip_existing = ! $options['all'] && ! ( ! empty( $options['ids'] ) && ! $options['missing'] );

	// Check if we should skip existing alt text.
	if ( $should_skip_existing ) {
		// Check for existing alt text.
		$existing_alt = get_post_meta( $image_id, '_wp_attachment_image_alt', true );

		// Skip if alt text already exists.
		if ( ! empty( $existing_alt ) ) {
			return [
				'id'       => $image_id,
				'success'  => true,
				'skipped'  => true,
				'reason'   => __( 'Alt text already exists.', 'travelopia-wp-ai' ),
				'alt_text' => strval( $existing_alt ),
			];
		}
	}

	// Generate alt text.
	$result = generate_alt_text_for_attachment( $image_id, true );

	// Handle successful generation.
	if ( $result['success'] && ! empty( $result['alt_text'] ) ) {
		return [
			'id'       => $image_id,
			'success'  => true,
			'alt_text' => $result['alt_text'],
		];
	}

	// Handle failure.
	return [
		'id'      => $image_id,
		'success' => false,
		'error'   => $result['error'] ?? __( 'Unknown error.', 'travelopia-wp-ai' ),
	];
}

/**
 * Create error result for CLI.
 *
 * @param string $error Error message.
 *
 * @return array{success: bool, processed: int, success_count: int, failed_count: int, images: array<int, array{id: int, success: bool, alt_text?: string, error?: string, skipped?: bool, reason?: string}>} Error result.
 */
function create_error_result( string $error ): array {
	// Return error result structure.
	return [
		'success'       => false,
		'error'         => $error,
		'processed'     => 0,
		'success_count' => 0,
		'failed_count'  => 0,
		'images'        => [],
	];
}
