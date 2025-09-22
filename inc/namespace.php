<?php
/**
 * Namespace functions.
 *
 * @package trav-ai
 */

namespace TravAI;

use WordPress\AiClient\AiClient;
use WordPress\AiClient\ProviderImplementations\OpenAi\OpenAiProvider;
use WordPress\AiClient\Providers\Http\DTO\ApiKeyRequestAuthentication;
use Exception;
use WP_CLI;
use WP_Post;

use function TravAI\Admin\get_default_settings;

/**
 * Bootstrap plugin.
 *
 * @return void
 */
function bootstrap(): void {
	// Actions.
	add_action( 'add_attachment', __NAMESPACE__ . '\\maybe_generate_alt_text_on_upload', 20 );
	add_action( 'add_meta_boxes', __NAMESPACE__ . '\\remove_image_editor' );
	add_action( 'edit_form_after_title', __NAMESPACE__ . '\\modify_image_editor', 11 );
	add_action( 'admin_enqueue_scripts', __NAMESPACE__ . '\\admin_enqueue_scripts' );
	add_action( 'wp_ajax_modify_attachment_alt_text', __NAMESPACE__ . '\\ajax_modify_attachment_alt_text' );

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
function maybe_generate_alt_text_on_upload( int $attachment_id ): void {
	// Validate attachment is an image and plugin is enabled.
	if (
			! wp_attachment_is_image( $attachment_id )
			|| ! get_ai_setting( 'ai_alt_text_enabled', false )
	) {
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
function get_ai_setting( string $key, mixed $default_value = null ): mixed {
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
 * Enqueue editor assets.
 *
 * @return void
 */
function admin_enqueue_scripts(): void {
	// Enqueue editor scripts.
	wp_enqueue_script( 'trav-ai-editor', plugins_url( 'dist/js/editor.js', __DIR__ ), [ 'wp-dom-ready' ], '1.0.0', true );
	wp_enqueue_style( 'trav-ai-editor', plugins_url( 'dist/css/editor.css', __DIR__ ), [], '1.0.0' );

	// Localize script with AJAX data and nonce.
	wp_localize_script(
		'trav-ai-editor',
		'wp',
		[
			'ajax'   => [
				'url' => admin_url( 'admin-ajax.php' ),
			],
			'nonces' => [
				'modifyAltText' => wp_create_nonce( 'modify_alt_text_nonce' ),
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
function generate_alt_text_for_attachment( int $attachment_id, bool $update = true ) {
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

	// Ensure ApiKeyRequestAuthentication is available.
	if ( ! class_exists( '\\WordPress\\AiClient\\Providers\\Http\\DTO\\ApiKeyRequestAuthentication' ) ) {
		return [
			'alt_text' => '',
			'success'  => false,
			'error'    => __( 'AI Client authentication class not available', 'trav-ai' ),
		];
	}

	// Get the file path.
	$file_path = get_attached_file( $attachment_id, true );

	// Validate file path exists.
	if ( empty( $file_path ) || ! file_exists( $file_path ) ) {
		return [
			'success'  => false,
			'alt_text' => '',
			'error'    => __( 'Image file not found', 'trav-ai' ),
		];
	}

	// Default options for the generation.
	$default_options = [
		'model'           => 'gpt-4o-mini',
		'temperature'     => 0.1,
		'prompt'          => $ai_prompt,
		'include_context' => true,
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

	// Initialize context.
	$context = '';

	// Build context from metadata if requested.
	if ( true === $options['include_context'] ) {
		$context_parts = [];
		$file_name     = wp_basename( $file_path );
		$title         = get_the_title( $attachment_id );

		// Add file name to context.
		if ( $file_name ) {
			$context_parts[] = sprintf(
				/* translators: %s: filename */
				__( 'filename: %s', 'trav-ai' ),
				$file_name
			);
		}

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

		// Set up authentication for OpenAI provider.
		$registry = AiClient::defaultRegistry();
		$auth     = new ApiKeyRequestAuthentication( $api_key );
		$registry->setProviderRequestAuthentication( OpenAiProvider::class, $auth );

		// Get actual image URL for the attachment.
		$image_url = wp_get_attachment_url( $attachment_id );

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

		// Append image URL to prompt.
		$prompt .= sprintf(
			/* translators: %s: image URL */
			__( ' Image: %s', 'trav-ai' ),
			$image_url
		);

		// Generate AI response.
		$generated = AiClient::prompt( $prompt )
			->usingModel( OpenAiProvider::model( strval( $options['model'] ) ) )
			->usingTemperature( floatval( $options['temperature'] ) )
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
function media_row_actions( array $actions, WP_Post $post ): array {
	// Return early if the post is not an image.
	if ( 'attachment' !== $post->post_type || strpos( $post->post_mime_type, 'image' ) === false ) {
		return $actions;
	}

	// Check if the image has alt text or not.
	$alt_text = get_post_meta( $post->ID, '_wp_attachment_image_alt', true );

	// Check if the image has alt text or not.
	$actions['generate_alt_text'] = sprintf(
		'<a href="%s">%s</a>',
		wp_nonce_url(
			admin_url( 'post.php?post=' . $post->ID . '&action=edit&' . ( empty( $alt_text ) ? 'tp_generate_alt_text=true' : 'tp_regenerate_alt_text=true' ) ),
			'generate_alt_text_' . $post->ID,
			'tp_nonce'
		),
		empty( $alt_text ) ? __( 'Generate Alt Text', 'et' ) : __( 'Regenerate Alt Text', 'et' )
	);

	// Return the updated actions.
	return $actions;
}

/**
 * Removes the editor options for the images. Its modified output is shown via modify_image_editor function.
 *
 * @return void
 */
function remove_image_editor(): void {
	// Remove the editor options for the images to be replaced by our own.
	remove_action( 'edit_form_after_title', 'edit_form_image_editor' );
}

/**
 * Get the CTA link.
 *
 * @param WP_Post $post            Post object.
 * @param boolean $is_regeneration URL is for alt text regeneration CTA.
 *
 * @return string
 */
function get_cta_link( WP_Post $post, bool $is_regeneration ): string {
	// Return the nonce URL.
	return wp_nonce_url(
		admin_url( 'post.php?post=' . $post->ID . '&action=edit&' . ( $is_regeneration ? 'tp_regenerate_alt_text=true' : 'tp_generate_alt_text=true' ) ),
		'generate_alt_text_' . $post->ID,
		'tp_nonce'
	);
}

/**
 * Modifies the image editor to show the Alt Text Field with Button to generate/regenerate alt text.
 *
 * @param WP_Post $post Post object.
 *
 * @return void
 */
function modify_image_editor( WP_Post $post ): void {
	// Return early if the post is not an image.
	if ( 'attachment' !== $post->post_type || strpos( $post->post_mime_type, 'image' ) === false ) {
		return;
	}

	// Get the existing alt text.
	$is_regeneration = isset( $_GET['tp_regenerate_alt_text'] );
	$is_generation   = isset( $_GET['tp_generate_alt_text'] );
	$valid_request   = wp_verify_nonce( $_GET['tp_nonce'], 'generate_alt_text_' . $post->ID );
	$alt_text        = get_post_meta( $post->ID, '_wp_attachment_image_alt', true );
	$is_empty_alt    = empty( $alt_text );

	// If query args has tp_generate_alt_text, then generate the alt text and save it.
	if ( $is_generation && $valid_request && $is_empty_alt ) {
		$result       = generate_alt_text_for_attachment( $post->ID, true );
		$alt_text     = $result['alt_text'];
		$is_empty_alt = false;
	}

	// If query args has tp_regenerate_alt_text, then regenerate the alt text.
	if ( $is_regeneration && $valid_request ) {
		$result = generate_alt_text_for_attachment( $post->ID, false );

		// On success, update the alt text only on the frontend.
		if ( $result['success'] ) {
			$alt_text = $result['alt_text'];
		}
	}

	// Get the original output as expected from WP Core.
	ob_start();
	edit_form_image_editor( $post );
	$output = ob_get_clean();

	// Add the CTA button alongside the Alt Text Field.
	ob_start();
	?>
	<div style="display: flex; gap: 10px;">
		<textarea class="widefat" name="_wp_attachment_image_alt" id="attachment_alt" aria-describedby="alt-text-description"><?php echo esc_attr( $alt_text ); ?></textarea>

		<?php if ( $is_regeneration ) : ?>
			<input type="hidden" name="alt_text" value="<?php echo esc_attr( $alt_text ); ?>">
			<button type="button" class="button button-success" value="accept">
				<?php esc_attr_e( 'Accept', 'et' ); ?>
			</button>
			<a class="button button-error" href="<?php echo esc_url( admin_url( 'post.php?post=' . $post->ID . '&action=edit' ) ); ?>">
				<?php esc_attr_e( 'Reject', 'et' ); ?>
			</a>
			<a
				type="button"
				class="button button-primary"
				value="regenerate"
				href="<?php echo esc_url( get_cta_link( $post, true ) ); ?>"
			>
				<?php esc_attr_e( 'Regenerate', 'et' ); ?>
			</a>
		<?php else : ?>
			<a
				class="button button-primary"
				href="<?php echo esc_url( get_cta_link( $post, ! $is_empty_alt ) ); ?>"
			>
				<?php $is_empty_alt ? esc_attr_e( 'Generate Alt Text', 'et' ) : esc_attr_e( 'Regenerate Alt Text', 'et' ); ?>
			</a>
		<?php endif; ?>
	</div>
	<?php
	$new_output = ob_get_clean();

	// Modify the original output with the new one.
	$output = preg_replace(
		'/<textarea[^>]*\bname=["\']_wp_attachment_image_alt["\'][^>]*\bid=["\']attachment_alt[^"\']*["\'][^>]*>.*?<\/textarea>/is',
		$new_output,
		$output
	);

	// Output the modified output.
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	echo $output;
}

/**
 * AJAX handler for modifying attachment alt text.
 *
 * @return void
 */
function ajax_modify_attachment_alt_text(): void {
	// Check nonce for security.
	check_ajax_referer( 'modify_alt_text_nonce', 'nonce' );

	// Check user permissions.
	if ( ! current_user_can( 'edit_posts' ) ) {
		wp_send_json_error(
			[
				'message' => __( 'You do not have permission to modify alt text.', 'trav-ai' ),
			],
			403
		);

		// Bail.
		return;
	}

	// Get and validate attachment ID.
	$attachment_id = isset( $_POST['attachment_id'] ) ? absint( $_POST['attachment_id'] ) : 0;

	// Check if the attachment ID is valid.
	if ( empty( $attachment_id ) || ! wp_attachment_is_image( $attachment_id ) ) {
		// Send JSON error.
		wp_send_json_error(
			[
				'message' => __( 'Invalid attachment ID or not an image.', 'trav-ai' ),
			],
			400
		);

		// Bail.
		return;
	}

	// Check if user can edit this specific attachment.
	if ( ! current_user_can( 'edit_post', $attachment_id ) ) {
		// Send JSON error.
		wp_send_json_error(
			[
				'message' => __( 'You do not have permission to edit this attachment.', 'trav-ai' ),
			],
			403
		);

		// Bail.
		return;
	}

	// Get and sanitize alt text.
	$alt_text = isset( $_POST['alt_text'] ) ? sanitize_text_field( $_POST['alt_text'] ) : '';

	// Validate alt text length (WordPress recommends under 125 characters for accessibility).
	if ( strlen( $alt_text ) > 125 ) {
		// Send JSON error.
		wp_send_json_error(
			[
				'message' => __( 'Alt text should be 125 characters or less for optimal accessibility.', 'trav-ai' ),
			],
			400
		);

		// Bail.
		return;
	}

	// Update the alt text.
	$updated = update_post_meta( $attachment_id, '_wp_attachment_image_alt', $alt_text );

	// Check if the alt text was updated.
	if ( false === $updated ) {
		// Send JSON error.
		wp_send_json_error(
			[
				'message' => __( 'Failed to update alt text. Please try again.', 'trav-ai' ),
			],
			500
		);

		// Bail.
		return;
	}

	// Fire action hook after successful alt text modification.
	do_action( 'trav_ai_alt_text_modified', $attachment_id, $alt_text );

	// Return success response.
	wp_send_json_success(
		[
			'message'       => __( 'Alt text updated successfully.', 'trav-ai' ),
			'attachment_id' => $attachment_id,
			'alt_text'      => $alt_text,
		]
	);
}
