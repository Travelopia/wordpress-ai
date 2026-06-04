# AWS Bedrock provider (vendored)

These classes are vendored from
[Aysnc-Labs/wordpress-php-ai-client-bedrock](https://github.com/Aysnc-Labs/wordpress-php-ai-client-bedrock)
(MIT, Copyright © Aysnc — see `LICENSE`), kept under their original
`Aysnc\WordPress\PhpAiClientBedrock` namespace and autoloaded from here.

They implement the `WordPress\AiClient` provider contracts, which WordPress 7.0+
ships in core (`wp-includes/php-ai-client`). We vendor them so the plugin no
longer pulls `wordpress/php-ai-client` via Composer — that copy collides with
core's. Excluded from our PHPCS/PHP-CS-Fixer to stay close to upstream.
