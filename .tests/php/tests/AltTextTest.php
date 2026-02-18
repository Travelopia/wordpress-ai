<?php
/**
 * Tests for AltText class.
 *
 * @package travelopia-wordpress-ai
 */

namespace Travelopia\WordPress_AI\Tests;

use Travelopia\WordPress_AI\Adapter;
use Travelopia\WordPress_AI\AltText;
use WP_Error;
use WP_UnitTestCase;

class AltTextTest extends WP_UnitTestCase
{
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
	}

	/**
	 * Clean up after each test.
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
	 * Create a test image attachment.
	 *
	 * @param string $title Attachment title.
	 *
	 * @return int Attachment ID.
	 */
	private function create_image_attachment( string $title = 'Test Image' ): int
	{
		$attachment_id = self::factory()->attachment->create_upload_object( DIR_TESTDATA . '/images/canola.jpg' );
		wp_update_post(
			[
				'ID'         => $attachment_id,
				'post_title' => $title,
			],
		);

		return $attachment_id;
	}

	/**
	 * Register and activate the mock adapter.
	 *
	 * @return void
	 */
	private function register_mock_adapter(): void
	{
		Adapter::register( 'mock', MockAdapter::class );
		Adapter::set( 'mock' );
	}

	/**
	 * Test generate returns error when no adapter is configured.
	 *
	 * @return void
	 */
	public function test_generate_no_adapter(): void
	{
		$attachment_id = $this->create_image_attachment();

		$result = AltText::generate( $attachment_id );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'travelopia_wordpress_ai_no_adapter', $result->get_error_code() );
	}

	/**
	 * Test generate returns error for invalid attachment.
	 *
	 * @return void
	 */
	public function test_generate_invalid_attachment(): void
	{
		$this->register_mock_adapter();

		$result = AltText::generate( 0 );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'travelopia_wordpress_ai_alt_text_invalid_image', $result->get_error_code() );
	}

	/**
	 * Test generate returns error for non-image attachment.
	 *
	 * @return void
	 */
	public function test_generate_non_image_attachment(): void
	{
		$this->register_mock_adapter();

		$attachment_id = self::factory()->attachment->create(
			[
				'post_mime_type' => 'application/pdf',
			],
		);

		$result = AltText::generate( $attachment_id );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'travelopia_wordpress_ai_alt_text_invalid_image', $result->get_error_code() );
	}

	/**
	 * Test successful alt text generation.
	 *
	 * @return void
	 */
	public function test_generate_success(): void
	{
		$this->register_mock_adapter();
		MockAdapter::$mock_response = 'A field of yellow canola flowers';

		$attachment_id = $this->create_image_attachment();
		$result        = AltText::generate( $attachment_id );

		$this->assertSame( 'A field of yellow canola flowers', $result );
	}

	/**
	 * Test generate saves alt text to post meta.
	 *
	 * @return void
	 */
	public function test_generate_saves_meta(): void
	{
		$this->register_mock_adapter();
		MockAdapter::$mock_response = 'Generated alt text';

		$attachment_id = $this->create_image_attachment();
		AltText::generate( $attachment_id, true );

		$this->assertSame( 'Generated alt text', get_post_meta( $attachment_id, '_wp_attachment_image_alt', true ) );
	}

	/**
	 * Test generate does not save meta when update is false.
	 *
	 * @return void
	 */
	public function test_generate_no_save_when_update_false(): void
	{
		$this->register_mock_adapter();
		MockAdapter::$mock_response = 'Generated alt text';

		$attachment_id = $this->create_image_attachment();
		AltText::generate( $attachment_id, false );

		$this->assertEmpty( get_post_meta( $attachment_id, '_wp_attachment_image_alt', true ) );
	}

	/**
	 * Test generate fires action hook on success.
	 *
	 * @return void
	 */
	public function test_generate_fires_action(): void
	{
		$this->register_mock_adapter();
		MockAdapter::$mock_response = 'Alt text result';

		$fired_with = null;
		add_action(
			'travelopia_wordpress_ai_alt_text_generated',
			// phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- Parameters used via compact.
			static function ( int $id, string $text ) use ( &$fired_with ): void {
				$fired_with = compact( 'id', 'text' );
			},
			10,
			2,
		);

		$attachment_id = $this->create_image_attachment();
		AltText::generate( $attachment_id );

		$this->assertNotNull( $fired_with );
		$this->assertSame( $attachment_id, $fired_with['id'] );
		$this->assertSame( 'Alt text result', $fired_with['text'] );
	}

	/**
	 * Test generate propagates adapter errors.
	 *
	 * @return void
	 */
	public function test_generate_propagates_adapter_error(): void
	{
		$this->register_mock_adapter();
		MockAdapter::$mock_response = new WP_Error( 'adapter_error', 'Something went wrong' );

		$attachment_id = $this->create_image_attachment();
		$result        = AltText::generate( $attachment_id );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'adapter_error', $result->get_error_code() );
	}

	/**
	 * Test generate includes context from attachment title.
	 *
	 * @return void
	 */
	public function test_generate_includes_context(): void
	{
		$this->register_mock_adapter();
		MockAdapter::$mock_response = 'Alt text';

		$attachment_id = $this->create_image_attachment( 'Sunset Photo' );
		AltText::generate( $attachment_id );

		$this->assertNotNull( MockAdapter::$last_call );
		$this->assertStringContainsString( 'Sunset Photo', MockAdapter::$last_call['options']['prompt'] );
	}

	/**
	 * Test generate excludes context when filter disables it.
	 *
	 * @return void
	 */
	public function test_generate_excludes_context_when_filtered(): void
	{
		$this->register_mock_adapter();
		MockAdapter::$mock_response = 'Alt text';

		add_filter( 'travelopia_wordpress_ai_alt_text_include_context', '__return_false' );

		$attachment_id = $this->create_image_attachment( 'Sunset Photo' );
		AltText::generate( $attachment_id );

		$this->assertNotNull( MockAdapter::$last_call );
		$this->assertStringNotContainsString( 'Sunset Photo', MockAdapter::$last_call['options']['prompt'] ?? '' );

		remove_filter( 'travelopia_wordpress_ai_alt_text_include_context', '__return_false' );
	}

	/**
	 * Test generate passes adapter default options.
	 *
	 * @return void
	 */
	public function test_generate_uses_adapter_defaults(): void
	{
		$this->register_mock_adapter();
		MockAdapter::$mock_response = 'Alt text';

		$attachment_id = $this->create_image_attachment();
		AltText::generate( $attachment_id );

		$this->assertNotNull( MockAdapter::$last_call );
		$this->assertSame( 'mock-model', MockAdapter::$last_call['options']['model'] );
		$this->assertSame( 0.1, MockAdapter::$last_call['options']['temperature'] );
	}

	/**
	 * Test generate options can be filtered.
	 *
	 * @return void
	 */
	public function test_generate_options_filter(): void
	{
		$this->register_mock_adapter();
		MockAdapter::$mock_response = 'Alt text';

		add_filter(
			'travelopia_wordpress_ai_alt_text_generation_options',
			static function ( array $options ): array {
				$options['model']       = 'custom-model';
				$options['temperature'] = 0.9;
				return $options;
			},
		);

		$attachment_id = $this->create_image_attachment();
		AltText::generate( $attachment_id );

		$this->assertSame( 'custom-model', MockAdapter::$last_call['options']['model'] );
		$this->assertSame( 0.9, MockAdapter::$last_call['options']['temperature'] );

		remove_all_filters( 'travelopia_wordpress_ai_alt_text_generation_options' );
	}

	/**
	 * Test query_images returns image attachment IDs.
	 *
	 * @return void
	 */
	public function test_query_images(): void
	{
		$id1 = $this->create_image_attachment();
		$id2 = $this->create_image_attachment();

		$results = AltText::query_images();

		$this->assertContains( $id1, $results );
		$this->assertContains( $id2, $results );
	}

	/**
	 * Test query_images with specific IDs.
	 *
	 * @return void
	 */
	public function test_query_images_specific_ids(): void
	{
		$id1 = $this->create_image_attachment();
		$id2 = $this->create_image_attachment();
		$this->create_image_attachment();

		$results = AltText::query_images( [ $id1, $id2 ] );

		$this->assertCount( 2, $results );
		$this->assertContains( $id1, $results );
		$this->assertContains( $id2, $results );
	}

	/**
	 * Test query_images missing_only filters correctly.
	 *
	 * @return void
	 */
	public function test_query_images_missing_only(): void
	{
		$id_with_alt    = $this->create_image_attachment();
		$id_without_alt = $this->create_image_attachment();

		update_post_meta( $id_with_alt, '_wp_attachment_image_alt', 'Has alt text' );

		$results = AltText::query_images( missing_only: true );

		$this->assertContains( $id_without_alt, $results );
		$this->assertNotContains( $id_with_alt, $results );
	}
}
