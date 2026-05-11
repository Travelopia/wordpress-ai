<?php
/**
 * PHPUnit bootstrap file.
 *
 * @package travelopia-wordpress-ai
 */

// Stub WP_CLI\Utils\make_progress_bar — not autoloaded in the test env.
// The production code guards all tick/finish calls with method_exists(), so
// returning a plain object is enough to avoid fatal errors.
namespace WP_CLI\Utils {
	if ( ! function_exists( 'WP_CLI\Utils\make_progress_bar' ) ) {
		/**
		 * No-op progress bar stub for PHPUnit.
		 *
		 * @param string $message  Progress bar label.
		 * @param int    $count    Total ticks.
		 * @param int    $interval Interval (unused).
		 *
		 * @return object Anonymous object with tick() and finish() no-ops.
		 */
		function make_progress_bar( string $message, int $count, int $interval = 100 ): object {
			return new class() {
				/** @return void */
				public function tick(): void {}
				/** @return void */
				public function finish(): void {}
			};
		}
	}
}

namespace {
	require_once dirname( __DIR__, 2 ) . '/vendor/autoload.php';

	$_tests_dir = getenv( 'WP_TESTS_DIR' );

	require_once $_tests_dir . '/includes/functions.php';
	require_once $_tests_dir . '/includes/bootstrap.php';
}
