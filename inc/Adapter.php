<?php
/**
 * AI Adapter Registry.
 *
 * @package travelopia-wordpress-ai
 */

namespace Travelopia\WordPress_AI;

use Travelopia\WordPress_AI\Adapters\AiAdapter;

/**
 * Registry for AI adapters.
 */
class Adapter
{
	/**
	 * Registered adapters.
	 *
	 * @var array<string, class-string<AiAdapter>>
	 */
	private static array $adapters = [];

	/**
	 * Currently active adapter name.
	 *
	 * @var string
	 */
	private static string $current = '';

	/**
	 * Register an adapter.
	 *
	 * @param string                  $name    Adapter name.
	 * @param class-string<AiAdapter> $adapter Adapter class name.
	 *
	 * @return void
	 */
	public static function register( string $name, string $adapter ): void // phpcs:ignore Squiz.Commenting.FunctionComment.IncorrectTypeHint -- class-string not supported as native type hint.
	{
		self::$adapters[ $name ] = $adapter;
	}

	/**
	 * Set the active adapter.
	 *
	 * @param string $name Adapter name.
	 *
	 * @return void
	 */
	public static function set( string $name ): void
	{
		self::$current = $name;
	}

	/**
	 * Get the active adapter class.
	 *
	 * @return class-string<AiAdapter>|null Adapter class name or null if not set.
	 */
	public static function get(): ?string
	{
		if ( empty( self::$current ) || ! isset( self::$adapters[ self::$current ] ) ) {
			return null;
		}

		return self::$adapters[ self::$current ];
	}

	/**
	 * Reset the registry. Useful for testing.
	 *
	 * @return void
	 */
	public static function reset(): void
	{
		self::$adapters = [];
		self::$current  = '';
	}
}
