# Scoped AI Dependency Bundle

> Why this plugin ships a private, namespace-isolated copy of the PHP AI Client,
> and how to rebuild it.
> Last updated: 2026-06-04

## Why

WordPress 7.0 ships the **PHP AI Client** in core (`wp-includes/php-ai-client/`,
v1.3.1) under the `WordPress\AiClient\*` namespace. This plugin also needs that
library (for AltText generation via the OpenAI / AWS Bedrock providers), pinned
to the `0.4.x` line.

If the plugin loaded its own copy under the **same** `WordPress\AiClient\*`
namespace, the two declarations would collide and WordPress would **fatal during
boot**:

```
Declaration of WP_AI_Client_HTTP_Client::sendRequestWithOptions(...)
must be compatible with ClientWithOptionsInterface::sendRequestWithOptions(...)
```

The provider ecosystem (OpenAI, Bedrock) still targets php-ai-client `0.4.x`, so
"just use core's 1.3.1 client" is not yet an option. Until it is, the plugin
isolates its own copy.

## How

[PHP-Scoper](https://github.com/humbug/php-scoper) rewrites the bundled library
(php-ai-client + Bedrock provider + Guzzle + PSR contracts) under a private
prefix:

```
WordPress\AiClient\*   →   Travelopia\WordPress_AI\Dependencies\WordPress\AiClient\*
Psr\Http\*             →   Travelopia\WordPress_AI\Dependencies\Psr\Http\*
GuzzleHttp\*           →   Travelopia\WordPress_AI\Dependencies\GuzzleHttp\*
...etc.
```

The scoped bundle is **committed** at `dependencies/` (like the JS `dist/`), so
consuming sites get it via Composer without running any build. `plugin.php` loads
its autoloader (`dependencies/vendor/autoload.php`). The adapters in
`inc/Adapters/` reference the scoped namespace directly.

Because the bundle is self-contained, the runtime AI libraries are **not** in the
plugin's own `composer.json` — they would otherwise flatten into a consuming
site's root `vendor/` and re-introduce the unscoped collision (and break this
plugin's own test runner on WP 7.0+). They live only in the throwaway build
manifest `bin/runtime-deps.composer.json`.

## Files

| Path | Role |
|---|---|
| `dependencies/` | **Committed** scoped bundle. Generated — do not edit by hand. |
| `scoper.inc.php` | PHP-Scoper config (prefix, finders, exclusions). |
| `bin/runtime-deps.composer.json` | Pins the exact runtime libs that get scoped. |
| `bin/build-dependencies.sh` | Build pipeline (install → scope → dump autoloader). |

## Rebuilding

After bumping a runtime library in `bin/runtime-deps.composer.json`:

```bash
composer install          # ensures php-scoper (require-dev) is present
composer build:dependencies
```

The build fails loudly if a bundled file still declares an unscoped
`WordPress\AiClient` namespace, or if the function-include file list drifts from
the installed dep set. Commit the regenerated `dependencies/`.

Runtime isolation is guarded by `DependencyScopingTest`
(`.tests/php/tests/DependencyScopingTest.php`).
