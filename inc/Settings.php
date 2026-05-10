<?php
/**
 * Settings module.
 *
 * @package travelopia-wordpress-ai
 */

namespace Travelopia\WordPress_AI;

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
	 * Alt text generation field name.
	 *
	 * @var string
	 */
	public const FIELD_ALT_TEXT_GENERATION = 'alt_text_generation';

	/**
	 * Alt text prompt field name.
	 *
	 * @var string
	 */
	public const FIELD_ALT_TEXT_PROMPT = 'alt_text_prompt';

	/**
	 * Default alt text prompt.
	 *
	 * @var string
	 */
	public const DEFAULT_ALT_TEXT_PROMPT = 'Describe this image in a concise, informative way for alt text. Focus on the main subject and important details that would help someone understand what is in the image.';

	/**
	 * Cached settings.
	 *
	 * @var array<string, mixed>|null
	 */
	private static ?array $settings = null;

	/**
	 * Bootstrap settings functionality.
	 *
	 * @return void
	 */
	public static function bootstrap(): void
	{
		add_action( 'init', [ __CLASS__, 'register_rest_setting' ] );
		add_action( 'admin_menu', [ __CLASS__, 'setup_settings' ] );
		add_action( 'admin_enqueue_scripts', [ __CLASS__, 'enqueue_admin_styles' ] );
		add_action( 'add_option_' . self::OPTION_NAME, [ __CLASS__, 'clear_cache' ] );
		add_action( 'update_option_' . self::OPTION_NAME, [ __CLASS__, 'clear_cache' ] );
		add_action( 'admin_notices', [ __CLASS__, 'display_build_missing_notice' ] );
		add_filter(
			'plugin_action_links_' . plugin_basename( dirname( __DIR__ ) . '/plugin.php' ),
			[ __CLASS__, 'add_settings_link' ],
		);
	}

	/**
	 * Clear the static settings cache.
	 *
	 * Hooked to the option's add/update actions so subsequent reads in the
	 * same request reflect the new value without `get( true )`.
	 *
	 * @return void
	 */
	public static function clear_cache(): void
	{
		self::$settings = null;
	}

	/**
	 * Register setting with REST API schema.
	 *
	 * @return void
	 */
	public static function register_rest_setting(): void
	{
		register_setting(
			self::SETTINGS_GROUP,
			self::OPTION_NAME,
			[
				'type'              => 'object',
				'sanitize_callback' => [ __CLASS__, 'sanitize_settings' ],
				'default'           => self::get_default_settings(),
				'show_in_rest'      => [
					'schema' => [
						'type'       => 'object',
						'properties' => [
							self::FIELD_ALT_TEXT_GENERATION => [
								'type' => 'boolean',
							],
							self::FIELD_ALT_TEXT_PROMPT => [
								'type' => 'string',
							],
						],
					],
				],
			],
		);
	}

	/**
	 * Get the required capability to manage settings.
	 *
	 * @return string The capability required.
	 */
	public static function get_capability(): string
	{
		/**
		 * Filter the capability required to manage settings.
		 *
		 * @param string $capability The capability required. Default 'manage_options'.
		 */
		$capability = apply_filters( 'travelopia_wordpress_ai_settings_capability', 'manage_options' );
		return is_string( $capability ) ? $capability : 'manage_options';
	}

	/**
	 * Get default settings.
	 *
	 * @return array<string,mixed> Default settings array.
	 */
	public static function get_default_settings(): array
	{
		return [
			self::FIELD_ALT_TEXT_GENERATION => false,
			self::FIELD_ALT_TEXT_PROMPT     => self::DEFAULT_ALT_TEXT_PROMPT,
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
		if ( false === $force && null !== self::$settings ) {
			return self::$settings;
		}

		$default_settings = self::get_default_settings();
		$saved_settings   = get_option( self::OPTION_NAME, [] );
		$saved_settings   = is_array( $saved_settings ) ? $saved_settings : [];

		// Merge saved settings into defaults, ensuring string keys are preserved.
		self::$settings = $default_settings;

		foreach ( $saved_settings as $key => $value ) {
			if ( is_string( $key ) && array_key_exists( $key, $default_settings ) ) {
				self::$settings[ $key ] = $value;
			}
		}

		return self::$settings;
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

		$alt_text_prompt = sanitize_textarea_field( (string) ( $input[ self::FIELD_ALT_TEXT_PROMPT ] ?? '' ) );

		return [
			self::FIELD_ALT_TEXT_GENERATION => ! empty( $input[ self::FIELD_ALT_TEXT_GENERATION ] ),
			self::FIELD_ALT_TEXT_PROMPT     => empty( trim( $alt_text_prompt ) ) ? self::DEFAULT_ALT_TEXT_PROMPT : $alt_text_prompt,
		];
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
			self::get_capability(),
			self::PAGE_SLUG,
			[ Page::class, 'render' ],
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
				esc_url( admin_url( 'options-general.php?page=' . self::PAGE_SLUG ) ),
				esc_html__( 'Settings', 'travelopia-wordpress-ai' ),
			),
		);

		return $links;
	}

	/**
	 * Get the path to the compiled settings asset PHP file.
	 *
	 * The path is filterable so tests can simulate a missing build.
	 *
	 * @return string
	 */
	private static function get_settings_asset_file_path(): string
	{
		$default = dirname( __DIR__ ) . '/dist/settings.asset.php';

		/**
		 * Filter the path used to detect whether the settings build is present.
		 *
		 * @param string $path Absolute path to the settings asset PHP file.
		 */
		$filtered = apply_filters( 'travelopia_wordpress_ai_settings_asset_file', $default );

		return is_string( $filtered ) ? $filtered : $default;
	}

	/**
	 * Whether the compiled settings asset is missing on disk.
	 *
	 * @return bool
	 */
	public static function is_build_missing(): bool
	{
		return ! file_exists( self::get_settings_asset_file_path() );
	}

	/**
	 * Render an admin notice on the settings page when the build is missing.
	 *
	 * @return void
	 */
	public static function display_build_missing_notice(): void
	{
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;

		if ( null === $screen || 'settings_page_' . self::PAGE_SLUG !== $screen->id ) {
			return;
		}

		if ( ! self::is_build_missing() ) {
			return;
		}

		wp_admin_notice(
			esc_html__(
				'Travelopia WordPress AI: build assets are missing. Run npm run build to compile the settings UI.',
				'travelopia-wordpress-ai',
			),
			[
				'type'           => 'warning',
				'paragraph_wrap' => true,
			],
		);
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

		$plugin_dir_path = dirname( __DIR__ );
		$asset_file_path = $plugin_dir_path . '/dist/settings.asset.php';

		if ( ! file_exists( $asset_file_path ) ) {
			return;
		}

		$plugin_dir_url = plugin_dir_url( $plugin_dir_path . '/plugin.php' );
		$asset_file     = include $asset_file_path;
		$dependencies   = is_array( $asset_file ) && isset( $asset_file['dependencies'] ) && is_array( $asset_file['dependencies'] ) ? array_map( static fn ( mixed $dep ): string => (string) $dep, $asset_file['dependencies'] ) : [];
		$version        = is_array( $asset_file ) && isset( $asset_file['version'] ) && is_string( $asset_file['version'] ) ? $asset_file['version'] : '1.0.0';

		wp_enqueue_style(
			'travelopia-wp-ai-settings',
			$plugin_dir_url . 'dist/settings.css',
			[ 'wp-components' ],
			$version,
		);

		wp_enqueue_script(
			'travelopia-wp-ai-settings',
			$plugin_dir_url . 'dist/settings.js',
			$dependencies,
			$version,
			true,
		);

		wp_add_inline_script(
			'travelopia-wp-ai-settings',
			'window.travelopiaWpAiSettings = ' . wp_json_encode( self::get() ) . ';',
			'before',
		);
	}
}
