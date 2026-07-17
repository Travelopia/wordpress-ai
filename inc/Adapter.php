<?php
/**
 * AI Adapter Registry.
 *
 * @package travelopia-wordpress-ai
 */

namespace Travelopia\WordPress_AI;

/**
 * Registry for AI adapters.
 */
class Adapter
{
	/**
	 * Registered adapters.
	 *
	 * @var array<string, class-string<Adapters\AbstractAiAdapter>>
	 */
	private static array $adapters = [];

	/**
	 * Currently active adapter name.
	 *
	 * @var string
	 */
	private static string $current = '';

	/**
	 * Names of adapters that have been booted.
	 *
	 * @var array<string, true>
	 */
	private static array $booted = [];

	/**
	 * Register an adapter.
	 *
	 * Registration only stores the mapping — the adapter's `boot()` runs the
	 * first time `set()` activates it, so unused providers never load their
	 * SDKs.
	 *
	 * @param string                                   $name    Adapter name.
	 * @param class-string<Adapters\AbstractAiAdapter> $adapter Adapter class name.
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
	 * Boots the adapter on first activation. Subsequent calls with the same
	 * name are no-ops at the boot level.
	 *
	 * @param string $name Adapter name.
	 *
	 * @return void
	 */
	public static function set( string $name ): void
	{
		self::$current = $name;

		if ( ! isset( self::$adapters[ $name ] ) || isset( self::$booted[ $name ] ) ) {
			return;
		}

		self::$adapters[ $name ]::boot();
		self::$booted[ $name ] = true;
	}

	/**
	 * Get the active adapter class.
	 *
	 * @return class-string<Adapters\AbstractAiAdapter>|null Adapter class name or null if not set.
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
		self::$booted   = [];
	}
}
