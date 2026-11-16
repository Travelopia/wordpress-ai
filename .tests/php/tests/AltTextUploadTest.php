<?php
/**
 * Tests for alt text generation on upload, including multisite.
 *
 * @package travelopia-wordpress-ai
 */

namespace Travelopia\WordPress_AI\Tests;

use Travelopia\WordPress_AI\Adapter;
use Travelopia\WordPress_AI\AltText;
use Travelopia\WordPress_AI\Settings;
use WP_UnitTestCase;

class AltTextUploadTest extends WP_UnitTestCase
{
	/**
	 * Second site on multisite runs.
	 *
	 * @var int
	 */
	private static int $other_site_id = 0;

	/**
	 * Create the second site once for the class.
	 *
	 * @return void
	 */
	public static function wpSetUpBeforeClass(): void
	{
		if ( ! is_multisite() ) {
			return;
		}

		self::$other_site_id = self::factory()->blog->create();
	}

	/**
	 * Delete the second site.
	 *
	 * @return void
	 */
	public static function wpTearDownAfterClass(): void
	{
		if ( 0 === self::$other_site_id ) {
			return;
		}

		wp_delete_site( self::$other_site_id );
	}

	/**
	 * Set up test fixtures.
	 *
	 * @return void
	 */
	public function setUp(): void
	{
		parent::setUp();
		Adapter::reset();
		MockAdapter::reset();
		Adapter::register( 'mock', MockAdapter::class );
		Adapter::set( 'mock' );
		MockAdapter::$mock_response = 'Generated alt text';

		// Start every test with generation disabled, so creating fixtures does not generate.
		$this->save_settings( false, 'Main site prompt.' );
	}

	/**
	 * Clean up after each test.
	 *
	 * @return void
	 */
	public function tearDown(): void
	{
		delete_option( Settings::OPTION_NAME );
		Settings::get( true );
		Adapter::reset();
		MockAdapter::reset();
		parent::tearDown();
	}

	/**
	 * Save settings on the current site and refresh its cache.
	 *
	 * @param bool   $enabled Whether generation is enabled.
	 * @param string $prompt  Alt text prompt.
	 *
	 * @return void
	 */
	private function save_settings( bool $enabled = false, string $prompt = '' ): void
	{
		update_option(
			Settings::OPTION_NAME,
			[
				Settings::FIELD_ALT_TEXT_GENERATION => $enabled,
				Settings::FIELD_ALT_TEXT_PROMPT     => $prompt,
			],
		);
		Settings::get( true );
	}

	/**
	 * Save settings on the second site, then cache the main site's settings last.
	 *
	 * Caching the main site last reproduces an upload request: the main
	 * site's settings are already in memory when the copy is inserted on
	 * the second site.
	 *
	 * @param bool   $enabled Whether generation is enabled on the second site.
	 * @param string $prompt  Second site's prompt.
	 *
	 * @return void
	 */
	private function save_other_site_settings( bool $enabled = false, string $prompt = '' ): void
	{
		switch_to_blog( self::$other_site_id );

		try {
			$this->save_settings( $enabled, $prompt );
		} finally {
			restore_current_blog();
		}

		$this->save_settings( true, 'Main site prompt.' );
	}

	/**
	 * Skip unless the suite runs as multisite.
	 *
	 * @return void
	 */
	private function require_multisite(): void
	{
		if ( ! is_multisite() ) {
			$this->markTestSkipped( 'Requires multisite: run with WP_MULTISITE=1.' );
		}
	}

	/**
	 * The upload hook is registered even when this site has generation disabled.
	 *
	 * @return void
	 */
	public function test_bootstrap_hooks_upload_when_disabled(): void
	{
		remove_all_actions( 'add_attachment' );

		AltText::bootstrap();

		$this->assertSame( 20, has_action( 'add_attachment', [ AltText::class, 'generate_on_upload' ] ) );
	}

	/**
	 * Nothing is generated when the current site has generation disabled.
	 *
	 * @return void
	 */
	public function test_generate_on_upload_skips_when_disabled(): void
	{
		$attachment_id = self::factory()->attachment->create_upload_object( DIR_TESTDATA . '/images/canola.jpg' );

		AltText::generate_on_upload( $attachment_id );

		$this->assertSame( 0, MockAdapter::$call_count );
		$this->assertSame( '', get_post_meta( $attachment_id, '_wp_attachment_image_alt', true ) );
	}

	/**
	 * Alt text is generated and saved when the current site has generation enabled.
	 *
	 * @return void
	 */
	public function test_generate_on_upload_generates_when_enabled(): void
	{
		$attachment_id = self::factory()->attachment->create_upload_object( DIR_TESTDATA . '/images/canola.jpg' );
		$this->save_settings( true, 'Main site prompt.' );

		AltText::generate_on_upload( $attachment_id );

		$this->assertSame( 1, MockAdapter::$call_count );
		$this->assertSame( 'Generated alt text', get_post_meta( $attachment_id, '_wp_attachment_image_alt', true ) );
	}

	/**
	 * Settings are read for the site switched to, not the site cached first.
	 *
	 * @return void
	 */
	public function test_settings_are_per_site(): void
	{
		$this->require_multisite();
		$this->save_other_site_settings( true, 'Other site prompt.' );

		switch_to_blog( self::$other_site_id );

		try {
			$prompt = Settings::get_setting( Settings::FIELD_ALT_TEXT_PROMPT );
		} finally {
			restore_current_blog();
		}

		$this->assertSame( 'Other site prompt.', $prompt );
		$this->assertSame( 'Main site prompt.', Settings::get_setting( Settings::FIELD_ALT_TEXT_PROMPT ) );
	}

	/**
	 * An attachment inserted on another site uses that site's prompt.
	 *
	 * @return void
	 */
	public function test_upload_on_other_site_uses_its_prompt(): void
	{
		$this->require_multisite();
		$this->save_other_site_settings( true, 'Other site prompt.' );

		switch_to_blog( self::$other_site_id );

		try {
			$attachment_id = self::factory()->attachment->create_upload_object( DIR_TESTDATA . '/images/canola.jpg' );
			$alt_text      = get_post_meta( $attachment_id, '_wp_attachment_image_alt', true );
		} finally {
			restore_current_blog();
		}

		$this->assertSame( 1, MockAdapter::$call_count );
		$this->assertIsArray( MockAdapter::$last_call );
		$this->assertIsArray( MockAdapter::$last_call['options'] );
		$this->assertIsString( MockAdapter::$last_call['options']['prompt'] );
		$this->assertStringStartsWith( 'Other site prompt.', MockAdapter::$last_call['options']['prompt'] );
		$this->assertSame( 'Generated alt text', $alt_text );
	}

	/**
	 * An attachment inserted on another site with generation disabled gets no alt text.
	 *
	 * @return void
	 */
	public function test_upload_on_other_site_skips_when_disabled_there(): void
	{
		$this->require_multisite();
		$this->save_other_site_settings( false, 'Other site prompt.' );

		switch_to_blog( self::$other_site_id );

		try {
			$attachment_id = self::factory()->attachment->create_upload_object( DIR_TESTDATA . '/images/canola.jpg' );
			$alt_text      = get_post_meta( $attachment_id, '_wp_attachment_image_alt', true );
		} finally {
			restore_current_blog();
		}

		$this->assertSame( 0, MockAdapter::$call_count );
		$this->assertSame( '', $alt_text );
	}
}
