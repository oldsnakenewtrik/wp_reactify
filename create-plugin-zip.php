<?php
/**
 * Simple ReactifyWP Plugin ZIP Creator
 * Creates a working WordPress plugin ZIP file
 */

echo "🚀 Creating ReactifyWP Plugin ZIP...\n\n";

$plugin_name = 'reactifywp';
$version = '1.0.1';
$zip_file = __DIR__ . '/' . $plugin_name . '-v' . $version . '.zip';

// Remove existing ZIP
if (file_exists($zip_file)) {
    unlink($zip_file);
    echo "🗑️ Removed existing ZIP file\n";
}

// Create ZIP
$zip = new ZipArchive();
$result = $zip->open($zip_file, ZipArchive::CREATE | ZipArchive::OVERWRITE);

if ($result !== TRUE) {
    die("❌ Cannot create ZIP file: $zip_file (Error: $result)\n");
}

echo "📦 Creating ZIP file...\n";

// Files to include (relative to current directory)
$files_to_include = [
    // Main plugin file
    'reactifywp.php' => 'reactifywp/reactifywp.php',
    
    // Documentation
    'README.md' => 'reactifywp/README.md',
    'QUICK-START.md' => 'reactifywp/QUICK-START.md',
    'verify-installation.php' => 'reactifywp/verify-installation.php',
    'test-functionality.php' => 'reactifywp/test-functionality.php',
    'create-test-zip.php' => 'reactifywp/create-test-zip.php',
    
    // Composer
    'composer.json' => 'reactifywp/composer.json',
    
    // Core classes
    'inc/class-admin.php' => 'reactifywp/inc/class-admin.php',
    'inc/class-asset-manager.php' => 'reactifywp/inc/class-asset-manager.php',
    'inc/class-database.php' => 'reactifywp/inc/class-database.php',
    'inc/class-project.php' => 'reactifywp/inc/class-project.php',
    'inc/class-shortcode.php' => 'reactifywp/inc/class-shortcode.php',
    'inc/class-settings.php' => 'reactifywp/inc/class-settings.php',
    'inc/class-file-uploader.php' => 'reactifywp/inc/class-file-uploader.php',
    'inc/class-zip-extractor.php' => 'reactifywp/inc/class-zip-extractor.php',
    'inc/class-security-validator.php' => 'reactifywp/inc/class-security-validator.php',
    'inc/class-error-handler.php' => 'reactifywp/inc/class-error-handler.php',
    
    // Frontend assets
    'assets/dist/frontend.js' => 'reactifywp/assets/dist/frontend.js',
    'assets/dist/frontend.css' => 'reactifywp/assets/dist/frontend.css',
    
    // Admin assets
    'assets/css/admin.css' => 'reactifywp/assets/css/admin.css',
    'assets/js/admin.js' => 'reactifywp/assets/js/admin.js',
    'assets/js/upload-manager.js' => 'reactifywp/assets/js/upload-manager.js',
    
    // Test React app
    'test-react-app/index.html' => 'reactifywp/test-react-app/index.html',
    'test-react-app/package.json' => 'reactifywp/test-react-app/package.json',
    'test-react-app/README.md' => 'reactifywp/test-react-app/README.md',
];

$added_count = 0;
$missing_count = 0;

foreach ($files_to_include as $source => $zip_path) {
    if (file_exists(__DIR__ . '/' . $source)) {
        $zip->addFile(__DIR__ . '/' . $source, $zip_path);
        echo "  ✅ $source\n";
        $added_count++;
    } else {
        echo "  ⚠️ $source (missing)\n";
        $missing_count++;
    }
}

// Add additional optional files if they exist
$optional_files = [
    'inc/class-cdn-manager.php' => 'reactifywp/inc/class-cdn-manager.php',
    'inc/class-performance-optimizer.php' => 'reactifywp/inc/class-performance-optimizer.php',
    'inc/class-gutenberg-block.php' => 'reactifywp/inc/class-gutenberg-block.php',
    'inc/class-elementor-widget.php' => 'reactifywp/inc/class-elementor-widget.php',
    'assets/js/wp-bridge.js' => 'reactifywp/assets/js/wp-bridge.js',
    'assets/js/react-integration.js' => 'reactifywp/assets/js/react-integration.js',
];

echo "\n📁 Adding optional files...\n";
foreach ($optional_files as $source => $zip_path) {
    if (file_exists(__DIR__ . '/' . $source)) {
        $zip->addFile(__DIR__ . '/' . $source, $zip_path);
        echo "  ✅ $source\n";
        $added_count++;
    }
}

// Create a simple installation guide
$install_guide = "# ReactifyWP Installation Guide

## Quick Install
1. Upload this ZIP file via WordPress Admin → Plugins → Add New → Upload Plugin
2. Activate the plugin
3. Go to ReactifyWP in admin menu
4. Upload your React apps!

## Requirements
- WordPress 5.0+
- PHP 7.4+
- ZIP extension enabled

## First Steps
1. Test installation: visit yoursite.com/wp-content/plugins/reactifywp/verify-installation.php
2. Upload a React app ZIP file via ReactifyWP admin
3. Use [reactify slug=\"your-app\"] shortcode in posts/pages

## Support
- GitHub: https://github.com/oldsnakenewtrik/wp_reactify
- Documentation: Check README.md

Version: $version
Built: " . date('Y-m-d H:i:s') . "
";

$zip->addFromString('reactifywp/INSTALL.txt', $install_guide);
echo "  ✅ INSTALL.txt (generated)\n";
$added_count++;

// Close ZIP
$zip->close();

// Get file info
$zip_size = filesize($zip_file);
$zip_size_mb = round($zip_size / 1024 / 1024, 2);

echo "\n🎉 Plugin ZIP created successfully!\n";
echo "📦 File: " . basename($zip_file) . "\n";
echo "📊 Size: {$zip_size_mb} MB\n";
echo "📋 Files: $added_count added";
if ($missing_count > 0) {
    echo ", $missing_count missing";
}
echo "\n";
echo "📍 Location: $zip_file\n\n";

// Verify ZIP
echo "🔍 Verifying ZIP contents...\n";
$verify_zip = new ZipArchive();
if ($verify_zip->open($zip_file) === TRUE) {
    echo "✅ ZIP file is valid\n";
    echo "📋 Contains {$verify_zip->numFiles} files\n";
    
    // Check essential files
    $essential_files = [
        'reactifywp/reactifywp.php',
        'reactifywp/inc/class-admin.php',
        'reactifywp/inc/class-shortcode.php',
        'reactifywp/assets/dist/frontend.js',
        'reactifywp/assets/dist/frontend.css'
    ];
    
    $all_essential_found = true;
    foreach ($essential_files as $essential_file) {
        if ($verify_zip->locateName($essential_file) !== false) {
            echo "  ✅ $essential_file\n";
        } else {
            echo "  ❌ $essential_file (MISSING!)\n";
            $all_essential_found = false;
        }
    }
    
    if ($all_essential_found) {
        echo "\n🎯 All essential files found! Plugin should work correctly.\n";
    } else {
        echo "\n⚠️ Some essential files are missing. Plugin may not work properly.\n";
    }
    
    $verify_zip->close();
} else {
    echo "❌ ZIP file verification failed\n";
}

echo "\n🚀 Ready for WordPress!\n";
echo "📥 Upload to: WordPress Admin → Plugins → Add New → Upload Plugin\n";
echo "🔧 Then activate ReactifyWP and start uploading React apps!\n\n";

// Show next steps
echo "📋 Next Steps:\n";
echo "1. Upload $zip_file to WordPress\n";
echo "2. Activate the plugin\n";
echo "3. Visit ReactifyWP admin page\n";
echo "4. Run verify-installation.php to test\n";
echo "5. Upload your first React app!\n\n";

echo "✨ Happy React-ing in WordPress! 🎉\n";
?>
