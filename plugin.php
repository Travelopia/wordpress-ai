<?php
/**
 * Plugin Name: TravAI
 * Description: WordPress Plugin for AI-powered alt text generation.
 * Author: Travelopia Team
 * Author URI: https://www.travelopia.com
 * Version: 0.1.0
 *
 * @package trav-ai
 */

namespace TravAI;

// Composer autoload.
if ( file_exists( __DIR__ . '/vendor/autoload.php' ) ) {
    require_once __DIR__ . '/vendor/autoload.php';
}

require_once __DIR__ . '/inc/namespace.php';

// Kick it off.
bootstrap();


