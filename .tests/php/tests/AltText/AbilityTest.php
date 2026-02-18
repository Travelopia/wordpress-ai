<?php
/**
 * Tests for AltText Ability class.
 *
 * @package travelopia-wordpress-ai
 */

namespace Travelopia\WordPress_AI\Tests\AltText;

use Travelopia\WordPress_AI\Adapter;
use Travelopia\WordPress_AI\AltText\Ability;
use Travelopia\WordPress_AI\Tests\MockAdapter;
use WP_Error;
use WP_UnitTestCase;

class AbilityTest extends WP_UnitTestCase
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
	 * @return int Attachment ID.
	 */
	private function create_image_attachment(): int
	{
		return self::factory()->attachment->create_upload_object( DIR_TESTDATA . '/images/canola.jpg' );
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
	 * Test check_permission returns error for empty post ID.
	 *
	 * @return void
	 */
	public function test_check_permission_empty_post_id(): void
	{
		$result = Ability::check_permission( [] );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'rest_invalid_post_id', $result->get_error_code() );
	}

	/**
	 * Test check_permission returns error for zero post ID.
	 *
	 * @return void
	 */
	public function test_check_permission_zero_post_id(): void
	{
		$result = Ability::check_permission( [ 'post_id' => 0 ] );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'rest_invalid_post_id', $result->get_error_code() );
	}

	/**
	 * Test check_permission returns error for non-existent post.
	 *
	 * @return void
	 */
	public function test_check_permission_nonexistent_post(): void
	{
		$result = Ability::check_permission( [ 'post_id' => 999999 ] );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'rest_invalid_attachment', $result->get_error_code() );
	}

	/**
	 * Test check_permission returns error for non-attachment post.
	 *
	 * @return void
	 */
	public function test_check_permission_non_attachment(): void
	{
		$post_id = self::factory()->post->create();

		$result = Ability::check_permission( [ 'post_id' => $post_id ] );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'rest_invalid_attachment', $result->get_error_code() );
	}

	/**
	 * Test check_permission returns error for non-image attachment.
	 *
	 * @return void
	 */
	public function test_check_permission_non_image_attachment(): void
	{
		$attachment_id = self::factory()->attachment->create(
			[
				'post_mime_type' => 'application/pdf',
			],
		);

		$result = Ability::check_permission( [ 'post_id' => $attachment_id ] );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'rest_invalid_attachment', $result->get_error_code() );
	}

	/**
	 * Test check_permission returns error for unauthorized user.
	 *
	 * @return void
	 */
	public function test_check_permission_unauthorized_user(): void
	{
		$attachment_id = $this->create_image_attachment();

		// Set current user to a subscriber who cannot edit posts.
		$user_id = self::factory()->user->create( [ 'role' => 'subscriber' ] );
		wp_set_current_user( $user_id );

		$result = Ability::check_permission( [ 'post_id' => $attachment_id ] );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'rest_forbidden', $result->get_error_code() );
	}

	/**
	 * Test check_permission returns true for authorized user.
	 *
	 * @return void
	 */
	public function test_check_permission_authorized_user(): void
	{
		$attachment_id = $this->create_image_attachment();

		$user_id = self::factory()->user->create( [ 'role' => 'administrator' ] );
		wp_set_current_user( $user_id );

		$result = Ability::check_permission( [ 'post_id' => $attachment_id ] );

		$this->assertTrue( $result );
	}

	/**
	 * Test execute returns success with generated alt text.
	 *
	 * @return void
	 */
	public function test_execute_success(): void
	{
		$this->register_mock_adapter();
		MockAdapter::$mock_response = 'A beautiful sunset over the ocean';

		$attachment_id = $this->create_image_attachment();
		$result        = Ability::execute( [ 'post_id' => $attachment_id ] );

		$this->assertTrue( $result['success'] );
		$this->assertSame( 'A beautiful sunset over the ocean', $result['alt_text'] );
		$this->assertNotEmpty( $result['message'] );
	}

	/**
	 * Test execute saves alt text to post meta.
	 *
	 * @return void
	 */
	public function test_execute_saves_meta(): void
	{
		$this->register_mock_adapter();
		MockAdapter::$mock_response = 'Generated alt text';

		$attachment_id = $this->create_image_attachment();
		Ability::execute( [ 'post_id' => $attachment_id ] );

		$this->assertSame( 'Generated alt text', get_post_meta( $attachment_id, '_wp_attachment_image_alt', true ) );
	}

	/**
	 * Test execute returns failure when adapter returns error.
	 *
	 * @return void
	 */
	public function test_execute_adapter_error(): void
	{
		$this->register_mock_adapter();
		MockAdapter::$mock_response = new WP_Error( 'adapter_error', 'API call failed' );

		$attachment_id = $this->create_image_attachment();
		$result        = Ability::execute( [ 'post_id' => $attachment_id ] );

		$this->assertFalse( $result['success'] );
		$this->assertSame( 'API call failed', $result['message'] );
		$this->assertArrayNotHasKey( 'alt_text', $result );
	}

	/**
	 * Test execute returns failure when no adapter is configured.
	 *
	 * @return void
	 */
	public function test_execute_no_adapter(): void
	{
		$attachment_id = $this->create_image_attachment();
		$result        = Ability::execute( [ 'post_id' => $attachment_id ] );

		$this->assertFalse( $result['success'] );
		$this->assertNotEmpty( $result['message'] );
	}

	/**
	 * Test ability name constant.
	 *
	 * @return void
	 */
	public function test_ability_name(): void
	{
		$this->assertSame( 'travelopia/generate-alt-text', Ability::ABILITY_NAME );
	}
}
