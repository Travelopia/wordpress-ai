<?php
/**
 * Generate Alt Text CLI Command.
 *
 * @package travelopia-wp-ai
 */

namespace Travelopia_WordPress_AI\WP_CLI;

use WP_CLI;
use WP_Query;
use WP_Post;
use WP_Error;

use function Travelopia_WordPress_AI\generate_alt_text_for_attachment;
use function Travelopia_WordPress_AI\get_setting;

/**
 * Class Alt_Text.
 */
class Alt_Text {

	/**
	 * Generate alt text for images using AI.
	 *
	 * @param array<string, mixed> $args       WP CLI arguments.
	 * @param array<string, mixed> $args_assoc WP CLI associative arguments.
	 *
	 * @subcommand generate
	 *
	 * @synopsis [--ids=<1,2,3>] [--missing]
	 *
	 * @return void
	 */
	public function generate( array $args, array $args_assoc ): void {
		// Ensure WP_CLI is available.
		if ( ! class_exists( 'WP_CLI' ) ) {
			return;
		}

		// Get options.
		$options = wp_parse_args(
			$args_assoc,
			[
				'ids'     => [],
				'missing' => false,
			]
		);

		// Parse IDs if provided.
		if ( ! empty( $options['ids'] ) ) {
			$options['ids'] = array_map( 'absint', array_filter( array_map( 'trim', explode( ',', $options['ids'] ) ) ) );
		} else {
			$options['ids'] = [];
		}

		// Return early if no IDs provided.
		if ( empty( $options['ids'] ) ) {
			$error = new WP_Error(
				'missing_ids',
				__( 'You must provide --ids=<1,2,3> to specify which images to process.', 'travelopia-wp-ai' )
			);
			WP_CLI::error( $error->get_error_message() );
		}

		// Check if AI alt text generation is enabled.
		$ai_enabled = get_setting( 'ai_alt_text_enabled', false );

		// If not enabled, show error and exit.
		if ( ! $ai_enabled ) {
			$error = new WP_Error(
				'ai_disabled',
				__( 'AI alt text generation is not enabled. Please enable it in Settings > Travelopia WP AI.', 'travelopia-wp-ai' )
			);
			WP_CLI::error( $error->get_error_message() );
		}

		// Get the AI prompt.
		$ai_prompt = get_setting( 'ai_alt_text_prompt', '' );

		// Validate prompt is configured.
		if ( empty( $ai_prompt ) ) {
			$error = new WP_Error(
				'missing_prompt',
				__( 'AI prompt is not configured. Please set it in Settings > Travelopia WP AI.', 'travelopia-wp-ai' )
			);
			WP_CLI::error( $error->get_error_message() );
		}

		// Welcome message.
		WP_CLI::log( WP_CLI::colorize( '%Y' . __( 'Generating alt text for images using AI...', 'travelopia-wp-ai' ) . '%n' ) );
		WP_CLI::log( WP_CLI::colorize( '%B' . __( 'Using prompt:', 'travelopia-wp-ai' ) . '%n ' . $ai_prompt ) );

		// Display mode information.
		if ( $options['missing'] ) {
			WP_CLI::log( WP_CLI::colorize( '%B' . __( 'Mode:', 'travelopia-wp-ai' ) . '%n ' . __( 'Processing specified image IDs that are missing alt text', 'travelopia-wp-ai' ) ) );
		} else {
			WP_CLI::log( WP_CLI::colorize( '%B' . __( 'Mode:', 'travelopia-wp-ai' ) . '%n ' . __( 'Processing specified image IDs', 'travelopia-wp-ai' ) ) );
		}

		// Build query arguments.
		$query_args = [
			'post_type'      => 'attachment',
			'post_mime_type' => 'image',
			'post_status'    => 'inherit',
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'post__in'       => $options['ids'],
		];

		// Filter for images missing alt text if --missing flag is used.
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

		// Get images.
		$images_query = new WP_Query( $query_args );
		$image_ids    = $images_query->posts;

		// Check if any images found.
		if ( empty( $image_ids ) ) {
			$error = new WP_Error(
				'no_images_found',
				__( 'No images found matching the specified criteria!', 'travelopia-wp-ai' )
			);
			WP_CLI::error( $error->get_error_message() );
		}

		// Initialize tracking.
		$total_images = count( $image_ids );
		$success      = 0;
		$failed       = 0;

		// Display found images count.
		WP_CLI::log( WP_CLI::colorize( '%G' . __( 'Found images:', 'travelopia-wp-ai' ) . ' %n' . $total_images ) );
		WP_CLI::log( __( 'Starting processing...', 'travelopia-wp-ai' ) );

		// Process each image.
		foreach ( $image_ids as $index => $image_id ) {
			// Log progress.
			WP_CLI::log(
				sprintf(
					/* translators: 1: current image number, 2: total images */
					__( 'Processing %1$d of %2$d...', 'travelopia-wp-ai' ),
					$index + 1,
					$total_images
				)
			);

			// Ensure we're working with an integer ID.
			if ( $image_id instanceof WP_Post ) {
				$image_id = $image_id->ID;
			}

			// Validate image ID.
			$image_id   = absint( $image_id );
			$image_post = get_post( $image_id );

			// Validate image post.
			if ( ! $image_post instanceof WP_Post ) {
				++$failed;
				WP_CLI::warning(
					sprintf(
						/* translators: %d: image ID */
						__( 'Invalid image post for ID %d', 'travelopia-wp-ai' ),
						$image_id
					)
				);
				continue;
			}

			// Log which image is being processed.
			WP_CLI::log(
				sprintf(
					/* translators: 1: image ID, 2: image title */
					__( 'Processing image ID %1$d: %2$s', 'travelopia-wp-ai' ),
					$image_id,
					$image_post->post_title ?: __( '(no title)', 'travelopia-wp-ai' )
				)
			);

			// Use the existing alt text generation function.
			$result = generate_alt_text_for_attachment( $image_id );

			// Check result and update counters.
			if ( $result['success'] && ! empty( $result['alt_text'] ) ) {
				++$success;
				$alt_text = $result['alt_text'];
				WP_CLI::success(
					sprintf(
						/* translators: 1: image ID, 2: alt text */
						__( 'Generated alt text for image ID %1$d: %2$s', 'travelopia-wp-ai' ),
						$image_id,
						$alt_text
					)
				);
			} else {
				++$failed;
				$error_msg = $result['error'] ?? __( 'Unknown error', 'travelopia-wp-ai' );
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
		WP_CLI::log( WP_CLI::colorize( '%G' . __( 'Success:', 'travelopia-wp-ai' ) . ' %n' . $success ) );
		WP_CLI::log( WP_CLI::colorize( '%R' . __( 'Failed:', 'travelopia-wp-ai' ) . ' %n' . $failed ) );

		// Final status.
		if ( 0 === $failed ) {
			WP_CLI::success( __( 'All images processed successfully!', 'travelopia-wp-ai' ) );
		} else {
			WP_CLI::warning(
				sprintf(
					/* translators: %d: number of failures */
					__( 'Processing completed with %d failures. Check the log above for details.', 'travelopia-wp-ai' ),
					$failed
				)
			);
		}
	}
}
