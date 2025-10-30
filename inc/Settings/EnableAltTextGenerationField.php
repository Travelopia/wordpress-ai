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
	 * Field name.
	 *
	 * @var string
	 */
	public const FIELD_NAME = 'alt_text_generation';

	/**
	 * Get default value for this field.
	 *
	 * @return bool Default value.
	 */
	public static function get_default(): bool
	{
		return false;
	}

	/**
	 * Register this settings field.
	 *
	 * @return void
	 */
	public static function register(): void
	{
		add_settings_field(
			self::FIELD_NAME,
			__( 'Enable AI Alt Text Generation', 'travelopia-wordpress-ai' ),
			[ __CLASS__, 'render' ],
			Settings::PAGE_SLUG,
			Settings::SECTION_ID,
		);
	}

	/**
	 * Sanitize field value.
	 *
	 * @param mixed $value The field value from input.
	 *
	 * @return bool Sanitized value.
	 */
	public static function sanitize( mixed $value ): bool
	{
		return ! empty( $value );
	}

	/**
	 * Render this field.
	 *
	 * @return void
	 */
	public static function render(): void
	{
		$settings = Settings::get();
		$enabled  = $settings[self::FIELD_NAME] ?? false;
		?>

		<label for="ai-alt-text-enabled">
			<input
				type="checkbox"
				id="ai-alt-text-enabled"
				name="<?php echo esc_attr( Settings::OPTION_NAME ); ?>[<?php echo esc_attr( self::FIELD_NAME ); ?>]"
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
