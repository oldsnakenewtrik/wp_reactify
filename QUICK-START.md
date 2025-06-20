# ReactifyWP Quick Start Guide

## 🚀 Getting Started

### Step 1: Install the Plugin

1. Upload the `reactifywp` folder to `/wp-content/plugins/`
2. Run `composer install` in the plugin directory (if not already done)
3. Activate the plugin through the WordPress admin

### Step 2: Test Installation

1. Copy `test-functionality.php` to your WordPress root directory
2. Visit `yoursite.com/test-functionality.php` in your browser
3. Check that all tests pass ✅

### Step 3: Create Your First React App

#### Option A: Use the Test App
1. Zip the `test-react-app` folder
2. Go to **WordPress Admin → ReactifyWP → Upload Project**
3. Upload the ZIP file with slug `test-app`
4. Add `[reactify slug="test-app"]` to any post or page

#### Option B: Create Your Own App
1. Create a folder with your React app files
2. Ensure you have an `index.html` file as the entry point
3. Zip the entire folder
4. Upload via ReactifyWP admin

### Step 4: Display Your App

Use the shortcode in any post, page, or widget:

```
[reactify slug="your-app-slug"]
```

#### Advanced Options:
```
[reactify slug="test-app" theme="dark" height="500px" loading="lazy"]
```

## 🔧 Troubleshooting

### Common Issues:

1. **Plugin won't activate**
   - Check PHP version (7.4+ required)
   - Run `composer install` in plugin directory
   - Check file permissions

2. **Upload fails**
   - Check upload directory permissions
   - Verify file size limits
   - Ensure ZIP contains valid files

3. **App doesn't display**
   - Check browser console for errors
   - Verify the app slug is correct
   - Ensure `index.html` exists in uploaded files

4. **Styling issues**
   - Check for CSS conflicts
   - Try different theme options
   - Use browser dev tools to debug

### Debug Mode:

Add to `wp-config.php`:
```php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
```

Enable ReactifyWP debug mode:
```
[reactify slug="test-app" debug="true"]
```

## 📁 File Structure

```
reactifywp/
├── assets/
│   ├── dist/
│   │   ├── frontend.js     # Main frontend JavaScript
│   │   └── frontend.css    # Frontend styles
│   └── js/
│       ├── wp-bridge.js    # WordPress integration
│       └── react-integration.js
├── inc/                    # PHP classes
├── test-react-app/         # Sample React app
├── test-functionality.php  # Test script
└── reactifywp.php         # Main plugin file
```

## 🎯 Next Steps

1. **Page Builder Integration**: Use ReactifyWP blocks in Gutenberg or Elementor
2. **Performance**: Enable CDN and caching in settings
3. **Security**: Review security settings and file type restrictions
4. **Customization**: Explore advanced shortcode options

## 📚 Documentation

- **Shortcode Reference**: See `inc/class-shortcode.php` for all options
- **API Reference**: Check `assets/js/wp-bridge.js` for WordPress integration
- **Hooks & Filters**: Review plugin files for customization points

## 🆘 Support

If you encounter issues:

1. Run the test script (`test-functionality.php`)
2. Check WordPress debug logs
3. Verify file permissions and PHP version
4. Test with the sample React app first

## 🔄 Updates

To update ReactifyWP:

1. Backup your current installation
2. Replace plugin files (keep `wp-content/uploads/reactify-projects/`)
3. Run `composer install` if needed
4. Check for any breaking changes in the changelog

---

**Happy React-ing in WordPress! 🎉**
