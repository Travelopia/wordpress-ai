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

// Register AI adapters.
Adapter::register( 'openai', Providers\OpenAI::class );
Adapter::register( 'bedrock', Providers\Bedrock::class );

/**
 * Filter the active AI provider.
 *
 * @param string $provider The provider name. Default 'bedrock'.
 */
$provider = (string) apply_filters( 'travelopia_wordpress_ai_provider', 'bedrock' );
Adapter::set( $provider );

add_action( 'plugins_loaded', [ Settings::class, 'bootstrap' ] );
add_action( 'plugins_loaded', [ AltText::class, 'bootstrap' ] );
