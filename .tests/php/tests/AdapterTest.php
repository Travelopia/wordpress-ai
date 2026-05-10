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
use Travelopia\WordPress_AI\Tests\MockAdapter;
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
		MockAdapter::reset();
	}

	/**
	 * Reset adapter registry after each test.
	 *
	 * @return void
	 */
	public function tearDown(): void
	{
		Adapter::reset();
		MockAdapter::reset();
		parent::tearDown();
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
	 * Test register does not boot the adapter.
	 *
	 * @return void
	 */
	public function test_register_does_not_boot(): void
	{
		Adapter::register( 'mock', MockAdapter::class );

		$this->assertSame( 0, MockAdapter::$boot_count );
	}

	/**
	 * Test set boots the active adapter.
	 *
	 * @return void
	 */
	public function test_set_boots_active_adapter(): void
	{
		Adapter::register( 'mock', MockAdapter::class );
		Adapter::set( 'mock' );

		$this->assertSame( 1, MockAdapter::$boot_count );
	}

	/**
	 * Test set boots only once when called repeatedly with the same adapter.
	 *
	 * @return void
	 */
	public function test_set_boots_only_once_per_adapter(): void
	{
		Adapter::register( 'mock', MockAdapter::class );
		Adapter::set( 'mock' );
		Adapter::set( 'mock' );

		$this->assertSame( 1, MockAdapter::$boot_count );
	}

	/**
	 * Test set ignores unknown adapter names.
	 *
	 * @return void
	 */
	public function test_set_unknown_adapter_does_not_boot(): void
	{
		Adapter::register( 'mock', MockAdapter::class );
		Adapter::set( 'nonexistent' );

		$this->assertSame( 0, MockAdapter::$boot_count );
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
}
