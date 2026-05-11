<?php
/**
 * Test-only stub for WP_CLI\Utils\make_progress_bar.
 *
 * The function lives in wp-cli/wp-cli's php/utils.php, which is not
 * registered on Composer's `files` autoload list, so it is not
 * available in the PHPUnit environment. This stub returns an object
 * with no-op tick() / finish() methods. CLI::process_batched() guards
 * both calls with method_exists() so the stub is a safe stand-in.
 *
 * @package travelopia-wordpress-ai
 */

namespace WP_CLI\Utils;

if ( ! function_exists( __NAMESPACE__ . '\\make_progress_bar' ) ) {
	/**
	 * Return a no-op progress-bar stand-in for tests.
	 *
	 * @param string $message  Progress message (unused).
	 * @param int    $count    Total ticks (unused).
	 * @param int    $interval Tick interval (unused).
	 *
	 * @return object Object with no-op tick() and finish() methods.
	 */
	function make_progress_bar( string $message, int $count, int $interval = 100 ): object // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter -- Stub matches real signature; arguments ignored intentionally.
	{
		return new class() {
			/**
			 * No-op tick.
			 *
			 * @return void
			 */
			public function tick(): void
			{
			}

			/**
			 * No-op finish.
			 *
			 * @return void
			 */
			public function finish(): void
			{
			}
		};
	}
}
