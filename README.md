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

Switch providers with a single filter:

```php
add_filter( 'travelopia_wordpress_ai_provider', fn() => 'openai' );
```

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

## Privacy

Images are sent to your configured AI provider for analysis when generating alt text.
