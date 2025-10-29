<?php
/**
 * Prompt Field Template.
 *
 * @package travelopia-wordpress-ai
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Get necessary functions.
use function Travelopia\WordPress_AI\Admin\get_default_settings;

// Get default settings and current settings.
$default_settings = get_default_settings();
$settings         = get_option( 'travelopia_wp_ai_settings', $default_settings );

// Ensure settings is an array.
if ( ! is_array( $settings ) ) {
	$settings = $default_settings;
}

// Get current values.
$prompt  = $settings['alt_text_prompt'] ?? $default_settings['alt_text_prompt'];
$enabled = $settings['alt_text_generation'] ?? false;
?>

<textarea
	id="ai-alt-text-prompt"
	name="travelopia_wp_ai_settings[alt_text_prompt]"
	rows="4"
	cols="50"
	placeholder="<?php esc_attr_e( 'Enter your AI prompt here...', 'travelopia-wp-ai' ); ?>"
	<?php disabled( ! $enabled ); ?>
	class="travelopia-wp-ai-prompt-field"
><?php echo esc_textarea( $prompt ); ?></textarea>
<p class="description">
	<?php esc_html_e( 'This prompt will be sent to the AI service to generate alt text for images.', 'travelopia-wp-ai' ); ?>
</p>
