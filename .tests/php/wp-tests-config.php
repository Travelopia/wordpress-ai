<?php
/**
 * Test Config.
 *
 * @package wordpress-ai
 */

define( 'WP_DEBUG', true );
define( 'WP_DEBUG_DISPLAY', true );
define( 'WP_TESTS_DOMAIN', 'localhost' );
define( 'WP_TESTS_EMAIL', 'admin@example.org' );
define( 'WP_TESTS_TITLE', 'Test Blog' );
define( 'WP_ENVIRONMENT_TYPE', 'development' );
define( 'WP_PHP_BINARY', 'php' );

define( 'DB_NAME', 'wordpress-ai-tests' );
define( 'DB_USER', 'root' );
define( 'DB_PASSWORD', 'root' );
define( 'DB_HOST', '0.0.0.0' );

define( 'WP_CONTENT_DIR', dirname( __DIR__ ) . '/wordpress/wp-content' );

if ( ! is_dir( WP_CONTENT_DIR ) ) {
	mkdir( WP_CONTENT_DIR );
}

if ( ! is_dir( WP_CONTENT_DIR . '/uploads' ) ) {
	mkdir( WP_CONTENT_DIR . '/uploads' );
}

// Absolute path to the WordPress directory.
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', dirname( __DIR__ ) . '/../../../../wp/' );
}