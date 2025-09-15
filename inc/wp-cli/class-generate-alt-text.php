<?php
/**
 * Generate Alt Text CLI Command.
 *
 * @package trav-ai
 */

namespace TravAI\WP_CLI;

use WP_CLI;
use WP_Query;
use WP_Post;

use function WP_CLI\Utils\make_progress_bar;
use function TravAI\generate_alt_text_for_attachment;

/**
 * Class Generate_Alt_Text.
 */
class Generate_Alt_Text {

	/**
	 * Generate alt text for images using AI.
	 *
	 * @param array<string, mixed> $args       WP CLI arguments.
	 * @param array<string, mixed> $args_assoc WP CLI associative arguments.
	 *
	 * @subcommand generate
	 *
	 * @synopsis [--ids=<1,2,3>]
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
				'ids' => [],
			]
		);

		// Parse IDs if provided.
		if ( ! empty( $options['ids'] ) ) {
			$options['ids'] = array_map( 'absint', array_filter( array_map( 'trim', explode( ',', $options['ids'] ) ) ) );
		} else {
			$options['ids'] = [];
		}

		// Check if AI alt text generation is enabled.
		$ai_enabled = \TravAI\get_ai_setting( 'ai_alt_text_enabled', false );

		// If not enabled, show error and exit.
		if ( ! $ai_enabled ) {
			WP_CLI::error( __( 'AI alt text generation is not enabled. Please enable it in Settings > TravAI.', 'trav-ai' ) );
		}

		// Get the AI prompt.
		$ai_prompt = \TravAI\get_ai_setting( 'ai_alt_text_prompt', '' );

		// Validate prompt is configured.
		if ( empty( $ai_prompt ) ) {
			WP_CLI::error( __( 'AI prompt is not configured. Please set it in Settings > TravAI.', 'trav-ai' ) );
		}

		// Welcome message.
		WP_CLI::log( WP_CLI::colorize( '%Y' . __( 'Generating alt text for images using AI...', 'trav-ai' ) . '%n' ) );
		WP_CLI::log( WP_CLI::colorize( '%B' . __( 'Using prompt:', 'trav-ai' ) . '%n ' . $ai_prompt ) );
		WP_CLI::log( '' );

		// Build query arguments.
		$query_args = [
			'post_type'      => 'attachment',
			'post_mime_type' => 'image',
			'post_status'    => 'inherit',
			'posts_per_page' => -1,
			'fields'         => 'ids',
		];

		// Add specific IDs if provided.
		if ( ! empty( $options['ids'] ) ) {
			$query_args['post__in'] = $options['ids'];
		}

		// Get images.
		$images_query = new WP_Query( $query_args );
		$image_ids    = $images_query->posts;

		// Check if any images found.
		if ( empty( $image_ids ) ) {
			WP_CLI::error( __( 'No images found!', 'trav-ai' ) );
		}

		// Initialize progress tracking.
		$total_images = count( $image_ids );
		$progress     = make_progress_bar( __( 'Generating alt text', 'trav-ai' ), $total_images );
		$success      = 0;
		$failed       = 0;

		// Display found images count.
		WP_CLI::log( WP_CLI::colorize( '%G' . __( 'Found images:', 'trav-ai' ) . ' %n' . $total_images ) );

		// Process each image.
		foreach ( $image_ids as $image_id ) {
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
				$progress->tick();
				continue;
			}

			// Use the existing alt text generation function.
			$result = generate_alt_text_for_attachment( $image_id );

			// Check result and update counters.
			if ( $result['success'] && ! empty( $result['alt_text'] ) ) {
				++$success;
				$alt_text = $result['alt_text'];
				WP_CLI::log(
					sprintf(
					/* translators: 1: image ID, 2: alt text */
						__( 'Generated alt text for image ID %1$d: %2$s', 'trav-ai' ),
						$image_id,
						$alt_text
					)
				);
			} else {
				++$failed;
				$error_msg = $result['error'] ?? __( 'Unknown error', 'trav-ai' );
				WP_CLI::warning(
					sprintf(
					/* translators: 1: image ID, 2: error message */
						__( 'Failed to generate alt text for image ID %1$d: %2$s', 'trav-ai' ),
						$image_id,
						$error_msg
					)
				);
			}

			// Update progress.
			$progress->tick();
		}

		// Finish progress display.
		$progress->finish();

		// Display summary.
		WP_CLI::success( __( 'Alt text generation completed!', 'trav-ai' ) );
		WP_CLI::log( WP_CLI::colorize( '%G' . __( 'Success:', 'trav-ai' ) . ' %n' . $success ) );
		WP_CLI::log( WP_CLI::colorize( '%R' . __( 'Failed:', 'trav-ai' ) . ' %n' . $failed ) );
	}
}
