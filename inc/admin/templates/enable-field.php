<?php
/**
 * Enable Field Template
 *
 * @package trav-ai
 */

// Get necessary functions.
use function TravAI\Admin\get_default_settings;

// Get default settings and current settings.
$default_settings = get_default_settings();
$settings         = get_option( 'travai_settings', $default_settings );

// Ensure settings is an array.
if ( ! is_array( $settings ) ) {
	$settings = $default_settings;
}

// Get current value.
$enabled = $settings['ai_alt_text_enabled'] ?? false;
?>

<label for="ai_alt_text_enabled">
	<input
		type="checkbox"
		id="ai-alt-text-enabled"
		name="travai_settings[ai_alt_text_enabled]"
		value="1"
		<?php checked( $enabled ); ?>
	/>
	<?php esc_html_e( 'Enable AI-powered alt text generation', 'trav-ai' ); ?>
</label>
<p class="description">
	<?php esc_html_e( 'When enabled, AI will be used to generate alt text for images.', 'trav-ai' ); ?>
</p>
