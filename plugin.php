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

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Load composer autoloader.
if ( file_exists( __DIR__ . '/vendor/autoload.php' ) ) {
	require_once __DIR__ . '/vendor/autoload.php';
}

// Register AI adapters.
Adapter::register( 'openai', Adapters\OpenAI::class );
Adapter::register( 'bedrock', Adapters\Bedrock::class );

/**
 * Filter the active AI provider.
 *
 * @param string $provider The provider name. Default 'bedrock'.
 */
// Default provider.
Adapter::set( 'bedrock' );

// Set provider once hooks/themes are loaded.
add_action(
	'init',
	static function (): void {
		$filtered_provider = apply_filters( 'travelopia_wordpress_ai_provider', 'bedrock' );
		$provider          = is_string( $filtered_provider ) ? $filtered_provider : 'bedrock';
		Adapter::set( $provider );
	},
	1,
);

// Bootstrap plugin modules.
add_action( 'plugins_loaded', [ Settings::class, 'bootstrap' ] );
add_action( 'plugins_loaded', [ AltText::class, 'bootstrap' ] );
