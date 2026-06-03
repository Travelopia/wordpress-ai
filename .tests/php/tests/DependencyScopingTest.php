<?php
/**
 * Regression tests for the scoped AI dependency bundle.
 *
 * WordPress 7.0+ ships the PHP AI Client in core under `WordPress\AiClient\*`.
 * This plugin bundles its own copy isolated under
 * `Travelopia\WordPress_AI\Dependencies\*` so the two never collide. These tests
 * guard that isolation at runtime — if the bundle ever stopped being scoped, a
 * site on WordPress 7.0+ would fatal during boot.
 *
 * The build-time guard in bin/build-dependencies.sh additionally fails the build
 * if any bundle file declares the unscoped namespace.
 *
 * @package travelopia-wordpress-ai
 */

namespace Travelopia\WordPress_AI\Tests;

use WP_UnitTestCase;

class DependencyScopingTest extends WP_UnitTestCase
{
	/**
	 * Read a plugin file through WP_Filesystem.
	 *
	 * @param string $relative_path Path relative to the plugin root.
	 *
	 * @return string File contents.
	 */
	private function read_plugin_file( string $relative_path = '' ): string
	{
		global $wp_filesystem;

		if ( ! $wp_filesystem ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
			WP_Filesystem();
		}

		return (string) $wp_filesystem->get_contents( dirname( __DIR__, 3 ) . '/' . $relative_path );
	}

	/**
	 * The scoped AI client class must be loadable via the committed bundle.
	 *
	 * @return void
	 */
	public function test_scoped_ai_client_class_is_loadable(): void
	{
		$this->assertTrue(
			class_exists( 'Travelopia\\WordPress_AI\\Dependencies\\WordPress\\AiClient\\AiClient' ),
			'Scoped AiClient is missing — run `composer build:dependencies`.',
		);
	}

	/**
	 * The scoped Bedrock provider class must be loadable via the committed bundle.
	 *
	 * @return void
	 */
	public function test_scoped_bedrock_provider_class_is_loadable(): void
	{
		$this->assertTrue(
			class_exists( 'Travelopia\\WordPress_AI\\Dependencies\\Aysnc\\WordPress\\PhpAiClientBedrock\\AwsBedrockProvider' ),
			'Scoped AwsBedrockProvider is missing — run `composer build:dependencies`.',
		);
	}

	/**
	 * The adapters must reference the scoped namespace, never the bare one.
	 *
	 * An unscoped `use WordPress\AiClient\…` would resolve to WordPress core's
	 * incompatible copy on 7.0+ instead of the bundled, version-pinned one.
	 *
	 * @return void
	 */
	public function test_adapters_reference_only_the_scoped_namespace(): void
	{
		foreach ( [ 'inc/Adapters/OpenAI.php', 'inc/Adapters/Bedrock.php' ] as $relative_path ) {
			$contents = $this->read_plugin_file( $relative_path );

			$this->assertDoesNotMatchRegularExpression(
				'/^use\s+WordPress\\\\AiClient/m',
				$contents,
				sprintf( '%s imports the unscoped WordPress\\AiClient namespace.', basename( $relative_path ) ),
			);

			$this->assertStringContainsString(
				'use Travelopia\\WordPress_AI\\Dependencies\\WordPress\\AiClient\\',
				$contents,
				sprintf( '%s does not import the scoped AI client namespace.', basename( $relative_path ) ),
			);
		}
	}
}
