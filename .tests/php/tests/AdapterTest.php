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
}
