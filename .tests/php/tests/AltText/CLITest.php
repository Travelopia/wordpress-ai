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
}
