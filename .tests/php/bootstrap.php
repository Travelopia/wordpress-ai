<?php
/**
 * Bootstrap for unit tests.
 *
 * Sets up the testing environment by loading dependencies, the plugin files,
 * and initializing WordPress testing framework.
 *
 * @package trav-ai
 *
 * @phpcs:disable PSR1.Files.SideEffects
 */

// Load Composer dependencies.
require_once __DIR__ . '/../../vendor/autoload.php';

// Load WordPress PHPUnit test functions.
require_once getenv( 'WP_PHPUNIT__DIR' ) . '/includes/functions.php';

/**
 * Manually load the plugin for testing.
 *
 * This function is called before WordPress initializes to ensure
 * the plugin code is available during tests.
 *
 * @return void
 */
function _manually_load_plugin(): void {
	// Load plugin namespace files.
	require __DIR__ . '/../../inc/namespace.php';
	require __DIR__ . '/../../inc/admin/namespace.php';
	require __DIR__ . '/../../inc/alt-text/namespace.php';
}

// Load the plugin before WordPress initializes.
tests_add_filter( 'muplugins_loaded', '_manually_load_plugin' );

// Bootstrap PHPUnit tests with WordPress.
require_once getenv( 'WP_PHPUNIT__DIR' ) . '/includes/bootstrap.php';
