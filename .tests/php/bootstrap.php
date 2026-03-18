<?php
/**
 * PHPUnit bootstrap file.
 *
 * @package travelopia-wordpress-ai
 */

require_once dirname( __DIR__, 2 ) . '/vendor/autoload.php';

$_tests_dir = getenv( 'WP_TESTS_DIR' );

require_once $_tests_dir . '/includes/functions.php';
require_once $_tests_dir . '/includes/bootstrap.php';
