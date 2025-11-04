<?php
/**
 * WordPress PHPUnit test configuration.
 *
 * Configures the test database, WordPress constants, and directory structure
 * required for running PHPUnit tests for the WordPress AI plugin.
 *
 * @package travelopia-wordpress-ai
 *
 * @phpcs:disable PSR1.Files.SideEffects
 */

// Define database constants.
define( 'DB_NAME', 'wordpress-ai-tests' );
define( 'DB_USER', 'root' );
define( 'DB_PASSWORD', 'root' );
define( 'DB_HOST', '0.0.0.0' );

// Enable debugging for tests.
define( 'WP_DEBUG', true );
define( 'WP_DEBUG_DISPLAY', true );
define( 'WP_TESTS_DOMAIN', 'localhost' );
define( 'WP_TESTS_EMAIL', 'admin@example.org' );
define( 'WP_TESTS_TITLE', 'Test Blog' );
define( 'WP_PHP_BINARY', 'php' );

// Set WP_ENVIRONMENT_TYPE via putenv so it can be changed at runtime in tests.
putenv( 'WP_ENVIRONMENT_TYPE=development' ); // phpcs:ignore

// Set WordPress content directory for tests.
define( 'WP_CONTENT_DIR', dirname( __DIR__ ) . '/../wordpress/wp-content' );

// Create content directory if it doesn't exist.
if ( ! is_dir( WP_CONTENT_DIR ) ) {
	mkdir( WP_CONTENT_DIR ); // phpcs:ignore
}

// Create uploads directory if it doesn't exist.
if ( ! is_dir( WP_CONTENT_DIR . '/uploads' ) ) {
	mkdir( WP_CONTENT_DIR . '/uploads' ); // phpcs:ignore
}

// Absolute path to the WordPress directory.
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', dirname( __DIR__ ) . '/../wordpress/' );
}
