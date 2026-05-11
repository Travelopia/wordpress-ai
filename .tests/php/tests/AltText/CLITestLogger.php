<?php
/**
 * Quiet WP_CLI logger that throws on error() so PHPUnit can catch it.
 *
 * @package travelopia-wordpress-ai
 */

namespace Travelopia\WordPress_AI\Tests\AltText;

use RuntimeException;

/**
 * Quiet WP_CLI logger used by CLITest.
 *
 * Throws RuntimeException on error() so PHPUnit can catch it via
 * expectException(). All other log levels are no-ops to keep test
 * output clean.
 */
class CLITestLogger
{
	/**
	 * Throw RuntimeException so the test harness can catch CLI errors.
	 *
	 * @param string $message Error message.
	 *
	 * @return void
	 *
	 * @throws RuntimeException Always.
	 */
	public function error( string $message ): void
	{
		// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Test shim; message is for assertion, not output.
		throw new RuntimeException( $message );
	}

	/**
	 * Suppress warning output.
	 *
	 * @param string $message Warning message.
	 *
	 * @return void
	 */
	public function warning( string $message ): void
	{
	}

	/**
	 * Suppress success output.
	 *
	 * @param string $message Success message.
	 *
	 * @return void
	 */
	public function success( string $message ): void
	{
	}

	/**
	 * Suppress info output.
	 *
	 * @param string $message Info message.
	 *
	 * @return void
	 */
	public function info( string $message ): void
	{
	}

	/**
	 * Suppress log output.
	 *
	 * @param string $message Log message.
	 *
	 * @return void
	 */
	public function log( string $message ): void
	{
	}

	/**
	 * Suppress debug output.
	 *
	 * @param string $message Debug message.
	 * @param string $group   Debug group.
	 *
	 * @return void
	 */
	public function debug( string $message, string $group = '' ): void
	{
	}
}
