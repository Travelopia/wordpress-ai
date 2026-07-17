<?php
/**
 * PHPUnit bootstrap file.
 *
 * @package travelopia-wordpress-ai
 */

require_once dirname( __DIR__, 2 ) . '/vendor/autoload.php';

$_tests_dir = getenv( 'WP_TESTS_DIR' );

require_once $_tests_dir . '/includes/functions.php';

/*
 * The WP test framework does not activate plugins, so plugin.php would
 * never be loaded — its top-level function declarations and `add_action`
 * registrations (register_default_adapters / bootstrap_settings /
 * bootstrap_alt_text on plugins_loaded) need to exist for the bootstrap
 * regression tests in AdapterTest.
 */
tests_add_filter(
	'muplugins_loaded',
	static function (): void {
		require dirname( __DIR__, 2 ) . '/plugin.php';
	}
);

require_once $_tests_dir . '/includes/bootstrap.php';
