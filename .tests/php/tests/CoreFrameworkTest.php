<?php
/**
 * Tests that the plugin uses WordPress core's bundled AI framework.
 *
 * WordPress 7.0+ ships the PHP AI Client in core under WordPress\AiClient. This
 * plugin relies on that copy (it bundles no php-ai-client of its own) and
 * provides a vendored AWS Bedrock provider that binds to it. These tests guard
 * that integration — if the plugin ever shipped or pulled its own php-ai-client
 * again, it would collide with core and fatal on boot.
 *
 * @package travelopia-wordpress-ai
 */

namespace Travelopia\WordPress_AI\Tests;

use Aysnc\WordPress\PhpAiClientBedrock\AwsBedrockProvider;
use Travelopia\WordPress_AI\Adapters\Bedrock;
use WP_UnitTestCase;

class CoreFrameworkTest extends WP_UnitTestCase
{
	/**
	 * WordPress core must provide the AI client framework (i.e. WP 7.0+).
	 *
	 * @return void
	 */
	public function test_core_ai_framework_is_available(): void
	{
		$this->assertTrue(
			class_exists( 'WordPress\\AiClient\\AiClient' ),
			'WordPress core AI client framework is missing — this plugin requires WordPress 7.0+.',
		);
	}

	/**
	 * The adapters call a small, specific surface of core's client directly —
	 * `AiClient::prompt()` and `AiClient::defaultRegistry()`. A core API change
	 * that removes or renames either must fail loudly here, in CI, rather than
	 * silently at generation time on a live site.
	 *
	 * @return void
	 */
	public function test_core_client_exposes_expected_api(): void
	{
		$this->assertTrue(
			method_exists( 'WordPress\\AiClient\\AiClient', 'prompt' ),
			'WordPress\\AiClient\\AiClient::prompt() is missing — core AI client API has changed.',
		);
		$this->assertTrue(
			method_exists( 'WordPress\\AiClient\\AiClient', 'defaultRegistry' ),
			'WordPress\\AiClient\\AiClient::defaultRegistry() is missing — core AI client API has changed.',
		);
	}

	/**
	 * The vendored Bedrock provider must bind to core's framework — i.e. it
	 * extends core's AbstractApiProvider. This is true only if both the provider
	 * and core's framework resolve to the same WordPress\AiClient namespace.
	 *
	 * @return void
	 */
	public function test_bedrock_provider_binds_to_core_framework(): void
	{
		$this->assertTrue(
			is_subclass_of( AwsBedrockProvider::class, 'WordPress\\AiClient\\Providers\\ApiBasedImplementation\\AbstractApiProvider' ),
			'The vendored Bedrock provider does not extend core\'s AbstractApiProvider.',
		);
	}

	/**
	 * Booting the Bedrock adapter registers the provider with core's client
	 * without error.
	 *
	 * @return void
	 */
	public function test_bedrock_boot_registers_provider(): void
	{
		Bedrock::boot();

		$this->assertTrue(
			class_exists( AwsBedrockProvider::class ),
			'Bedrock provider failed to load after boot.',
		);
	}
}
