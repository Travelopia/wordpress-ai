<?php
/**
 * Bootstrap for unit tests.
 */

require_once __DIR__ . '/../../vendor/autoload.php';

// Load functions.
require_once getenv( 'WP_PHPUNIT__DIR' ) . '/includes/functions.php';

// Bootstrap PHPUnit tests.
require_once getenv( 'WP_PHPUNIT__DIR' ) . '/includes/bootstrap.php';