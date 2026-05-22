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

add_action( 'plugins_loaded', __NAMESPACE__ . '\\register_default_adapters', 5 );
add_action( 'plugins_loaded', [ Settings::class, 'bootstrap' ] );
add_action( 'plugins_loaded', [ AltText::class, 'bootstrap' ] );
