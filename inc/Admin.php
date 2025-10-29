<?php
/**
 * Admin module.
 *
 * @package travelopia-wordpress-ai
 */

namespace Travelopia\WordPress_AI;

/**
 * Admin class.
 */
class Admin
{
	/**
	 * Bootstrap admin functionality.
	 *
	 * @return void
	 */
	public static function bootstrap(): void
	{
		// Hooks.
		add_action( 'admin_enqueue_scripts', [ __CLASS__, 'enqueue_admin_styles' ] );
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
