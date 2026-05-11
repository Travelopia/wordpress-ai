<?php
/**
 * Tests for AltText CLI.
 *
 * @package travelopia-wordpress-ai
 */

namespace Travelopia\WordPress_AI\Tests\AltText;

use Travelopia\WordPress_AI\Adapter;
use Travelopia\WordPress_AI\AltText\CLI;
use Travelopia\WordPress_AI\Tests\AltText\CLITestLogger;
use Travelopia\WordPress_AI\Tests\MockAdapter;
use WP_CLI;
use WP_UnitTestCase;

class CLITest extends WP_UnitTestCase
{
	/**
	 * Set up — register mock adapter and a quiet WP_CLI logger.
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
		MockAdapter::$mock_response = 'alt';

		WP_CLI::set_logger( new CLITestLogger() );
	}

	/**
	 * Tear down.
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
	 * Create N image attachments and return their IDs.
	 *
	 * @param int $count Number of images to create.
	 *
	 * @return int[]
	 */
	private function create_images( int $count ): array
	{
		$ids = [];
		for ( $i = 0; $i < $count; $i++ ) {
			$ids[] = self::factory()->attachment->create_upload_object( DIR_TESTDATA . '/images/canola.jpg' );
		}
		return $ids;
	}

	/**
	 * --missing --limit=2 caps attempts at exactly 2 even when more images are missing.
	 *
	 * @return void
	 */
	public function test_limit_caps_attempts_with_missing(): void
	{
		$this->create_images( 5 );

		( new CLI() )->generate(
			[],
			[
				'missing' => true,
				'limit' => 2,
			]
		);

		$this->assertSame( 2, MockAdapter::$call_count );
	}

	/**
	 * --ids=A,B,C,D,E --limit=2 processes only the first two IDs.
	 *
	 * @return void
	 */
	public function test_limit_with_ids_truncates_list(): void
	{
		$ids = $this->create_images( 5 );

		( new CLI() )->generate(
			[],
			[
				'ids'   => implode( ',', $ids ),
				'limit' => 2,
			],
		);

		$this->assertSame( 2, MockAdapter::$call_count );
	}

	/**
	 * --limit=0 raises WP_CLI::error.
	 *
	 * @return void
	 */
	public function test_limit_zero_errors(): void
	{
		$this->expectException( \RuntimeException::class );
		$this->expectExceptionMessageMatches( '/positive integer/i' );

		( new CLI() )->generate(
			[],
			[
				'missing' => true,
				'limit'   => 0,
			],
		);
	}

	/**
	 * --limit=-5 raises WP_CLI::error.
	 *
	 * @return void
	 */
	public function test_limit_negative_errors(): void
	{
		$this->expectException( \RuntimeException::class );
		$this->expectExceptionMessageMatches( '/positive integer/i' );

		( new CLI() )->generate(
			[],
			[
				'missing' => true,
				'limit'   => -5,
			],
		);
	}

	/**
	 * --limit=abc raises WP_CLI::error.
	 *
	 * @return void
	 */
	public function test_limit_non_numeric_errors(): void
	{
		$this->expectException( \RuntimeException::class );
		$this->expectExceptionMessageMatches( '/positive integer/i' );

		( new CLI() )->generate(
			[],
			[
				'missing' => true,
				'limit'   => 'abc',
			],
		);
	}
}
