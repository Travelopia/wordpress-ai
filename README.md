# Travelopia WordPress AI

![maintenance-status](https://img.shields.io/badge/maintenance-actively--developed-brightgreen.svg)

An extensible WordPress plugin that brings AI capabilities to your content workflows. Configure your preferred AI provider once, and unlock a growing set of features — starting with automatic image alt text generation.

<table width="100%">
	<tr>
		<td align="left" width="70%">
        	<p>Built by the super talented team at <strong><a href="https://www.travelopia.com/work-with-us/">Travelopia</a></strong>.</p>
		</td>
		<td align="center" width="30%">
			<img src="https://www.travelopia.com/wp-content/themes/travelopia/src/assets/svg/logo-travelopia-circle.svg" width="50" />
		</td>
	</tr>
</table>

## AI Providers

The plugin supports multiple providers out of the box:

- **AWS Bedrock** (default)
- **OpenAI**

### Provider Configuration

**AWS Bedrock** (Claude 3.5 Sonnet) — set via `wp-config.php` or environment variable:

```php
define( 'AWS_BEDROCK_API_KEY', 'your-key-here' );
define( 'AWS_BEDROCK_REGION', 'us-east-1' ); // optional, defaults to us-east-1
```

```bash
export AWS_BEDROCK_API_KEY="your-key-here"
export AWS_BEDROCK_REGION="us-east-1" # optional, defaults to us-east-1
```

**OpenAI** (GPT-4o Mini) — set via `wp-config.php` or environment variable:

```php
define( 'OPENAI_API_KEY', 'your-key-here' );
```

```bash
export OPENAI_API_KEY="your-key-here"
```

## Features

### Alt Text Generation

Automatically generates descriptive alt text for images — improving accessibility and SEO with zero manual effort.

- **Auto-generate on upload** — alt text is filled in the moment an image hits the Media Library
- **Generate for existing images** — click "Generate Alt Text" from the attachment edit screen
- **Batch processing via WP-CLI** — backfill alt text for your entire media library
- **Respects manual alt text** — never overwrites unless you explicitly regenerate
- **Customizable prompt** — tailor the generation instructions from the settings page

WP-CLI examples:

```bash
wp travelopia-wp-ai alt-text generate --missing   # only images without alt text
wp travelopia-wp-ai alt-text generate --all        # every image
wp travelopia-wp-ai alt-text generate --ids=1,2,3  # specific attachments
```

## Hooks

### Filters

**`travelopia_wordpress_ai_provider`** — Switch the active AI provider.

```php
add_filter(
	'travelopia_wordpress_ai_provider',
	fn() => 'openai'
);
```

---

**`travelopia_wordpress_ai_alt_text_generation_options`** — Override model, temperature, or system instruction for alt text generation.

```php
add_filter(
	'travelopia_wordpress_ai_alt_text_generation_options',
	function ( array $options, int $attachment_id ): array {
		$options['model']              = 'anthropic.claude-3-5-sonnet-20241022-v2:0';
		$options['temperature']        = 0.5;
		$options['system_instruction'] = 'You are an accessibility expert.';
		return $options;
	},
	10,
	2
);
```

---

**`travelopia_wordpress_ai_alt_text_include_context`** — By default, the plugin sends the image title and filename as additional context alongside the image to help the AI produce more accurate alt text. Set this to `false` to send only the image itself.

```php
add_filter(
	'travelopia_wordpress_ai_alt_text_include_context',
	'__return_false'
);
```

---

**`travelopia_wordpress_ai_settings_capability`** — Control which user role can access the plugin settings page. Defaults to `manage_options` (Administrator).

```php
add_filter(
	'travelopia_wordpress_ai_settings_capability',
	fn() => 'edit_posts'
);
```

### Actions

**`travelopia_wordpress_ai_alt_text_generated`** — Fired after alt text is successfully generated for an attachment.

```php
add_action(
	'travelopia_wordpress_ai_alt_text_generated',
	function ( int $attachment_id, string $alt_text ): void {
		// Log, notify, or post-process.
	},
	10,
	2
);
```

---

**`travelopia_wordpress_ai_bedrock_error`** / **`travelopia_wordpress_ai_open_ai_error`** — Fired when a provider encounters an error during generation.

```php
add_action(
	'travelopia_wordpress_ai_bedrock_error',
	function ( string $code, string $message, array $data ): void {
		// Handle error.
	},
	10,
	3
);
```

## Local Development

Requires [Docker](https://www.docker.com/products/docker-desktop/) to be installed and running.

```bash
npm run start            # install deps and start the dev environment
npm run wp-env:start     # start the dev environment (without installing deps)
npm run wp-env:stop      # stop the dev environment
npm run test:php:setup   # install deps in container and run PHP tests (first run)
npm run test:php         # run PHP tests
```

## Privacy

Images are sent to your configured AI provider for analysis when generating alt text.
