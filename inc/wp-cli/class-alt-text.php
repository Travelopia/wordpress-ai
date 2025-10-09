<?php
/**
 * Generate Alt Text CLI Command.
 *
 * @package travelopia-wp-ai
 */

namespace Travelopia_WordPress_AI\WP_CLI;

use WP_CLI;
use WP_Error;

use function Travelopia_WordPress_AI\Alt_Text\get_all_images;
use function Travelopia_WordPress_AI\Alt_Text\get_ai_configuration;
use function Travelopia_WordPress_AI\Alt_Text\get_image_details;
use function Travelopia_WordPress_AI\Alt_Text\cli_generate_alt_text;

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
	 * @synopsis [--ids=<1,2,3>] [--missing] [--all]
	 *
	 * @return void
	 */
	public function generate( array $args, array $args_assoc ): void {
		// Ensure WP_CLI is available.
		if ( ! class_exists( 'WP_CLI' ) ) {
			return;
		}

		// Parse command arguments.
		$parsed_args = $this->parse_command_arguments( $args_assoc );

		// Validate parsed arguments.
		$validation_result = $this->validate_command_arguments( $parsed_args );

		// Bail if validation failed.
		if ( ! $validation_result['valid'] ) {
			// Handle validation errors.
			$error = new WP_Error( 'invalid_arguments', $validation_result['error'] ?? 'Unknown validation error' );
			WP_CLI::error( $error->get_error_message() );
		}

		// Handle confirmation for expensive operations.
		if ( $parsed_args['needs_confirmation'] ) {
			$this->request_confirmation( $parsed_args );
		}

		// Display command information.
		$this->display_command_info( $parsed_args );

		// Process images using alt text functionality.
		$result = cli_generate_alt_text( $parsed_args );

		// Display results.
		$this->display_results( $result );
	}

	/**
	 * Parse command arguments.
	 *
	 * @param array<string, mixed> $args_assoc WP CLI associative arguments.
	 *
	 * @return array{ids: array<int>, missing: bool, all: bool, needs_confirmation: bool}
	 */
	private function parse_command_arguments( array $args_assoc ): array {
		// Parse and validate command arguments.
		$options = wp_parse_args(
			$args_assoc,
			[
				'ids'     => [],
				'missing' => false,
				'all'     => false,
			]
		);

		// Parse IDs if provided.
		$ids = [];

		// Check if IDs are provided.
		if ( ! empty( $options['ids'] ) ) {
			// Convert comma-separated string to array of integers.
			$ids = array_map( 'absint', array_filter( array_map( 'trim', explode( ',', $options['ids'] ) ) ) );
		}

		// Parse missing and all arguments.
		$missing = (bool) $options['missing'];
		$all     = (bool) $options['all'];

		// Determine if confirmation is needed.
		$needs_confirmation = false;

		// Check for conflicting arguments.
		if ( ! empty( $ids ) && $all ) {
			// Handle conflicting arguments.
			WP_CLI::error( __( 'Cannot use --ids and --all together. Use --ids for specific images or --all for all images.', 'travelopia-wp-ai' ) );
		}

		// Determine operation type and confirmation needs.
		if ( $all ) {
			// Process all images on the site (or all missing if --missing is also used).
			$needs_confirmation = true;
		} elseif ( $missing && empty( $ids ) ) {
			// Process all missing alt text images.
			$needs_confirmation = true;
		}

		// Return parsed arguments.
		return [
			'ids'                => $ids,
			'missing'            => $missing,
			'all'                => $all,
			'needs_confirmation' => $needs_confirmation,
		];
	}

	/**
	 * Validate command arguments.
	 *
	 * @param array{ids: array<int>, missing: bool, all: bool, needs_confirmation: bool} $args Parsed arguments.
	 *
	 * @return array{valid: bool, error?: string}
	 */
	private function validate_command_arguments( array $args ): array {
		// Validate that some operation is specified.
		if ( empty( $args['ids'] ) && ! $args['all'] && ! $args['missing'] ) {
			// Return validation error if no operation specified.
			return [
				'valid' => false,
				'error' => __( 'You must provide --ids=<1,2,3>, --missing, or --all to specify which images to process.', 'travelopia-wp-ai' ),
			];
		}

		// Return validation result.
		return [ 'valid' => true ];
	}

	/**
	 * Request confirmation for expensive operations.
	 *
	 * @param array{ids: array<int>, missing: bool, all: bool, needs_confirmation: bool} $args Command arguments.
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
		$image_count = count( get_all_images( $args['missing'] ) );

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
	 * @param array{ids: array<int>, missing: bool, all: bool, needs_confirmation: bool} $args Command arguments.
	 *
	 * @return void
	 */
	private function display_command_info( array $args ): void {
		// Display command information and configuration.
		// Get AI configuration.
		$config = get_ai_configuration();

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

		// Display found images count (will be updated after processing).
		WP_CLI::log( WP_CLI::colorize( '%G' . __( 'Processing images...', 'travelopia-wp-ai' ) . '%n' ) );
		WP_CLI::log( __( 'Starting processing...', 'travelopia-wp-ai' ) );
	}

	/**
	 * Display processing results.
	 *
	 * @param array{success: bool, processed: int, success_count: int, failed_count: int, images: array<int, array{id: int, success: bool, alt_text?: string, error?: string, skipped?: bool, reason?: string}>} $result Processing result.
	 *
	 * @return void
	 */
	private function display_results( array $result ): void {
		// Display processing results and summary.
		// Process each result.
		foreach ( $result['images'] as $image_result ) {
			$image_id      = $image_result['id'];
			$image_details = get_image_details( $image_id );

			// Log which image is being processed.
			WP_CLI::log(
				sprintf(
					/* translators: 1: image ID, 2: image title */
					__( 'Processing image ID %1$d: %2$s', 'travelopia-wp-ai' ),
					$image_id,
					$image_details['title']
				)
			);

			// Check if generation was successful.
			if ( $image_result['success'] ) {
				if ( ! empty( $image_result['skipped'] ) ) {
					$reason = $image_result['reason'] ?? __( 'Unknown reason', 'travelopia-wp-ai' );
					WP_CLI::log(
						sprintf(
							/* translators: 1: image ID, 2: reason */
							__( 'Skipped image ID %1$d: %2$s', 'travelopia-wp-ai' ),
							$image_id,
							$reason
						)
					);
				} elseif ( ! empty( $image_result['alt_text'] ) ) {
					WP_CLI::success(
						sprintf(
							/* translators: 1: image ID, 2: alt text */
							__( 'Generated alt text for image ID %1$d: %2$s', 'travelopia-wp-ai' ),
							$image_id,
							$image_result['alt_text']
						)
					);
				}
			} else {
				$error_msg = $image_result['error'] ?? __( 'Unknown error', 'travelopia-wp-ai' );
				$wp_error  = new WP_Error(
					'alt_text_generation_failed',
					sprintf(
						/* translators: 1: image ID, 2: error message */
						__( 'Failed to generate alt text for image ID %1$d: %2$s', 'travelopia-wp-ai' ),
						$image_id,
						$error_msg
					)
				);
				WP_CLI::warning( $wp_error->get_error_message() );
			}
		}

		// Display summary.
		WP_CLI::log( __( 'Alt text generation completed!', 'travelopia-wp-ai' ) );
		WP_CLI::log( WP_CLI::colorize( '%G' . __( 'Success:', 'travelopia-wp-ai' ) . ' %n' . $result['success_count'] ) );
		WP_CLI::log( WP_CLI::colorize( '%R' . __( 'Failed:', 'travelopia-wp-ai' ) . ' %n' . $result['failed_count'] ) );
		WP_CLI::log( WP_CLI::colorize( '%B' . __( 'Total processed:', 'travelopia-wp-ai' ) . ' %n' . $result['processed'] ) );

		// Final status.
		if ( 0 === $result['failed_count'] ) {
			WP_CLI::success( __( 'All images processed successfully!', 'travelopia-wp-ai' ) );
		} else {
			WP_CLI::warning(
				sprintf(
					/* translators: %d: number of failures */
					__( 'Processing completed with %d failures. Check the log above for details.', 'travelopia-wp-ai' ),
					$result['failed_count']
				)
			);
		}
	}
}
