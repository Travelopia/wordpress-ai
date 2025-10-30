<?php
/**
 * Prompt field renderer.
 *
 * @package travelopia-wordpress-ai
 */

namespace Travelopia\WordPress_AI\Settings;

use Travelopia\WordPress_AI\Settings;

class AltTextPromptField
{
	/**
	 * Render this field.
	 *
	 * @return void
	 */
	public static function render(): void
	{
		$settings = Settings::get();
		$prompt   = $settings['alt_text_prompt'] ?? '';
		$enabled  = $settings['alt_text_generation'] ?? false;
		?>

		<textarea
			id="ai-alt-text-prompt"
			name="travelopia_wp_ai_settings[alt_text_prompt]"
			rows="4"
			cols="50"
			placeholder="<?php esc_attr_e( 'Enter your AI prompt here...', 'travelopia-wordpress-ai' ); ?>"
			<?php disabled( ! $enabled ); ?>
			class="travelopia-wp-ai-prompt-field"
		><?php echo esc_textarea( $prompt ); ?></textarea>
		<p class="description">
			<?php esc_html_e( 'This prompt will be sent to the AI service to generate alt text for images.', 'travelopia-wordpress-ai' ); ?>
		</p>
		<?php
	}
}
