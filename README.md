# ReactifyWP

> Democratise React on WordPress: one-click deployment of any compiled React SPA/MPA without touching the theme or server.

## Overview

ReactifyWP is a WordPress plugin that allows agencies, freelancers, and WordPress site owners to easily embed modern React applications into their WordPress sites. Upload a compiled React build as a ZIP file, and embed it anywhere using shortcodes - no theme modifications or server configuration required.

## Features

### Core Functionality
- **One-Click Upload**: Upload React production builds as ZIP files
- **Shortcode Integration**: Embed apps anywhere with `[reactify slug="your-app"]`
- **Multiple Projects**: Manage multiple React apps with isolated assets
- **Multisite Support**: Per-site isolation in WordPress multisite networks

### Developer Experience
- **Asset Management**: Automatic JS/CSS enqueuing with cache busting
- **Scoped Styles**: Optional style isolation to prevent theme conflicts
- **WP-CLI Support**: Command-line tools for CI/CD integration
- **REST API**: Programmatic project management

### Page Builder Integration
- **Gutenberg Block**: Drag-and-drop React app insertion
- **Elementor Widget**: Native Elementor integration
- **Beaver Builder**: Compatible with popular page builders

## Requirements

- **WordPress**: 6.5 or higher
- **PHP**: 7.4 or higher
- **Multisite**: Supported (optional)

## Installation

### From WordPress Admin
1. Download the plugin ZIP file
2. Go to `Plugins > Add New > Upload Plugin`
3. Upload the ZIP file and activate

### Manual Installation
1. Upload the plugin folder to `/wp-content/plugins/`
2. Activate the plugin through the WordPress admin

## Quick Start

### 1. Prepare Your React App

Ensure your React app is built for production and uses relative paths:

```json
// package.json
{
  "homepage": "."
}
```

Build your app:
```bash
npm run build
# or
yarn build
```

### 2. Create ZIP File

Zip the contents of your `build` or `dist` folder (not the folder itself):

```
your-app.zip
├── index.html
├── static/
│   ├── js/
│   │   ├── main.abc123.js
│   │   └── ...
│   └── css/
│       ├── main.def456.css
│       └── ...
└── asset-manifest.json (optional)
```

### 3. Upload to WordPress

1. Go to `Settings > ReactifyWP` in your WordPress admin
2. Upload your ZIP file
3. Set a unique project slug (e.g., `my-calculator`)
4. Set a shortcode name (defaults to slug)
5. Click "Upload Project"

### 4. Embed in Content

Use the shortcode anywhere in your WordPress content:

```
[reactify slug="my-calculator"]
```

Or use the Gutenberg block for visual editing.

## React App Requirements

### Mount Point

Your React app must mount to a dynamic container ID:

```javascript
// Instead of mounting to a fixed ID
ReactDOM.render(<App />, document.getElementById('root'));

// Mount to the dynamic ReactifyWP container
const containerId = 'reactify-' + window.reactifySlug;
ReactDOM.render(<App />, document.getElementById(containerId));
```

ReactifyWP automatically provides `window.reactifySlug` with the project slug.

### Asset Paths

Use relative paths in your build configuration:

```javascript
// webpack.config.js or vite.config.js
export default {
  base: './', // Vite
  // or
  publicPath: './', // Webpack
}
```

## Configuration

### Plugin Settings

Access settings at `Settings > ReactifyWP`:

- **Max Upload Size**: Maximum ZIP file size (default: 50MB)
- **Scoped Styles**: Enable style isolation (recommended)
- **Cache Busting**: Enable asset versioning
- **Defer JS Loading**: Improve page load performance

### Shortcode Options

```
[reactify slug="my-app" class="custom-wrapper" style="width: 100%; height: 400px;"]
```

- `slug`: Project identifier (required)
- `class`: Additional CSS classes
- `style`: Inline styles for the container

## WP-CLI Commands

```bash
# List all projects
wp reactifywp list

# Upload a project
wp reactifywp upload my-app.zip --slug=my-app --shortcode=my-app

# Delete a project
wp reactifywp delete my-app

# Get project info
wp reactifywp info my-app
```

## REST API

### List Projects
```
GET /wp-json/reactify/v1/projects
```

### Upload Project
```
POST /wp-json/reactify/v1/projects
Content-Type: multipart/form-data

{
  "file": [ZIP file],
  "slug": "project-slug",
  "shortcode": "shortcode-name"
}
```

### Delete Project
```
DELETE /wp-json/reactify/v1/projects/{slug}
```

## Security

ReactifyWP implements multiple security measures:

- **File Type Validation**: Only ZIP files are accepted
- **ZIP Bomb Protection**: Size and extraction limits
- **Path Traversal Prevention**: Sanitized file paths
- **Capability Checks**: Requires `manage_options` permission
- **Nonce Verification**: CSRF protection on all forms

## Performance

- **Asset Versioning**: Automatic cache busting based on file modification time
- **Deferred Loading**: JavaScript files loaded with `defer` attribute
- **Scoped Styles**: Optional CSS isolation to prevent conflicts
- **Optimized Queries**: Efficient database operations

## Troubleshooting

### Common Issues

**App doesn't render**
- Check browser console for JavaScript errors
- Verify your app mounts to the correct container ID
- Ensure all assets use relative paths

**Styles conflict with theme**
- Enable "Scoped Styles" in plugin settings
- Use CSS specificity in your React app styles

**Upload fails**
- Check file size limits in WordPress and server
- Verify ZIP file structure (contents, not folder)
- Ensure proper file permissions

### Debug Mode

Enable WordPress debug mode to see detailed error messages:

```php
// wp-config.php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
```

## Contributing

We welcome contributions! Please see our [Contributing Guide](CONTRIBUTING.md) for details.

## License

This plugin is licensed under the GPL v2 or later.

## Support

- **Documentation**: [Full documentation](https://github.com/your-username/reactifywp/wiki)
- **Issues**: [GitHub Issues](https://github.com/your-username/reactifywp/issues)
- **Community**: [WordPress.org Support Forum](https://wordpress.org/support/plugin/reactifywp/)
