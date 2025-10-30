<?php
/**
 * Enable field renderer.
 *
 * @package travelopia-wordpress-ai
 */

namespace Travelopia\WordPress_AI\Settings;

use Travelopia\WordPress_AI\Settings;

class EnableAltTextGenerationField
{
	/**
	 * Render this field.
	 *
	 * @return void
	 */
	public static function render(): void
	{
		$settings = Settings::get();
		$enabled  = $settings['alt_text_generation'] ?? false;
		?>

		<label for="ai-alt-text-enabled">
			<input
				type="checkbox"
				id="ai-alt-text-enabled"
				name="travelopia_wp_ai_settings[alt_text_generation]"
				value="1"
				<?php checked( $enabled ); ?>
			/>
			<?php esc_html_e( 'Enable AI-powered alt text generation', 'travelopia-wordpress-ai' ); ?>
		</label>
		<p class="description">
			<?php esc_html_e( 'When enabled, AI will be used to generate alt text for images.', 'travelopia-wordpress-ai' ); ?>
		</p>
		<?php
	}
}
