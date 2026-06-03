<?php
/**
 * PHP-Scoper configuration.
 *
 * Isolates the bundled AI client libraries under a private namespace prefix so
 * they no longer collide with the copy of `WordPress\AiClient\*` that WordPress
 * 7.0+ ships in core (`wp-includes/php-ai-client`). See docs/dependencies.md.
 *
 * @package travelopia-wordpress-ai
 */

declare(strict_types=1);

use Isolated\Symfony\Component\Finder\Finder;

// The build script installs the runtime-only dependency closure (AI client +
// Bedrock provider + Guzzle PSR-18 client) into `build/runtime/vendor`, with no
// dev tooling present. Scoping the whole directory — including Composer's
// generated autoload files — yields a self-contained, prefixed bundle whose own
// autoloader resolves the prefixed classes.
$runtime_vendor_dir = 'build/runtime/vendor';

return [
	'prefix' => 'Travelopia\\WordPress_AI\\Dependencies',

	'finders' => [
		Finder::create()
			->files()
			->ignoreVCS( true )
			->notName( '/.*\\.dist|Makefile|composer\\.(json|lock)|.*\\.md/' )
			->exclude( [ 'doc', 'docs', 'test', 'tests', 'Tests', 'vendor-bin', 'bin' ] )
			->name( [ '*.php', 'installed.json' ] )
			->in( $runtime_vendor_dir ),
	],

	// First-party plugin code keeps its own namespace; it is not part of the
	// scoped bundle (it references the prefixed classes directly).
	'exclude-namespaces' => [
		'Travelopia\\WordPress_AI',
	],

	// Do NOT expose / alias any scoped class back to its original FQN — aliasing
	// `WordPress\AiClient\*` back would re-introduce the core collision.
	'expose-global-constants' => false,
	'expose-global-classes'   => false,
	'expose-global-functions' => false,
];
