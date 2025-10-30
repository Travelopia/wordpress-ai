<?php
/**
 * Alt Text module.
 *
 * @package travelopia-wordpress-ai
 */

namespace Travelopia\WordPress_AI;

use Travelopia\WordPress_AI\AltText\Admin;
use Travelopia\WordPress_AI\Providers\OpenAI;
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
		if ( true !== Settings::get_setting( 'alt_text_generation', false ) ) {
			return;
		}

		// Hooks.
		add_action( 'add_attachment', [ __CLASS__, 'maybe_generate_alt_text_on_upload' ], 20 );

		// Bootstrap admin functionality.
		Admin::bootstrap();

		// Register WP CLI commands.
		if ( defined( 'WP_CLI' ) && true === WP_CLI && class_exists( 'WP_CLI' ) ) {
			WP_CLI::add_command( 'travelopia-wp-ai alt-text', AltText\CLI::class );
		}
	}

	/**
	 * Generate alt text for an uploaded image if missing.
	 *
	 * @param int $attachment_id Attachment ID.
	 *
	 * @return void
	 */
	public static function maybe_generate_alt_text_on_upload( int $attachment_id = 0 ): void
	{
		if ( ! wp_attachment_is_image( $attachment_id ) ) {
			return;
		}

		self::generate_alt_text_for_attachment( $attachment_id );
	}

	/**
	 * Generate alt text for any image attachment.
	 *
	 * @param int     $attachment_id Attachment ID.
	 * @param boolean $update        Whether to update the alt text.
	 *
	 * @return string|WP_Error Generated alt text on success, WP_Error on failure.
	 */
	public static function generate_alt_text_for_attachment( int $attachment_id = 0, bool $update = true ): WP_Error|string
	{
		// Get actual image URL for the attachment.
		$image_url = wp_get_attachment_url( $attachment_id );

		if ( ! is_string( $image_url ) ) {
			return self::create_alt_text_error(
				'invalid_image_url',
				__( 'Could not get image URL or is not a string.', 'travelopia-wordpress-ai' ),
				[ 'attachment_id' => $attachment_id ],
			);
		}

		// Initialize context.
		$context = '';

		/**
		 * Should we include additional context about the image?
		 *
		 * @param bool $include_context Whether to include context.
		 */
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
		$options = (array) apply_filters(
			'travelopia_wordpress_ai_alt_text_generation_options',
			array_merge(
				OpenAI::get_default_options(),
				[
					'prompt'  => Settings::get_setting( 'alt_text_prompt', '' ),
					'context' => $context,
				],
			),
			$attachment_id,
		);

		// Add context to prompt if requested.
		if ( ! empty( $options['context'] ) ) {
			$options['prompt'] .= sprintf(
				/* translators: %s: context */
				__( ' Additional context: %s', 'travelopia-wordpress-ai' ),
				$options['context'],
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
	 * Get the translated default AI prompt.
	 *
	 * @return string Translated default prompt.
	 */
	public static function get_default_alt_text_prompt(): string
	{
		return __( 'Describe this image in a concise, informative way for alt text. Focus on the main subject and important details that would help someone understand what is in the image.', 'travelopia-wordpress-ai' );
	}

	/**
	 * Get meta query for missing alt text filter.
	 *
	 * @return array<int|string, mixed> Meta query array.
	 */
	public static function get_missing_alt_text_meta_query(): array
	{
		return [
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

	/**
	 * Create a standardized WP_Error for alt text operations.
	 *
	 * @param string               $error_code    Error code.
	 * @param string               $error_message Error message.
	 * @param array<string, mixed> $error_data    Additional error data.
	 *
	 * @return WP_Error
	 */
	public static function create_alt_text_error( string $error_code = '', string $error_message = '', array $error_data = [] ): WP_Error
	{
		$error = new WP_Error( $error_code, $error_message, $error_data );
		do_action( 'travelopia_wordpress_ai_alt_text_error', $error_code, $error_message, $error_data );
		return $error;
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
			$query_args['meta_query'] = self::get_missing_alt_text_meta_query();
		}

		$images_query = new WP_Query( $query_args );

		return array_map( 'absint', $images_query->posts );
	}
}
