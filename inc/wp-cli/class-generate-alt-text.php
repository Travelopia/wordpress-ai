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
use function TravAI\get_ai_setting;

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
	 * @synopsis [--ids=<1,2,3>] [--missing]
	 *
	 * @return void
	 */
	public function generate( array $args = [], array $args_assoc = [] ): void {
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
			WP_CLI::error( __( 'You must provide --ids=<1,2,3> to specify which images to process.', 'trav-ai' ) );
		}

		// Check if AI alt text generation is enabled.
		$ai_enabled = get_ai_setting( 'ai_alt_text_enabled', false );

		// If not enabled, show error and exit.
		if ( ! $ai_enabled ) {
			WP_CLI::error( __( 'AI alt text generation is not enabled. Please enable it in Settings > TravAI.', 'trav-ai' ) );
		}

		// Get the AI prompt.
		$ai_prompt = get_ai_setting( 'ai_alt_text_prompt', '' );

		// Validate prompt is configured.
		if ( empty( $ai_prompt ) ) {
			WP_CLI::error( __( 'AI prompt is not configured. Please set it in Settings > TravAI.', 'trav-ai' ) );
		}

		// Welcome message.
		WP_CLI::log( WP_CLI::colorize( '%Y' . __( 'Generating alt text for images using AI...', 'trav-ai' ) . '%n' ) );
		WP_CLI::log( WP_CLI::colorize( '%B' . __( 'Using prompt:', 'trav-ai' ) . '%n ' . $ai_prompt ) );

		// Display mode information.
		if ( $options['missing'] ) {
			WP_CLI::log( WP_CLI::colorize( '%B' . __( 'Mode:', 'trav-ai' ) . '%n ' . __( 'Processing specified image IDs that are missing alt text', 'trav-ai' ) ) );
		} else {
			WP_CLI::log( WP_CLI::colorize( '%B' . __( 'Mode:', 'trav-ai' ) . '%n ' . __( 'Processing specified image IDs', 'trav-ai' ) ) );
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
