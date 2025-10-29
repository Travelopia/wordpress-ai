# Alt Text Generator Web Components

A modular web component system for AI-powered alt text generation in WordPress media attachments.

## LLM Quick Reference

**Component Hierarchy:** `alt-text-container` → (`alt-text-generator` | `alt-text-accept` + `alt-text-reject` + `alt-text-generator`)

**Modes:**

- `default`: Shows single generator button (generate/regenerate)
- `regenerate`: Shows accept + reject + regenerate buttons

**Data Flow:**

- Global: `window.travelopiaWpAi` (config, URLs, labels, attachment data, nonces)
- Attributes: `attachment-id`, `mode` (observed, reactive)
- Events: `alttext:generate`, `alttext:accepted`, `alttext:rejected`, `alttext:error`

**API Calls:**

- Accept: `POST /wp/v2/media/{id}` with `alt_text` (REST API, needs X-WP-Nonce)
- Generator/Reject: Page navigation via URLs from `window.travelopiaWpAi.urls`

**Files:** 6 total (index.ts, declarations.d.ts, 4 component files)

---

## Overview

This directory contains a web component-based system for managing AI-generated alt text in the WordPress media editor. The components replace traditional PHP templating with a reactive, modular architecture that handles two distinct workflows: initial generation and regeneration with approval.

## Architecture

### File Structure

```
alt-text-generator/
├── index.ts                    # Entry point, DOM initialization
├── declarations.d.ts           # TypeScript type definitions
├── alt-text-container.ts       # Parent orchestrator component
├── alt-text-generator.ts       # Generate/regenerate button
├── alt-text-accept.ts          # Accept button (REST API)
└── alt-text-reject.ts          # Reject button (navigation)
```

### Component Hierarchy

```
<alt-text-container mode="default|regenerate" attachment-id="123">

  [Default Mode]
  └── <alt-text-generator>        # Single button

  [Regenerate Mode]
  ├── <alt-text-accept>            # Save via REST API
  ├── <alt-text-reject>            # Discard and reload
  └── <alt-text-generator>         # Generate new version
</alt-text-container>
```

## Components

### alt-text-container

**Purpose:** Parent container that conditionally renders child components based on operating mode.

**Responsibilities:**

- Mode-based conditional rendering
- Propagates attributes to children
- Re-renders UI when mode changes

**Attributes:**

- `mode`: `"default"` | `"regenerate"` (observed)
- `attachment-id`: WordPress attachment post ID (observed)

**Behavior:**

- Default mode: Renders only `alt-text-generator`
- Regenerate mode: Renders `alt-text-accept`, `alt-text-reject`, and `alt-text-generator`
- Observes attribute changes and triggers re-render
- Updates `attachment-id` on all children when changed

### alt-text-generator

**Purpose:** Button that initiates alt text generation or regeneration via page navigation.

**Responsibilities:**

- Displays context-aware button text
- Navigates to generation URL
- Dispatches pre-navigation event

**Attributes:**

- `attachment-id`: WordPress attachment post ID
- `mode`: Current operating mode

**Behavior:**

- Button text: "Generate Alt Text" (empty alt text) or "Regenerate Alt Text" (existing alt text)
- Click → Dispatches `alttext:generate` event → Navigates to `window.travelopiaWpAi.urls.generate` or `urls.regenerate`
- URL is determined by checking if `window.travelopiaWpAi.attachment.altText` is empty

**Custom Events:**

- `alttext:generate` (bubbles)
  - `detail.attachmentId`: Attachment ID
  - `detail.mode`: Current mode
  - `detail.href`: Target URL

### alt-text-accept

**Purpose:** Button that saves generated alt text via WordPress REST API.

**Responsibilities:**

- Reads alt text from WordPress textarea
- Saves via REST API (async)
- Manages loading states
- Redirects on success

**Attributes:**

- `attachment-id`: WordPress attachment post ID

**Behavior:**

- Click → Changes text to "Saving..." → POST to `/wp/v2/media/{id}` → Redirect to clean edit URL
- Uses REST API nonce from `window.travelopiaWpAi.nonces.rest`
- Reads alt text from `textarea[name="_wp_attachment_image_alt"]#attachment_alt`
- Success: Redirects to `post={id}&action=edit` (removes generation parameters)
- Failure: Restores button text, dispatches error event

**API Integration:**

```typescript
POST {root}/wp/v2/media/{attachmentId}
Headers:
  Content-Type: application/json
  X-WP-Nonce: {window.travelopiaWpAi.nonces.rest}
Body:
  { "alt_text": "..." }
```

**Custom Events:**

- `alttext:accepted` (bubbles, on success)
  - `detail.attachmentId`: Attachment ID
  - `detail.responseData`: API response
- `alttext:error` (bubbles, on failure)
  - `detail.attachmentId`: Attachment ID
  - `detail.message`: Error message
  - `detail.data`: Error data (optional)
  - `detail.source`: "accept-button"

### alt-text-reject

**Purpose:** Button that discards generated alt text and reloads the page.

**Responsibilities:**

- Navigates to reject URL
- Dispatches pre-navigation event

**Attributes:**

- `attachment-id`: WordPress attachment post ID
- `mode`: Current operating mode

**Behavior:**

- Click → Dispatches `alttext:rejected` event → Navigates to `window.travelopiaWpAi.urls.reject`
- Reject URL typically clears generation parameters and reloads with clean state

**Custom Events:**

- `alttext:rejected` (bubbles)
  - `detail.attachmentId`: Attachment ID
  - `detail.mode`: Current mode
  - `detail.href`: Target URL

## Data Flow

### Global Configuration

All components depend on `window.travelopiaWpAi` object injected via WordPress localization:

```typescript
window.travelopiaWpAi = {
	api: {
		root: string, // REST API root (/wp-json/)
		nonce: string, // API nonce
	},
	nonces: {
		rest: string, // REST API nonce for headers
	},
	attachment: {
		id: number, // Current attachment ID
		altText: string, // Current/generated alt text
		mode: "default" | "regenerate",
	},
	urls: {
		generate: string, // URL to trigger generation
		regenerate: string, // URL to trigger regeneration
		reject: string, // URL to discard and reload
	},
	labels: {
		generateAltText: string,
		regenerateAltText: string,
		accept: string,
		reject: string,
		regenerate: string,
		saving: string,
	},
};
```

### Initialization Flow

1. **DOMContentLoaded**: `index.ts` waits for DOM ready
2. **Validation**: Checks for `window.travelopiaWpAi.attachment` and textarea element
3. **Wrapper Creation**: Wraps existing textarea in `.alt-text-wrapper` div
4. **Container Injection**: Creates `<alt-text-container>` with `mode` and `attachment-id` attributes
5. **Auto-render**: Container observes attributes and renders appropriate children

### Attribute Reactivity

Components use `observedAttributes` and `attributeChangedCallback` for reactive updates:

```typescript
static get observedAttributes() {
  return ['mode', 'attachment-id'];
}

attributeChangedCallback(name, oldValue, newValue) {
  if (oldValue === newValue) return;

  switch (name) {
    case 'mode':
      this.mode = newValue;
      this.render(); // Re-render children
      break;
    case 'attachment-id':
      this.attachmentId = newValue;
      this.updateChildAttachmentIds();
      break;
  }
}
```

### Custom Events

All components dispatch bubbling custom events for external listeners:

```typescript
// Example: Listen for acceptance
document.addEventListener("alttext:accepted", (event) => {
	console.log("Alt text saved:", event.detail.attachmentId);
});

// Example: Listen for errors
document.addEventListener("alttext:error", (event) => {
	console.error("Error:", event.detail.message);
});
```

## Usage

### WordPress Integration

The PHP backend (`inc/namespace.php`) should:

1. **Localize Script**:

```php
wp_localize_script('travelopia-wp-ai-editor', 'travelopiaWpAi', [
    'api' => [
        'root' => esc_url_raw(rest_url()),
        'nonce' => wp_create_nonce('wp_rest')
    ],
    'nonces' => [
        'rest' => wp_create_nonce('wp_rest')
    ],
    'attachment' => [
        'id' => $post->ID,
        'altText' => get_post_meta($post->ID, '_wp_attachment_image_alt', true),
        'mode' => $mode
    ],
    'urls' => [
        'generate' => add_query_arg(['generate_alt' => 'true'], $edit_url),
        'regenerate' => add_query_arg(['generate_alt' => 'true'], $edit_url),
        'reject' => remove_query_arg(['generate_alt', 'alt_text_generated'])
    ],
    'labels' => [
        'generateAltText' => __('Generate Alt Text', 'wordpress-ai'),
        'regenerateAltText' => __('Regenerate', 'wordpress-ai'),
        'accept' => __('Accept', 'wordpress-ai'),
        'reject' => __('Reject', 'wordpress-ai'),
        'saving' => __('Saving...', 'wordpress-ai')
    ]
]);
```

2. **Ensure Textarea Exists**:
   The standard WordPress alt text textarea must be present:

```html
<textarea name="_wp_attachment_image_alt" id="attachment_alt"></textarea>
```

3. **Enqueue Script**:
   The compiled script will automatically initialize on DOM ready.

### Workflow Examples

#### Initial Generation (Default Mode)

1. User opens media editor (alt text empty)
2. Container renders in `mode="default"`
3. Shows "Generate Alt Text" button
4. Click → Navigate to generate URL → Backend processes → Reload with generated text
5. Container switches to `mode="regenerate"`

#### Accepting Generated Text (Regenerate Mode)

1. User sees generated alt text in textarea
2. Container renders in `mode="regenerate"`
3. Shows "Accept", "Reject", "Regenerate" buttons
4. Click "Accept" → REST API saves alt text → Redirect to clean URL
5. Container switches to `mode="default"`

#### Rejecting Generated Text (Regenerate Mode)

1. User doesn't like generated alt text
2. Click "Reject" → Navigate to reject URL → Backend clears state → Reload
3. Container switches to `mode="default"`
4. Original alt text (if any) is restored

## Styling

Components apply WordPress button classes:

- `class="button button-primary"` on all buttons

External styling should target:

- `.alt-text-wrapper` (wrapper div)
- `alt-text-container` (container element)
- `alt-text-generator`, `alt-text-accept`, `alt-text-reject` (button elements)

Recommended SCSS:

```scss
.alt-text-wrapper {
	display: flex;
	flex-direction: column;
	gap: 10px;
}

alt-text-container {
	display: flex;
	gap: 8px;
	flex-wrap: wrap;
}
```

## Error Handling

### Accept Button Errors

The accept button handles errors gracefully:

1. **No attachment ID**: Dispatches error, exits early
2. **No REST nonce**: Dispatches error, re-enables button
3. **Network error**: Dispatches error with `detail.message = "Network error"`
4. **API error**: Dispatches error with `detail.message = "API Error: {status}"`

All errors dispatch `alttext:error` event with:

```typescript
{
  detail: {
    attachmentId: string,
    message: string,
    data?: any,
    source: 'accept-button'
  }
}
```

### Generator/Reject Errors

These components rely on backend URL handling. If URLs are invalid, navigation will fail at the browser level.

## TypeScript Support

Full type definitions are provided in `declarations.d.ts`. To use types in external code:

```typescript
// TypeScript automatically recognizes window.travelopiaWpAi
const { attachment, urls } = window.travelopiaWpAi;

// Custom events are typed via standard DOM events
document.addEventListener("alttext:accepted", (event: CustomEvent) => {
	const { attachmentId, responseData } = event.detail;
});
```

## Building

Compile TypeScript to JavaScript using your build tool (webpack, rollup, etc.):

```bash
npm run build
# or for development
npm run watch
```

## Testing Scenarios

1. **Empty alt text**: Should show "Generate Alt Text" button
2. **Existing alt text**: Should show "Regenerate Alt Text" button
3. **After generation**: Should show accept/reject/regenerate buttons
4. **Accept success**: Should save via REST API and redirect
5. **Accept failure**: Should show error, restore button
6. **Reject**: Should navigate to reject URL
7. **Regenerate**: Should navigate to generate URL
8. **Attribute changes**: Should update children reactively

## Best Practices

1. **Always provide `window.travelopiaWpAi`**: Components will exit gracefully if missing, but no UI will render
2. **Use observed attributes for state**: Leverage reactive attribute changes instead of manual updates
3. **Listen to custom events**: Hook into events for logging, analytics, or additional functionality
4. **Handle errors externally**: Listen to `alttext:error` events for user notifications
5. **Style via classes**: Components apply WordPress classes, extend with CSS
6. **Test REST API nonce**: Accept button requires valid REST API authentication

## Troubleshooting

| Issue                   | Cause                          | Solution                              |
| ----------------------- | ------------------------------ | ------------------------------------- |
| No buttons appear       | `window.travelopiaWpAi` not defined    | Check script localization             |
| Accept fails silently   | Invalid REST nonce             | Verify `wp_create_nonce('wp_rest')`   |
| Wrong button text       | `window.travelopiaWpAi.labels` missing | Ensure all labels are localized       |
| Attributes not updating | Not in `observedAttributes`    | Add to static getter array            |
| Events not firing       | No listener attached           | Add event listener before interaction |

## Dependencies

- **Runtime**: Modern browser with Custom Elements API (ES6+)
- **Build**: TypeScript compiler
- **WordPress**: REST API v2, media endpoints

## Browser Support

- Chrome/Edge 67+
- Firefox 63+
- Safari 10.1+
- No polyfills required (assumes modern WordPress admin environment)

## Future Enhancements

- [ ] Inline error messages (currently only events)
- [ ] Loading spinners for REST API calls
- [ ] Undo/redo support
- [ ] Batch alt text generation for multiple images
- [ ] Preview modal before accepting
- [ ] Character count display
- [ ] Accessibility improvements (ARIA live regions)

## License

See root project license.

## Support

For issues or questions, refer to the main WordPress AI plugin documentation or open an issue in the project repository.
