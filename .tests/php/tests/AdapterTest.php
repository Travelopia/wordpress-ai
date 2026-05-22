<?php
/**
 * Tests for Adapter registry.
 *
 * @package travelopia-wordpress-ai
 */

namespace Travelopia\WordPress_AI\Tests;

use Travelopia\WordPress_AI\Adapter;
use Travelopia\WordPress_AI\Adapters\Bedrock;
use Travelopia\WordPress_AI\Adapters\OpenAI;
use WP_UnitTestCase;

class AdapterTest extends WP_UnitTestCase
{
	/**
	 * Reset adapter registry before each test.
	 *
	 * @return void
	 */
	public function setUp(): void
	{
		parent::setUp();
		Adapter::reset();
	}

	/**
	 * Test that get returns null when no adapter is registered.
	 *
	 * @return void
	 */
	public function test_get_returns_null_when_empty(): void
	{
		$this->assertNull( Adapter::get() );
	}

	/**
	 * Test that get returns null when current is set but not registered.
	 *
	 * @return void
	 */
	public function test_get_returns_null_for_unregistered_adapter(): void
	{
		Adapter::set( 'nonexistent' );
		$this->assertNull( Adapter::get() );
	}

	/**
	 * Test register and get.
	 *
	 * @return void
	 */
	public function test_register_and_get(): void
	{
		Adapter::register( 'openai', OpenAI::class );
		Adapter::set( 'openai' );
		$this->assertSame( OpenAI::class, Adapter::get() );
	}

	/**
	 * Test switching between adapters.
	 *
	 * @return void
	 */
	public function test_switch_adapter(): void
	{
		Adapter::register( 'openai', OpenAI::class );
		Adapter::register( 'bedrock', Bedrock::class );

		Adapter::set( 'openai' );
		$this->assertSame( OpenAI::class, Adapter::get() );

		Adapter::set( 'bedrock' );
		$this->assertSame( Bedrock::class, Adapter::get() );
	}

	/**
	 * Test reset clears all state.
	 *
	 * @return void
	 */
	public function test_reset(): void
	{
		Adapter::register( 'openai', OpenAI::class );
		Adapter::set( 'openai' );
		$this->assertSame( OpenAI::class, Adapter::get() );

		Adapter::reset();
		$this->assertNull( Adapter::get() );
	}

	/**
	 * Test registering overwrites existing adapter with same name.
	 *
	 * @return void
	 */
	public function test_register_overwrites(): void
	{
		Adapter::register( 'provider', OpenAI::class );
		Adapter::register( 'provider', Bedrock::class );
		Adapter::set( 'provider' );
		$this->assertSame( Bedrock::class, Adapter::get() );
	}

	/*
	 * Bootstrap regression — MR-2011.
	 * The plugin previously called Adapter::register() at file load time, which
	 * fatalled WordPress boot in environments where the composer autoloader was
	 * not wired into wp-config.php (e.g. the CI database-dump workflow). The
	 * fix moves registration into register_default_adapters() hooked on
	 * plugins_loaded at priority 5, guarded by class_exists.
	 */

	/**
	 * Bootstrap function must be hooked on plugins_loaded at priority 5,
	 * before Settings::bootstrap and AltText::bootstrap which run at the
	 * default priority 10.
	 *
	 * @return void
	 */
	public function test_register_default_adapters_is_hooked_on_plugins_loaded(): void
	{
		$this->assertSame(
			5,
			has_action( 'plugins_loaded', 'Travelopia\\WordPress_AI\\register_default_adapters' ),
			'register_default_adapters must be hooked on plugins_loaded at priority 5.'
		);
	}

	/**
	 * Calling the bootstrap function registers both adapters and sets the
	 * default provider — same end state as the previous top-level code.
	 *
	 * @return void
	 */
	public function test_register_default_adapters_registers_both_adapters_and_default_provider(): void
	{
		\Travelopia\WordPress_AI\register_default_adapters();

		$this->assertSame( Bedrock::class, Adapter::get(), 'Default provider must be bedrock after bootstrap.' );

		Adapter::set( 'openai' );
		$this->assertSame( OpenAI::class, Adapter::get(), 'OpenAI adapter must be registered after bootstrap.' );
	}

	/**
	 * The provider filter must still resolve the active adapter when the
	 * bootstrap runs.
	 *
	 * @return void
	 */
	public function test_register_default_adapters_respects_provider_filter(): void
	{
		add_filter(
			'travelopia_wordpress_ai_provider',
			static function (): string {
				return 'openai';
			}
		);

		\Travelopia\WordPress_AI\register_default_adapters();

		$this->assertSame( OpenAI::class, Adapter::get(), 'Provider filter must override the default during bootstrap.' );

		remove_all_filters( 'travelopia_wordpress_ai_provider' );
	}
}
