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
use Travelopia\WordPress_AI\AltText;
use Travelopia\WordPress_AI\Settings;
use WP_UnitTestCase;

use function Travelopia\WordPress_AI\bootstrap_alt_text;
use function Travelopia\WordPress_AI\bootstrap_settings;
use function Travelopia\WordPress_AI\register_default_adapters;

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
	public function test_register_default_adapters_registers_bedrock_as_default_provider(): void
	{
		register_default_adapters();

		$this->assertSame( Bedrock::class, Adapter::get(), 'Default provider must be bedrock after bootstrap.' );
	}

	/**
	 * The adapters filter must allow brands to register additional adapters.
	 *
	 * @return void
	 */
	public function test_adapters_filter_allows_additional_adapters(): void
	{
		add_filter(
			'travelopia_wordpress_ai_adapters',
			static function ( array $adapters ): array {
				$adapters['custom'] = Bedrock::class;
				return $adapters;
			},
		);

		register_default_adapters();

		Adapter::set( 'custom' );
		$this->assertSame( Bedrock::class, Adapter::get(), 'Adapters filter must allow registering additional adapters.' );

		remove_all_filters( 'travelopia_wordpress_ai_adapters' );
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
				return 'bedrock';
			}
		);

		register_default_adapters();

		$this->assertSame( Bedrock::class, Adapter::get(), 'Provider filter must override the default during bootstrap.' );

		remove_all_filters( 'travelopia_wordpress_ai_provider' );
	}

	/*
	 * Bootstrap regression — MR-2011 follow-up.
	 * Settings::bootstrap and AltText::bootstrap were still hooked with
	 * `[ ClassName::class, 'bootstrap' ]` callables. When the composer
	 * autoloader is missing, WP-Hook fatals on invocation because PHP
	 * tries to autoload the class at call time. The fix wraps each
	 * bootstrap in a named function with a `class_exists` guard.
	 */

	/**
	 * `bootstrap_settings` must be hooked on `plugins_loaded` at the
	 * default priority 10.
	 *
	 * @return void
	 */
	public function test_bootstrap_settings_is_hooked_on_plugins_loaded(): void
	{
		$this->assertSame(
			10,
			has_action( 'plugins_loaded', 'Travelopia\\WordPress_AI\\bootstrap_settings' ),
			'bootstrap_settings must be hooked on plugins_loaded at priority 10.'
		);
	}

	/**
	 * `bootstrap_alt_text` must be hooked on `plugins_loaded` at the
	 * default priority 10.
	 *
	 * @return void
	 */
	public function test_bootstrap_alt_text_is_hooked_on_plugins_loaded(): void
	{
		$this->assertSame(
			10,
			has_action( 'plugins_loaded', 'Travelopia\\WordPress_AI\\bootstrap_alt_text' ),
			'bootstrap_alt_text must be hooked on plugins_loaded at priority 10.'
		);
	}

	/**
	 * Calling `bootstrap_settings` must register the Settings module's
	 * hooks — same end state as the previous `[ Settings::class,
	 * 'bootstrap' ]` callable.
	 *
	 * @return void
	 */
	public function test_bootstrap_settings_registers_settings_hooks(): void
	{
		remove_all_actions( 'init' );
		remove_all_actions( 'admin_menu' );

		bootstrap_settings();

		$this->assertNotFalse(
			has_action( 'init', [ Settings::class, 'register_rest_setting' ] ),
			'Settings::register_rest_setting must be hooked on init after bootstrap.'
		);
		$this->assertNotFalse(
			has_action( 'admin_menu', [ Settings::class, 'setup_settings' ] ),
			'Settings::setup_settings must be hooked on admin_menu after bootstrap.'
		);
	}

	/**
	 * Calling `bootstrap_alt_text` must run `AltText::bootstrap` — verified
	 * via the `add_attachment` hook it registers when alt-text generation
	 * is enabled in settings.
	 *
	 * @return void
	 */
	public function test_bootstrap_alt_text_invokes_alt_text_bootstrap(): void
	{
		remove_all_actions( 'add_attachment' );

		update_option(
			Settings::OPTION_NAME,
			[ Settings::FIELD_ALT_TEXT_GENERATION => true ],
		);
		Settings::get( true );

		bootstrap_alt_text();

		$this->assertNotFalse(
			has_action( 'add_attachment', [ AltText::class, 'generate' ] ),
			'AltText::generate must be hooked on add_attachment after bootstrap when generation is enabled.'
		);

		delete_option( Settings::OPTION_NAME );
		Settings::get( true );
	}
}
