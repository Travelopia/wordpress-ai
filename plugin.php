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

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( file_exists( __DIR__ . '/vendor/autoload.php' ) ) {
	require_once __DIR__ . '/vendor/autoload.php';
}

add_action( 'plugins_loaded', [ WordPressAI::class, 'bootstrap' ] );
add_action( 'plugins_loaded', [ Admin::class, 'bootstrap' ] );
add_action( 'plugins_loaded', [ AltText::class, 'bootstrap' ] );
