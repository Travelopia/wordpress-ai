<?php
/**
 * Plugin Name: Travelopia AI
 * Description: WordPress Plugin for WordPress AI integration.
 * Author: Travelopia Team
 * Author URI: https://www.travelopia.com
 * Version: 0.1.0
 *
 * @package travelopia-ai
 */

namespace TravelopiaAI;

// Composer autoload.
if ( file_exists( __DIR__ . '/vendor/autoload.php' ) ) {
    require_once __DIR__ . '/vendor/autoload.php';
}

require_once __DIR__ . '/inc/namespace.php';

// Kick it off.
bootstrap();


