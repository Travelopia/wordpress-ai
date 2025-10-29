<?php
/**
 * Plugin Name: Travelopia WordPress AI
 * Description: WordPress Plugin for AI-powered alt text generation
 * Author: Travelopia Team
 * Author URI: https://www.travelopia.com
 * Version: 0.1.0
 *
 * @package travelopia-wordpress-ai
 */

namespace Travelopia\WordPress_AI;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Composer autoload.
if ( file_exists( __DIR__ . '/vendor/autoload.php' ) ) {
	require_once __DIR__ . '/vendor/autoload.php';
}

// Kick it off.
add_action( 'plugins_loaded', [ WordPressAI::class, 'bootstrap' ] );
add_action( 'plugins_loaded', [ Admin::class, 'bootstrap' ] );
add_action( 'plugins_loaded', [ AltText::class, 'bootstrap' ] );

// Plugin activation hook.
register_activation_hook( __FILE__, [ WordPressAI::class, 'activate_plugin' ] );
