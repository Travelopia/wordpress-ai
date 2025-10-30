<?php
/**
 * Admin functionality.
 *
 * @package travelopia-wordpress-ai
 */

namespace Travelopia\WordPress_AI\AltText;

use Exception;
use Travelopia\WordPress_AI\AltText;
use WP_Error;
use WP_Post;

class Admin
{
	/**
	 * Admin action name for generating/regenerating alt text.
	 *
	 * @var string
	 */
	private const ACTION_GENERATE_ALT_TEXT = 'tp_generate_alt_text';

	/**
	 * Bootstrap the admin functionality.
	 *
	 * @return void
	 */
	public static function bootstrap(): void
	{
		add_filter( 'media_row_actions', [ __CLASS__, 'media_row_actions' ], 10, 2 );
		add_action( 'admin_action_' . self::ACTION_GENERATE_ALT_TEXT, [ __CLASS__, 'handle_generate_alt_text_action' ] );
		add_action( 'admin_notices', [ __CLASS__, 'display_admin_notices' ] );
	}

	/**
	 * Display admin notices for alt text generation errors and successes.
	 *
	 * @return void
	 */
	public static function display_admin_notices(): void
	{
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- This is just displaying a notice, not processing data.
		if ( isset( $_GET['tp_error'] ) ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- This is just displaying a notice, not processing data.
			$error_code = sanitize_key( (string) wp_unslash( $_GET['tp_error'] ) );

			$error_messages = [
				'invalid_post_id'       => __( 'Invalid post ID.', 'travelopia-wordpress-ai' ),
				'security_check_failed' => __( 'Security check failed.', 'travelopia-wordpress-ai' ),
				'invalid_attachment'    => __( 'Invalid attachment.', 'travelopia-wordpress-ai' ),
				'permission_denied'     => __( 'You do not have permission to edit this attachment.', 'travelopia-wordpress-ai' ),
				'generation_failed'     => __( 'Failed to generate alt text. Please try again.', 'travelopia-wordpress-ai' ),
			];

			if ( isset( $error_messages[ $error_code ] ) ) {
				wp_admin_notice(
					$error_messages[ $error_code ],
					[
						'type'           => 'error',
						'dismissible'    => true,
						'paragraph_wrap' => true,
					],
				);
			}
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- This is just displaying a notice, not processing data.
		if ( isset( $_GET['tp_success'] ) ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- This is just displaying a notice, not processing data.
			$success_code = sanitize_key( (string) wp_unslash( $_GET['tp_success'] ) );

			$success_messages = [
				'generated'   => __( 'Alt text generated successfully.', 'travelopia-wordpress-ai' ),
				'regenerated' => __( 'Alt text regenerated successfully.', 'travelopia-wordpress-ai' ),
			];

			if ( isset( $success_messages[ $success_code ] ) ) {
				wp_admin_notice(
					$success_messages[ $success_code ],
					[
						'type'           => 'success',
						'dismissible'    => true,
						'paragraph_wrap' => true,
					],
				);
			}
		}
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
		if ( ! $post instanceof WP_Post || 'attachment' !== $post->post_type || ! str_contains( $post->post_mime_type, 'image' ) ) {
			return $actions;
		}

		$alt_text        = get_post_meta( $post->ID, '_wp_attachment_image_alt', true );
		$is_regeneration = ! empty( $alt_text );

		// Add the CTA on the actions row of the media item in list view.
		$actions['generate_alt_text'] = sprintf(
			'<a href="%s">%s</a>',
			esc_url( self::get_alt_text_action_url( $post ) ),
			$is_regeneration ? __( 'Regenerate Alt Text', 'travelopia-wordpress-ai' ) : __( 'Generate Alt Text', 'travelopia-wordpress-ai' ),
		);

		return $actions;
	}

	/**
	 * Handle the generate/regenerate alt text admin action.
	 *
	 * @return void
	 *
	 * @throws Exception
	 */
	public static function handle_generate_alt_text_action(): void
	{
		$redirect_url = admin_url( 'upload.php' );

		try {
			// Get and validate post ID.
			$post_id = isset( $_GET['post'] ) ? absint( $_GET['post'] ) : 0;

			if ( empty( $post_id ) ) {
				throw new Exception( 'invalid_post_id' );
			}

			// Verify nonce.
			$nonce_action = 'generate_alt_text_' . $post_id;

			if ( ! isset( $_GET['tp_nonce'] ) || ! wp_verify_nonce( $_GET['tp_nonce'], $nonce_action ) ) {
				throw new Exception( 'security_check_failed' );
			}

			// Get and validate post.
			$post = get_post( $post_id );

			if ( ! $post instanceof WP_Post || 'attachment' !== $post->post_type || ! str_contains( $post->post_mime_type, 'image' ) ) {
				throw new Exception( 'invalid_attachment' );
			}

			// Check user capabilities.
			if ( ! current_user_can( 'edit_post', $post_id ) ) {
				throw new Exception( 'permission_denied' );
			}

			// Check if alt text already exists to determine if this is a regeneration.
			$existing_alt_text = get_post_meta( $post_id, '_wp_attachment_image_alt', true );
			$is_regeneration   = ! empty( $existing_alt_text );

			// Generate the alt text.
			$result = AltText::generate( $post_id, update: true );

			// Check if generation was successful.
			if ( $result instanceof WP_Error ) {
				throw new Exception( 'generation_failed' );
			}

			// Redirect back to media library with success message.
			wp_safe_redirect(
				add_query_arg(
					[
						'tp_success'       => $is_regeneration ? 'regenerated' : 'generated',
						'tp_attachment_id' => $post_id,
					],
					$redirect_url,
				),
			);
			exit;
		} catch ( Exception $e ) {
			// Redirect with error message.
			wp_safe_redirect(
				add_query_arg(
					[
						'tp_error' => $e->getMessage(),
					],
					$redirect_url,
				),
			);
			exit;
		}
	}

	/**
	 * Get the admin action URL for generating or regenerating alt text.
	 *
	 * @param ?WP_Post $post Post object.
	 *
	 * @return string
	 */
	public static function get_alt_text_action_url( ?WP_Post $post = null ): string
	{
		if ( ! $post ) {
			return '';
		}

		$nonce = wp_create_nonce( 'generate_alt_text_' . $post->ID );

		return add_query_arg(
			[
				'action'   => self::ACTION_GENERATE_ALT_TEXT,
				'post'     => $post->ID,
				'tp_nonce' => $nonce,
			],
			admin_url( 'admin.php' ),
		);
	}
}
