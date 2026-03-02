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

use Aysnc\WordPress\PhpAiClientBedrock\AwsBedrockProvider;
use WordPress\AiClient\AiClient;

const REST_API_NAMESPACE = 'travelopia-wp-ai/v1';

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( file_exists( __DIR__ . '/vendor/autoload.php' ) ) {
	require_once __DIR__ . '/vendor/autoload.php';
}

// Register the AWS Bedrock provider with AiClient (not built-in, comes from aysnc/wordpress-php-ai-client-bedrock).
AiClient::defaultRegistry()->registerProvider( AwsBedrockProvider::class );

// Register AI adapters.
Adapter::register( 'openai', Adapters\OpenAI::class );
Adapter::register( 'bedrock', Adapters\Bedrock::class );

/**
 * Filter the active AI provider.
 *
 * @param string $provider The provider name. Default 'bedrock'.
 */
$provider = (string) apply_filters( 'travelopia_wordpress_ai_provider', 'bedrock' );
Adapter::set( $provider );

add_action( 'plugins_loaded', [ Settings::class, 'bootstrap' ] );
add_action( 'plugins_loaded', [ AltText::class, 'bootstrap' ] );
