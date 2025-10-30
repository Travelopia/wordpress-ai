<?php
/**
 * Settings module.
 *
 * @package travelopia-wordpress-ai
 */

namespace Travelopia\WordPress_AI;

use Travelopia\WordPress_AI\Settings\AltTextPromptField;
use Travelopia\WordPress_AI\Settings\EnableAltTextGenerationField;
use Travelopia\WordPress_AI\Settings\Page;

/**
 * Settings class.
 */
class Settings
{
	/**
	 * Settings page slug.
	 *
	 * @var string
	 */
	public const PAGE_SLUG = 'travelopia-wp-ai-settings';

	/**
	 * Settings option name.
	 *
	 * @var string
	 */
	public const OPTION_NAME = 'travelopia_wp_ai_settings';

	/**
	 * Settings group name.
	 *
	 * @var string
	 */
	public const SETTINGS_GROUP = 'travelopia_wp_ai_settings_group';

	/**
	 * Main settings section ID.
	 *
	 * @var string
	 */
	public const SECTION_ID = 'travelopia_wp_ai_main_section';

	/**
	 * Settings fields.
	 *
	 * @var array<class-string>
	 */
	private const FIELDS = [
		EnableAltTextGenerationField::class,
		AltTextPromptField::class,
	];

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
		$defaults = [];

		foreach ( self::FIELDS as $field_class ) {
			$defaults[ $field_class::FIELD_NAME ] = $field_class::get_default();
		}

		return $defaults;
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
		$settings         = get_option( self::OPTION_NAME, $default_settings );

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
		if ( null === $input ) {
			return self::get_default_settings();
		}

		$sanitized = [];

		foreach ( self::FIELDS as $field_class ) {
			$field_name               = $field_class::FIELD_NAME;
			$sanitized[ $field_name ] = $field_class::sanitize( $input[ $field_name ] ?? null );
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
			self::PAGE_SLUG,
			[ Page::class, 'render' ],
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
			self::SETTINGS_GROUP,
			self::OPTION_NAME,
			[
				'sanitize_callback' => [ __CLASS__, 'sanitize_settings' ],
				'default'           => self::get_default_settings(),
			],
		);

		Page::register();

		foreach ( self::FIELDS as $field_class ) {
			$field_class::register();
		}
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
				esc_url( admin_url( 'options-general.php?page=' . self::PAGE_SLUG ) ),
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
		if ( 'settings_page_' . self::PAGE_SLUG !== $hook_suffix ) {
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
