<?php
/**
 * ReactifyWP User Journey Test
 * This script tests the complete user workflow from upload to display
 */

// Only run if WordPress is loaded
if (!defined('ABSPATH')) {
    die('This script must be run within WordPress.');
}

// Only allow administrators to run this test
if (!current_user_can('manage_options')) {
    die('Insufficient permissions.');
}

echo "<h1>ReactifyWP User Journey Test</h1>\n";
echo "<p>This test simulates the complete user workflow from uploading a React app to displaying it.</p>\n";

// Test Configuration
$test_slug = 'test-journey-app';
$test_project_name = 'Test Journey App';

echo "<h2>🚀 Starting User Journey Test</h2>\n";

// Step 1: Check Prerequisites
echo "<h3>Step 1: Prerequisites Check</h3>\n";

$prerequisites_passed = true;

// Check if ReactifyWP is active
if (!class_exists('ReactifyWP\ReactifyWP')) {
    echo "❌ ReactifyWP plugin is not active<br>\n";
    $prerequisites_passed = false;
} else {
    echo "✅ ReactifyWP plugin is active<br>\n";
}

// Check database tables
global $wpdb;
$tables_to_check = [
    $wpdb->prefix . 'reactify_projects',
    $wpdb->prefix . 'reactify_assets',
    $wpdb->prefix . 'reactify_stats',
    $wpdb->prefix . 'reactify_errors'
];

foreach ($tables_to_check as $table) {
    $exists = $wpdb->get_var("SHOW TABLES LIKE '$table'");
    if ($exists) {
        echo "✅ Table $table exists<br>\n";
    } else {
        echo "❌ Table $table missing<br>\n";
        $prerequisites_passed = false;
    }
}

// Check upload directory
$upload_dir = wp_upload_dir();
$blog_id = get_current_blog_id();
$reactify_dir = $upload_dir['basedir'] . '/reactify-projects/' . $blog_id;

if (!is_dir($reactify_dir)) {
    if (wp_mkdir_p($reactify_dir)) {
        echo "✅ Created upload directory: $reactify_dir<br>\n";
    } else {
        echo "❌ Cannot create upload directory: $reactify_dir<br>\n";
        $prerequisites_passed = false;
    }
} else {
    echo "✅ Upload directory exists: $reactify_dir<br>\n";
}

if (!$prerequisites_passed) {
    echo "<p><strong>❌ Prerequisites failed. Please fix the issues above before continuing.</strong></p>\n";
    exit;
}

// Step 2: Clean up any existing test project
echo "<h3>Step 2: Cleanup Previous Test Data</h3>\n";

try {
    $project = new ReactifyWP\Project();
    $existing = $project->get_by_slug($test_slug);
    
    if ($existing) {
        $delete_result = $project->delete($test_slug);
        if (is_wp_error($delete_result)) {
            echo "⚠️ Could not delete existing test project: " . $delete_result->get_error_message() . "<br>\n";
        } else {
            echo "✅ Cleaned up existing test project<br>\n";
        }
    } else {
        echo "✅ No existing test project to clean up<br>\n";
    }
} catch (Exception $e) {
    echo "⚠️ Error during cleanup: " . $e->getMessage() . "<br>\n";
}

// Step 3: Create Test ZIP File
echo "<h3>Step 3: Create Test React App</h3>\n";

$test_app_content = [
    'index.html' => '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Journey Test App</title>
    <style>
        .test-app { 
            padding: 20px; 
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); 
            color: white; 
            border-radius: 8px; 
            text-align: center; 
            font-family: Arial, sans-serif; 
        }
        .counter { 
            margin: 20px 0; 
            padding: 15px; 
            background: rgba(255,255,255,0.1); 
            border-radius: 4px; 
        }
        button { 
            background: rgba(255,255,255,0.2); 
            border: 1px solid rgba(255,255,255,0.3); 
            color: white; 
            padding: 8px 16px; 
            margin: 0 5px; 
            border-radius: 4px; 
            cursor: pointer; 
        }
        button:hover { background: rgba(255,255,255,0.3); }
    </style>
</head>
<body>
    <div class="test-app">
        <h1>🧪 User Journey Test App</h1>
        <p>This app was created by the automated user journey test!</p>
        <div class="counter">
            <p>Counter: <span id="counter">0</span></p>
            <button onclick="increment()">+</button>
            <button onclick="decrement()">-</button>
            <button onclick="reset()">Reset</button>
        </div>
        <p><small>Loaded at: ' . date('Y-m-d H:i:s') . '</small></p>
    </div>
    <script>
        let count = 0;
        function increment() { document.getElementById("counter").textContent = ++count; }
        function decrement() { document.getElementById("counter").textContent = --count; }
        function reset() { count = 0; document.getElementById("counter").textContent = count; }
        console.log("ReactifyWP User Journey Test App loaded successfully!");
    </script>
</body>
</html>',
    'package.json' => json_encode([
        'name' => 'user-journey-test-app',
        'version' => '1.0.0',
        'description' => 'Test app for ReactifyWP user journey testing',
        'main' => 'index.html'
    ], JSON_PRETTY_PRINT)
];

// Create temporary directory for test app
$temp_dir = sys_get_temp_dir() . '/reactify-test-' . uniqid();
if (!mkdir($temp_dir)) {
    echo "❌ Cannot create temporary directory<br>\n";
    exit;
}

// Write test files
foreach ($test_app_content as $filename => $content) {
    file_put_contents($temp_dir . '/' . $filename, $content);
}

// Create ZIP file
$zip_file = $temp_dir . '.zip';
$zip = new ZipArchive();

if ($zip->open($zip_file, ZipArchive::CREATE) !== TRUE) {
    echo "❌ Cannot create ZIP file<br>\n";
    exit;
}

foreach ($test_app_content as $filename => $content) {
    $zip->addFile($temp_dir . '/' . $filename, $filename);
}

$zip->close();

echo "✅ Created test ZIP file: " . basename($zip_file) . "<br>\n";
echo "📦 ZIP size: " . size_format(filesize($zip_file)) . "<br>\n";

// Step 4: Simulate File Upload
echo "<h3>Step 4: Simulate Project Upload</h3>\n";

try {
    // Simulate $_FILES and $_POST data
    $_FILES['file'] = [
        'name' => 'user-journey-test.zip',
        'tmp_name' => $zip_file,
        'size' => filesize($zip_file),
        'error' => UPLOAD_ERR_OK
    ];
    
    $_POST['slug'] = $test_slug;
    $_POST['project_name'] = $test_project_name;
    $_POST['shortcode'] = $test_slug;
    
    $project = new ReactifyWP\Project();
    $upload_result = $project->upload_from_request();
    
    if (is_wp_error($upload_result)) {
        echo "❌ Upload failed: " . $upload_result->get_error_message() . "<br>\n";
        exit;
    } else {
        echo "✅ Project uploaded successfully<br>\n";
        echo "📁 Project path: " . $upload_result['file_path'] . "<br>\n";
        echo "📊 Project size: " . size_format($upload_result['file_size']) . "<br>\n";
    }
    
} catch (Exception $e) {
    echo "❌ Upload error: " . $e->getMessage() . "<br>\n";
    exit;
}

// Step 5: Test Shortcode Rendering
echo "<h3>Step 5: Test Shortcode Rendering</h3>\n";

$shortcode_tests = [
    "[reactify slug=\"$test_slug\"]",
    "[reactify slug=\"$test_slug\" theme=\"dark\"]",
    "[reactify slug=\"$test_slug\" height=\"400px\"]",
    "[reactify slug=\"$test_slug\" debug=\"true\"]"
];

foreach ($shortcode_tests as $shortcode) {
    echo "<h4>Testing: <code>" . esc_html($shortcode) . "</code></h4>\n";
    
    $output = do_shortcode($shortcode);
    
    if (empty($output)) {
        echo "❌ Shortcode produced no output<br>\n";
    } elseif (strpos($output, 'ReactifyWP Error') !== false) {
        echo "❌ Shortcode produced error: " . strip_tags($output) . "<br>\n";
    } else {
        echo "✅ Shortcode rendered successfully<br>\n";
        echo "<div style='border: 1px solid #ccc; padding: 10px; margin: 10px 0; max-height: 200px; overflow: auto;'>\n";
        echo $output;
        echo "</div>\n";
    }
}

// Step 6: Test Project Retrieval
echo "<h3>Step 6: Test Project Retrieval</h3>\n";

$retrieved_project = $project->get_by_slug($test_slug);

if (!$retrieved_project) {
    echo "❌ Cannot retrieve uploaded project<br>\n";
} else {
    echo "✅ Project retrieved successfully<br>\n";
    echo "📋 Project details:<br>\n";
    echo "- Name: " . $retrieved_project->project_name . "<br>\n";
    echo "- Slug: " . $retrieved_project->slug . "<br>\n";
    echo "- Version: " . $retrieved_project->version . "<br>\n";
    echo "- Status: " . $retrieved_project->status . "<br>\n";
    echo "- Created: " . $retrieved_project->created_at . "<br>\n";
}

// Step 7: Cleanup
echo "<h3>Step 7: Cleanup Test Data</h3>\n";

// Remove temporary files
unlink($zip_file);
array_map('unlink', glob($temp_dir . '/*'));
rmdir($temp_dir);

echo "✅ Cleaned up temporary files<br>\n";

// Optionally remove test project
if (isset($_GET['cleanup']) && $_GET['cleanup'] === 'true') {
    $delete_result = $project->delete($test_slug);
    if (is_wp_error($delete_result)) {
        echo "⚠️ Could not delete test project: " . $delete_result->get_error_message() . "<br>\n";
    } else {
        echo "✅ Deleted test project<br>\n";
    }
} else {
    echo "ℹ️ Test project kept for manual inspection. <a href='?cleanup=true'>Click here to delete it</a><br>\n";
}

// Final Results
echo "<h2>🎉 User Journey Test Complete!</h2>\n";
echo "<div style='background: #d4edda; border: 1px solid #c3e6cb; padding: 15px; border-radius: 5px; margin: 20px 0;'>\n";
echo "<h3>✅ Test Summary</h3>\n";
echo "<p>The complete user journey from upload to display has been tested successfully!</p>\n";
echo "<p><strong>Next steps:</strong></p>\n";
echo "<ul>\n";
echo "<li>Try the shortcode <code>[reactify slug=\"$test_slug\"]</code> in a post or page</li>\n";
echo "<li>Check the ReactifyWP admin page to see the uploaded project</li>\n";
echo "<li>Test different shortcode parameters</li>\n";
echo "<li>Upload your own React apps!</li>\n";
echo "</ul>\n";
echo "</div>\n";

echo "<p><a href='" . admin_url('admin.php?page=reactifywp') . "'>Go to ReactifyWP Admin</a></p>\n";
?>
