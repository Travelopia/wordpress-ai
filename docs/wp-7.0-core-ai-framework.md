# Spec: Use WordPress core's AI client framework (WordPress 7.0)

| Field | Value |
|-------|-------|
| **Status** | In review |
| **Branch** | `feature/wp-7-0-core-ai-framework` |
| **PR** | [Travelopia/wordpress-ai#29](https://github.com/Travelopia/wordpress-ai/pull/29) |
| **Supersedes** | [#28](https://github.com/Travelopia/wordpress-ai/pull/28) (PHP-Scoper / private-namespace approach — closed) |
| **Downstream** | H&J [hayesandjarvis#1055](https://github.com/Travelopia/hayesandjarvis/pull/1055) (WEBHJ-1198) |

## Problem

WordPress 7.0 ships the PHP AI Client in core under the `WordPress\AiClient\*`
namespace (`wp-includes/php-ai-client`). This plugin bundled its own copy of the
same namespace (php-ai-client 0.4, pulled in via the AWS Bedrock provider). On
WordPress 7.0 the two declarations collide and the site **fatals on boot**:

```
Declaration of WP_AI_Client_HTTP_Client::sendRequestWithOptions(...)
must be compatible with ClientWithOptionsInterface::sendRequestWithOptions(...)
```

There is no core setting to disable this — the AI client loads unconditionally
in `wp-settings.php`. Pinning the plugin's `.wp-env.json` to an older WordPress
only hides it in this plugin's CI; real WordPress 7.0 sites still crash.

## Decision

Consume **WordPress core's bundled AI framework** instead of shipping our own
copy. Two earlier options were considered:

- **PHP-Scoper isolation** (#28): rename our copy to a private namespace. Works,
  but ships a duplicate ~300-file bundle and a build step. Rejected as the
  long-term shape.
- **Consume core's framework** (this PR): cleaner, no duplication. Chosen.

Core ships only the framework, not concrete providers, so we vendor the AWS
Bedrock provider (which H&J uses) and bind it to core's framework.

## Approach

- Drop `wordpress/php-ai-client`, `aysnc/wordpress-php-ai-client-bedrock` and
  guzzle from `require`. Nothing Composer-installed loads `WordPress\AiClient` at
  runtime — core provides it. This is what removes the collision.
- Vendor the AWS Bedrock provider (4 classes, MIT, Aysnc-Labs) into
  `inc/Providers/Bedrock/` under its original namespace, autoloaded via Composer.
  Excluded from PHPCS/PHP-CS-Fixer to stay close to upstream; `LICENSE` retained.
- Guard adapter registration behind `class_exists( WordPress\AiClient\AiClient )`
  so the plugin no-ops on WordPress < 7.0. **The plugin now targets WordPress 7.0+.**
- Fix WordPress 7.0 stub type tightening (nullable `WP_Query::posts`,
  non-empty-string `wp_enqueue_script` deps). Pin wp-env to WordPress 7.0.
- Add `CoreFrameworkTest` guarding the integration (the Bedrock provider binds to
  core's `AbstractApiProvider`).

## OpenAI

The OpenAI adapter is **registered but currently a no-op** — WordPress core ships
no OpenAI provider, and the upstream OpenAI provider package still targets
php-ai-client 0.4. It is left in place for now and can be removed (along with its
adapter and tests) if OpenAI support isn't required before a php-ai-client 1.x
OpenAI provider exists. H&J uses Bedrock, which is the supported provider.

## Trade-off

The plugin is now **WordPress 7.0+ only** (it relies on core's framework). Flag
if any brand runs this plugin on older WordPress.

## Testing

Verified on a real WordPress 7.0 container: **PHPUnit 79/79**, no boot fatal;
PHPStan max clean, PHPCS clean, PHP-CS-Fixer clean.

## Downstream (H&J)

Once this is merged and released, bump `travelopia/wordpress-ai` in H&J
(hayesandjarvis#1055), `composer update`, and confirm `npm run test:php` passes
on WordPress 7.0.
