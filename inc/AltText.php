<?php
/**
 * Alt Text module.
 *
 * @package travelopia-wordpress-ai
 */

namespace Travelopia\WordPress_AI;

use Closure;
use Exception;
use Travelopia\WordPress_AI\AltText\Admin;
use WP_CLI;
use WP_Error;
use WP_Query;

class AltText
{
	/**
	 * Default batch size for processing.
	 *
	 * @var int
	 */
	public const DEFAULT_BATCH_SIZE = 50;

	/**
	 * Bootstrap the alt text module.
	 *
	 * @return void
	 *
	 * @throws Exception
	 */
	public static function bootstrap(): void
	{
		// Register WP CLI commands regardless of settings — CLI should always be available.
		if ( class_exists( 'WP_CLI' ) ) {
			WP_CLI::add_command( 'travelopia-wp-ai alt-text', AltText\CLI::class );
		}

		// Check if this module is enabled.
		if ( true !== Settings::get_setting( Settings::FIELD_ALT_TEXT_GENERATION, false ) ) {
			return;
		}

		// Hooks.
		add_action( 'add_attachment', [ __CLASS__, 'generate' ], 20 );

		// Bootstrap admin functionality.
		Admin::bootstrap();
	}

	/**
	 * Generate alt text for any image attachment.
	 *
	 * @param int     $attachment_id Attachment ID.
	 * @param boolean $update        Whether to update the alt text.
	 *
	 * @return string|WP_Error Generated alt text on success, WP_Error on failure.
	 */
	public static function generate( int $attachment_id = 0, bool $update = true ): WP_Error|string
	{
		// Get actual image URL for the attachment.
		$image_url = wp_get_attachment_image_url( $attachment_id, 'large' );

		if ( ! is_string( $image_url ) || ! wp_attachment_is_image( $attachment_id ) ) {
			return self::report_error(
				'travelopia_wordpress_ai_alt_text_invalid_image',
				__( 'Invalid image.', 'travelopia-wordpress-ai' ),
				[ 'attachment_id' => $attachment_id ],
			);
		}

		/**
		 * Should we include additional context about the image?
		 *
		 * @param bool $include_context Whether to include context.
		 */
		$context         = '';
		$include_context = (bool) apply_filters( 'travelopia_wordpress_ai_alt_text_include_context', true );

		// Build context from metadata if requested.
		if ( true === $include_context ) {
			$context_parts = [];
			$title         = get_the_title( $attachment_id );

			// Add title to context.
			if ( ! empty( $title ) ) {
				$context_parts[] = sprintf(
					/* translators: %s: title */
					__( 'title: %s', 'travelopia-wordpress-ai' ),
					$title,
				);
			}

			// Join context parts with a semicolon.
			$context = implode( '; ', $context_parts );
		}

		$adapter = Adapter::get();

		if ( null === $adapter ) {
			return self::report_error(
				'travelopia_wordpress_ai_no_adapter',
				__( 'No AI adapter configured.', 'travelopia-wordpress-ai' ),
			);
		}

		/**
		 * Filter the ALT text generation options.
		 *
		 * @param array $default_options The generation options.
		 * @param int   $attachment_id   The attachment ID.
		 */
		$default_options = [
			...$adapter::get_default_options(),
			'prompt'  => Settings::get_setting( Settings::FIELD_ALT_TEXT_PROMPT, '' ),
			'context' => $context,
		];

		$filtered = apply_filters(
			'travelopia_wordpress_ai_alt_text_generation_options',
			$default_options,
			$attachment_id,
		);

		$options = is_array( $filtered ) ? $filtered : $default_options;

		// Ensure string keys from the filter output.
		$options = array_combine(
			array_map( static fn ( mixed $key ): string => (string) $key, array_keys( $options ) ),
			array_values( $options ),
		);

		// Add context to prompt if requested.
		if ( ! empty( $options['context'] ) && is_string( $options['prompt'] ) ) {
			$options['prompt'] .= sprintf(
				/* translators: %s: context */
				__( ' Additional context: %s', 'travelopia-wordpress-ai' ),
				(string) $options['context'],
			);
		}

		// Generate alt text using the active AI adapter.
		$alt_text = $adapter::generate_alt_text( $image_url, $options );

		if ( $alt_text instanceof WP_Error ) {
			return $alt_text;
		}

		// Save generated alt text to database.
		if ( true === $update ) {
			update_post_meta( $attachment_id, '_wp_attachment_image_alt', $alt_text );
		}

		// Fire action hook after successful generation.
		do_action( 'travelopia_wordpress_ai_alt_text_generated', $attachment_id, $alt_text );

		return $alt_text;
	}

	/**
	 * Run alt-text generation against a batch of images.
	 *
	 * Two modes:
	 * - $image_ids non-empty → process exactly those IDs (sliced to $limit if set).
	 * - $image_ids empty     → paginate the attachment query (filtered by $missing_only).
	 *
	 * Counts every attempt toward $limit regardless of success or failure.
	 * For --missing runs, successfully processed images naturally drop out
	 * of subsequent page-1 queries — re-runs continue chunked backfills
	 * without an external cursor.
	 *
	 * The optional $on_image callback receives ( int $image_id, string|WP_Error $result )
	 * after each attempt so callers can render progress or per-image output without
	 * coupling this method to any presentation layer.
	 *
	 * @param int[]    $image_ids    Optional explicit list. Empty means "query from DB".
	 * @param bool     $missing_only When querying, only return images missing alt text.
	 * @param int      $batch_size   Page size for the paginated query.
	 * @param ?int     $limit        Maximum number of attempts. Null means no cap.
	 * @param ?Closure $on_image     Optional callback invoked after each attempt.
	 *
	 * @return array{success: int, failed: int, attempts: int}
	 */
	public static function run_batch(
		array $image_ids = [],
		bool $missing_only = false,
		int $batch_size = self::DEFAULT_BATCH_SIZE,
		?int $limit = null,
		?Closure $on_image = null,
	): array {
		$cap           = $limit ?? PHP_INT_MAX;
		$attempts      = 0;
		$success_count = 0;
		$failed_count  = 0;

		// Explicit ID path — slice up front, no pagination, no failed-ID tracking.
		if ( ! empty( $image_ids ) ) {
			if ( null !== $limit ) {
				$image_ids = array_slice( $image_ids, 0, $limit );
			}

			foreach ( $image_ids as $image_id ) {
				$result = self::generate( $image_id );
				++$attempts;

				if ( $result instanceof WP_Error ) {
					++$failed_count;
				} else {
					++$success_count;
				}

				if ( null !== $on_image ) {
					$on_image( $image_id, $result );
				}
			}

			return [
				'success'  => $success_count,
				'failed'   => $failed_count,
				'attempts' => $attempts,
			];
		}

		// Paginated path.
		$page       = 1;
		$failed_ids = [];

		do {
			// --missing re-queries page 1 (processed items drop out of result set); --all uses standard page increment.
			$query_page = $missing_only ? 1 : $page;

			$batch = self::query_images(
				missing_only: $missing_only,
				page:         $query_page,
				per_page:     $batch_size,
			);

			if ( empty( $batch ) ) {
				break;
			}

			// Skip already-failed IDs to prevent infinite loops on --missing.
			$actionable = $missing_only ? array_diff( $batch, $failed_ids ) : $batch;

			if ( empty( $actionable ) ) {
				break;
			}

			foreach ( $actionable as $image_id ) {
				if ( $attempts >= $cap ) {
					break 2;
				}

				$result = self::generate( $image_id );
				++$attempts;

				if ( $result instanceof WP_Error ) {
					$failed_ids[] = $image_id;
					++$failed_count;
				} else {
					++$success_count;
				}

				if ( null !== $on_image ) {
					$on_image( $image_id, $result );
				}
			}

			// Free memory between batches.
			if ( function_exists( 'wp_cache_flush_runtime' ) ) {
				wp_cache_flush_runtime();
			}

			++$page;
		} while ( true );

		return [
			'success'  => $success_count,
			'failed'   => $failed_count,
			'attempts' => $attempts,
		];
	}

	/**
	 * Query images for alt text generation.
	 *
	 * @param int[] $image_ids    Specific image IDs to query. Default empty (all images).
	 * @param bool  $missing_only Only images missing alt text.
	 * @param int   $page         Page number for pagination.
	 * @param int   $per_page     Images per page.
	 *
	 * @return int[] Array of image IDs.
	 */
	public static function query_images(
		array $image_ids = [],
		bool $missing_only = false,
		int $page = 1,
		int $per_page = self::DEFAULT_BATCH_SIZE,
	): array {
		$query_args = self::build_query_args( $missing_only );

		// Handle specific image IDs.
		if ( ! empty( $image_ids ) ) {
			$query_args['post__in']       = $image_ids;
			$query_args['posts_per_page'] = count( $image_ids );
			$query_args['no_found_rows']  = true;
		} else {
			$query_args['posts_per_page'] = $per_page;
			$query_args['paged']          = $page;
			$query_args['no_found_rows']  = true;
		}

		$images_query = new WP_Query( $query_args );

		return array_map( 'absint', $images_query->posts ?? [] );
	}

	/**
	 * Count images matching the given criteria.
	 *
	 * Uses a lightweight COUNT query via found_posts — fetches only 1 row
	 * to avoid loading large result sets into memory.
	 *
	 * @param bool $missing_only Only count images missing alt text.
	 *
	 * @return int Total image count.
	 */
	public static function count_images( bool $missing_only = false ): int
	{
		$query_args = self::build_query_args( $missing_only );

		$query_args['posts_per_page'] = 1;
		$query_args['no_found_rows']  = false;

		$images_query = new WP_Query( $query_args );

		return (int) $images_query->found_posts;
	}

	/**
	 * Build a WP_Error and fire the generic plugin error action.
	 *
	 * @param string               $code    Error code.
	 * @param string               $message Error message.
	 * @param array<string, mixed> $data    Error data.
	 *
	 * @return WP_Error
	 */
	private static function report_error( string $code, string $message, array $data = [] ): WP_Error
	{
		/**
		 * Fires when the alt text module returns an error before invoking the active AI adapter.
		 *
		 * Adapter-level failures fire `travelopia_wordpress_ai_bedrock_error` /
		 * `travelopia_wordpress_ai_open_ai_error` instead.
		 *
		 * @param string               $code    Error code.
		 * @param string               $message Error message.
		 * @param array<string, mixed> $data    Error data.
		 */
		do_action( 'travelopia_wordpress_ai_error', $code, $message, $data );

		return new WP_Error( $code, $message, $data );
	}

	/**
	 * Build shared WP_Query arguments for image queries.
	 *
	 * @param bool $missing_only Only images missing alt text.
	 *
	 * @return array<string, mixed> Query arguments.
	 */
	private static function build_query_args( bool $missing_only = false ): array
	{
		$query_args = [
			'post_type'              => 'attachment',
			'post_mime_type'         => 'image',
			'post_status'            => 'inherit',
			'fields'                 => 'ids',
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
		];

		if ( $missing_only ) {
			$query_args['meta_query'] = [ // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query.
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

		return $query_args;
	}
}
