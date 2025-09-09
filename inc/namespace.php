<?php
/**
 * Namespace functions.
 *
 * @package travelopia-ai
 */

namespace TravelopiaAI;

// Include AI class.
require_once __DIR__ . '/class-ai.php';

/**
 * Bootstrap plugin.
 *
 * @return void
 */
function bootstrap(): void {
	// Filters.
	add_filter( 'media_row_actions', __NAMESPACE__ . '\\media_row_actions', 10, 2 );

	// Actions.
	add_action( 'add_meta_boxes', __NAMESPACE__ . '\\remove_image_editor' );
	add_action( 'edit_form_after_title', __NAMESPACE__ . '\\modify_image_editor', 11 );
}

/**
 * Adds Quick Action CTA for generating Alt Text in Media Library Admin Page.
 *
 * @param mixed[] $actions Actions.
 * @param WP_Post $post Post object.
 *
 * @return mixed[]
 */
function media_row_actions( $actions, $post ) {
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
			admin_url( 'post.php?post=' . $post->ID . '&action=edit&tp_generate_alt_text=true' ),
			'generate_alt_text_' . $post->ID,
			'tp_nonce'
		),
		empty( $alt_text ) ? __( 'Generate Alt Text', 'et' ) : __( 'Regenerate Alt Text', 'et' )
	);

	return $actions;
}

/**
 * Removes the editor options for the images. Its modified output is shown via modify_image_editor function.
 *
 * @return void
 */
function remove_image_editor(): void {
	remove_action( 'edit_form_after_title', 'edit_form_image_editor' );
}

/**
 * Modifies the image editor to show the Alt Text Field with Button to generate/regenerate alt text.
 *
 * @param WP_Post $post Post object.
 *
 * @return void
 */
function modify_image_editor( $post ): void {
	// Return early if the post is not an image.
	if ( 'attachment' !== $post->post_type || strpos( $post->post_mime_type, 'image' ) === false ) {
		return;
	}

	// If query args has tp_generate_alt_text, then generate the alt text.
	if ( isset( $_GET['tp_generate_alt_text'] ) && wp_verify_nonce( $_GET['tp_nonce'], 'generate_alt_text_' . $post->ID ) ) {
		$ai       = new \TravelopiaAI\AI();
		$alt_text = $ai->generate_alt_text( wp_get_attachment_image_src( $post->ID, 'full' )[0] );
		$ai->update_attachment_alt_text( $post->ID, $alt_text );
	}

	// Get the original output as expected from WP Core.
	ob_start();
	edit_form_image_editor( $post );
	$output = ob_get_clean();

	// Get the alt text from the post meta.
	$alt_text = get_post_meta( $post->ID, '_wp_attachment_image_alt', true );

	// Add the CTA button alongside the Alt Text Field.
	ob_start();

	?>
	<div style="display: flex; gap: 10px;">
		<textarea class="widefat" name="_wp_attachment_image_alt" id="attachment_alt" aria-describedby="alt-text-description"><?php echo esc_attr( $alt_text ); ?></textarea>
		<button type="button" class="button button-primary"><?php empty( $alt_text ) ? esc_attr_e( 'Generate Alt Text', 'et' ) : esc_attr_e( 'Regenerate Alt Text', 'et' ); ?></button>
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
