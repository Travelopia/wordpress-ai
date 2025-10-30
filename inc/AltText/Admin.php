<?php
/**
 * Admin functionality.
 *
 * @package travelopia-wordpress-ai
 */

namespace Travelopia\WordPress_AI\AltText;

use Travelopia\WordPress_AI\AltText;
use WP_Post;
use WP_REST_Request;

class Admin
{
	/**
	 * Bootstrap the admin functionality.
	 *
	 * @return void
	 */
	public static function bootstrap(): void
	{
		add_action( 'admin_enqueue_scripts', [ __CLASS__, 'admin_enqueue_scripts' ] );
		add_action( 'rest_after_insert_attachment', [ __CLASS__, 'handle_rest_alt_text_update' ], 10, 2 );
		add_filter( 'media_row_actions', [ __CLASS__, 'media_row_actions' ], 10, 2 );
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

		if ( 'attachment' !== $post->post_type || ! str_contains( $post->post_mime_type, 'image' ) ) {
			return null;
		}

		$is_regeneration = isset( $_GET['tp_regenerate_alt_text'] );
		$is_generation   = isset( $_GET['tp_generate_alt_text'] );
		$alt_text        = get_post_meta( $post->ID, '_wp_attachment_image_alt', true );

		if ( $is_generation && empty( $alt_text ) ) {
			$result = AltText::generate_alt_text_for_attachment( $post->ID, update: true );

			if ( ! is_wp_error( $result ) ) {
				$alt_text = $result;
			}
		}

		if ( $is_regeneration ) {
			$result = AltText::generate_alt_text_for_attachment( $post->ID, update: false );

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
	 * @param mixed[]  $actions Actions.
	 * @param ?WP_Post $post    Post object.
	 *
	 * @return mixed[]
	 */
	public static function media_row_actions( array $actions = [], ?WP_Post $post = null ): array
	{
		if ( ! $post instanceof WP_Post ) {
			return $actions;
		}

		if ( 'attachment' !== $post->post_type || ! str_contains( $post->post_mime_type, 'image' ) ) {
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
