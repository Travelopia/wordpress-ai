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
	// Load plugin main file - composer autoloader handles PSR-4 class loading.
	require __DIR__ . '/../../plugin.php';
}

// Load the plugin before WordPress initializes.
tests_add_filter( 'muplugins_loaded', '_manually_load_plugin' );

// Bootstrap PHPUnit tests with WordPress.
require_once getenv( 'WP_PHPUNIT__DIR' ) . '/includes/bootstrap.php';
