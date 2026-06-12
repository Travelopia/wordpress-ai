# Spec: Use WordPress core's AI client framework (WordPress 7.0)

| Field | Value |
|-------|-------|
| **Status** | In review |
| **Branch** | `feature/wp-7-0-upstream-bedrock-fix` |
| **PR** | [Travelopia/wordpress-ai#30](https://github.com/Travelopia/wordpress-ai/pull/30) |
| **Supersedes** | [#29](https://github.com/Travelopia/wordpress-ai/pull/29) (vendoring approach — closed), [#28](https://github.com/Travelopia/wordpress-ai/pull/28) (PHP-Scoper — closed) |
| **Upstream fix** | [Aysnc-Labs/wordpress-php-ai-client-bedrock#1](https://github.com/Aysnc-Labs/wordpress-php-ai-client-bedrock/pull/1) (released as `0.2.0`) |
| **Downstream** | H&J WEBHJ-1198 |

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

Contribute the fix **upstream** to `Aysnc-Labs/wordpress-php-ai-client-bedrock`,
then consume the released package via Composer. Three options were considered:

- **PHP-Scoper isolation** (#28): rename our copy to a private namespace. Works,
  but ships a duplicate ~300-file bundle and a build step. Rejected.
- **Vendoring** (#29): copy the 4 Bedrock provider classes into `inc/Providers/Bedrock/`
  and drop the Composer dependency. Avoids the collision but puts us in the business
  of maintaining a fork. Superseded by this PR.
- **Upstream fix** (this PR): remove `wordpress/php-ai-client` from the Bedrock
  package's `require` — WP 7.0 core provides the namespace, so the Composer package
  is redundant and conflicting. Released as `aysnc/wordpress-php-ai-client-bedrock 0.2.0`. Chosen.

## Approach

- Require `aysnc/wordpress-php-ai-client-bedrock ^0.2.0` via Packagist.
- `0.2.0` drops `wordpress/php-ai-client` from its own `require`, so no Composer
  package installs the namespace alongside WP 7.0 core's copy. No PHP source changes
  needed — the provider classes already import from `WordPress\AiClient\*`.
- Guard adapter registration behind `class_exists( WordPress\AiClient\AiClient )`
  so the plugin no-ops on WordPress < 7.0. **The plugin targets WordPress 7.0+.**
- Fix WordPress 7.0 stub type tightening (nullable `WP_Query::posts`,
  non-empty-string `wp_enqueue_script` deps). Pin wp-env to WordPress 7.0.
- `CoreFrameworkTest` guards the integration (the Bedrock provider binds to
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
PHPStan max clean, PHPCS clean. H&J PHPUnit suite: 110/110, 488 assertions.

## Downstream (H&J)

Once this is merged and released as `0.2.0`, bump `travelopia/wordpress-ai` to
`"*"` in H&J, remove `minimum-stability: dev`, `composer update`, and confirm
`npm run test:php` passes on WordPress 7.0.
