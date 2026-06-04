<?php
/**
 * Plugin Name: Travelopia WordPress AI
 * Description: AI functionality for WordPress
 * Author: Travelopia Team
 * Author URI: https://www.travelopia.com
 * Version: 0.1.0
 *
 * @package travelopia-wordpress-ai
 */

namespace Travelopia\WordPress_AI;

const REST_API_NAMESPACE = 'travelopia-wp-ai/v1';

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( file_exists( __DIR__ . '/vendor/autoload.php' ) ) {
	require_once __DIR__ . '/vendor/autoload.php';
}

/*
 * Register the bundled AI adapters and apply the provider filter.
 *
 * Deferred from plugin file load to `plugins_loaded` so consumers wiring
 * the composer autoloader through `wp-config.php` or an MU plugin have had
 * a chance to load it. The `class_exists` guard turns a missing autoloader
 * into a graceful no-op rather than a fatal during WordPress boot — matters
 * for CI environments (e.g. the database-dump workflow) that build a
 * throwaway `wp-config.php` without the autoload line.
 *
 * Hooked at priority 5 so the adapter registry is populated before any
 * `plugins_loaded` consumer at the default priority might probe
 * `Adapter::get()`.
 */
function register_default_adapters(): void
{
	if ( ! class_exists( __NAMESPACE__ . '\\Adapter' ) ) {
		return;
	}

	/*
	 * The adapters build on the PHP AI Client framework that WordPress 7.0+
	 * ships in core (`wp-includes/php-ai-client`). On older WordPress the
	 * framework is absent, so there is nothing to register — no-op gracefully
	 * rather than fataling. This plugin targets WordPress 7.0+.
	 */
	if ( ! class_exists( 'WordPress\\AiClient\\AiClient' ) ) {
		return;
	}

	Adapter::register( 'openai', Adapters\OpenAI::class );
	Adapter::register( 'bedrock', Adapters\Bedrock::class );

	/**
	 * Filter the active AI provider.
	 *
	 * @param string $provider The provider name. Default 'bedrock'.
	 */
	$provider = (string) apply_filters( 'travelopia_wordpress_ai_provider', 'bedrock' );
	Adapter::set( $provider );
}

/*
 * Guarded bootstraps for the Settings and AltText modules.
 *
 * Same rationale as `register_default_adapters`: when the composer
 * autoloader is not wired into `wp-config.php`, an unguarded callable
 * such as `[ Settings::class, 'bootstrap' ]` fatals at
 * `wp-includes/class-wp-hook.php` when WP-Hook invokes it — the class is
 * referenced as a string at registration time (no autoload) but
 * `call_user_func_array` triggers the autoload when the hook fires.
 *
 * Wrapping each bootstrap in a named function lets us `class_exists`-
 * guard before the invocation, turning a missing autoloader into a
 * graceful no-op rather than a fatal during WordPress boot.
 */
function bootstrap_settings(): void
{
	if ( ! class_exists( __NAMESPACE__ . '\\Settings' ) ) {
		return;
	}

	Settings::bootstrap();
}

function bootstrap_alt_text(): void
{
	if ( ! class_exists( __NAMESPACE__ . '\\AltText' ) ) {
		return;
	}

	AltText::bootstrap();
}

add_action( 'plugins_loaded', __NAMESPACE__ . '\\register_default_adapters', 5 );
add_action( 'plugins_loaded', __NAMESPACE__ . '\\bootstrap_settings' );
add_action( 'plugins_loaded', __NAMESPACE__ . '\\bootstrap_alt_text' );
