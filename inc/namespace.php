<?php
/**
 * Namespace functions.
 *
 * @package trav-ai
 */

namespace TravAI;

use WordPress\AiClient\AiClient;
use WordPress\AiClient\ProviderImplementations\OpenAi\OpenAiProvider;
use Exception;
use WP_CLI;
use WP_Post;
use WP_REST_Request;

use function TravAI\Admin\get_default_settings;

/**
 * Bootstrap plugin.
 *
 * @return void
 */
function bootstrap(): void {
	// Actions.
	add_action( 'add_attachment', __NAMESPACE__ . '\\maybe_generate_alt_text_on_upload', 20 );
	add_action( 'admin_enqueue_scripts', __NAMESPACE__ . '\\admin_enqueue_scripts' );
	add_action( 'rest_after_insert_attachment', __NAMESPACE__ . '\\handle_rest_alt_text_update', 10, 2 );

	// Filters.
	add_filter( 'media_row_actions', __NAMESPACE__ . '\\media_row_actions', 10, 2 );

	// Register WP CLI commands.
	if ( defined( 'WP_CLI' ) && true === WP_CLI && class_exists( 'WP_CLI' ) ) {
		require_once __DIR__ . '/wp-cli/class-generate-alt-text.php';

		// Register commands.
		WP_CLI::add_command( 'travai alt-text', __NAMESPACE__ . '\\WP_CLI\\Generate_Alt_Text' );
	}
}

/**
 * Generate alt text for an uploaded image if missing.
 *
 * @param int $attachment_id Attachment ID.
 *
 * @return void
 */
function maybe_generate_alt_text_on_upload( int $attachment_id = 0 ): void {
	// Validate attachment is an image and plugin is enabled.
	if ( ! wp_attachment_is_image( $attachment_id ) || ! get_ai_setting( 'ai_alt_text_enabled', false ) ) {
		return;
	}

	// Generate alt text for the uploaded image.
	generate_alt_text_for_attachment( $attachment_id );
}

/**
 * Get AI setting value.
 *
 * @param string $key           Setting key.
 * @param mixed  $default_value Default value if setting not found.
 *
 * @return mixed Setting value or default.
 */
function get_ai_setting( string $key = '', mixed $default_value = null ): mixed {
	// Fetch settings with default fallback.
	$settings = get_option( 'travai_settings', get_default_settings() );

	// Ensure settings is an array.
	if ( ! is_array( $settings ) ) {
		$settings = get_default_settings();
	}

	// Return.
	return $settings[ $key ] ?? $default_value;
}

/**
 * Get attachment data for alt text editor.
 *
 * @return array<string, mixed>|null Array with post, alt_text, and mode, or null if invalid.
 */
function get_attachment_editor_data(): ?array {
	// Get current screen.
	$screen = get_current_screen();

	// Only proceed on attachment edit screen.
	if ( ! $screen || 'attachment' !== $screen->id ) {
		return null;
	}

	// Get post ID from URL.
	$post_id = isset( $_GET['post'] ) ? absint( $_GET['post'] ) : 0;

	// Return if no valid post ID.
	if ( ! $post_id ) {
		return null;
	}

	// Get post object.
	$post = get_post( $post_id );

	// Return if not a valid WP_Post object.
	if ( ! $post instanceof WP_Post ) {
		return null;
	}

	// Return if not an image attachment.
	if ( 'attachment' !== $post->post_type || false === strpos( $post->post_mime_type, 'image' ) ) {
		return null;
	}

	// Get the existing alt text.
	$is_regeneration = isset( $_GET['tp_regenerate_alt_text'] );
	$valid_request   = ! isset( $_GET['tp_nonce'] ) ? false : wp_verify_nonce( $_GET['tp_nonce'], 'generate_alt_text_' . $post->ID );
	$is_generation   = isset( $_GET['tp_generate_alt_text'] );
	$alt_text        = get_post_meta( $post->ID, '_wp_attachment_image_alt', true );
	$is_empty_alt    = empty( $alt_text );

	// If query args has tp_generate_alt_text, then generate the alt text and save it.
	if ( $is_generation && $valid_request && $is_empty_alt ) {
		$result       = generate_alt_text_for_attachment( $post->ID, true );
		$alt_text     = $result['alt_text'] ?? '';
		$is_empty_alt = false;
	}

	// If query args has tp_regenerate_alt_text, then regenerate the alt text.
	if ( $is_regeneration && $valid_request ) {
		$result = generate_alt_text_for_attachment( $post->ID, false );

		// On success, update the alt text.
		if ( ! empty( $result['success'] ) ) {
			$alt_text = $result['alt_text'] ?? '';
		}
	}

	// Determine the mode for the component.
	$mode = $is_regeneration ? 'regenerate' : 'default';

	// Return the attachment editor data.
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
function admin_enqueue_scripts(): void {
	// Get attachment data.
	$data = get_attachment_editor_data();

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
	wp_enqueue_script( 'trav-ai-editor', plugins_url( 'dist/editor.js', __DIR__ ), [ 'wp-dom-ready', 'media-editor' ], '1.0.0', true );
	wp_enqueue_style( 'trav-ai-editor', plugins_url( 'dist/editor.css', __DIR__ ), [], '1.0.0' );

	// Localize script with all necessary data.
	wp_localize_script(
		'trav-ai-editor',
		'travAi',
		[
			'api'        => [
				'root'  => rest_url(),
				'nonce' => wp_create_nonce( 'wp_rest' ),
			],
			'nonces'     => [
				'rest' => wp_create_nonce( 'wp_rest' ),
			],
			'attachment' => [
				'id'      => $post->ID,
				'altText' => $data['alt_text'],
				'mode'    => $data['mode'],
			],
			'urls'       => [
				'generate'   => get_alt_text_action_url( $post, false ),
				'regenerate' => get_alt_text_action_url( $post, true ),
				'reject'     => admin_url( 'post.php?post=' . $post->ID . '&action=edit' ),
			],
			'labels'     => [
				'generateAltText'   => __( 'Generate Alt Text', 'trav-ai' ),
				'regenerateAltText' => __( 'Regenerate Alt Text', 'trav-ai' ),
				'accept'            => __( 'Accept', 'trav-ai' ),
				'reject'            => __( 'Reject', 'trav-ai' ),
				'regenerate'        => __( 'Regenerate', 'trav-ai' ),
				'saving'            => __( 'Saving...', 'trav-ai' ),
			],
		]
	);
}

/**
 * Generate alt text for any image attachment.
 *
 * @param int     $attachment_id Attachment ID.
 * @param boolean $update        Whether to update the alt text.
 *
 * @return array{success: bool, alt_text?: string, error?: string}
 */
function generate_alt_text_for_attachment( int $attachment_id = 0, bool $update = true ): array {
	// Early validation checks.
	if ( ! function_exists( 'wp_attachment_is_image' ) || ! wp_attachment_is_image( $attachment_id ) ) {
		return [
			'success'  => false,
			'alt_text' => '',
			'error'    => __( 'Invalid attachment ID or not an image', 'trav-ai' ),
		];
	}

	// Check if AI alt text generation is enabled.
	if ( ! get_ai_setting( 'ai_alt_text_enabled', false ) ) {
		return [
			'success'  => false,
			'alt_text' => '',
			'error'    => __( 'AI alt text generation is not enabled', 'trav-ai' ),
		];
	}

	// Get the AI prompt from settings.
	$ai_prompt = get_ai_setting( 'ai_alt_text_prompt', '' );

	// Validate prompt is configured.
	if ( empty( $ai_prompt ) || ! is_string( $ai_prompt ) ) {
		return [
			'success'  => false,
			'alt_text' => '',
			'error'    => __( 'AI prompt is not configured', 'trav-ai' ),
		];
	}

	// Ensure AI client is available.
	if ( ! class_exists( '\\WordPress\\AiClient\\AiClient' ) ) {
		return [
			'alt_text' => '',
			'success'  => false,
			'error'    => __( 'AI Client not available', 'trav-ai' ),
		];
	}

	// Default options for the generation.
	$default_options = [
		'model'              => 'gpt-4o-mini',
		'temperature'        => 0.1,
		'prompt'             => $ai_prompt,
		'include_context'    => true,
		'system_instruction' => 'You are an accessibility tool.',
	];

	/**
	 * Filter the generation options.
	 *
	 * @param array $default_options The generation options.
	 * @param int   $attachment_id The attachment ID.
	 */
	$options = apply_filters( 'trav_ai_generation_options', $default_options, $attachment_id );

	// Ensure options is an array.
	if ( ! is_array( $options ) ) {
		$options = $default_options;
	}

	// Build final prompt.
	$prompt = $options['prompt'];

	/**
	 * Filter the prompt.
	 *
	 * @param string $prompt The prompt.
	 * @param int $attachment_id The attachment ID.
	 */
	$prompt = apply_filters( 'trav_ai_alt_text_prompt', $prompt, $attachment_id );

	// Validate prompt is a string after filtering.
	if ( ! is_string( $prompt ) ) {
		return [
			'success'  => false,
			'alt_text' => '',
			'error'    => __( 'Invalid prompt type after filtering', 'trav-ai' ),
		];
	}

	// Initialize context.
	$context = '';

	// Build context from metadata if requested.
	if ( true === $options['include_context'] ) {
		$context_parts = [];
		$title         = get_the_title( $attachment_id );

		// Add title to context.
		if ( $title ) {
			$context_parts[] = sprintf(
				/* translators: %s: title */
				__( 'title: %s', 'trav-ai' ),
				$title
			);
		}

		// Join context parts with a semicolon.
		$context = implode( '; ', $context_parts );
	}

	// Add context to prompt if requested.
	if ( $context ) {
		$prompt .= sprintf(
			/* translators: %s: context */
			__( ' Additional context: %s', 'trav-ai' ),
			$context
		);
	}

	// Start AI generation process.
	try {
		// Check API key availability.
		if ( ! defined( 'OPENAI_API_KEY' ) && ! getenv( 'OPENAI_API_KEY' ) ) {
			return [
				'success'  => false,
				'alt_text' => '',
				'error'    => __( 'OpenAI API key not configured', 'trav-ai' ),
			];
		}

		// Get the API key.
		$api_key = defined( 'OPENAI_API_KEY' ) ? OPENAI_API_KEY : getenv( 'OPENAI_API_KEY' );

		// Validate API key is not empty.
		if ( empty( $api_key ) ) {
			return [
				'success'  => false,
				'alt_text' => '',
				'error'    => __( 'OpenAI API key is empty', 'trav-ai' ),
			];
		}

		// Get actual image URL for the attachment.
		$image_url = get_the_guid( $attachment_id );

		// Filter the image URL if needed.
		$image_url = apply_filters( 'trav_ai_image_url', $image_url, $attachment_id );

		// Validate image URL exists.
		if ( ! $image_url || ! is_string( $image_url ) ) {
			return [
				'success'  => false,
				'alt_text' => '',
				'error'    => __( 'Could not get image URL or is not a string.', 'trav-ai' ),
			];
		}

		// Generate AI response.
		$generated = AiClient::prompt( $prompt )
			->usingModel( OpenAiProvider::model( strval( $options['model'] ) ) )
			->usingTemperature( floatval( $options['temperature'] ) )
			->withFile( $image_url )
			->usingSystemInstruction( $options['system_instruction'] )
			->generateText();

		// Process and validate generated text.
		$generated = sanitize_text_field( trim( wp_strip_all_tags( strval( $generated ) ) ) );

		// Validate generated text is not empty.
		if ( empty( $generated ) ) {
			return [
				'success'  => false,
				'alt_text' => '',
				'error'    => __( 'AI generated empty response', 'trav-ai' ),
			];
		}

		// Save generated alt text to database.
		if ( $update ) {
			update_post_meta( $attachment_id, '_wp_attachment_image_alt', $generated );
		}

		// Fire action hook after successful generation.
		do_action( 'trav_ai_alt_text_generated', $attachment_id, $generated );

		// Return success with generated alt text.
		return [
			'success'  => true,
			'alt_text' => $generated,
		];
	} catch ( Exception $e ) {
		// Return error details.
		return [
			'success'  => false,
			'alt_text' => '',
			'error'    => sprintf(
				/* translators: %s: error message */
				__( 'AI generation failed: %s', 'trav-ai' ),
				$e->getMessage()
			),
		];
	}
}

/**
 * Plugin activation hook handler.
 *
 * This function is called when the plugin is activated.
 * It can be used to set up initial options, create database tables,
 * or perform any other setup tasks required for the plugin.
 *
 * @return void
 */
function activate_plugin(): void {
	// Initialize default settings if they don't exist.
	$default_settings = [
		'ai_alt_text_enabled' => false,
		'ai_prompt'           => __( 'Describe this image in a concise, informative way for alt text. Focus on the main subject and important details that would help someone understand what is in the image.', 'trav-ai' ),
	];

	// Only set defaults if no settings exist yet.
	if ( false === get_option( 'travai_settings' ) ) {
		add_option( 'travai_settings', $default_settings );
	}
}

/**
 * Adds Quick Action CTA for generating Alt Text in Media Library Admin Page.
 *
 * @param mixed[] $actions Actions.
 * @param WP_Post $post    Post object.
 *
 * @return mixed[]
 */
function media_row_actions( array $actions = [], ?WP_Post $post = null ): array {
	// Return early if post is null.
	if ( ! $post ) {
		return $actions;
	}

	// Return early if the post is not an image.
	if ( 'attachment' !== $post->post_type || strpos( $post->post_mime_type, 'image' ) === false ) {
		return $actions;
	}

	// Check if the image has alt text or not.
	$alt_text = get_post_meta( $post->ID, '_wp_attachment_image_alt', true );

	// Check if the image has alt text or not.
	$base_url = admin_url( 'post.php?post=' . $post->ID . '&action=edit&' . ( empty( $alt_text ) ? 'tp_generate_alt_text=true' : 'tp_regenerate_alt_text=true' ) );
	$nonce    = wp_create_nonce( 'generate_alt_text_' . $post->ID );
	$url      = add_query_arg( 'tp_nonce', $nonce, $base_url );

	// Add the CTA on the actions row of the media item in list view.
	$actions['generate_alt_text'] = sprintf(
		'<a href="%s">%s</a>',
		esc_url( $url ),
		empty( $alt_text ) ? __( 'Generate Alt Text', 'trav-ai' ) : __( 'Regenerate Alt Text', 'trav-ai' )
	);

	// Return the updated actions.
	return $actions;
}

/**
 * Get the CTA link.
 *
 * @param WP_Post $post            Post object.
 * @param boolean $is_regeneration URL is for alt text regeneration CTA.
 *
 * @return string
 */
function get_alt_text_action_url( ?WP_Post $post = null, bool $is_regeneration = false ): string {
	// Return empty string if post is null.
	if ( ! $post ) {
		return '';
	}

	// Build base URL.
	$base_url = admin_url( 'post.php?post=' . $post->ID . '&action=edit&' . ( $is_regeneration ? 'tp_regenerate_alt_text=true' : 'tp_generate_alt_text=true' ) );

	// Add nonce parameter.
	$nonce = wp_create_nonce( 'generate_alt_text_' . $post->ID );

	// Return the URL with nonce.
	return add_query_arg( 'tp_nonce', $nonce, $base_url );
}

/**
 * Handle REST API alt text update.
 *
 * This function hooks into the REST API after an attachment is updated
 * to trigger our custom action and maintain compatibility with existing hooks.
 *
 * @param WP_Post         $attachment The updated attachment object.
 * @param WP_REST_Request $request    The request object.
 *
 * @return void
 */
function handle_rest_alt_text_update( ?WP_Post $attachment = null, ?WP_REST_Request $request = null ): void {
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
	do_action( 'trav_ai_alt_text_modified', $attachment->ID, $alt_text );
}
