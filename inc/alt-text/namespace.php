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
use WP_REST_Request;

use function Travelopia_WordPress_AI\generate_alt_text_for_attachment;
use function Travelopia_WordPress_AI\get_setting;

/**
 * Alt Text Constants.
 */
const DEFAULT_BATCH_SIZE = 50;

/**
 * Bootstrap the alt text namespace.
 *
 * @return void
 */
function bootstrap(): void {
	// If AI alt text generation is not enabled.
	if ( ! get_setting( 'ai_alt_text_enabled', false ) ) {
		// Bail.
		return;
	}

	// Actions.
	add_action( 'admin_enqueue_scripts', __NAMESPACE__ . '\\admin_enqueue_scripts' );
	add_action( 'rest_after_insert_attachment', __NAMESPACE__ . '\\handle_rest_alt_text_update', 10, 2 );

	// Filters.
	add_filter( 'media_row_actions', __NAMESPACE__ . '\\media_row_actions', 10, 2 );
}

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
 * Get meta query for missing alt text filter.
 *
 * @return array<int|string, mixed> Meta query array.
 */
function get_missing_alt_text_meta_query(): array {
	// Return meta query for missing alt text filter.
	return [
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

/**
 * Create a standardized WP_Error for alt text operations.
 *
 * @param string               $error_code    Error code.
 * @param string               $error_message Error message.
 * @param array<string, mixed> $error_data    Additional error data.
 *
 * @return WP_Error
 */
function create_alt_text_error( string $error_code = '', string $error_message = '', array $error_data = [] ): WP_Error {
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
function handle_alt_text_error( string $error_code = '', string $error_message = '', array $error_data = [] ): array {
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
		$query_args['meta_query'] = get_missing_alt_text_meta_query();
	}

	// Get images.
	$images_query = new WP_Query( $query_args );

	// Return images.
	return array_map( 'absint', $images_query->posts );
}

/**
 * Get all images on the site with pagination.
 *
 * @param bool $missing_only Whether to only get images missing alt text.
 * @param int  $page         Page number.
 * @param int  $per_page     Number of images per page.
 *
 * @return array<int> Array of image IDs.
 */
function get_all_images( bool $missing_only = false, int $page = 1, int $per_page = DEFAULT_BATCH_SIZE ): array {
	// Build query arguments for all images.
	$query_args = [
		'post_type'              => 'attachment',
		'post_mime_type'         => 'image',
		'post_status'            => 'inherit',
		'posts_per_page'         => $per_page,
		'paged'                  => $page,
		'fields'                 => 'ids',
		'no_found_rows'          => false,
		'update_post_meta_cache' => false,
		'update_post_term_cache' => false,
	];

	// Filter for images missing alt text if requested.
	if ( $missing_only ) {
		$query_args['meta_query'] = get_missing_alt_text_meta_query();
	}

	// Get images.
	$images_query = new WP_Query( $query_args );

	// Return images.
	return array_map( 'absint', $images_query->posts );
}

/**
 * Get total count of images on the site.
 *
 * @param bool $missing_only Whether to only count images missing alt text.
 *
 * @return int Total count of images.
 */
function get_images_count( bool $missing_only = false ): int {
	// Build query arguments for counting.
	$query_args = [
		'post_type'              => 'attachment',
		'post_mime_type'         => 'image',
		'post_status'            => 'inherit',
		'posts_per_page'         => 1,
		'fields'                 => 'ids',
		'no_found_rows'          => false,
		'update_post_meta_cache' => false,
		'update_post_term_cache' => false,
	];

	// Filter for images missing alt text if requested.
	if ( $missing_only ) {
		$query_args['meta_query'] = get_missing_alt_text_meta_query();
	}

	// Get count using WP_Query.
	$images_query = new WP_Query( $query_args );

	// Return total count.
	return $images_query->found_posts;
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
		do_action(
			'trav_ai_alt_text_error',
			'ai_not_enabled',
			sprintf(
				/* translators: %s: settings page URL */
				__( 'AI alt text generation is not enabled. Please enable it in Settings > Travelopia WP AI.', 'travelopia-wp-ai' ),
				admin_url( 'options-general.php?page=travelopia-wp-ai-settings' )
			)
		);

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
		// Error action hook.
		do_action(
			'trav_ai_alt_text_error',
			'ai_prompt_not_configured',
			sprintf(
				/* translators: %s: settings page URL */
				__( 'AI prompt is not configured. Please set it in Settings > Travelopia WP AI.', 'travelopia-wp-ai' ),
				admin_url( 'options-general.php?page=travelopia-wp-ai-settings' )
			)
		);

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
		// Error action hook.
		do_action(
			'trav_ai_alt_text_error',
			'api_key_not_configured',
			sprintf(
				/* translators: %s: settings page URL */
				__( 'OpenAI API key not configured. Please set OPENAI_API_KEY in wp-config.php or environment.', 'travelopia-wp-ai' ),
				admin_url( 'options-general.php?page=travelopia-wp-ai-settings' )
			)
		);

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
function get_image_details( int $image_id = 0 ): array {
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
function parse_cli_arguments( array $args_assoc = [] ): array {
	// Parse and validate command arguments.
	$options = wp_parse_args(
		$args_assoc,
		[
			'ids'        => [],
			'missing'    => false,
			'all'        => false,
			'batch-size' => DEFAULT_BATCH_SIZE,
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

	// Use get_all_images for bulk operations with pagination.
	$missing  = isset( $options['missing'] ) ? (bool) $options['missing'] : false;
	$page     = isset( $options['page'] ) ? max( 1, absint( $options['page'] ) ) : 1;
	$per_page = isset( $options['per_page'] ) ? max( 1, absint( $options['per_page'] ) ) : DEFAULT_BATCH_SIZE;

	// Return images to process.
	return get_all_images( $missing, $page, $per_page );
}

/**
 * Process images and generate alt text for CLI with batching and timing.
 *
 * @param array<int>           $image_ids Image IDs.
 * @param array<string, mixed> $options   Options.
 *
 * @return array<string, mixed> Results.
 */
function process_cli_images( array $image_ids = [], array $options = [] ): array {
	// Initialize options with defaults.
	$options = wp_parse_args(
		$options,
		[
			'ids'        => [],
			'all'        => false,
			'missing'    => false,
			'batch-size' => DEFAULT_BATCH_SIZE,
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
function create_error_result( string $error_message = '' ): array {
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
function format_processing_time( float $seconds = 0.0 ): string {
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

/**
 * Get attachment data for alt text editor.
 *
 * @return array<string, mixed>|null Array with post, alt_text, and mode, or null if invalid.
 */
function get_attachment_editor_data(): ?array {
	// Get current screen.
	$screen = get_current_screen();

	// Only proceed on attachment edit screen.
	if ( ! $screen || 'attachment' !== $screen->id ) {
		return null;
	}

	// Get post ID from URL.
	$post_id = isset( $_GET['post'] ) ? absint( $_GET['post'] ) : 0;

	// Return if no valid post ID.
	if ( ! $post_id ) {
		return null;
	}

	// Get post object.
	$post = get_post( $post_id );

	// Return if not a valid WP_Post object.
	if ( ! $post instanceof WP_Post ) {
		return null;
	}

	// Return if not an image attachment.
	if ( 'attachment' !== $post->post_type || false === strpos( $post->post_mime_type, 'image' ) ) {
		return null;
	}

	// Get the existing alt text.
	$is_regeneration = isset( $_GET['tp_regenerate_alt_text'] );
	$valid_request   = ! isset( $_GET['tp_nonce'] ) ? false : wp_verify_nonce( $_GET['tp_nonce'], 'generate_alt_text_' . $post->ID );
	$is_generation   = isset( $_GET['tp_generate_alt_text'] );
	$alt_text        = get_post_meta( $post->ID, '_wp_attachment_image_alt', true );
	$is_empty_alt    = empty( $alt_text );

	// If query args has tp_generate_alt_text, then generate the alt text and save it.
	if ( $is_generation && $valid_request && $is_empty_alt ) {
		$result       = generate_alt_text_for_attachment( $post->ID, true );
		$alt_text     = $result['alt_text'] ?? '';
		$is_empty_alt = false;
	}

	// If query args has tp_regenerate_alt_text, then regenerate the alt text.
	if ( $is_regeneration && $valid_request ) {
		$result = generate_alt_text_for_attachment( $post->ID, false );

		// On success, update the alt text.
		if ( ! empty( $result['success'] ) ) {
			$alt_text = $result['alt_text'] ?? '';
		}
	}

	// Determine the mode for the component.
	$mode = $is_regeneration ? 'regenerate' : 'default';

	// Return the attachment editor data.
	return [
		'post'     => $post,
		'alt_text' => $alt_text,
		'mode'     => $mode,
	];
}

/**
 * Enqueue editor assets.
 *
 * @return void
 */
function admin_enqueue_scripts(): void {
	// Get attachment data.
	$data = get_attachment_editor_data();

	// Return if no valid data.
	if ( ! $data ) {
		return;
	}

	// Extract post from data and ensure it's a WP_Post object.
	$post = $data['post'] ?? null;

	// Return if post is not a valid WP_Post instance.
	if ( ! $post instanceof WP_Post ) {
		return;
	}

	// Enqueue editor scripts.
	wp_enqueue_script( 'trav-ai-editor', plugins_url( 'dist/editor.js', plugin_dir_path( __DIR__ ) ), [], '1.0.0', true );
	wp_enqueue_style( 'trav-ai-editor', plugins_url( 'dist/editor.css', plugin_dir_path( __DIR__ ) ), [], '1.0.0' );

	// Localize script with all necessary data.
	wp_localize_script(
		'trav-ai-editor',
		'travelopiaWpAi',
		[
			'api'        => [
				'root'  => rest_url(),
				'nonce' => wp_create_nonce( 'wp_rest' ),
			],
			'nonces'     => [
				'rest' => wp_create_nonce( 'wp_rest' ),
			],
			'attachment' => [
				'id'      => $post->ID,
				'altText' => $data['alt_text'],
				'mode'    => $data['mode'],
			],
			'urls'       => [
				'generate'   => get_alt_text_action_url( $post, false ),
				'regenerate' => get_alt_text_action_url( $post, true ),
				'reject'     => admin_url( 'post.php?post=' . $post->ID . '&action=edit' ),
			],
			'labels'     => [
				'generateAltText'   => __( 'Generate Alt Text', 'travelopia-wp-ai' ),
				'regenerateAltText' => __( 'Regenerate Alt Text', 'travelopia-wp-ai' ),
				'accept'            => __( 'Accept', 'travelopia-wp-ai' ),
				'reject'            => __( 'Reject', 'travelopia-wp-ai' ),
				'regenerate'        => __( 'Regenerate', 'travelopia-wp-ai' ),
				'saving'            => __( 'Saving...', 'travelopia-wp-ai' ),
			],
		]
	);
}

/**
 * Adds Quick Action CTA for generating Alt Text in Media Library Admin Page.
 *
 * @param mixed[] $actions Actions.
 * @param WP_Post $post    Post object.
 *
 * @return mixed[]
 */
function media_row_actions( array $actions = [], ?WP_Post $post = null ): array {
	// Return early if post is null.
	if ( ! $post ) {
		return $actions;
	}

	// Return early if the post is not an image.
	if ( 'attachment' !== $post->post_type || strpos( $post->post_mime_type, 'image' ) === false ) {
		return $actions;
	}

	// Check if the image has alt text or not.
	$alt_text = get_post_meta( $post->ID, '_wp_attachment_image_alt', true );

	// Check if the image has alt text or not.
	$base_url = admin_url( 'post.php?post=' . $post->ID . '&action=edit&' . ( empty( $alt_text ) ? 'tp_generate_alt_text=true' : 'tp_regenerate_alt_text=true' ) );
	$nonce    = wp_create_nonce( 'generate_alt_text_' . $post->ID );
	$url      = add_query_arg( 'tp_nonce', $nonce, $base_url );

	// Add the CTA on the actions row of the media item in list view.
	$actions['generate_alt_text'] = sprintf(
		'<a href="%s">%s</a>',
		esc_url( $url ),
		empty( $alt_text ) ? __( 'Generate Alt Text', 'travelopia-wp-ai' ) : __( 'Regenerate Alt Text', 'travelopia-wp-ai' )
	);

	// Return the updated actions.
	return $actions;
}

/**
 * Get the CTA link.
 *
 * @param WP_Post $post            Post object.
 * @param boolean $is_regeneration URL is for alt text regeneration CTA.
 *
 * @return string
 */
function get_alt_text_action_url( ?WP_Post $post = null, bool $is_regeneration = false ): string {
	// Return empty string if post is null.
	if ( ! $post ) {
		return '';
	}

	// Build base URL.
	$base_url = admin_url( 'post.php?post=' . $post->ID . '&action=edit&' . ( $is_regeneration ? 'tp_regenerate_alt_text=true' : 'tp_generate_alt_text=true' ) );

	// Add nonce parameter.
	$nonce = wp_create_nonce( 'generate_alt_text_' . $post->ID );

	// Return the URL with nonce.
	return add_query_arg( 'tp_nonce', $nonce, $base_url );
}

/**
 * Handle REST API alt text update.
 *
 * This function hooks into the REST API after an attachment is updated
 * to trigger our custom action and maintain compatibility with existing hooks.
 *
 * @param WP_Post         $attachment The updated attachment object.
 * @param WP_REST_Request $request    The request object.
 *
 * @return void
 */
function handle_rest_alt_text_update( ?WP_Post $attachment = null, ?WP_REST_Request $request = null ): void {
	// Return early if attachment or request is null.
	if ( ! $attachment || ! $request ) {
		return;
	}

	// Check if this is an alt text update.
	if ( ! $request->has_param( 'alt_text' ) ) {
		return;
	}

	// Get the alt text from the request.
	$alt_text = $request->get_param( 'alt_text' );

	// Fire action hook after successful alt text modification via REST API.
	do_action( 'trav_ai_alt_text_modified', $attachment->ID, $alt_text );
}
