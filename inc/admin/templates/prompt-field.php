<?php
/**
 * Prompt Field Template
 *
 * @package trav-ai
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Get necessary functions.
use function TravAI\Admin\get_default_settings;

// Get default settings and current settings.
$default_settings = get_default_settings();
$settings         = get_option( 'travai_settings', $default_settings );

// Ensure settings is an array.
if ( ! is_array( $settings ) ) {
	$settings = $default_settings;
}

// Get current values.
$prompt  = $settings['ai_alt_text_prompt'] ?? $default_settings['ai_alt_text_prompt'];
$enabled = $settings['ai_alt_text_enabled'] ?? false;
?>

<textarea
	id="ai-alt-text-prompt"
	name="travai_settings[ai_alt_text_prompt]"
	rows="4"
	cols="50"
	placeholder="<?php esc_attr_e( 'Enter your AI prompt here...', 'trav-ai' ); ?>"
	<?php disabled( ! $enabled ); ?>
	class="travai-prompt-field"
><?php echo esc_textarea( $prompt ); ?></textarea>
<p class="description">
	<?php esc_html_e( 'This prompt will be sent to the AI service to generate alt text for images.', 'trav-ai' ); ?>
</p>
