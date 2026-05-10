<?php
/**
 * Mock AI adapter for testing.
 *
 * @package travelopia-wordpress-ai
 */

namespace Travelopia\WordPress_AI\Tests;

use Travelopia\WordPress_AI\Adapters\AbstractAiAdapter;
use WP_Error;

/**
 * Mock AI adapter for testing.
 */
class MockAdapter extends AbstractAiAdapter
{
	/**
	 * The value to return from generate_alt_text.
	 *
	 * @var string|WP_Error
	 */
	public static string|WP_Error $mock_response = '';

	/**
	 * The arguments passed to generate_alt_text.
	 *
	 * @var array<string, mixed>|null
	 */
	public static ?array $last_call = null;

	/**
	 * Number of times generate_alt_text has been invoked.
	 *
	 * @var int
	 */
	public static int $call_count = 0;

	/**
	 * Number of times the adapter has been booted.
	 *
	 * @var int
	 */
	public static int $boot_count = 0;

	/**
	 * Reset mock state.
	 *
	 * @return void
	 */
	public static function reset(): void
	{
		self::$mock_response = '';
		self::$last_call     = null;
		self::$call_count    = 0;
		self::$boot_count    = 0;
	}

	/**
	 * Generate alt text (mock).
	 *
	 * @param string               $image_url Image URL.
	 * @param array<string, mixed> $options   Options.
	 *
	 * @return string|WP_Error
	 */
	public static function generate_alt_text( string $image_url = '', array $options = [] ): string|WP_Error
	{
		++self::$call_count;
		self::$last_call = compact( 'image_url', 'options' );
		return self::$mock_response;
	}

	/**
	 * Get default options (mock).
	 *
	 * @return array<string, mixed>
	 */
	public static function get_default_options(): array
	{
		return [
			'model'              => 'mock-model',
			'temperature'        => 0.1,
			'system_instruction' => 'Mock instruction.',
		];
	}

	/**
	 * Validate API key (mock).
	 *
	 * @return true|WP_Error
	 */
	public static function validate_api_key(): true|WP_Error
	{
		return true;
	}

	/**
	 * Call AI client (mock).
	 *
	 * @param string $prompt             The prompt.
	 * @param string $model              The model ID.
	 * @param float  $temperature        The temperature.
	 * @param string $image_url          The image URL.
	 * @param string $system_instruction The system instruction.
	 *
	 * @return string
	 */
	protected static function call_ai_client( string $prompt, string $model, float $temperature, string $image_url, string $system_instruction ): string
	{
		return '';
	}
}
