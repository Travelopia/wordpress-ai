<?php
/**
 * Plugin Name: Travelopia WordPress AI
 * Description: WordPress Plugin for AI-powered alt text generation
 * Author: Travelopia Team
 * Author URI: https://www.travelopia.com
 * Version: 0.1.0.
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

add_action( 'plugins_loaded', [ Settings::class, 'bootstrap' ] );
add_action( 'plugins_loaded', [ AltText::class, 'bootstrap' ] );
