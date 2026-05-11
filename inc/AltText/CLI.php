<?php
/**
 * CLI Command for Alt Text Generation.
 *
 * @package travelopia-wordpress-ai
 */

namespace Travelopia\WordPress_AI\AltText;

use Travelopia\WordPress_AI\AltText;
use WP_CLI;
use WP_Error;

use function WP_CLI\Utils\make_progress_bar;

/**
 * CLI handler for alt text generation.
 */
class CLI
{
	/**
	 * Generate alt text for images using AI.
	 *
	 * ## OPTIONS
	 *
	 * [--ids=<1,2,3>]
	 * : Comma-separated list of image attachment IDs to process.
	 *
	 * [--missing]
	 * : Only process images that are missing alt text.
	 *
	 * [--all]
	 * : Process all images on the site.
	 *
	 * [--batch-size=<number>]
	 * : Number of images to process per batch. Default 50.
	 *
	 * [--limit=<number>]
	 * : Maximum number of images to attempt in this run (success or failure).
	 * : Useful for cost measurement, quality dry-runs, and chunked backfills.
	 * : With --ids: truncates the supplied list to the first N entries.
	 * : With --all: not resumable — successive runs reprocess the same first N images.
	 *
	 * ## EXAMPLES
	 *
	 *     # Generate alt text for specific images
	 *     wp travelopia-wp-ai alt-text generate --ids=123,456,789
	 *
	 *     # Process all images missing alt text
	 *     wp travelopia-wp-ai alt-text generate --missing
	 *
	 *     # Process all images on the site
	 *     wp travelopia-wp-ai alt-text generate --all
	 *
	 *     # Process in smaller batches (useful for memory-constrained environments)
	 *     wp travelopia-wp-ai alt-text generate --all --batch-size=20
	 *
	 *     # Cost / quality sample — process 1000 missing, then stop
	 *     wp travelopia-wp-ai alt-text generate --missing --limit=1000
	 *
	 *     # Chunked nightly backfill — 5000 a night via cron
	 *     wp travelopia-wp-ai alt-text generate --missing --limit=5000
	 *
	 * @param array<string, mixed> $args       WP CLI arguments.
	 * @param array<string, mixed> $args_assoc WP CLI associative arguments.
	 *
	 * @subcommand generate
	 *
	 * @synopsis [--ids=<1,2,3>] [--missing] [--all] [--batch-size=<number>] [--limit=<number>]
	 *
	 * @return void
	 */
	public function generate( array $args = [], array $args_assoc = [] ): void
	{
		$missing_only   = isset( $args_assoc['missing'] );
		$batch_size_raw = $args_assoc['batch-size'] ?? AltText::DEFAULT_BATCH_SIZE;
		$batch_size     = is_numeric( $batch_size_raw ) ? (int) $batch_size_raw : AltText::DEFAULT_BATCH_SIZE;

		$limit_raw = $args_assoc['limit'] ?? null;
		$limit     = null;
		if ( null !== $limit_raw ) {
			if ( ! is_numeric( $limit_raw ) || (int) (string) $limit_raw <= 0 ) {
				WP_CLI::error( __( 'Limit must be a positive integer.', 'travelopia-wordpress-ai' ) );
			}
			$limit = (int) (string) $limit_raw;
		}

		if ( isset( $args_assoc['ids'] ) ) {
			$ids       = explode( ',', (string) $args_assoc['ids'] );
			$image_ids = array_map( 'absint', $ids );
			$this->process_ids( $image_ids, $limit );
			return;
		}

		if ( ! isset( $args_assoc['all'] ) && ! $missing_only ) {
			WP_CLI::error( __( 'Please specify --ids, --missing, or --all.', 'travelopia-wordpress-ai' ) );
		}

		$this->process_batched( $missing_only, $batch_size, $limit );
	}

	/**
	 * Process a known list of image IDs.
	 *
	 * Used for --ids flag where the set is user-provided and bounded.
	 * When $limit is provided, truncates the list to the first N entries
	 * before processing — letting cost / quality sampling work for known
	 * ID lists too.
	 *
	 * @param int[] $image_ids Image attachment IDs.
	 * @param ?int  $limit     Maximum number of images to attempt. Null means no limit.
	 *
	 * @return void
	 */
	private function process_ids( array $image_ids, ?int $limit = null ): void
	{
		if ( empty( $image_ids ) ) {
			WP_CLI::warning( __( 'No images found to process.', 'travelopia-wordpress-ai' ) );
			return;
		}

		if ( null !== $limit ) {
			$image_ids = array_slice( $image_ids, 0, $limit );
		}

		$start_time = microtime( true );
		$counts     = $this->process_batch( $image_ids, count( $image_ids ) );

		$this->summary( $counts['success'], $counts['failed'], $start_time );
	}

	/**
	 * Process images in batches using paginated queries.
	 *
	 * Fetches images in configurable batch sizes to keep memory usage
	 * constant regardless of total image count. Flushes the object cache
	 * between batches to prevent memory growth.
	 *
	 * For --missing: always re-queries page 1 since successfully processed
	 * images drop out of the result set. Tracks failed IDs to break out
	 * if only unprocessable images remain.
	 *
	 * For --all: uses standard page incrementing.
	 *
	 * @param bool $missing_only Only process images missing alt text.
	 * @param int  $batch_size   Images per batch.
	 * @param ?int $limit        Maximum number of images to attempt. Null means no limit.
	 *
	 * @return void
	 */
	private function process_batched( bool $missing_only, int $batch_size, ?int $limit = null ): void
	{
		$total = AltText::count_images( missing_only: $missing_only );

		if ( 0 === $total ) {
			WP_CLI::warning( __( 'No images found to process.', 'travelopia-wordpress-ai' ) );
			return;
		}

		$cap          = $limit ?? PHP_INT_MAX;
		$progress_max = min( $total, $cap );

		WP_CLI::log(
			sprintf(
				/* translators: 1: total images, 2: batch size */
				__( 'Found %1$d images. Processing in batches of %2$d.', 'travelopia-wordpress-ai' ),
				$total,
				$batch_size,
			),
		);

		$success_count = 0;
		$failed_count  = 0;
		$attempts      = 0;
		$start_time    = microtime( true );
		$progress      = make_progress_bar(
			sprintf(
				/* translators: %d: number of images */
				__( 'Processing %d images', 'travelopia-wordpress-ai' ),
				$progress_max,
			),
			$progress_max,
		);

		$page       = 1;
		$failed_ids = [];

		do {
			// --missing re-queries page 1 (processed items drop out of result set); --all uses standard page increment.
			$query_page = $missing_only ? 1 : $page;

			$batch = AltText::query_images(
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

				$result = AltText::generate( $image_id );
				++$attempts;

				if ( $result instanceof WP_Error ) {
					$failed_ids[] = $image_id;
					++$failed_count;
					WP_CLI::warning(
						sprintf(
							/* translators: 1: attachment ID, 2: error message */
							__( 'ID %1$d failed: %2$s', 'travelopia-wordpress-ai' ),
							$image_id,
							$result->get_error_message(),
						),
					);
				} else {
					++$success_count;
				}

				if ( method_exists( $progress, 'tick' ) ) {
					$progress->tick();
				}
			}

			// Free memory between batches.
			if ( function_exists( 'wp_cache_flush_runtime' ) ) {
				wp_cache_flush_runtime();
			}

			++$page;
		} while ( true );

		if ( method_exists( $progress, 'finish' ) ) {
			$progress->finish();
		}

		$this->summary( $success_count, $failed_count, $start_time );
	}

	/**
	 * Process a single batch of image IDs and tick the progress bar.
	 *
	 * @param int[] $image_ids Image attachment IDs.
	 * @param int   $total     Total images for the progress bar.
	 *
	 * @return array{success: int, failed: int} Counts.
	 */
	private function process_batch( array $image_ids, int $total ): array
	{
		$success_count = 0;
		$failed_count  = 0;
		$progress      = make_progress_bar(
			sprintf(
				/* translators: %d: number of images */
				__( 'Processing %d images', 'travelopia-wordpress-ai' ),
				$total,
			),
			$total,
		);

		foreach ( $image_ids as $image_id ) {
			$result = AltText::generate( $image_id );

			if ( $result instanceof WP_Error ) {
				++$failed_count;
				WP_CLI::warning(
					sprintf(
						/* translators: 1: attachment ID, 2: error message */
						__( 'ID %1$d failed: %2$s', 'travelopia-wordpress-ai' ),
						$image_id,
						$result->get_error_message(),
					),
				);
			} else {
				++$success_count;
			}

			if ( method_exists( $progress, 'tick' ) ) {
				$progress->tick();
			}
		}

		if ( method_exists( $progress, 'finish' ) ) {
			$progress->finish();
		}

		return [
			'success' => $success_count,
			'failed'  => $failed_count,
		];
	}

	/**
	 * Display final summary.
	 *
	 * @param int   $success_count Number of successful generations.
	 * @param int   $failed_count  Number of failed generations.
	 * @param float $start_time    Microtime at start.
	 *
	 * @return void
	 */
	private function summary( int $success_count, int $failed_count, float $start_time ): void
	{
		$total_time = round( microtime( true ) - $start_time, 2 );

		WP_CLI::log(
			sprintf(
				/* translators: %s: time in seconds */
				__( 'Completed in %ss', 'travelopia-wordpress-ai' ),
				$total_time,
			),
		);
		WP_CLI::success(
			sprintf(
				/* translators: 1: number of successful generations, 2: number of failed generations */
				__( '%1$d succeeded, %2$d failed', 'travelopia-wordpress-ai' ),
				$success_count,
				$failed_count,
			),
		);
	}
}
