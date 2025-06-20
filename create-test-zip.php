<?php
/**
 * Create Test ZIP for ReactifyWP
 * This script creates a ZIP file of the test React app for easy testing
 */

// Only run if WordPress is loaded
if (!defined('ABSPATH')) {
    // If not in WordPress, try to load it
    $wp_load_paths = [
        '../../../wp-load.php',
        '../../wp-load.php',
        '../wp-load.php',
        'wp-load.php'
    ];
    
    $wp_loaded = false;
    foreach ($wp_load_paths as $path) {
        if (file_exists($path)) {
            require_once $path;
            $wp_loaded = true;
            break;
        }
    }
    
    if (!$wp_loaded) {
        die('WordPress not found. Please run this script from the WordPress root or plugin directory.');
    }
}

// Only allow administrators to run this script
if (!current_user_can('manage_options')) {
    die('Insufficient permissions.');
}

echo "<h1>ReactifyWP Test ZIP Creator</h1>\n";

// Define paths
$plugin_dir = dirname(__FILE__);
$test_app_dir = $plugin_dir . '/test-react-app';
$zip_file = $plugin_dir . '/test-react-app.zip';

// Check if test app directory exists
if (!is_dir($test_app_dir)) {
    die("❌ Test app directory not found: $test_app_dir");
}

echo "<h2>Creating ZIP file...</h2>\n";

// Create ZIP file
$zip = new ZipArchive();
$result = $zip->open($zip_file, ZipArchive::CREATE | ZipArchive::OVERWRITE);

if ($result !== TRUE) {
    die("❌ Cannot create ZIP file: $zip_file (Error code: $result)");
}

// Add files to ZIP
$files = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($test_app_dir),
    RecursiveIteratorIterator::LEAVES_ONLY
);

$file_count = 0;
foreach ($files as $name => $file) {
    // Skip directories (they would be added automatically)
    if (!$file->isDir()) {
        // Get real and relative path for current file
        $filePath = $file->getRealPath();
        $relativePath = substr($filePath, strlen($test_app_dir) + 1);
        
        // Add current file to archive
        $zip->addFile($filePath, $relativePath);
        $file_count++;
        
        echo "Added: $relativePath<br>\n";
    }
}

// Close ZIP file
$zip->close();

echo "<h2>ZIP Creation Complete!</h2>\n";
echo "✅ Created ZIP file: <strong>$zip_file</strong><br>\n";
echo "📁 Files added: $file_count<br>\n";
echo "📦 File size: " . size_format(filesize($zip_file)) . "<br>\n";

// Test ZIP file integrity
echo "<h2>Testing ZIP Integrity...</h2>\n";
$test_zip = new ZipArchive();
if ($test_zip->open($zip_file) === TRUE) {
    echo "✅ ZIP file is valid<br>\n";
    echo "📋 Contains " . $test_zip->numFiles . " files<br>\n";
    
    // List contents
    echo "<h3>ZIP Contents:</h3>\n";
    echo "<ul>\n";
    for ($i = 0; $i < $test_zip->numFiles; $i++) {
        $stat = $test_zip->statIndex($i);
        echo "<li>" . htmlspecialchars($stat['name']) . " (" . size_format($stat['size']) . ")</li>\n";
    }
    echo "</ul>\n";
    
    $test_zip->close();
} else {
    echo "❌ ZIP file is corrupted<br>\n";
}

// Provide download link
echo "<h2>Next Steps</h2>\n";
echo "<ol>\n";
echo "<li><a href='" . plugin_dir_url(__FILE__) . "test-react-app.zip' download>Download test-react-app.zip</a></li>\n";
echo "<li>Go to <a href='" . admin_url('admin.php?page=reactifywp') . "'>ReactifyWP Admin</a></li>\n";
echo "<li>Upload the ZIP file with slug: <code>test-app</code></li>\n";
echo "<li>Add this shortcode to a post/page: <code>[reactify slug=\"test-app\"]</code></li>\n";
echo "<li>View the post/page to see your React app!</li>\n";
echo "</ol>\n";

// Test shortcode preview
echo "<h2>Shortcode Preview</h2>\n";
echo "<p>Here's what the shortcode will look like once you upload the app:</p>\n";
echo "<div style='border: 2px dashed #ccc; padding: 20px; margin: 20px 0; background: #f9f9f9;'>\n";
echo "<code>[reactify slug=\"test-app\"]</code>\n";
echo "</div>\n";

echo "<h2>Advanced Usage Examples</h2>\n";
echo "<div style='background: #f0f0f0; padding: 15px; border-radius: 5px;'>\n";
echo "<h4>Basic Usage:</h4>\n";
echo "<code>[reactify slug=\"test-app\"]</code><br><br>\n";

echo "<h4>With Custom Height:</h4>\n";
echo "<code>[reactify slug=\"test-app\" height=\"500px\"]</code><br><br>\n";

echo "<h4>With Dark Theme:</h4>\n";
echo "<code>[reactify slug=\"test-app\" theme=\"dark\"]</code><br><br>\n";

echo "<h4>With Lazy Loading:</h4>\n";
echo "<code>[reactify slug=\"test-app\" loading=\"lazy\"]</code><br><br>\n";

echo "<h4>With Debug Mode:</h4>\n";
echo "<code>[reactify slug=\"test-app\" debug=\"true\"]</code><br><br>\n";

echo "<h4>Full Configuration:</h4>\n";
echo "<code>[reactify slug=\"test-app\" theme=\"dark\" height=\"600px\" loading=\"lazy\" responsive=\"true\" debug=\"true\"]</code>\n";
echo "</div>\n";

// Cleanup instructions
echo "<h2>Cleanup</h2>\n";
echo "<p>To remove the test app later:</p>\n";
echo "<ol>\n";
echo "<li>Go to ReactifyWP Admin</li>\n";
echo "<li>Find the 'test-app' project</li>\n";
echo "<li>Click 'Delete' to remove it</li>\n";
echo "</ol>\n";

echo "<hr>\n";
echo "<p><strong>Happy testing! 🚀</strong></p>\n";
?>
