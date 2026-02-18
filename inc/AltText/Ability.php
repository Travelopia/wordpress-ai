<?php
/**
 * Alt Text Ability.
 *
 * @package travelopia-wordpress-ai
 */

namespace Travelopia\WordPress_AI\AltText;

use Exception;
use Travelopia\WordPress_AI\AltText;
use WP_Error;
use WP_Post;

/**
 * Class Ability.
 */
class Ability
{
	/**
	 * Ability name.
	 *
	 * @var string
	 */
	public const string ABILITY_NAME = 'travelopia/generate-alt-text';

	/**
	 * Register the ability.
	 *
	 * @return void
	 */
	public static function register(): void
	{
		wp_register_ability(
			self::ABILITY_NAME,
			[
				'label'       => __( 'Generate Alt Text', 'travelopia-wordpress-ai' ),
				'description' => __( 'Generates alt text for an attachment image using AI.', 'travelopia-wordpress-ai' ),
				'category'    => 'ai',

				'input_schema' => [
					'type'       => 'object',
					'properties' => [
						'post_id' => [
							'type'        => 'integer',
							'description' => __( 'Attachment Post ID', 'travelopia-wordpress-ai' ),
						],
					],
					'required' => [ 'post_id' ],
				],

				'output_schema' => [
					'type'       => 'object',
					'properties' => [
						'success' => [
							'type'        => 'boolean',
							'description' => __( 'Whether the generation was successful', 'travelopia-wordpress-ai' ),
						],
						'alt_text' => [
							'type'        => 'string',
							'description' => __( 'The generated alt text', 'travelopia-wordpress-ai' ),
						],
						'message' => [
							'type'        => 'string',
							'description' => __( 'Status message', 'travelopia-wordpress-ai' ),
						],
					],
				],

				'execute_callback'    => [ __CLASS__, 'execute' ],
				'permission_callback' => [ __CLASS__, 'check_permission' ],

				'meta' => [
					'show_in_rest' => true,
				],
			],
		);
	}

	/**
	 * Check permission.
	 *
	 * @param array<string, mixed> $input Input data.
	 *
	 * @return bool|WP_Error
	 */
	public static function check_permission( array $input = [] ): bool|WP_Error
	{
		$post_id = absint( $input['post_id'] ?? 0 );

		if ( empty( $post_id ) ) {
			return new WP_Error(
				'rest_invalid_post_id',
				__( 'Invalid post ID.', 'travelopia-wordpress-ai' ),
			);
		}

		$post = get_post( $post_id );

		if ( ! $post instanceof WP_Post || 'attachment' !== $post->post_type || ! str_contains( $post->post_mime_type, 'image' ) ) {
			return new WP_Error(
				'rest_invalid_attachment',
				__( 'Invalid image attachment.', 'travelopia-wordpress-ai' ),
			);
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return new WP_Error(
				'rest_forbidden',
				__( 'You do not have permission to edit this attachment.', 'travelopia-wordpress-ai' ),
			);
		}

		return true;
	}

	/**
	 * Execute the ability.
	 *
	 * @param array<string, mixed> $input Input data.
	 *
	 * @return array{success: bool, alt_text?: string, message: string}
	 */
	public static function execute( array $input = [] ): array
	{
		try {
			$post_id = absint( $input['post_id'] ?? 0 );

			$result = AltText::generate( $post_id, update: true );

			if ( $result instanceof WP_Error ) {
				return [
					'success' => false,
					'message' => $result->get_error_message(),
				];
			}

			return [
				'success'  => true,
				'alt_text' => $result,
				'message'  => __( 'Alt text generated successfully.', 'travelopia-wordpress-ai' ),
			];
		} catch ( Exception $e ) {
			return [
				'success' => false,
				'message' => __( 'Failed to generate alt text. Please try again.', 'travelopia-wordpress-ai' ),
			];
		}
	}
}
