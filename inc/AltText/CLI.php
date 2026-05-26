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
 *
 * Thin presentation layer around AltText::run_batch — parses CLI arguments,
 * renders progress / log output, delegates all batch logic to the service.
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
			if ( ! is_numeric( $limit_raw ) || 0 >= (int) (string) $limit_raw ) {
				WP_CLI::error( __( 'Limit must be a positive integer.', 'travelopia-wordpress-ai' ) );
			}

			$limit = (int) (string) $limit_raw;
		}

		if ( isset( $args_assoc['ids'] ) ) {
			$ids       = explode( ',', (string) $args_assoc['ids'] );
			$image_ids = array_map( 'absint', $ids );
			$this->run_ids( $image_ids, $limit );
			return;
		}

		if ( ! isset( $args_assoc['all'] ) && ! $missing_only ) {
			WP_CLI::error( __( 'Please specify --ids, --missing, or --all.', 'travelopia-wordpress-ai' ) );
		}

		$this->run_paginated( $missing_only, $batch_size, $limit );
	}

	/**
	 * Run against a user-supplied ID list.
	 *
	 * @param int[] $image_ids Attachment IDs.
	 * @param ?int  $limit     Maximum number of images to attempt. Null means no limit.
	 *
	 * @return void
	 */
	private function run_ids( array $image_ids, ?int $limit ): void
	{
		if ( empty( $image_ids ) ) {
			WP_CLI::warning( __( 'No images found to process.', 'travelopia-wordpress-ai' ) );
			return;
		}

		$effective_total = null !== $limit ? min( count( $image_ids ), $limit ) : count( $image_ids );

		$start_time = microtime( true );
		$progress   = make_progress_bar(
			sprintf(
				/* translators: %d: number of images */
				__( 'Processing %d images', 'travelopia-wordpress-ai' ),
				$effective_total,
			),
			$effective_total,
		);

		$counts = AltText::run_batch(
			image_ids: $image_ids,
			limit:     $limit,
			on_image:  function ( int $image_id, mixed $result ) use ( $progress ): void {
				$this->emit_per_image( $image_id, $result );

				if ( method_exists( $progress, 'tick' ) ) {
					$progress->tick();
				}
			},
		);

		if ( method_exists( $progress, 'finish' ) ) {
			$progress->finish();
		}

		$this->summary( $counts['success'], $counts['failed'], $start_time );
	}

	/**
	 * Run against the paginated attachment query.
	 *
	 * @param bool $missing_only Only process images missing alt text.
	 * @param int  $batch_size   Images per batch.
	 * @param ?int $limit        Maximum number of images to attempt. Null means no limit.
	 *
	 * @return void
	 */
	private function run_paginated( bool $missing_only, int $batch_size, ?int $limit ): void
	{
		$total = AltText::count_images( missing_only: $missing_only );

		if ( 0 === $total ) {
			WP_CLI::warning( __( 'No images found to process.', 'travelopia-wordpress-ai' ) );
			return;
		}

		$effective_total = null !== $limit ? min( $total, $limit ) : $total;

		WP_CLI::log(
			sprintf(
				/* translators: 1: total images, 2: batch size */
				__( 'Found %1$d images. Processing in batches of %2$d.', 'travelopia-wordpress-ai' ),
				$total,
				$batch_size,
			),
		);

		$start_time = microtime( true );
		$progress   = make_progress_bar(
			sprintf(
				/* translators: %d: number of images */
				__( 'Processing %d images', 'travelopia-wordpress-ai' ),
				$effective_total,
			),
			$effective_total,
		);

		$counts = AltText::run_batch(
			missing_only: $missing_only,
			batch_size:   $batch_size,
			limit:        $limit,
			on_image:     function ( int $image_id, mixed $result ) use ( $progress ): void {
				$this->emit_per_image( $image_id, $result );

				if ( method_exists( $progress, 'tick' ) ) {
					$progress->tick();
				}
			},
		);

		if ( method_exists( $progress, 'finish' ) ) {
			$progress->finish();
		}

		$this->summary( $counts['success'], $counts['failed'], $start_time );
	}

	/**
	 * Emit per-image output: a warning on WP_Error or a success log otherwise.
	 *
	 * @param int   $image_id Attachment ID.
	 * @param mixed $result   Result from AltText::generate — string on success, WP_Error on failure.
	 *
	 * @return void
	 */
	private function emit_per_image( int $image_id, mixed $result ): void
	{
		if ( $result instanceof WP_Error ) {
			WP_CLI::warning(
				sprintf(
					/* translators: 1: attachment ID, 2: error message */
					__( 'ID %1$d failed: %2$s', 'travelopia-wordpress-ai' ),
					$image_id,
					$result->get_error_message(),
				),
			);
			return;
		}

		$title = get_the_title( $image_id );

		WP_CLI::log(
			sprintf(
				/* translators: 1: attachment ID, 2: attachment title */
				__( "ID: %1\$d\nTitle: %2\$s", 'travelopia-wordpress-ai' ),
				$image_id,
				'' !== $title ? $title : __( '(untitled)', 'travelopia-wordpress-ai' ),
			),
		);
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
