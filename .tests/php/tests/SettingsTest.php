<?php
/**
 * Tests for Settings class.
 *
 * @package travelopia-wordpress-ai
 */

namespace Travelopia\WordPress_AI\Tests;

use Travelopia\WordPress_AI\Settings;
use WP_UnitTestCase;

class SettingsTest extends WP_UnitTestCase
{
	/**
	 * Set up — bootstrap settings hooks and clear option.
	 *
	 * @return void
	 */
	public function setUp(): void
	{
		parent::setUp();
		Settings::bootstrap();
		delete_option( Settings::OPTION_NAME );
		Settings::get( true );
	}

	/**
	 * Tear down — clear option.
	 *
	 * @return void
	 */
	public function tearDown(): void
	{
		delete_option( Settings::OPTION_NAME );
		parent::tearDown();
	}

	/**
	 * Default settings are returned when no option is stored.
	 *
	 * @return void
	 */
	public function test_default_settings_returned_when_unset(): void
	{
		Settings::get( true );

		$this->assertFalse( Settings::get_setting( Settings::FIELD_ALT_TEXT_GENERATION ) );
		$this->assertSame(
			Settings::DEFAULT_ALT_TEXT_PROMPT,
			Settings::get_setting( Settings::FIELD_ALT_TEXT_PROMPT ),
		);
	}

	/**
	 * Stored settings override defaults.
	 *
	 * @return void
	 */
	public function test_stored_settings_override_defaults(): void
	{
		update_option(
			Settings::OPTION_NAME,
			[
				Settings::FIELD_ALT_TEXT_GENERATION => true,
				Settings::FIELD_ALT_TEXT_PROMPT     => 'Custom prompt',
			],
		);

		Settings::get( true );

		$this->assertTrue( Settings::get_setting( Settings::FIELD_ALT_TEXT_GENERATION ) );
		$this->assertSame( 'Custom prompt', Settings::get_setting( Settings::FIELD_ALT_TEXT_PROMPT ) );
	}

	/**
	 * Updating the option invalidates the static cache without an explicit force.
	 *
	 * @return void
	 */
	public function test_update_option_invalidates_cache(): void
	{
		// Prime the cache with defaults.
		Settings::get( true );
		$this->assertFalse( Settings::get_setting( Settings::FIELD_ALT_TEXT_GENERATION ) );

		// Save new settings — the bootstrap hook should clear the cache.
		update_option(
			Settings::OPTION_NAME,
			[
				Settings::FIELD_ALT_TEXT_GENERATION => true,
				Settings::FIELD_ALT_TEXT_PROMPT     => 'New prompt',
			],
		);

		// Without force=true, the cached value should reflect the update.
		$this->assertTrue( Settings::get_setting( Settings::FIELD_ALT_TEXT_GENERATION ) );
		$this->assertSame( 'New prompt', Settings::get_setting( Settings::FIELD_ALT_TEXT_PROMPT ) );
	}

	/**
	 * Adding the option (first save) invalidates the static cache.
	 *
	 * @return void
	 */
	public function test_add_option_invalidates_cache(): void
	{
		delete_option( Settings::OPTION_NAME );
		Settings::get( true );

		add_option(
			Settings::OPTION_NAME,
			[
				Settings::FIELD_ALT_TEXT_GENERATION => true,
				Settings::FIELD_ALT_TEXT_PROMPT     => Settings::DEFAULT_ALT_TEXT_PROMPT,
			],
		);

		$this->assertTrue( Settings::get_setting( Settings::FIELD_ALT_TEXT_GENERATION ) );
	}

	/**
	 * The build-missing check returns false when the dist asset is present.
	 *
	 * @return void
	 */
	public function test_is_build_missing_returns_false_when_asset_present(): void
	{
		$this->assertFalse( Settings::is_build_missing() );
	}

	/**
	 * The build-missing check returns true when the dist asset path is not on disk.
	 *
	 * @return void
	 */
	public function test_is_build_missing_returns_true_when_asset_absent(): void
	{
		add_filter(
			'travelopia_wordpress_ai_settings_asset_file',
			static fn (): string => '/tmp/does-not-exist-' . uniqid() . '.php',
		);

		$this->assertTrue( Settings::is_build_missing() );

		remove_all_filters( 'travelopia_wordpress_ai_settings_asset_file' );
	}

	/**
	 * Notice renders on the settings page when the build is missing.
	 *
	 * @return void
	 */
	public function test_build_missing_notice_renders_on_settings_page(): void
	{
		add_filter(
			'travelopia_wordpress_ai_settings_asset_file',
			static fn (): string => '/tmp/does-not-exist-' . uniqid() . '.php',
		);

		set_current_screen( 'settings_page_' . Settings::PAGE_SLUG );

		ob_start();
		Settings::display_build_missing_notice();
		$output = (string) ob_get_clean();

		$this->assertStringContainsString( 'notice', $output );
		$this->assertStringContainsString( 'build', $output );

		remove_all_filters( 'travelopia_wordpress_ai_settings_asset_file' );
	}

	/**
	 * Notice does not render on unrelated screens.
	 *
	 * @return void
	 */
	public function test_build_missing_notice_silent_on_other_screens(): void
	{
		add_filter(
			'travelopia_wordpress_ai_settings_asset_file',
			static fn (): string => '/tmp/does-not-exist-' . uniqid() . '.php',
		);

		set_current_screen( 'edit-post' );

		ob_start();
		Settings::display_build_missing_notice();
		$output = (string) ob_get_clean();

		$this->assertSame( '', $output );

		remove_all_filters( 'travelopia_wordpress_ai_settings_asset_file' );
	}

	/**
	 * Notice does not render when build assets are present.
	 *
	 * @return void
	 */
	public function test_build_missing_notice_silent_when_build_present(): void
	{
		set_current_screen( 'settings_page_' . Settings::PAGE_SLUG );

		ob_start();
		Settings::display_build_missing_notice();
		$output = (string) ob_get_clean();

		$this->assertSame( '', $output );
	}
}
