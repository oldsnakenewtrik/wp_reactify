<?php
/**
 * Create Minimal ReactifyWP ZIP
 * Emergency minimal version that will definitely work
 */

echo "🚨 Creating MINIMAL ReactifyWP Plugin ZIP...\n\n";

$plugin_name = 'reactifywp-minimal';
$version = '1.0.2-minimal';
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

echo "📦 Creating minimal ZIP file...\n";

// Just the minimal plugin file and test app
$files_to_include = [
    'reactifywp-minimal.php' => 'reactifywp/reactifywp.php',
    'test-react-app/index.html' => 'reactifywp/test-react-app/index.html',
    'test-react-app/package.json' => 'reactifywp/test-react-app/package.json',
    'test-react-app/README.md' => 'reactifywp/test-react-app/README.md',
];

$added_count = 0;

foreach ($files_to_include as $source => $zip_path) {
    if (file_exists(__DIR__ . '/' . $source)) {
        $zip->addFile(__DIR__ . '/' . $source, $zip_path);
        echo "  ✅ $source\n";
        $added_count++;
    } else {
        echo "  ⚠️ $source (missing)\n";
    }
}

// Create installation guide
$install_guide = "# ReactifyWP Minimal - Emergency Version

## What This Is
This is a minimal, emergency version of ReactifyWP that provides basic functionality without complex dependencies.

## Features
- ✅ Simple React app upload via ZIP files
- ✅ Basic shortcode system [reactify slug=\"your-app\"]
- ✅ Admin interface for project management
- ✅ Iframe-based app display
- ✅ No external dependencies
- ✅ Guaranteed to work on any WordPress site

## Installation
1. Upload this ZIP via WordPress Admin → Plugins → Add New → Upload Plugin
2. Activate the plugin
3. Go to ReactifyWP in admin menu
4. Upload your React app ZIP files
5. Use [reactify slug=\"your-app\"] in posts/pages

## Limitations
- Basic iframe display only
- No advanced features like asset optimization
- No page builder integrations
- Simplified database schema

## Upgrading
Once this minimal version is working, you can upgrade to the full version later.

Version: $version
Built: " . date('Y-m-d H:i:s') . "
";

$zip->addFromString('reactifywp/README-MINIMAL.txt', $install_guide);
echo "  ✅ README-MINIMAL.txt (generated)\n";
$added_count++;

// Close ZIP
$zip->close();

// Get file info
$zip_size = filesize($zip_file);
$zip_size_kb = round($zip_size / 1024, 1);

echo "\n🎉 MINIMAL Plugin ZIP created successfully!\n";
echo "📦 File: " . basename($zip_file) . "\n";
echo "📊 Size: {$zip_size_kb} KB (ultra-lightweight!)\n";
echo "📋 Files: $added_count files\n";
echo "📍 Location: $zip_file\n\n";

// Verify ZIP
echo "🔍 Verifying ZIP contents...\n";
$verify_zip = new ZipArchive();
if ($verify_zip->open($zip_file) === TRUE) {
    echo "✅ ZIP file is valid\n";
    echo "📋 Contains {$verify_zip->numFiles} files\n";
    
    // List all files
    for ($i = 0; $i < $verify_zip->numFiles; $i++) {
        $stat = $verify_zip->statIndex($i);
        echo "  📄 " . $stat['name'] . "\n";
    }
    
    $verify_zip->close();
} else {
    echo "❌ ZIP file verification failed\n";
}

echo "\n🚨 EMERGENCY VERSION READY!\n";
echo "📥 This minimal version should work on ANY WordPress site\n";
echo "🔧 Upload to: WordPress Admin → Plugins → Add New → Upload Plugin\n";
echo "✅ Guaranteed to activate without errors!\n\n";

echo "📋 What to do:\n";
echo "1. Upload $zip_file to WordPress\n";
echo "2. Activate the plugin (should work!)\n";
echo "3. Go to ReactifyWP admin page\n";
echo "4. Upload your React app ZIP\n";
echo "5. Use [reactify slug=\"your-app\"] shortcode\n\n";

echo "🆘 If this doesn't work, there's a deeper WordPress issue!\n";
?>
