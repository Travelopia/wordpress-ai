<?php
/**
 * Generate Alt Text CLI Command.
 *
 * @package travelopia-wp-ai
 */

namespace Travelopia_WordPress_AI\WP_CLI;

use WP_CLI;

use function Travelopia_WordPress_AI\Alt_Text\get_all_images;
use function Travelopia_WordPress_AI\Alt_Text\get_ai_configuration;
use function Travelopia_WordPress_AI\Alt_Text\get_image_details;
use function Travelopia_WordPress_AI\Alt_Text\get_cli_images_to_process;
use function Travelopia_WordPress_AI\Alt_Text\parse_cli_arguments;
use function Travelopia_WordPress_AI\Alt_Text\format_processing_time;
use function Travelopia_WordPress_AI\Alt_Text\create_alt_text_error;
use function Travelopia_WordPress_AI\generate_alt_text_for_attachment;

/**
 * Class Alt_Text.
 */
class Alt_Text {

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
	 * - AI alt text generation must be enabled in Settings > Travelopia WP AI
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
	public function generate( array $args, array $args_assoc ): void {
		// Ensure WP_CLI is available.
		if ( ! class_exists( 'WP_CLI' ) ) {
			return;
		}

		// Parse and validate command arguments using centralized function.
		$parsed_args = parse_cli_arguments( $args_assoc );

		// Bail if validation failed.
		if ( ! $parsed_args['valid'] ) {
			// Create WP_Error for validation failure.
			$error_message = isset( $parsed_args['error'] ) ? strval( $parsed_args['error'] ) : 'Unknown validation error';
			$error         = create_alt_text_error(
				'invalid_arguments',
				$error_message,
				[ 'parsed_args' => $parsed_args ]
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
	private function request_confirmation( array $args ): void {
		// Determine operation description.
		if ( ! empty( $args['ids'] ) ) {
			if ( $args['missing'] ) {
				$operation = __( 'process specified image IDs that are missing alt text', 'travelopia-wp-ai' );
			} else {
				$operation = __( 'process all specified image IDs', 'travelopia-wp-ai' );
			}
		} elseif ( $args['all'] && $args['missing'] ) {
			$operation = __( 'process ALL images missing alt text on the site', 'travelopia-wp-ai' );
		} elseif ( $args['all'] ) {
			$operation = __( 'process ALL images on the site', 'travelopia-wp-ai' );
		} else {
			$operation = __( 'process ALL images missing alt text on the site', 'travelopia-wp-ai' );
		}

		// Get count of images that will be processed.
		$missing_only = isset( $args['missing'] ) ? (bool) $args['missing'] : false;
		$image_count  = count( get_all_images( $missing_only ) );

		// Display warning and request confirmation.
		WP_CLI::log(
			WP_CLI::colorize(
				'%R' . __( 'WARNING:', 'travelopia-wp-ai' ) . '%n ' . sprintf(
				/* translators: 1: operation description, 2: number of images */
					__( 'You are about to %1$s (%2$d images).', 'travelopia-wp-ai' ),
					$operation,
					$image_count
				)
			)
		);
		WP_CLI::log( WP_CLI::colorize( '%Y' . __( 'This operation may take a significant amount of time and resources.', 'travelopia-wp-ai' ) . '%n' ) );

		// Request confirmation.
		WP_CLI::confirm( __( 'Do you want to continue?', 'travelopia-wp-ai' ) );
	}

	/**
	 * Display command information.
	 *
	 * @param mixed[] $args Command arguments.
	 *
	 * @return void
	 */
	private function display_command_info( array $args ): void {
		// Get AI configuration.
		$config = get_ai_configuration();

		// Parse args.
		$args = wp_parse_args(
			$args,
			[
				'ids'        => [],
				'missing'    => false,
				'all'        => false,
				'batch-size' => 50,
			]
		);

		// Welcome message.
		WP_CLI::log( WP_CLI::colorize( '%Y' . __( 'Generating alt text for images using AI...', 'travelopia-wp-ai' ) . '%n' ) );
		WP_CLI::log( WP_CLI::colorize( '%B' . __( 'Using prompt:', 'travelopia-wp-ai' ) . '%n ' . $config['prompt'] ) );

		// Display mode information.
		if ( ! empty( $args['ids'] ) ) {
			if ( $args['missing'] ) {
				WP_CLI::log( WP_CLI::colorize( '%B' . __( 'Mode:', 'travelopia-wp-ai' ) . '%n ' . __( 'Processing specified image IDs that are missing alt text', 'travelopia-wp-ai' ) ) );
			} else {
				WP_CLI::log( WP_CLI::colorize( '%B' . __( 'Mode:', 'travelopia-wp-ai' ) . '%n ' . __( 'Processing all specified image IDs', 'travelopia-wp-ai' ) ) );
			}
		} elseif ( $args['all'] && $args['missing'] ) {
			WP_CLI::log( WP_CLI::colorize( '%B' . __( 'Mode:', 'travelopia-wp-ai' ) . '%n ' . __( 'Processing all images missing alt text on the site', 'travelopia-wp-ai' ) ) );
		} elseif ( $args['all'] ) {
			WP_CLI::log( WP_CLI::colorize( '%B' . __( 'Mode:', 'travelopia-wp-ai' ) . '%n ' . __( 'Processing all images on the site', 'travelopia-wp-ai' ) ) );
		} else {
			WP_CLI::log( WP_CLI::colorize( '%B' . __( 'Mode:', 'travelopia-wp-ai' ) . '%n ' . __( 'Processing all images missing alt text on the site', 'travelopia-wp-ai' ) ) );
		}

		// Display batch size information.
		WP_CLI::log( WP_CLI::colorize( '%B' . __( 'Batch size:', 'travelopia-wp-ai' ) . '%n ' . $args['batch-size'] . ' ' . __( 'images per batch', 'travelopia-wp-ai' ) ) );

		// Get images to process.
		$image_ids   = get_cli_images_to_process( $args );
		$image_count = count( $image_ids );

		// Display found images count.
		if ( $image_count > 0 ) {
			$estimated_batches = ceil( $image_count / $args['batch-size'] );
			WP_CLI::log(
				WP_CLI::colorize(
					'%Y' . sprintf(
						/* translators: 1: number of images, 2: number of batches */
						__( 'Found %1$d images to process (%2$d batches)', 'travelopia-wp-ai' ),
						$image_count,
						$estimated_batches
					) . '%n'
				)
			);
		}

		// Display processing start message.
		WP_CLI::log( WP_CLI::colorize( '%G' . __( 'Processing images...', 'travelopia-wp-ai' ) . '%n' ) );
		WP_CLI::log( __( 'Starting processing...', 'travelopia-wp-ai' ) );
	}

	/**
	 * Display processing results.
	 *
	 * @param array<string, mixed> $result Processing result.
	 *
	 * @return void
	 */
	private function display_results( array $result ): void {
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
			WP_CLI::warning( __( 'No images were processed. Please check your criteria and try again.', 'travelopia-wp-ai' ) );

			// Return.
			return;
		}

		// Display summary with timing information.
		WP_CLI::log( __( 'Alt text generation completed!', 'travelopia-wp-ai' ) );
		WP_CLI::log( WP_CLI::colorize( '%G' . __( 'Success:', 'travelopia-wp-ai' ) . ' %n' . $result['success_count'] ) );
		WP_CLI::log( WP_CLI::colorize( '%R' . __( 'Failed:', 'travelopia-wp-ai' ) . ' %n' . $result['failed_count'] ) );
		WP_CLI::log( WP_CLI::colorize( '%B' . __( 'Total processed:', 'travelopia-wp-ai' ) . ' %n' . $result['processed'] ) );

		// Display timing information if available.
		if ( isset( $result['total_time'] ) && isset( $result['average_time'] ) ) {
			// Format timing information.
			$total_time             = is_numeric( $result['total_time'] ) ? floatval( $result['total_time'] ) : 0.0;
			$average_time           = is_numeric( $result['average_time'] ) ? floatval( $result['average_time'] ) : 0.0;
			$total_time_formatted   = format_processing_time( $total_time );
			$average_time_formatted = format_processing_time( $average_time );

			// Display timing information.
			WP_CLI::log( WP_CLI::colorize( '%C' . __( 'Total time:', 'travelopia-wp-ai' ) . ' %n' . $total_time_formatted ) );
			WP_CLI::log( WP_CLI::colorize( '%C' . __( 'Average time per image:', 'travelopia-wp-ai' ) . ' %n' . $average_time_formatted ) );
		}

		// Final status - only show success if we actually processed images successfully.
		if ( $result['processed'] > 0 && 0 === $result['failed_count'] ) {
			WP_CLI::success( __( 'All images processed successfully!', 'travelopia-wp-ai' ) );
		} elseif ( $result['processed'] > 0 && $result['failed_count'] > 0 ) {
			WP_CLI::warning(
				sprintf(
					/* translators: %d: number of failures */
					__( 'Processing completed with %d failures. Check the log above for details.', 'travelopia-wp-ai' ),
					absint( $result['failed_count'] )
				)
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
	private function process_images_with_streaming( array $args ): array {
		// Get images to process.
		$image_ids = get_cli_images_to_process( $args );

		// Return empty result if no images found.
		if ( empty( $image_ids ) ) {
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

		// Process images in batches with real-time streaming.
		$success_count = 0;
		$failed_count  = 0;
		$images        = [];
		$start_time    = microtime( true );
		$batch_size    = isset( $args['batch-size'] ) ? max( 1, absint( $args['batch-size'] ) ) : 50;
		$batches       = array_chunk( $image_ids, $batch_size );
		$total_batches = count( $batches );
		$current_batch = 0;

		// Process each batch of images.
		foreach ( $batches as $batch ) {
			++$current_batch;
			$batch_start_time = microtime( true );

			// Display batch progress.
			$progress_percent = round( ( ( $current_batch - 1 ) / $total_batches ) * 100, 1 );
			$processed_images = ( $current_batch - 1 ) * $args['batch-size'];
			$total_images     = count( $image_ids );

			// Calculate ETA only after first batch is processed.
			$eta_formatted = '';

			// Calculate ETA only after first batch is processed.
			if ( $current_batch > 1 ) {
				// Calculate elapsed time and average batch processing time.
				$elapsed_time       = microtime( true ) - $start_time;
				$average_batch_time = $elapsed_time / ( $current_batch - 1 );
				$remaining_batches  = $total_batches - $current_batch + 1;
				$eta_seconds        = $remaining_batches * $average_batch_time;
				$eta_formatted      = ' - ETA: ' . round( $eta_seconds, 1 ) . 's';
			}

			// Display batch start information.
			$batch_message = sprintf(
				'Batch %d/%d (%s%%) - Images: %d/%d%s',
				$current_batch,
				$total_batches,
				$progress_percent,
				$processed_images + 1,
				$total_images,
				$eta_formatted
			);
			$this->cli_output( $batch_message );

			// Process each image in the batch.
			foreach ( $batch as $image_id ) {
				$image_start_time = microtime( true );

				// Display processing start for this image.
				$image_details      = get_image_details( $image_id );
				$image_title        = isset( $image_details['title'] ) ? strval( $image_details['title'] ) : 'Unknown';
				$processing_message = sprintf( 'Processing image ID %d: %s', $image_id, $image_title );
				$this->cli_output( $processing_message );

				// Generate alt text for this image.
				$image_result = generate_alt_text_for_attachment( $image_id );

				// Calculate processing time for this image.
				$processing_time = microtime( true ) - $image_start_time;

				// Check if result is successful or failed.
				if ( $image_result['success'] ) {
					// Handle success case.
					$alt_text            = $image_result['alt_text'] ?? '';
					$images[ $image_id ] = [
						'id'              => $image_id,
						'success'         => true,
						'processing_time' => round( $processing_time, 3 ),
						'alt_text'        => $alt_text,
						'error'           => null,
						'skipped'         => null,
						'reason'          => null,
					];

					// Increment success counter.
					++$success_count;

					// Display success message.
					$message = sprintf( 'Success: Generated alt text for image ID %d: %s', $image_id, $alt_text );
					$this->cli_output( $message, 'success' );
				} else {
					// Handle error case.
					$images[ $image_id ] = [
						'id'              => $image_id,
						'success'         => false,
						'processing_time' => round( $processing_time, 3 ),
						'alt_text'        => null,
						'error'           => $image_result['error'] ?? 'Unknown error',
						'skipped'         => null,
						'reason'          => null,
					];

					// Increment failed counter.
					++$failed_count;

					// Display error message.
					$message = sprintf( 'Warning: Failed to generate alt text for image ID %d: %s', $image_id, $image_result['error'] ?? 'Unknown error' );
					$this->cli_output( $message, 'warning' );
				}
			}

			// Clean up memory after each batch.
			wp_cache_flush();
		}

		// Calculate total time and average time.
		$total_time      = microtime( true ) - $start_time;
		$processed_count = $success_count + $failed_count;
		$average_time    = $processed_count > 0 ? $total_time / $processed_count : 0.0;

		// Return final results.
		return [
			'success'       => true,
			'processed'     => $success_count + $failed_count,
			'success_count' => $success_count,
			'failed_count'  => $failed_count,
			'images'        => $images,
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
	private function cli_output( string $message, string $type = 'log' ): void {
		// Handle different message types.
		switch ( $type ) {
			// Output success message.
			case 'success':
				WP_CLI::success( $message );
				break;

			// Output warning message.
			case 'warning':
				WP_CLI::warning( $message );
				break;

			// Output regular log message.
			default:
				WP_CLI::log( $message );
				break;
		}
	}
}
