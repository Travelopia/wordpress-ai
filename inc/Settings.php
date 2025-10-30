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
		add_action( 'admin_enqueue_scripts', [ __CLASS__, 'enqueue_admin_styles' ] );
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
			'alt_text_prompt'     => __( 'Describe this image in a concise, informative way for alt text. Focus on the main subject and important details that would help someone understand what is in the image.', 'travelopia-wordpress-ai' ),
		];
	}

	/**
	 * Get all settings.
	 *
	 * @param bool $force Force getting settings from database.
	 *
	 * @return array<string,mixed> All settings.
	 */
	public static function get( bool $force = false ): array
	{
		$settings = null;

		if ( false === $force && is_array( $settings ) ) {
			return $settings;
		}

		$default_settings = self::get_default_settings();
		$settings         = get_option( 'travelopia_wp_ai_settings', $default_settings );

		if ( ! is_array( $settings ) ) {
			$settings = $default_settings;
		}

		return $settings;
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
		$settings = self::get();

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
			__( 'Travelopia WordPress AI Settings', 'travelopia-wordpress-ai' ),
			__( 'WordPress AI', 'travelopia-wordpress-ai' ),
			'manage_options',
			'travelopia-wp-ai-settings',
			[ Settings\Page::class, 'render' ],
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
			__( 'AI Alt Text Generation Settings', 'travelopia-wordpress-ai' ),
			fn () => esc_html_e( 'Configure the AI-powered alt text generation settings for your WordPress site.', 'travelopia-wordpress-ai' ),
			'travelopia-wp-ai-settings',
		);

		add_settings_field(
			'alt_text_generation',
			__( 'Enable AI Alt Text Generation', 'travelopia-wordpress-ai' ),
			[ Settings\EnableAltTextGenerationField::class, 'render' ],
			'travelopia-wp-ai-settings',
			'travelopia_wp_ai_main_section',
		);

		add_settings_field(
			'alt_text_prompt',
			__( 'AI Alt Text Prompt', 'travelopia-wordpress-ai' ),
			[ Settings\AltTextPromptField::class, 'render' ],
			'travelopia-wp-ai-settings',
			'travelopia_wp_ai_main_section',
		);
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
				esc_html__( 'Settings', 'travelopia-wordpress-ai' ),
			),
		);

		return $links;
	}

	/**
	 * Enqueue admin styles for WordPress AI settings page.
	 *
	 * @param string $hook_suffix The current admin page hook suffix.
	 *
	 * @return void
	 */
	public static function enqueue_admin_styles( string $hook_suffix = '' ): void
	{
		if ( 'settings_page_travelopia-wp-ai-settings' !== $hook_suffix ) {
			return;
		}

		$plugin_dir_url = plugin_dir_url( dirname( __DIR__ ) );

		wp_enqueue_style(
			'travelopia-wp-ai-admin',
			$plugin_dir_url . 'dist/admin.css',
			[],
			'1.0.0',
		);

		wp_enqueue_script(
			'travelopia-wp-ai-admin',
			$plugin_dir_url . 'dist/admin.js',
			[],
			'1.0.0',
			true,
		);
	}
}
