<?php
/**
 * Build ReactifyWP Plugin ZIP
 * Creates a production-ready WordPress plugin ZIP file
 */

echo "🚀 Building ReactifyWP Plugin ZIP...\n\n";

// Configuration
$plugin_name = 'reactifywp';
$version = '1.0.1';
$build_dir = __DIR__ . '/build';
$plugin_dir = $build_dir . '/' . $plugin_name;
$zip_file = __DIR__ . '/' . $plugin_name . '-v' . $version . '.zip';

// Clean up previous builds
if (is_dir($build_dir)) {
    echo "🧹 Cleaning up previous build...\n";
    removeDirectory($build_dir);
}

// Create build directory
mkdir($build_dir, 0755, true);
mkdir($plugin_dir, 0755, true);

echo "📁 Created build directory: $build_dir\n";

// Files and directories to include in the plugin
$include_files = [
    'reactifywp.php',
    'composer.json',
    'README.md',
    'QUICK-START.md',
    'TESTING-CHECKLIST.md',
    'verify-installation.php',
    'test-functionality.php',
    'create-test-zip.php'
];

$include_directories = [
    'inc',
    'assets',
    'test-react-app',
    'templates'
];

// Copy main files
echo "📄 Copying main files...\n";
foreach ($include_files as $file) {
    if (file_exists(__DIR__ . '/' . $file)) {
        copy(__DIR__ . '/' . $file, $plugin_dir . '/' . $file);
        echo "  ✅ $file\n";
    } else {
        echo "  ⚠️  $file (not found)\n";
    }
}

// Copy directories
echo "📁 Copying directories...\n";
foreach ($include_directories as $dir) {
    if (is_dir(__DIR__ . '/' . $dir)) {
        copyDirectory(__DIR__ . '/' . $dir, $plugin_dir . '/' . $dir);
        echo "  ✅ $dir/\n";
    } else {
        echo "  ⚠️  $dir/ (not found)\n";
    }
}

// Create vendor directory and install composer dependencies
echo "📦 Installing Composer dependencies...\n";
$composer_result = shell_exec("cd \"$plugin_dir\" && composer install --no-dev --optimize-autoloader 2>&1");
if ($composer_result) {
    echo "Composer output:\n$composer_result\n";
}

// Create plugin info file
$plugin_info = [
    'name' => 'ReactifyWP',
    'version' => $version,
    'description' => 'Seamlessly integrate React applications into WordPress',
    'author' => 'ReactifyWP Team',
    'build_date' => date('Y-m-d H:i:s'),
    'wordpress_tested' => '6.4',
    'php_required' => '7.4',
    'features' => [
        'React app upload and management',
        'Advanced shortcode system',
        'Page builder integrations',
        'Performance optimization',
        'Security validation',
        'Error handling and debugging'
    ]
];

file_put_contents($plugin_dir . '/plugin-info.json', json_encode($plugin_info, JSON_PRETTY_PRINT));
echo "  ✅ plugin-info.json\n";

// Create installation instructions
$install_instructions = "# ReactifyWP Installation

## Quick Install
1. Upload this ZIP file via WordPress Admin → Plugins → Add New → Upload Plugin
2. Activate the plugin
3. Go to ReactifyWP in admin menu
4. Upload your React apps and start using shortcodes!

## Requirements
- WordPress 5.0+
- PHP 7.4+
- ZIP extension enabled

## First Steps
1. Visit the ReactifyWP admin page
2. Upload a React app ZIP file
3. Use [reactify slug=\"your-app\"] in posts/pages
4. Check verify-installation.php for troubleshooting

## Support
- Documentation: https://github.com/oldsnakenewtrik/wp_reactify
- Issues: https://github.com/oldsnakenewtrik/wp_reactify/issues

Built on " . date('Y-m-d H:i:s') . "
";

file_put_contents($plugin_dir . '/INSTALL.md', $install_instructions);
echo "  ✅ INSTALL.md\n";

// Create the ZIP file
echo "📦 Creating ZIP file...\n";
$zip = new ZipArchive();
$result = $zip->open($zip_file, ZipArchive::CREATE | ZipArchive::OVERWRITE);

if ($result !== TRUE) {
    die("❌ Cannot create ZIP file: $zip_file (Error: $result)\n");
}

// Add all files to ZIP
$files = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($plugin_dir),
    RecursiveIteratorIterator::LEAVES_ONLY
);

$file_count = 0;
foreach ($files as $name => $file) {
    if (!$file->isDir()) {
        $filePath = $file->getRealPath();
        $relativePath = $plugin_name . '/' . substr($filePath, strlen($plugin_dir) + 1);
        
        $zip->addFile($filePath, $relativePath);
        $file_count++;
    }
}

$zip->close();

// Get ZIP file size
$zip_size = filesize($zip_file);
$zip_size_mb = round($zip_size / 1024 / 1024, 2);

echo "\n🎉 Plugin ZIP created successfully!\n";
echo "📦 File: " . basename($zip_file) . "\n";
echo "📊 Size: {$zip_size_mb} MB ({$file_count} files)\n";
echo "📍 Location: $zip_file\n\n";

// Verify ZIP contents
echo "🔍 Verifying ZIP contents...\n";
$verify_zip = new ZipArchive();
if ($verify_zip->open($zip_file) === TRUE) {
    echo "✅ ZIP file is valid\n";
    echo "📋 Contains {$verify_zip->numFiles} files\n";
    
    // Check for essential files
    $essential_files = [
        $plugin_name . '/reactifywp.php',
        $plugin_name . '/inc/class-admin.php',
        $plugin_name . '/inc/class-shortcode.php',
        $plugin_name . '/assets/dist/frontend.js',
        $plugin_name . '/assets/dist/frontend.css'
    ];
    
    foreach ($essential_files as $essential_file) {
        if ($verify_zip->locateName($essential_file) !== false) {
            echo "  ✅ $essential_file\n";
        } else {
            echo "  ❌ $essential_file (MISSING!)\n";
        }
    }
    
    $verify_zip->close();
} else {
    echo "❌ ZIP file verification failed\n";
}

// Clean up build directory
echo "\n🧹 Cleaning up build directory...\n";
removeDirectory($build_dir);

echo "\n🚀 Ready for WordPress!\n";
echo "📥 Upload $zip_file to WordPress Admin → Plugins → Add New → Upload Plugin\n";
echo "🔧 Then activate and configure ReactifyWP\n\n";

// Helper functions
function copyDirectory($src, $dst) {
    $dir = opendir($src);
    @mkdir($dst);
    while (false !== ($file = readdir($dir))) {
        if (($file != '.') && ($file != '..')) {
            if (is_dir($src . '/' . $file)) {
                copyDirectory($src . '/' . $file, $dst . '/' . $file);
            } else {
                copy($src . '/' . $file, $dst . '/' . $file);
            }
        }
    }
    closedir($dir);
}

function removeDirectory($dir) {
    if (!is_dir($dir)) {
        return;
    }
    
    $files = array_diff(scandir($dir), array('.', '..'));
    foreach ($files as $file) {
        $path = $dir . '/' . $file;
        if (is_dir($path)) {
            removeDirectory($path);
        } else {
            unlink($path);
        }
    }
    rmdir($dir);
}
?>
