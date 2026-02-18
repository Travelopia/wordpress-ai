<?php
/**
 * Testable OpenAI subclass for unit tests.
 *
 * @package travelopia-wordpress-ai
 */

namespace Travelopia\WordPress_AI\Tests\Adapters;

use Exception;
use Travelopia\WordPress_AI\Adapters\OpenAI;

/**
 * Testable OpenAI subclass that overrides the AI client call.
 */
class TestableOpenAI extends OpenAI
{
	/**
	 * The value to return from call_ai_client.
	 *
	 * @var string|null
	 */
	public static ?string $mock_response = null;

	/**
	 * Exception to throw from call_ai_client.
	 *
	 * @var Exception|null
	 */
	public static ?Exception $mock_exception = null;

	/**
	 * The arguments passed to call_ai_client.
	 *
	 * @var array<string, mixed>|null
	 */
	public static ?array $last_call_args = null;

	/**
	 * Reset mock state.
	 *
	 * @return void
	 */
	public static function reset(): void
	{
		self::$mock_response  = null;
		self::$mock_exception = null;
		self::$last_call_args = null;
	}

	/**
	 * Override the AI client call.
	 *
	 * @param string $prompt             The prompt.
	 * @param string $model              The model ID.
	 * @param float  $temperature        The temperature.
	 * @param string $image_url          The image URL.
	 * @param string $system_instruction The system instruction.
	 *
	 * @return string
	 *
	 * @throws Exception If mock_exception is set.
	 */
	protected static function call_ai_client( string $prompt, string $model, float $temperature, string $image_url, string $system_instruction ): string
	{
		self::$last_call_args = compact( 'prompt', 'model', 'temperature', 'image_url', 'system_instruction' );

		if ( null !== self::$mock_exception ) {
			throw self::$mock_exception;
		}

		return self::$mock_response ?? '';
	}
}
