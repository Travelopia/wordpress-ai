<?php
/**
 * AI class for WordPress AI integration.
 *
 * @package travelopia-ai
 */

namespace TravelopiaAI;

use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use Exception;
use WordPress\AiClient\AiClient;
use WordPress\AiClient\ProviderImplementations\Google\GoogleProvider;

/**
 * AI class for alt text generation and management.
 */
class AI {

	/**
	 * Available AI providers.
	 *
	 * @var array
	 */
	private $providers = [];

	/**
	 * Constructor - Initialize API keys for providers.
	 */
	public function __construct() {
		$this->initialize_api_client();
		$this->register_rest_endpoints();
	}

	/**
	 * Initialize the AI client with API keys.
	 *
	 * @return void
	 */
	private function initialize_api_client(): void {
		define( 'GOOGLE_API_KEY', 'AIzaSyBeLVe6SzjS6ItBrFzvJhM2LUPT7sb9DV4' );
		define( 'OPENAI_API_KEY', 'xxx' );
	}

	/**
	 * Generate alt text for an image URL.
	 *
	 * @param string $image_url The URL of the image.
	 * @param string $provider Optional. AI provider to use. Default 'openai'.
	 *
	 * @return string|WP_Error Generated alt text or error.
	 */
	public function generate_alt_text( $image_url, $provider = 'gemini' ) {
		// Early bailout checks.
		if ( empty( $image_url ) || ! is_string( $image_url ) ) {
			return new WP_Error(
				'invalid_image_url',
				__( 'Invalid or empty image URL provided.', 'travelopia-ai' )
			);
		}

		try {
			// Prepare the prompt for alt text generation.
			$prompt = $this->build_alt_text_prompt( $image_url );

			if ( is_wp_error( $prompt ) ) {
				return $prompt;
			}

			// Generate alt text using the AI client.
			$response = AiClient::prompt( $prompt )
			->usingModel( GoogleProvider::model( 'gemini-2.5-flash' ) )
			->generateText();

			// Null check and validation.
			if ( empty( $response ) || ! is_string( $response ) ) {
				return new WP_Error(
					'empty_response',
					__( 'AI service returned an empty response.', 'travelopia-ai' )
				);
			}

			// Final validation.
			if ( empty( $response ) ) {
				return new WP_Error(
					'invalid_alt_text',
					__( 'Generated alt text is invalid or empty.', 'travelopia-ai' )
				);
			}

			return $response;

		} catch ( Exception $e ) {
			return new WP_Error(
				'generation_failed',
				sprintf(
					/* translators: %s: Error message */
					__( 'Failed to generate alt text: %s', 'travelopia-ai' ),
					$e->getMessage()
				)
			);
		}
	}

	/**
	 * Build the prompt for alt text generation.
	 *
	 * @param string $image_url The image URL.
	 * @return string|WP_Error The prompt or error.
	 */
	private function build_alt_text_prompt( $image_url ) {
		if ( empty( $image_url ) ) {
			return new WP_Error(
				'empty_url',
				__( 'Image URL is required for prompt generation.', 'travelopia-ai' )
			);
		}

		return sprintf(
			'Analyze this travel-related image and generate a concise, descriptive alt text that describes the main visual elements, location, and context. Keep it under 125 characters and focus on what travelers would find most relevant. Image URL: %s',
			esc_url( $image_url )
		);
	}

	/**
	 * Update alt text for an attachment in the database.
	 *
	 * @param int    $attachment_id The attachment ID.
	 * @param string $alt_text The alt text to set.
	 * @return bool|WP_Error True on success, error on failure.
	 */
	public function update_attachment_alt_text( $attachment_id, $alt_text ) {
		// Early bailout checks.
		if ( empty( $attachment_id ) || ! is_numeric( $attachment_id ) ) {
			return new WP_Error(
				'invalid_attachment_id',
				__( 'Invalid attachment ID provided.', 'travelopia-ai' )
			);
		}

		$attachment_id = absint( $attachment_id );

		if ( $attachment_id <= 0 ) {
			return new WP_Error(
				'invalid_attachment_id',
				__( 'Attachment ID must be a positive integer.', 'travelopia-ai' )
			);
		}

		// Verify attachment exists.
		$attachment = get_post( $attachment_id );
		if ( ! $attachment || 'attachment' !== $attachment->post_type ) {
			return new WP_Error(
				'attachment_not_found',
				__( 'Attachment not found.', 'travelopia-ai' )
			);
		}

		// Verify it's an image attachment.
		if ( ! wp_attachment_is_image( $attachment_id ) ) {
			return new WP_Error(
				'not_image_attachment',
				__( 'Attachment is not an image.', 'travelopia-ai' )
			);
		}

		// Validate and sanitize alt text.
		if ( ! is_string( $alt_text ) ) {
			$alt_text = '';
		}

		$alt_text = sanitize_textarea_field( $alt_text );

		// Update the alt text in database.
		$result = update_post_meta( $attachment_id, '_wp_attachment_image_alt', $alt_text );

		if ( false === $result ) {
			return new WP_Error(
				'update_failed',
				__( 'Failed to update attachment alt text.', 'travelopia-ai' )
			);
		}

		return true;
	}

	/**
	 * Register REST API endpoints.
	 *
	 * @return void
	 */
	private function register_rest_endpoints(): void {
		add_action( 'rest_api_init', [ $this, 'rest_api_init' ] );
	}

	/**
	 * Initialize REST API endpoints.
	 *
	 * @return void
	 */
	public function rest_api_init(): void {
		register_rest_route(
			'travelopia-ai/v1',
			'/generate-alt-text',
			[
				'methods'             => 'POST',
				'callback'            => [ $this, 'rest_generate_alt_text' ],
				'permission_callback' => [ $this, 'rest_permission_check' ],
				'args'                => [
					'image_url'     => [
						'required'          => true,
						'type'              => 'string',
						'format'            => 'uri',
						'sanitize_callback' => 'esc_url_raw',
						'validate_callback' => [ $this, 'validate_image_url' ],
					],
					'attachment_id' => [
						'required'          => false,
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
						'validate_callback' => [ $this, 'validate_attachment_id' ],
					],
					'provider'      => [
						'required'          => false,
						'type'              => 'string',
						'default'           => 'gemini',
						'enum'              => array_keys( $this->providers ),
						'sanitize_callback' => 'sanitize_text_field',
					],
				],
			]
		);
	}

	/**
	 * REST API callback for generating alt text.
	 *
	 * @param WP_REST_Request $request The REST request.
	 * @return WP_REST_Response|WP_Error Response or error.
	 */
	public function rest_generate_alt_text( $request ) {
		// Get parameters with null checks.
		$image_url     = $request->get_param( 'image_url' );
		$attachment_id = $request->get_param( 'attachment_id' );
		$provider      = $request->get_param( 'provider' ) ?: 'openai';

		// Early bailout for missing required params.
		if ( empty( $image_url ) ) {
			return new WP_Error(
				'missing_image_url',
				__( 'Image URL is required.', 'travelopia-ai' ),
				[ 'status' => 400 ]
			);
		}

		// Generate alt text.
		$alt_text = $this->generate_alt_text( $image_url, $provider );

		if ( is_wp_error( $alt_text ) ) {
			return new WP_REST_Response(
				[
					'success' => false,
					'error'   => $alt_text->get_error_message(),
					'code'    => $alt_text->get_error_code(),
				],
				500
			);
		}

		$response_data = [
			'success'  => true,
			'alt_text' => $alt_text,
			'provider' => $provider,
		];

		// If attachment ID provided, update the database.
		if ( ! empty( $attachment_id ) ) {
			$update_result = $this->update_attachment_alt_text( $attachment_id, $alt_text );

			if ( is_wp_error( $update_result ) ) {
				$response_data['warning'] = sprintf(
					/* translators: %s: Error message */
					__( 'Alt text generated but database update failed: %s', 'travelopia-ai' ),
					$update_result->get_error_message()
				);
			} else {
				$response_data['updated'] = true;
			}
		}

		return new WP_REST_Response( $response_data, 200 );
	}

	/**
	 * Permission check for REST API endpoints.
	 *
	 * @return bool|WP_Error
	 */
	public function rest_permission_check() {
		// Check if user can upload files (edit media).
		if ( ! current_user_can( 'upload_files' ) ) {
			return new WP_Error(
				'insufficient_permissions',
				__( 'You do not have permission to generate alt text.', 'travelopia-ai' ),
				[ 'status' => 403 ]
			);
		}

		return true;
	}

	/**
	 * Validate image URL parameter.
	 *
	 * @param string $value The URL value.
	 * @return bool
	 */
	public function validate_image_url( $value ): bool {
		if ( empty( $value ) || ! is_string( $value ) ) {
			return false;
		}

		// Check if it's a valid URL.
		if ( ! filter_var( $value, FILTER_VALIDATE_URL ) ) {
			return false;
		}

		// Basic check for image extensions (optional, as some URLs might not have extensions).
		$image_extensions = [ 'jpg', 'jpeg', 'png', 'gif', 'webp', 'svg' ];
		$path_info        = pathinfo( wp_parse_url( $value, PHP_URL_PATH ) );

		// Allow URLs without extensions (they might be served dynamically).
		return empty( $path_info['extension'] ) || in_array(
			strtolower( $path_info['extension'] ),
			$image_extensions,
			true
		);
	}

	/**
	 * Validate attachment ID parameter.
	 *
	 * @param int $value The attachment ID.
	 * @return bool
	 */
	public function validate_attachment_id( $value ): bool {
		if ( empty( $value ) ) {
			return true; // Optional parameter.
		}

		$attachment_id = absint( $value );

		if ( $attachment_id <= 0 ) {
			return false;
		}

		// Check if attachment exists and is an image.
		$attachment = get_post( $attachment_id );
		return $attachment &&
				'attachment' === $attachment->post_type &&
				wp_attachment_is_image( $attachment_id );
	}
}
