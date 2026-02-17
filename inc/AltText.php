<?php
/**
 * Alt Text module.
 *
 * @package travelopia-wordpress-ai
 */

namespace Travelopia\WordPress_AI;

use Exception;
use Travelopia\WordPress_AI\AltText\Admin;
use Travelopia\WordPress_AI\Providers\OpenAI;
use Travelopia\WordPress_AI\Settings\AltTextPromptField;
use Travelopia\WordPress_AI\Settings\EnableAltTextGenerationField;
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
		// Check if this module is enabled.
		if ( true !== Settings::get_setting( EnableAltTextGenerationField::FIELD_NAME, false ) ) {
			return;
		}

		// Hooks.
		add_action( 'add_attachment', [ __CLASS__, 'generate' ], 20 );

		// Bootstrap admin functionality.
		Admin::bootstrap();

		// Register WP CLI commands.
		if ( class_exists( 'WP_CLI' ) ) {
			WP_CLI::add_command( 'travelopia-wp-ai alt-text', AltText\CLI::class );
		}
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
		$image_url = wp_get_attachment_url( $attachment_id );

		if ( ! is_string( $image_url ) || ! wp_attachment_is_image( $attachment_id ) ) {
			$error = new WP_Error(
				'travelopia_wordpress_ai_alt_text_invalid_image',
				__(
					'Invalid image.',
					'travelopia-wordpress-ai',
				),
				[ 'attachment_id' => $attachment_id ],
			);

			$error_code = (string) $error->get_error_code();
			do_action( $error_code, $error_code, $error->get_error_message(), $error->get_error_data() );
			return $error;
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

		/**
		 * Filter the ALT text generation options.
		 *
		 * @param array $default_options The generation options.
		 * @param int   $attachment_id   The attachment ID.
		 */
		$default_options = [
			...OpenAI::get_default_options(),
			'prompt'  => Settings::get_setting( AltTextPromptField::FIELD_NAME, '' ),
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

		// Generate alt text using OpenAI provider.
		$alt_text = OpenAI::generate_alt_text( $image_url, $options );

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
		// Build query arguments.
		$query_args = [
			'post_type'              => 'attachment',
			'post_mime_type'         => 'image',
			'post_status'            => 'inherit',
			'fields'                 => 'ids',
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
		];

		// Handle specific image IDs.
		if ( ! empty( $image_ids ) ) {
			$query_args['post__in']       = $image_ids;
			$query_args['posts_per_page'] = -1;
			$query_args['no_found_rows']  = true;
		} else {
			// Pagination for all images.
			$query_args['posts_per_page'] = $per_page;
			$query_args['paged']          = $page;
			$query_args['no_found_rows']  = false;
		}

		// Filter for missing alt text.
		if ( $missing_only ) {
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

		$images_query = new WP_Query( $query_args );

		return array_map( 'absint', $images_query->posts );
	}
}
