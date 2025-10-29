<?php
/**
 * Settings module.
 *
 * @package travelopia-wordpress-ai
 */

namespace Travelopia\WordPress_AI;

/**
 * Settings class.
 */
class Settings
{
	/**
	 * Bootstrap settings functionality.
	 *
	 * @return void
	 */
	public static function bootstrap(): void
	{
		add_action( 'admin_menu', [ __CLASS__, 'setup_settings' ] );
		add_action( 'admin_init', [ __CLASS__, 'initialize_settings' ] );
		add_filter(
			'plugin_action_links_' . plugin_basename( dirname( __DIR__ ) . '/plugin.php' ),
			[ __CLASS__, 'add_settings_link' ],
		);
	}

	/**
	 * Get default settings.
	 *
	 * @return array<string,mixed> Default settings array.
	 */
	public static function get_default_settings(): array
	{
		return [
			'alt_text_generation' => false,
			'alt_text_prompt'     => AltText::get_default_alt_text_prompt(),
		];
	}

	/**
	 * Get AI setting value.
	 *
	 * @param string $key           Setting key.
	 * @param mixed  $default_value Default value if setting not found.
	 *
	 * @return mixed Setting value or default.
	 */
	public static function get_setting( string $key = '', mixed $default_value = null ): mixed
	{
		$default_settings = self::get_default_settings();
		$settings         = get_option( 'travelopia_wp_ai_settings', $default_settings );

		if ( ! is_array( $settings ) ) {
			$settings = $default_settings;
		}

		return $settings[ $key ] ?? $default_value;
	}

	/**
	 * Sanitize settings input.
	 *
	 * @param array<string,mixed>|null $input Raw input from the form.
	 *
	 * @return array<string,mixed> Sanitized settings.
	 */
	public static function sanitize_settings( ?array $input = null ): array
	{
		$sanitized = [];

		if ( null === $input ) {
			return self::get_default_settings();
		}

		$sanitized['alt_text_generation'] = ! empty( $input['alt_text_generation'] );
		$sanitized['alt_text_prompt']     = sanitize_textarea_field( strval( $input['alt_text_prompt'] ?? '' ) );

		if ( empty( trim( $sanitized['alt_text_prompt'] ) ) ) {
			$defaults                     = self::get_default_settings();
			$sanitized['alt_text_prompt'] = $defaults['alt_text_prompt'];
		}

		return $sanitized;
	}

	/**
	 * Setup admin settings menu.
	 *
	 * @return void
	 */
	public static function setup_settings(): void
	{
		add_options_page(
			__( 'Travelopia WordPress AI Settings', 'travelopia-wp-ai' ),
			__( 'WordPress AI', 'travelopia-wp-ai' ),
			'manage_options',
			'travelopia-wp-ai-settings',
			[ __CLASS__, 'render_settings_page' ],
		);
	}

	/**
	 * Initialize and register settings.
	 *
	 * @return void
	 */
	public static function initialize_settings(): void
	{
		register_setting(
			'travelopia_wp_ai_settings_group',
			'travelopia_wp_ai_settings',
			[
				'sanitize_callback' => [ __CLASS__, 'sanitize_settings' ],
				'default'           => self::get_default_settings(),
			],
		);

		add_settings_section(
			'travelopia_wp_ai_main_section',
			__( 'AI Alt Text Generation Settings', 'travelopia-wp-ai' ),
			[ __CLASS__, 'render_section_description' ],
			'travelopia-wp-ai-settings',
		);

		add_settings_field(
			'alt_text_generation',
			__( 'Enable AI Alt Text Generation', 'travelopia-wp-ai' ),
			[ __CLASS__, 'render_enable_field' ],
			'travelopia-wp-ai-settings',
			'travelopia_wp_ai_main_section',
		);

		add_settings_field(
			'alt_text_prompt',
			__( 'AI Alt Text Prompt', 'travelopia-wp-ai' ),
			[ __CLASS__, 'render_prompt_field' ],
			'travelopia-wp-ai-settings',
			'travelopia_wp_ai_main_section',
		);
	}

	/**
	 * Render the settings page.
	 *
	 * @return void
	 */
	public static function render_settings_page(): void
	{
		include __DIR__ . '/admin/templates/settings-page.php';
	}

	/**
	 * Render section description.
	 *
	 * @return void
	 */
	public static function render_section_description(): void
	{
		esc_html_e( 'Configure the AI-powered alt text generation settings for your WordPress site.', 'travelopia-wp-ai' );
	}

	/**
	 * Render the enable field.
	 *
	 * @return void
	 */
	public static function render_enable_field(): void
	{
		include __DIR__ . '/admin/templates/enable-field.php';
	}

	/**
	 * Render the prompt field.
	 *
	 * @return void
	 */
	public static function render_prompt_field(): void
	{
		include __DIR__ . '/admin/templates/prompt-field.php';
	}

	/**
	 * Add settings link to plugin actions.
	 *
	 * @param array<string> $links Existing plugin action links.
	 *
	 * @return array<string> Modified plugin action links.
	 */
	public static function add_settings_link( array $links = [] ): array
	{
		array_unshift(
			$links,
			sprintf(
				'<a href="%s">%s</a>',
				esc_url( admin_url( 'options-general.php?page=travelopia-wp-ai-settings' ) ),
				esc_html__( 'Settings', 'travelopia-wp-ai' ),
			),
		);

		return $links;
	}
}
