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
		add_action( 'admin_enqueue_scripts', [ __CLASS__, 'enqueue_editor_scripts' ] );
		add_action( 'wp_abilities_api_init', [ __CLASS__, 'register_abilities' ] );
	}

	/**
	 * Display admin notices for alt text generation errors and successes.
	 *
	 * @return void
	 */
	public static function display_admin_notices(): void
	{
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- This is just displaying a notice, not processing data.
		if ( isset( $_GET['tp_ai_error'] ) ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- This is just displaying a notice, not processing data.
			$raw_error  = is_string( $_GET['tp_ai_error'] ) ? $_GET['tp_ai_error'] : '';
			$error_code = sanitize_key( $raw_error );

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
			$raw_success  = is_string( $_GET['tp_success'] ) ? $_GET['tp_success'] : '';
			$success_code = sanitize_key( $raw_success );

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

			$nonce = isset( $_GET['tp_nonce'] ) && is_string( $_GET['tp_nonce'] ) ? sanitize_text_field( $_GET['tp_nonce'] ) : '';

			if ( ! wp_verify_nonce( $nonce, $nonce_action ) ) {
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
						'tp_ai_error' => $e->getMessage(),
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

	/**
	 * Enqueue editor scripts on attachment edit screen.
	 *
	 * @param string $hook_suffix Current admin page hook suffix.
	 *
	 * @return void
	 */
	public static function enqueue_editor_scripts( string $hook_suffix = '' ): void
	{
		// Only load on post.php (attachment edit screen).
		if ( 'post.php' !== $hook_suffix ) {
			return;
		}

		// Get the current screen.
		$screen = get_current_screen();

		// Only load for attachment post type.
		if ( ! $screen || 'attachment' !== $screen->post_type ) {
			return;
		}

		// Get current post to verify it's an image.
		global $post;

		if ( ! $post instanceof WP_Post || ! str_contains( $post->post_mime_type, 'image' ) ) {
			return;
		}

		// Get plugin directory paths.
		$plugin_dir_path = dirname( __DIR__, 2 );
		$plugin_dir_url  = plugin_dir_url( $plugin_dir_path . '/plugin.php' );
		$asset_file      = include $plugin_dir_path . '/dist/alt-text.asset.php';
		$dependencies    = is_array( $asset_file ) && isset( $asset_file['dependencies'] ) && is_array( $asset_file['dependencies'] ) ? array_map( static fn ( mixed $dep ): string => (string) $dep, $asset_file['dependencies'] ) : [];
		$version         = is_array( $asset_file ) && isset( $asset_file['version'] ) && is_string( $asset_file['version'] ) ? $asset_file['version'] : '1.0.0';

		// Enqueue script.
		wp_enqueue_script(
			'travelopia-wp-ai-alt-text',
			$plugin_dir_url . 'dist/alt-text.js',
			$dependencies,
			$version,
			true,
		);

		// Get current alt text.
		$alt_text = get_post_meta( $post->ID, '_wp_attachment_image_alt', true );

		// Localize script with data.
		wp_localize_script(
			'travelopia-wp-ai-alt-text',
			'travelopiaWpAi',
			[
				'attachmentId'   => $post->ID,
				'currentAltText' => is_string( $alt_text ) ? $alt_text : '',
				'restUrl'        => rest_url( 'wp-abilities/v1/abilities/' . Ability::ABILITY_NAME . '/run' ),
				'nonce'          => wp_create_nonce( 'wp_rest' ),
				'labels'         => [
					'generate'   => __( 'Generate Alt Text', 'travelopia-wordpress-ai' ),
					'regenerate' => __( 'Regenerate Alt Text', 'travelopia-wordpress-ai' ),
					'saving'     => __( 'Generating...', 'travelopia-wordpress-ai' ),
				],
			],
		);
	}

	/**
	 * Register abilities.
	 *
	 * @return void
	 */
	public static function register_abilities(): void
	{
		Ability::register();
	}
}
