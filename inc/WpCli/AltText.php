<?php
/**
 * Generate Alt Text CLI Command.
 *
 * @package travelopia-wordpress-ai
 */

namespace Travelopia\WordPress_AI\WpCli;

use Travelopia\WordPress_AI\AltText as AltTextModule;
use WP_CLI;

/**
 * Class AltText.
 */
class AltText
{
	/**
	 * Generate alt text for images using AI.
	 *
	 * ## DESCRIPTION
	 *
	 * Generates AI-powered alt text for specified images. This command helps improve
	 * accessibility by automatically creating descriptive alt text for images that
	 * are missing it or need regeneration.
	 *
	 * ## OPTIONS
	 *
	 * [--ids=<1,2,3>]
	 * : Comma-separated list of image attachment IDs to process. Processes all
	 * specified images regardless of existing alt text. If combined with --missing,
	 * only processes specified images that are missing alt text.
	 *
	 * [--missing]
	 * : Only process images that are missing alt text. When used without --ids,
	 * processes all images on the site that are missing alt text (requires confirmation).
	 *
	 * [--all]
	 * : Process all images on the site regardless of existing alt text (requires confirmation).
	 * Cannot be used with --ids.
	 *
	 * [--batch-size=<50>]
	 * : Number of images to process per batch (default: 50). Larger batches may use more memory
	 * but process faster. Smaller batches use less memory but may be slower.
	 *
	 * ## EXAMPLES
	 *
	 *     # Generate alt text for specific images (all specified images)
	 *     wp travelopia-wp-ai alt-text generate --ids=123,456,789
	 *
	 *     # Generate alt text only for specific images that are missing alt text
	 *     wp travelopia-wp-ai alt-text generate --ids=123,456,789 --missing
	 *
	 *     # Process all images missing alt text on the site (requires confirmation)
	 *     wp travelopia-wp-ai alt-text generate --missing
	 *
	 *     # Process all images on the site (requires confirmation)
	 *     wp travelopia-wp-ai alt-text generate --all
	 *
	 *     # Process all images missing alt text (same as --missing alone)
	 *     wp travelopia-wp-ai alt-text generate --all --missing
	 *
	 *     # Process with custom batch size (smaller batches for memory-constrained environments)
	 *     wp travelopia-wp-ai alt-text generate --missing --batch-size=25
	 *
	 *     # Process with larger batches for faster processing (more memory required)
	 *     wp travelopia-wp-ai alt-text generate --all --batch-size=100
	 *
	 * ## REQUIREMENTS
	 *
	 * - AI alt text generation must be enabled in Settings > WordPress AI
	 * - AI prompt must be configured in the plugin settings
	 * - Images must be valid attachment posts of type 'image'
	 *
	 * ## CONFIRMATION REQUIRED
	 *
	 * The following operations require user confirmation due to their potentially
	 * expensive nature:
	 * - Processing all images on the site (--all)
	 * - Processing all missing alt text images (--missing without --ids)
	 * - Processing all images missing alt text (--all --missing)
	 *
	 * @param array<string, mixed> $args       WP CLI arguments.
	 * @param array<string, mixed> $args_assoc WP CLI associative arguments.
	 *
	 * @subcommand generate
	 *
	 * @synopsis [--ids=<1,2,3>] [--missing] [--all] [--batch-size=<50>]
	 *
	 * @return void
	 */
	public function generate( array $args = [], array $args_assoc = [] ): void
	{
		// Ensure WP_CLI is available.
		if ( ! class_exists( 'WP_CLI' ) ) {
			return;
		}

		// Parse and validate command arguments using centralized function.
		$parsed_args = AltTextModule::parse_cli_arguments( $args_assoc );

		// Bail if validation failed.
		if ( ! $parsed_args['valid'] ) {
			// Create WP_Error for validation failure.
			$error_message = isset( $parsed_args['error'] ) ? strval( $parsed_args['error'] ) : 'Unknown validation error';
			$error         = AltTextModule::create_alt_text_error(
				'invalid_arguments',
				$error_message,
				[ 'parsed_args' => $parsed_args ],
			);
			WP_CLI::error( $error->get_error_message() );
		}

		// Handle confirmation for expensive operations.
		if ( $parsed_args['needs_confirmation'] ) {
			$this->request_confirmation( $parsed_args );
		}

		// Display command information.
		$this->display_command_info( $parsed_args );

		// Process images using alt text functionality with real-time streaming.
		$result = $this->process_images_with_streaming( $parsed_args );

		// Display final summary.
		$this->display_results( $result );
	}

	/**
	 * Request confirmation for expensive operations.
	 *
	 * @param array<string, mixed> $args Command arguments.
	 *
	 * @return void
	 */
	private function request_confirmation( array $args = [] ): void
	{
		// Determine operation description.
		if ( ! empty( $args['ids'] ) ) {
			if ( $args['missing'] ) {
				$operation = __( 'process specified image IDs that are missing alt text', 'travelopia-wordpress-ai' );
			} else {
				$operation = __( 'process all specified image IDs', 'travelopia-wordpress-ai' );
			}
		} elseif ( $args['all'] && $args['missing'] ) {
			$operation = __( 'process ALL images missing alt text on the site', 'travelopia-wordpress-ai' );
		} elseif ( $args['all'] ) {
			$operation = __( 'process ALL images on the site', 'travelopia-wordpress-ai' );
		} else {
			$operation = __( 'process ALL images missing alt text on the site', 'travelopia-wordpress-ai' );
		}

		// Get count of images that will be processed.
		$missing_only = isset( $args['missing'] ) ? (bool) $args['missing'] : false;
		$image_count  = count( AltTextModule::query_images( missing_only: $missing_only ) );

		// Display warning and request confirmation.
		WP_CLI::log(
			WP_CLI::colorize(
				'%R' . __( 'WARNING:', 'travelopia-wordpress-ai' ) . '%n ' . sprintf(
				/* translators: 1: operation description, 2: number of images */
					__( 'You are about to %1$s (%2$d images).', 'travelopia-wordpress-ai' ),
					$operation,
					$image_count,
				),
			),
		);
		WP_CLI::log( WP_CLI::colorize( '%Y' . __( 'This operation may take a significant amount of time and resources.', 'travelopia-wordpress-ai' ) . '%n' ) );

		// Request confirmation.
		WP_CLI::confirm( __( 'Do you want to continue?', 'travelopia-wordpress-ai' ) );
	}

	/**
	 * Display command information.
	 *
	 * @param mixed[] $args Command arguments.
	 *
	 * @return void
	 */
	private function display_command_info( array $args = [] ): void
	{
		// Get AI configuration.
		$config = AltTextModule::get_ai_configuration();

		// Parse args.
		$args = wp_parse_args(
			$args,
			[
				'ids'        => [],
				'missing'    => false,
				'all'        => false,
				'batch-size' => AltTextModule::DEFAULT_BATCH_SIZE,
			],
		);

		// Welcome message.
		WP_CLI::log( WP_CLI::colorize( '%Y' . __( 'Generating alt text for images using AI...', 'travelopia-wordpress-ai' ) . '%n' ) );
		WP_CLI::log( WP_CLI::colorize( '%B' . __( 'Using prompt:', 'travelopia-wordpress-ai' ) . '%n ' . $config['prompt'] ) );

		// Display mode information.
		if ( ! empty( $args['ids'] ) ) {
			if ( $args['missing'] ) {
				WP_CLI::log( WP_CLI::colorize( '%B' . __( 'Mode:', 'travelopia-wordpress-ai' ) . '%n ' . __( 'Processing specified image IDs that are missing alt text', 'travelopia-wordpress-ai' ) ) );
			} else {
				WP_CLI::log( WP_CLI::colorize( '%B' . __( 'Mode:', 'travelopia-wordpress-ai' ) . '%n ' . __( 'Processing all specified image IDs', 'travelopia-wordpress-ai' ) ) );
			}
		} elseif ( $args['all'] && $args['missing'] ) {
			WP_CLI::log( WP_CLI::colorize( '%B' . __( 'Mode:', 'travelopia-wordpress-ai' ) . '%n ' . __( 'Processing all images missing alt text on the site', 'travelopia-wordpress-ai' ) ) );
		} elseif ( $args['all'] ) {
			WP_CLI::log( WP_CLI::colorize( '%B' . __( 'Mode:', 'travelopia-wordpress-ai' ) . '%n ' . __( 'Processing all images on the site', 'travelopia-wordpress-ai' ) ) );
		} else {
			WP_CLI::log( WP_CLI::colorize( '%B' . __( 'Mode:', 'travelopia-wordpress-ai' ) . '%n ' . __( 'Processing all images missing alt text on the site', 'travelopia-wordpress-ai' ) ) );
		}

		// Display batch size information.
		WP_CLI::log( WP_CLI::colorize( '%B' . __( 'Batch size:', 'travelopia-wordpress-ai' ) . '%n ' . $args['batch-size'] . ' ' . __( 'images per batch', 'travelopia-wordpress-ai' ) ) );

		// Get images to process.
		$image_ids   = AltTextModule::get_cli_images_to_process( $args );
		$image_count = count( $image_ids );

		// Display found images count.
		if ( 0 < $image_count ) {
			$estimated_batches = ceil( $image_count / $args['batch-size'] );
			WP_CLI::log(
				WP_CLI::colorize(
					'%Y' . sprintf(
						/* translators: 1: number of images, 2: number of batches */
						__( 'Found %1$d images to process (%2$d batches)', 'travelopia-wordpress-ai' ),
						$image_count,
						$estimated_batches,
					) . '%n',
				),
			);
		}

		// Display processing start message.
		WP_CLI::log( WP_CLI::colorize( '%G' . __( 'Processing images...', 'travelopia-wordpress-ai' ) . '%n' ) );
		WP_CLI::log( __( 'Starting processing...', 'travelopia-wordpress-ai' ) );
	}

	/**
	 * Display processing results.
	 *
	 * @param array<string, mixed> $result Processing result.
	 *
	 * @return void
	 */
	private function display_results( array $result = [] ): void
	{
		// Check if the operation failed completely (no images processed).
		if ( ! $result['success'] && isset( $result['error'] ) ) {
			// Display error message.
			WP_CLI::error( strval( $result['error'] ) );

			// Return.
			return;
		}

		// Check if no images were processed.
		if ( 0 === $result['processed'] ) {
			// Display warning for no processed images.
			WP_CLI::warning( __( 'No images were processed. Please check your criteria and try again.', 'travelopia-wordpress-ai' ) );

			// Return.
			return;
		}

		// Display summary with timing information.
		WP_CLI::log( __( 'Alt text generation completed!', 'travelopia-wordpress-ai' ) );
		WP_CLI::log( WP_CLI::colorize( '%G' . __( 'Success:', 'travelopia-wordpress-ai' ) . ' %n' . $result['success_count'] ) );
		WP_CLI::log( WP_CLI::colorize( '%R' . __( 'Failed:', 'travelopia-wordpress-ai' ) . ' %n' . $result['failed_count'] ) );
		WP_CLI::log( WP_CLI::colorize( '%B' . __( 'Total processed:', 'travelopia-wordpress-ai' ) . ' %n' . $result['processed'] ) );

		// Display timing information if available.
		if ( isset( $result['total_time'] ) && isset( $result['average_time'] ) ) {
			// Format timing information.
			$total_time             = is_numeric( $result['total_time'] ) ? floatval( $result['total_time'] ) : 0.0;
			$average_time           = is_numeric( $result['average_time'] ) ? floatval( $result['average_time'] ) : 0.0;
			$total_time_formatted   = AltTextModule::format_processing_time( $total_time );
			$average_time_formatted = AltTextModule::format_processing_time( $average_time );

			// Display timing information.
			WP_CLI::log( WP_CLI::colorize( '%C' . __( 'Total time:', 'travelopia-wordpress-ai' ) . ' %n' . $total_time_formatted ) );
			WP_CLI::log( WP_CLI::colorize( '%C' . __( 'Average time per image:', 'travelopia-wordpress-ai' ) . ' %n' . $average_time_formatted ) );
		}

		// Final status - only show success if we actually processed images successfully.
		if ( 0 < $result['processed'] && 0 === $result['failed_count'] ) {
			WP_CLI::success( __( 'All images processed successfully!', 'travelopia-wordpress-ai' ) );
		} elseif ( 0 < $result['processed'] && 0 < $result['failed_count'] ) {
			WP_CLI::warning(
				sprintf(
					/* translators: %d: number of failures */
					__( 'Processing completed with %d failures. Check the log above for details.', 'travelopia-wordpress-ai' ),
					absint( $result['failed_count'] ),
				),
			);
		}
	}

	/**
	 * Process images with real-time streaming output.
	 *
	 * @param array<string, mixed> $args Command arguments.
	 *
	 * @return array<string, mixed> Results.
	 */
	private function process_images_with_streaming( array $args = [] ): array
	{
		// Get images to process.
		$image_ids = AltTextModule::get_cli_images_to_process( $args );

		// Return empty result if no images found.
		if ( empty( $image_ids ) ) {
			return $this->get_empty_result();
		}

		// Initialize processing variables.
		$processing_data = $this->initialize_processing_data( $args, $image_ids );

		// Process each batch of images.
		$batches = $processing_data['batches'];

		// Process each batch of images.
		if ( is_array( $batches ) ) {
			foreach ( $batches as $batch ) {
				++$processing_data['current_batch'];

				// Display batch progress and process images.
				$this->display_batch_progress( $processing_data );
				$this->process_batch( $batch, $processing_data );
				$this->cleanup_after_batch( $processing_data['current_batch'] );
			}
		}

		// Calculate and return final results.
		return $this->calculate_final_results( $processing_data );
	}

	/**
	 * Get empty result structure.
	 *
	 * @return array<string, mixed>
	 */
	private function get_empty_result(): array
	{
		// Return empty result structure.
		return [
			'success'       => false,
			'processed'     => 0,
			'success_count' => 0,
			'failed_count'  => 0,
			'images'        => [],
			'total_time'    => 0.0,
			'average_time'  => 0.0,
		];
	}

	/**
	 * Initialize processing data.
	 *
	 * @param array<string, mixed> $args      Command arguments.
	 * @param array<int>           $image_ids Image IDs to process.
	 *
	 * @return array{success_count: int, failed_count: int, images: array<int, array<string, mixed>>, start_time: float, batch_size: int, batches: array<int, array<int>>, total_batches: int, current_batch: int, total_images: int}
	 */
	private function initialize_processing_data( array $args = [], array $image_ids = [] ): array
	{
		// Get batch size with validation.
		$batch_size = isset( $args['batch-size'] ) ? max( 1, absint( $args['batch-size'] ) ) : AltTextModule::DEFAULT_BATCH_SIZE;

		// Return processing data structure.
		return [
			'success_count' => 0,
			'failed_count'  => 0,
			'images'        => [],
			'start_time'    => microtime( true ),
			'batch_size'    => $batch_size,
			'batches'       => array_chunk( $image_ids, $batch_size ),
			'total_batches' => 0,
			'current_batch' => 0,
			'total_images'  => count( $image_ids ),
		];
	}

	/**
	 * Display batch progress information.
	 *
	 * @param array<string, mixed> $processing_data Processing data.
	 *
	 * @return void
	 */
	private function display_batch_progress( array &$processing_data = [] ): void
	{
		// Get batch progress information.
		$current_batch    = isset( $processing_data['current_batch'] ) && is_numeric( $processing_data['current_batch'] ) ? absint( $processing_data['current_batch'] ) : 0;
		$batches          = $processing_data['batches'];
		$total_batches    = is_array( $batches ) ? count( $batches ) : 0;
		$progress_percent = 0 < $total_batches ? round( ( ( $current_batch - 1 ) / $total_batches ) * 100, 1 ) : 0.0;
		$batch_size       = isset( $processing_data['batch_size'] ) && is_numeric( $processing_data['batch_size'] ) ? absint( $processing_data['batch_size'] ) : 0;
		$processed_images = ( $current_batch - 1 ) * $batch_size;

		// Calculate ETA only after first batch is processed.
		$eta_formatted = '';

		// Calculate ETA only after first batch is processed.
		if ( 1 < $current_batch ) {
			$elapsed_time       = microtime( true ) - $processing_data['start_time'];
			$average_batch_time = $elapsed_time / ( $current_batch - 1 );
			$remaining_batches  = $total_batches - $current_batch + 1;
			$eta_seconds        = $remaining_batches * $average_batch_time;
			$eta_formatted      = ' - ETA: ' . round( $eta_seconds, 1 ) . 's';
		}

		// Display batch start information.
		$total_images  = isset( $processing_data['total_images'] ) && is_numeric( $processing_data['total_images'] ) ? absint( $processing_data['total_images'] ) : 0;
		$batch_message = sprintf(
			'Batch %d/%d (%s%%) - Images: %d/%d%s',
			$current_batch,
			$total_batches,
			strval( $progress_percent ),
			$processed_images + 1,
			$total_images,
			strval( $eta_formatted ),
		);
		$this->cli_output( $batch_message );
	}

	/**
	 * Process a batch of images.
	 *
	 * @param array<int>           $batch           Batch of image IDs.
	 * @param array<string, mixed> $processing_data Processing data.
	 *
	 * @return void
	 */
	private function process_batch( array $batch = [], array &$processing_data = [] ): void
	{
		// Process each image in the batch.
		foreach ( $batch as $image_id ) {
			// Ensure image_id is an integer.
			$image_id         = absint( $image_id );
			$image_start_time = microtime( true );

			// Display processing start for this image.
			$image_details      = AltTextModule::get_image_details( $image_id );
			$image_title        = isset( $image_details['title'] ) ? strval( $image_details['title'] ) : 'Unknown';
			$processing_message = sprintf( 'Processing image ID %d: %s', $image_id, $image_title );
			$this->cli_output( $processing_message );

			// Generate alt text for this image.
			$image_result    = AltTextModule::generate_alt_text_for_attachment( $image_id );
			$processing_time = microtime( true ) - $image_start_time;

			// Process result and update counters.
			$this->process_image_result( $image_id, $image_result, $processing_time, $processing_data );
		}
	}

	/**
	 * Process individual image result.
	 *
	 * @param int                  $image_id        Image ID.
	 * @param string|\WP_Error     $image_result    Image processing result.
	 * @param float                $processing_time Processing time.
	 * @param array<string, mixed> $processing_data Processing data.
	 *
	 * @return void
	 */
	private function process_image_result( int $image_id = 0, $image_result = '', float $processing_time = 0.0, array &$processing_data = [] ): void
	{
		// Build result entry for this image.
		$result_entry = [
			'id'              => $image_id,
			'success'         => ! is_wp_error( $image_result ),
			'processing_time' => round( $processing_time, 3 ),
			'alt_text'        => null,
			'error'           => null,
			'skipped'         => null,
			'reason'          => null,
		];

		// Handle success case.
		if ( ! is_wp_error( $image_result ) ) {
			$alt_text                 = is_string( $image_result ) ? $image_result : '';
			$result_entry['alt_text'] = $alt_text;

			// Ensure images array exists and is array.
			if ( ! isset( $processing_data['images'] ) || ! is_array( $processing_data['images'] ) ) {
				$processing_data['images'] = [];
			}

			$processing_data['images'][ $image_id ] = $result_entry;
			++$processing_data['success_count'];

			// Display success message.
			$message = sprintf( 'Success: Generated alt text for image ID %d: %s', $image_id, $alt_text );
			$this->cli_output( $message, 'success' );
		} else {
			// Handle error case.
			$error_message         = $image_result->get_error_message();
			$result_entry['error'] = $error_message;

			// Ensure images array exists and is array.
			if ( ! isset( $processing_data['images'] ) || ! is_array( $processing_data['images'] ) ) {
				$processing_data['images'] = [];
			}

			$processing_data['images'][ $image_id ] = $result_entry;
			++$processing_data['failed_count'];

			// Display warning message.
			$message = sprintf( 'Warning: Failed to generate alt text for image ID %d: %s', $image_id, $error_message );
			$this->cli_output( $message, 'warning' );
		}
	}

	/**
	 * Cleanup after processing a batch.
	 *
	 * @param int $current_batch Current batch number.
	 *
	 * @return void
	 */
	private function cleanup_after_batch( int $current_batch = 0 ): void
	{
		// Clean up memory after each batch.
		wp_cache_flush();

		// Additional memory cleanup for large batches.
		if ( 0 === $current_batch % 5 ) {
			if ( function_exists( 'gc_collect_cycles' ) ) {
				gc_collect_cycles();
			}
		}
	}

	/**
	 * Calculate final processing results.
	 *
	 * @param array<string, mixed> $processing_data Processing data.
	 *
	 * @return array<string, mixed>
	 */
	private function calculate_final_results( array $processing_data = [] ): array
	{
		// Calculate timing and statistics.
		$total_time      = microtime( true ) - $processing_data['start_time'];
		$success_count   = isset( $processing_data['success_count'] ) && is_numeric( $processing_data['success_count'] ) ? absint( $processing_data['success_count'] ) : 0;
		$failed_count    = isset( $processing_data['failed_count'] ) && is_numeric( $processing_data['failed_count'] ) ? absint( $processing_data['failed_count'] ) : 0;
		$processed_count = $success_count + $failed_count;
		$average_time    = 0 < $processed_count ? $total_time / $processed_count : 0.0;

		// Return final results array.
		return [
			'success'       => true,
			'processed'     => $processed_count,
			'success_count' => $success_count,
			'failed_count'  => $failed_count,
			'images'        => $processing_data['images'],
			'total_time'    => round( $total_time, 3 ),
			'average_time'  => round( $average_time, 3 ),
		];
	}

	/**
	 * Simple output function that handles both WP_CLI and fallback.
	 *
	 * @param string $message The message to output.
	 * @param string $type    The type of message (log, success, warning).
	 *
	 * @return void
	 */
	private function cli_output( string $message = '', string $type = 'log' ): void
	{
		switch ( $type ) {
			case 'success':
				WP_CLI::success( $message );
				break;
			case 'warning':
				WP_CLI::warning( $message );
				break;
			default:
				WP_CLI::log( $message );
				break;
		}
	}
}
