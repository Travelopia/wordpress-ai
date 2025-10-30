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
	 * @param array<string, mixed> $args       WP CLI arguments.
	 * @param array<string, mixed> $args_assoc WP CLI associative arguments.
	 *
	 * @subcommand generate
	 *
	 * @synopsis [--ids=<1,2,3>] [--missing] [--all]
	 *
	 * @return void
	 */
	public function generate( array $args = [], array $args_assoc = [] ): void
	{
		$missing_only = isset( $args_assoc['missing'] );

		if ( isset( $args_assoc['ids'] ) ) {
			// Process specific image IDs.
			$ids       = explode( ',', strval( $args_assoc['ids'] ) );
			$image_ids = array_map( 'absint', $ids );
		} else {
			// Query all images or missing images.
			$image_ids = AltText::query_images( missing_only: $missing_only );
		}

		if ( empty( $image_ids ) ) {
			WP_CLI::warning( __( 'No images found to process.', 'travelopia-wordpress-ai' ) );
			return;
		}

		// Process images with progress bar.
		$success_count = 0;
		$failed_count  = 0;
		$start_time    = microtime( true );

		$progress = make_progress_bar(
			sprintf( __( 'Processing %d images', 'travelopia-wordpress-ai' ), count( $image_ids ) ),
			count( $image_ids ),
		);

		foreach ( $image_ids as $image_id ) {
			$result = AltText::generate( $image_id );

			if ( $result instanceof WP_Error ) {
				++$failed_count;
			} else {
				++$success_count;
			}

			$progress->tick();
		}

		$progress->finish();

		// Display summary.
		$total_time = round( microtime( true ) - $start_time, 2 );
		WP_CLI::log( sprintf( __( 'Completed in %ss', 'travelopia-wordpress-ai' ), $total_time ) );
		WP_CLI::success( sprintf( __( '%d succeeded, %d failed', 'travelopia-wordpress-ai' ), $success_count, $failed_count ) );
	}
}
