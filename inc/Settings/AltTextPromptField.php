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
	 * Field name.
	 *
	 * @var string
	 */
	public const FIELD_NAME = 'alt_text_prompt';

	/**
	 * Get default value for this field.
	 *
	 * @return string Default value.
	 */
	public static function get_default(): string
	{
		return __( 'Describe this image in a concise, informative way for alt text. Focus on the main subject and important details that would help someone understand what is in the image.', 'travelopia-wordpress-ai' );
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
			__( 'AI Alt Text Prompt', 'travelopia-wordpress-ai' ),
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
	 * @return string Sanitized value.
	 */
	public static function sanitize( mixed $value ): string
	{
		$sanitized = sanitize_textarea_field( (string) ( $value ?? '' ) );

		if ( empty( trim( $sanitized ) ) ) {
			return self::get_default();
		}

		return $sanitized;
	}

	/**
	 * Render this field.
	 *
	 * @return void
	 */
	public static function render(): void
	{
		$settings = Settings::get();
		$prompt   = (string) ( $settings[self::FIELD_NAME] ?? '' );
		$enabled  = $settings[EnableAltTextGenerationField::FIELD_NAME] ?? false;
		?>

		<textarea
			id="ai-alt-text-prompt"
			name="<?php echo esc_attr( Settings::OPTION_NAME ); ?>[<?php echo esc_attr( self::FIELD_NAME ); ?>]"
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
