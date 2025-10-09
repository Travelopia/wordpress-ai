## Travelopia WP AI WordPress Plugin

**What it does**: Automatically generates image alt text using AI when you upload an image to the Media Library, or when editing an existing image.

### Features
- **Auto-generate on upload**: Fills the Alt Text field right after the file is uploaded.
- **Generate on edit**: From the Edit Media screen, trigger generation for existing images.
- **Respects manual alt text**: Won’t overwrite existing alt text unless you explicitly regenerate.
- **Pluggable provider**: Designed to work with common AI APIs (provider wiring can be swapped).

### Requirements
- **WordPress**: 6.0+
- **PHP**: 8.0+
- **AI provider key**: You’ll need an API key for your chosen provider.

### Install
1. Copy the `wordpress-ai` folder into `wp-content/plugins/`.
2. Activate "Travelopia WordPress AI" in Plugins.

### Configure
- Set your OpenAI API key (environment variable or wp-config.php).
- Optional: Update prompt/language or control overwrite behavior.

**Set OpenAI API Key:**

Option 1 - Environment Variable (Recommended):
```bash
export OPENAI_API_KEY="your-openai-api-key-here"
```

Option 2 - WordPress wp-config.php:
```php
define( 'OPENAI_API_KEY', 'your-openai-api-key-here' );
```

Option 3 - Server Environment:
Add to your server's environment variables.

**Get an OpenAI API Key:**
1. Go to https://platform.openai.com/api-keys
2. Create a new API key
3. Copy the key and set it using one of the methods above

**Note:** The plugin uses GPT-4o Mini for image analysis, which supports vision capabilities.

### Usage
- **New uploads**: Go to Media > Add New and upload an image. Alt text is generated automatically.
- **Existing images**: Open Media > Library, edit an image, and click “Generate Alt Text”

### Privacy
- Images (or related metadata) may be sent to OpenAI to generate alt text.

### Troubleshooting
- **No alt text generated**: Check OpenAI API key, provider availability, and that the site can make outbound requests.
- **Overwrites**: If you don't want overwrites, disable the regenerate action or ensure the plugin is configured to skip when alt text exists.

### Roadmap
- Batch backfill for existing media
- Multi-language support and prompt tuning
- Provider selection UI and per-role permissions
