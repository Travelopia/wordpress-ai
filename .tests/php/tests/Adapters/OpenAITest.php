<?php
/**
 * Tests for OpenAI adapter.
 *
 * @package travelopia-wordpress-ai
 */

namespace Travelopia\WordPress_AI\Tests\Adapters;

use Exception;
use Travelopia\WordPress_AI\Adapters\OpenAI;
use WP_Error;
use WP_UnitTestCase;

class OpenAITest extends WP_UnitTestCase
{
	/**
	 * Set up test fixtures.
	 *
	 * @return void
	 */
	public function setUp(): void
	{
		parent::setUp();
		TestableOpenAI::reset();
	}

	/**
	 * Clean up after each test.
	 *
	 * @return void
	 */
	public function tearDown(): void
	{
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.runtime_configuration_putenv -- Required for testing env-based API key validation.
		putenv( 'OPENAI_API_KEY' );
		TestableOpenAI::reset();
		parent::tearDown();
	}

	/**
	 * Test get_default_options returns expected defaults.
	 *
	 * @return void
	 */
	public function test_get_default_options(): void
	{
		$options = OpenAI::get_default_options();

		$this->assertSame( 'gpt-4o-mini', $options['model'] );
		$this->assertSame( 0.1, $options['temperature'] );
		$this->assertArrayHasKey( 'system_instruction', $options );
	}

	/**
	 * Test validate_api_key returns error when no key configured.
	 *
	 * @return void
	 */
	public function test_validate_api_key_not_configured(): void
	{
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.runtime_configuration_putenv -- Required for testing env-based API key validation.
		putenv( 'OPENAI_API_KEY' );

		$result = OpenAI::validate_api_key();

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'api_key_not_configured', $result->get_error_code() );
	}

	/**
	 * Test validate_api_key returns true when env var is set.
	 *
	 * @return void
	 */
	public function test_validate_api_key_with_env(): void
	{
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.runtime_configuration_putenv -- Required for testing env-based API key validation.
		putenv( 'OPENAI_API_KEY=sk-test-key' );

		$result = OpenAI::validate_api_key();

		$this->assertTrue( $result );
	}

	/**
	 * Test generate_alt_text returns error when API key is missing.
	 *
	 * @return void
	 */
	public function test_generate_alt_text_no_api_key(): void
	{
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.runtime_configuration_putenv -- Required for testing env-based API key validation.
		putenv( 'OPENAI_API_KEY' );

		$result = TestableOpenAI::generate_alt_text( 'https://example.com/image.jpg' );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'api_key_not_configured', $result->get_error_code() );
	}

	/**
	 * Test generate_alt_text returns generated text on success.
	 *
	 * @return void
	 */
	public function test_generate_alt_text_success(): void
	{
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.runtime_configuration_putenv -- Required for testing env-based API key validation.
		putenv( 'OPENAI_API_KEY=sk-test-key' );
		TestableOpenAI::$mock_response = 'A beautiful sunset over the ocean';

		$result = TestableOpenAI::generate_alt_text( 'https://example.com/image.jpg', [ 'prompt' => 'Describe this image' ] );

		$this->assertSame( 'A beautiful sunset over the ocean', $result );
	}

	/**
	 * Test generate_alt_text passes correct arguments to AI client.
	 *
	 * @return void
	 */
	public function test_generate_alt_text_passes_options(): void
	{
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.runtime_configuration_putenv -- Required for testing env-based API key validation.
		putenv( 'OPENAI_API_KEY=sk-test-key' );
		TestableOpenAI::$mock_response = 'Alt text';

		TestableOpenAI::generate_alt_text(
			'https://example.com/image.jpg',
			[
				'prompt'             => 'Test prompt',
				'model'              => 'gpt-4o',
				'temperature'        => 0.5,
				'system_instruction' => 'Be concise',
			],
		);

		$this->assertSame( 'Test prompt', TestableOpenAI::$last_call_args['prompt'] );
		$this->assertSame( 'gpt-4o', TestableOpenAI::$last_call_args['model'] );
		$this->assertSame( 0.5, TestableOpenAI::$last_call_args['temperature'] );
		$this->assertSame( 'https://example.com/image.jpg', TestableOpenAI::$last_call_args['image_url'] );
		$this->assertSame( 'Be concise', TestableOpenAI::$last_call_args['system_instruction'] );
	}

	/**
	 * Test generate_alt_text uses defaults when options are missing.
	 *
	 * @return void
	 */
	public function test_generate_alt_text_uses_defaults(): void
	{
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.runtime_configuration_putenv -- Required for testing env-based API key validation.
		putenv( 'OPENAI_API_KEY=sk-test-key' );
		TestableOpenAI::$mock_response = 'Alt text';

		TestableOpenAI::generate_alt_text( 'https://example.com/image.jpg' );

		$this->assertSame( '', TestableOpenAI::$last_call_args['prompt'] );
		$this->assertSame( 'gpt-4o-mini', TestableOpenAI::$last_call_args['model'] );
		$this->assertSame( 0.1, TestableOpenAI::$last_call_args['temperature'] );
	}

	/**
	 * Test generate_alt_text returns error on empty response.
	 *
	 * @return void
	 */
	public function test_generate_alt_text_empty_response(): void
	{
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.runtime_configuration_putenv -- Required for testing env-based API key validation.
		putenv( 'OPENAI_API_KEY=sk-test-key' );
		TestableOpenAI::$mock_response = '';

		$result = TestableOpenAI::generate_alt_text( 'https://example.com/image.jpg' );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'travelopia_wordpress_ai_open_ai_alt_text_empty_response', $result->get_error_code() );
	}

	/**
	 * Test generate_alt_text returns error on exception.
	 *
	 * @return void
	 */
	public function test_generate_alt_text_exception(): void
	{
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.runtime_configuration_putenv -- Required for testing env-based API key validation.
		putenv( 'OPENAI_API_KEY=sk-test-key' );
		TestableOpenAI::$mock_exception = new Exception( 'API timeout' );

		$result = TestableOpenAI::generate_alt_text( 'https://example.com/image.jpg' );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'travelopia_wordpress_ai_open_ai_alt_text_generation_failed', $result->get_error_code() );
		$this->assertStringContainsString( 'API timeout', $result->get_error_message() );
	}

	/**
	 * Test generate_alt_text strips HTML tags from response.
	 *
	 * @return void
	 */
	public function test_generate_alt_text_strips_html(): void
	{
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.runtime_configuration_putenv -- Required for testing env-based API key validation.
		putenv( 'OPENAI_API_KEY=sk-test-key' );
		TestableOpenAI::$mock_response = '<p>A <strong>bold</strong> image</p>';

		$result = TestableOpenAI::generate_alt_text( 'https://example.com/image.jpg' );

		$this->assertSame( 'A bold image', $result );
	}

	/**
	 * Test generate_alt_text fires error action on failure.
	 *
	 * @return void
	 */
	public function test_generate_alt_text_fires_error_action(): void
	{
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.runtime_configuration_putenv -- Required for testing env-based API key validation.
		putenv( 'OPENAI_API_KEY=sk-test-key' );
		TestableOpenAI::$mock_exception = new Exception( 'Fail' );

		$action_fired = false;
		add_action(
			'travelopia_wordpress_ai_open_ai_error',
			static function () use ( &$action_fired ): void {
				$action_fired = true;
			},
		);

		TestableOpenAI::generate_alt_text( 'https://example.com/image.jpg' );

		$this->assertTrue( $action_fired );
	}
}
