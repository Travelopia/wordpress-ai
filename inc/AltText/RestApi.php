<?php
/**
 * Alt Text REST API.
 *
 * @package travelopia-wordpress-ai
 */

namespace Travelopia\WordPress_AI\AltText;

use Exception;
use Travelopia\WordPress_AI\AltText;
use WP_Error;
use WP_Post;
use WP_REST_Controller;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

use const Travelopia\WordPress_AI\REST_API_NAMESPACE;

/**
 * Class RestApi.
 */
class RestApi extends WP_REST_Controller
{
	/**
	 * The namespace of this controller's route.
	 *
	 * @var string
	 */
	protected $namespace = REST_API_NAMESPACE;

	/**
	 * The base of this controller's route.
	 *
	 * @var string
	 */
	protected $rest_base = 'alt-text';

	/**
	 * Register the routes for the objects of the controller.
	 *
	 * @return void
	 */
	public function register_routes(): void
	{
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/generate',
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'generate_alt_text' ],
				'permission_callback' => [ $this, 'check_permission' ],
				'args'                => [
					'post_id' => [
						'required'          => true,
						'type'              => 'integer',
						'description'       => __( 'Attachment Post ID', 'travelopia-wordpress-ai' ),
						'sanitize_callback' => 'absint',
						'validate_callback' => [ $this, 'validate_post_id' ],
					],
				],
			],
		);
	}

	/**
	 * Validate post ID.
	 *
	 * @param int $post_id Post ID.
	 *
	 * @return bool
	 */
	public function validate_post_id( int $post_id ): bool
	{
		if ( empty( $post_id ) ) {
			return false;
		}

		$post = get_post( $post_id );

		if ( ! $post instanceof WP_Post ) {
			return false;
		}

		if ( 'attachment' !== $post->post_type ) {
			return false;
		}

		if ( ! str_contains( $post->post_mime_type, 'image' ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Check permission.
	 *
	 * @param WP_REST_Request $request The request object.
	 *
	 * @return bool|WP_Error
	 */
	public function check_permission( WP_REST_Request $request ): bool|WP_Error
	{
		$post_id = $request->get_param( 'post_id' );

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return new WP_Error(
				'rest_forbidden',
				__( 'You do not have permission to edit this attachment.', 'travelopia-wordpress-ai' ),
				[ 'status' => 403 ],
			);
		}

		return true;
	}

	/**
	 * Generate alt text.
	 *
	 * @param WP_REST_Request $request The request object.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function generate_alt_text( WP_REST_Request $request ): WP_REST_Response|WP_Error
	{
		try {
			$post_id = absint( $request->get_param( 'post_id' ) );

			// Generate the alt text.
			$result = AltText::generate( $post_id, update: true );

			// Check if generation was successful.
			if ( $result instanceof WP_Error ) {
				return new WP_Error(
					'generation_failed',
					$result->get_error_message(),
					[ 'status' => 500 ],
				);
			}

			// Return success with generated alt text.
			return new WP_REST_Response(
				[
					'success'  => true,
					'alt_text' => $result,
					'message'  => __( 'Alt text generated successfully.', 'travelopia-wordpress-ai' ),
				],
				200,
			);
		} catch ( Exception $e ) {
			return new WP_Error(
				'server_error',
				__( 'Failed to generate alt text. Please try again.', 'travelopia-wordpress-ai' ),
				[ 'status' => 500 ],
			);
		}
	}
}
