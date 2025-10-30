<?php
/**
 * Alt Text module.
 *
 * @package travelopia-wordpress-ai
 */

namespace Travelopia\WordPress_AI;

use Travelopia\WordPress_AI\Providers\OpenAI;
use WP_CLI;
use WP_Error;
use WP_Post;
use WP_Query;
use WP_REST_Request;

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
		add_action( 'admin_enqueue_scripts', [ __CLASS__, 'admin_enqueue_scripts' ] );
		add_action( 'rest_after_insert_attachment', [ __CLASS__, 'handle_rest_alt_text_update' ], 10, 2 );
		add_filter( 'media_row_actions', [ __CLASS__, 'media_row_actions' ], 10, 2 );

		// Register WP CLI commands.
		if ( defined( 'WP_CLI' ) && true === WP_CLI && class_exists( 'WP_CLI' ) ) {
			WP_CLI::add_command( 'travelopia-wp-ai alt-text', WpCli\AltText::class );
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

	/**
	 * Get attachment data for alt text editor.
	 *
	 * @return array<string, mixed>|null Array with post, alt_text, and mode, or null if invalid.
	 */
	public static function get_attachment_editor_data(): ?array
	{
		$screen = get_current_screen();

		if ( ! $screen || 'attachment' !== $screen->id ) {
			return null;
		}

		$post_id = isset( $_GET['post'] ) ? absint( $_GET['post'] ) : 0;

		if ( ! $post_id ) {
			return null;
		}

		$valid_request = ! isset( $_GET['tp_nonce'] ) ? false : wp_verify_nonce( $_GET['tp_nonce'], 'generate_alt_text_' . $post_id );

		if ( ! $valid_request ) {
			return null;
		}

		$post = get_post( $post_id );

		if ( ! $post instanceof WP_Post ) {
			return null;
		}

		if ( 'attachment' !== $post->post_type || false === strpos( $post->post_mime_type, 'image' ) ) {
			return null;
		}

		$is_regeneration = isset( $_GET['tp_regenerate_alt_text'] );
		$is_generation   = isset( $_GET['tp_generate_alt_text'] );
		$alt_text        = get_post_meta( $post->ID, '_wp_attachment_image_alt', true );

		if ( $is_generation && empty( $alt_text ) ) {
			$result = self::generate_alt_text_for_attachment( $post->ID, true );

			if ( ! is_wp_error( $result ) ) {
				$alt_text = $result;
			}
		}

		if ( $is_regeneration ) {
			$result = self::generate_alt_text_for_attachment( $post->ID, false );

			if ( ! is_wp_error( $result ) ) {
				$alt_text = $result;
			}
		}

		$mode = $is_regeneration ? 'regenerate' : 'default';

		return [
			'post'     => $post,
			'alt_text' => $alt_text,
			'mode'     => $mode,
		];
	}

	/**
	 * Enqueue editor assets.
	 *
	 * @return void
	 */
	public static function admin_enqueue_scripts(): void
	{
		// Get attachment data.
		$data = self::get_attachment_editor_data();

		// Return if no valid data.
		if ( ! $data ) {
			return;
		}

		// Extract post from data and ensure it's a WP_Post object.
		$post = $data['post'] ?? null;

		// Return if post is not a valid WP_Post instance.
		if ( ! $post instanceof WP_Post ) {
			return;
		}

		// Enqueue editor scripts.
		wp_enqueue_script( 'travelopia-wp-ai-editor', plugins_url( 'dist/editor.js', plugin_dir_path( __DIR__ ) ), [], '1.0.0', true );
		wp_enqueue_style( 'travelopia-wp-ai-editor', plugins_url( 'dist/editor.css', plugin_dir_path( __DIR__ ) ), [], '1.0.0' );

		// Localize script with all necessary data.
		wp_localize_script(
			'travelopia-wp-ai-editor',
			'travelopiaWpAi',
			[
				'api' => [
					'root'  => rest_url(),
					'nonce' => wp_create_nonce( 'wp_rest' ),
				],
				'nonces' => [
					'rest' => wp_create_nonce( 'wp_rest' ),
				],
				'attachment' => [
					'id'      => $post->ID,
					'altText' => $data['alt_text'],
					'mode'    => $data['mode'],
				],
				'urls' => [
					'generate'   => self::get_alt_text_action_url( $post, false ),
					'regenerate' => self::get_alt_text_action_url( $post, true ),
					'reject'     => admin_url( 'post.php?post=' . $post->ID . '&action=edit' ),
				],
				'labels' => [
					'generateAltText'   => __( 'Generate Alt Text', 'travelopia-wordpress-ai' ),
					'regenerateAltText' => __( 'Regenerate Alt Text', 'travelopia-wordpress-ai' ),
					'accept'            => __( 'Accept', 'travelopia-wordpress-ai' ),
					'reject'            => __( 'Reject', 'travelopia-wordpress-ai' ),
					'regenerate'        => __( 'Regenerate', 'travelopia-wordpress-ai' ),
					'saving'            => __( 'Saving...', 'travelopia-wordpress-ai' ),
				],
			],
		);
	}

	/**
	 * Adds Quick Action CTA for generating Alt Text in Media Library Admin Page.
	 *
	 * @param mixed[] $actions Actions.
	 * @param WP_Post $post    Post object.
	 *
	 * @return mixed[]
	 */
	public static function media_row_actions( array $actions = [], ?WP_Post $post = null ): array
	{
		if ( ! $post ) {
			return $actions;
		}

		if ( 'attachment' !== $post->post_type || false === strpos( $post->post_mime_type, 'image' ) ) {
			return $actions;
		}

		$alt_text = get_post_meta( $post->ID, '_wp_attachment_image_alt', true );
		$base_url = admin_url( 'post.php?post=' . $post->ID . '&action=edit&' . ( empty( $alt_text ) ? 'tp_generate_alt_text=true' : 'tp_regenerate_alt_text=true' ) );
		$nonce    = wp_create_nonce( 'generate_alt_text_' . $post->ID );
		$url      = add_query_arg( 'tp_nonce', $nonce, $base_url );

		// Add the CTA on the actions row of the media item in list view.
		$actions['generate_alt_text'] = sprintf(
			'<a href="%s">%s</a>',
			esc_url( $url ),
			empty( $alt_text ) ? __( 'Generate Alt Text', 'travelopia-wordpress-ai' ) : __( 'Regenerate Alt Text', 'travelopia-wordpress-ai' ),
		);

		return $actions;
	}

	/**
	 * Get the CTA link.
	 *
	 * @param ?WP_Post $post            Post object.
	 * @param boolean  $is_regeneration URL is for alt text regeneration CTA.
	 *
	 * @return string
	 */
	public static function get_alt_text_action_url( ?WP_Post $post = null, bool $is_regeneration = false ): string
	{
		if ( ! $post ) {
			return '';
		}

		$base_url = admin_url( 'post.php?post=' . $post->ID . '&action=edit&' . ( $is_regeneration ? 'tp_regenerate_alt_text=true' : 'tp_generate_alt_text=true' ) );
		$nonce    = wp_create_nonce( 'generate_alt_text_' . $post->ID );

		return add_query_arg( 'tp_nonce', $nonce, $base_url );
	}

	/**
	 * Handle REST API alt text update.
	 *
	 * This function hooks into the REST API after an attachment is updated
	 * to trigger our custom action and maintain compatibility with existing hooks.
	 *
	 * @param ?WP_Post         $attachment The updated attachment object.
	 * @param ?WP_REST_Request $request    The request object.
	 *
	 * @return void
	 */
	public static function handle_rest_alt_text_update( ?WP_Post $attachment = null, ?WP_REST_Request $request = null ): void
	{
		// Return early if attachment or request is null.
		if ( ! $attachment || ! $request ) {
			return;
		}

		// Check if this is an alt text update.
		if ( ! $request->has_param( 'alt_text' ) ) {
			return;
		}

		// Get the alt text from the request.
		$alt_text = $request->get_param( 'alt_text' );

		// Fire action hook after successful alt text modification via REST API.
		do_action( 'travelopia_wordpress_ai_alt_text_modified', $attachment->ID, $alt_text );
	}
}
