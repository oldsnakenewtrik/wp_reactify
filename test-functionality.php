<?php
/**
 * Test script for ReactifyWP functionality
 * This script tests the basic upload and display workflow
 */

// Only run if WordPress is loaded
if (!defined('ABSPATH')) {
    die('This script must be run within WordPress.');
}

// Only allow administrators to run this test
if (!current_user_can('manage_options')) {
    die('Insufficient permissions.');
}

echo "<h1>ReactifyWP Functionality Test</h1>\n";

// Test 1: Check if plugin is active
echo "<h2>Test 1: Plugin Status</h2>\n";
if (class_exists('ReactifyWP\ReactifyWP')) {
    echo "✅ ReactifyWP plugin is loaded<br>\n";
} else {
    echo "❌ ReactifyWP plugin is not loaded<br>\n";
    exit;
}

// Test 2: Check database tables
echo "<h2>Test 2: Database Tables</h2>\n";
global $wpdb;

$tables = [
    $wpdb->prefix . 'reactify_projects',
    $wpdb->prefix . 'reactify_assets',
    $wpdb->prefix . 'reactify_stats',
    $wpdb->prefix . 'reactify_errors'
];

foreach ($tables as $table) {
    $exists = $wpdb->get_var("SHOW TABLES LIKE '$table'");
    if ($exists) {
        echo "✅ Table $table exists<br>\n";
    } else {
        echo "❌ Table $table does not exist<br>\n";
    }
}

// Test 3: Check upload directory
echo "<h2>Test 3: Upload Directory</h2>\n";
$upload_dir = wp_upload_dir();
$blog_id = get_current_blog_id();
$reactify_dir = $upload_dir['basedir'] . '/reactify-projects/' . $blog_id;

if (is_dir($reactify_dir)) {
    echo "✅ ReactifyWP upload directory exists: $reactify_dir<br>\n";
    if (is_writable($reactify_dir)) {
        echo "✅ Upload directory is writable<br>\n";
    } else {
        echo "❌ Upload directory is not writable<br>\n";
    }
} else {
    echo "❌ ReactifyWP upload directory does not exist: $reactify_dir<br>\n";
    echo "Attempting to create directory...<br>\n";
    if (wp_mkdir_p($reactify_dir)) {
        echo "✅ Successfully created upload directory<br>\n";
    } else {
        echo "❌ Failed to create upload directory<br>\n";
    }
}

// Test 4: Check shortcode registration
echo "<h2>Test 4: Shortcode Registration</h2>\n";
if (shortcode_exists('reactify')) {
    echo "✅ [reactify] shortcode is registered<br>\n";
} else {
    echo "❌ [reactify] shortcode is not registered<br>\n";
}

// Test 5: Check frontend assets
echo "<h2>Test 5: Frontend Assets</h2>\n";
$frontend_js = REACTIFYWP_PLUGIN_DIR . 'assets/dist/frontend.js';
$frontend_css = REACTIFYWP_PLUGIN_DIR . 'assets/dist/frontend.css';

if (file_exists($frontend_js)) {
    echo "✅ Frontend JavaScript exists<br>\n";
} else {
    echo "❌ Frontend JavaScript missing: $frontend_js<br>\n";
}

if (file_exists($frontend_css)) {
    echo "✅ Frontend CSS exists<br>\n";
} else {
    echo "❌ Frontend CSS missing: $frontend_css<br>\n";
}

// Test 6: Test shortcode output
echo "<h2>Test 6: Shortcode Output</h2>\n";
echo "Testing shortcode with non-existent project:<br>\n";
$shortcode_output = do_shortcode('[reactify slug="test-app"]');
echo "<div style='border: 1px solid #ccc; padding: 10px; margin: 10px 0;'>\n";
echo $shortcode_output;
echo "</div>\n";

// Test 7: Check project creation
echo "<h2>Test 7: Project Creation Test</h2>\n";
try {
    $project = new ReactifyWP\Project();
    echo "✅ Project class can be instantiated<br>\n";
    
    // Try to get all projects
    $projects = $project->get_all();
    echo "✅ Can retrieve projects (found " . count($projects) . " projects)<br>\n";
    
    if (!empty($projects)) {
        echo "Existing projects:<br>\n";
        foreach ($projects as $proj) {
            echo "- {$proj->project_name} (slug: {$proj->slug})<br>\n";
        }
    }
    
} catch (Exception $e) {
    echo "❌ Error with Project class: " . $e->getMessage() . "<br>\n";
}

// Test 8: Check admin interface
echo "<h2>Test 8: Admin Interface</h2>\n";
if (class_exists('ReactifyWP\Admin')) {
    echo "✅ Admin class exists<br>\n";
    
    // Check if admin menu is registered
    global $menu, $submenu;
    $found_menu = false;
    foreach ($menu as $menu_item) {
        if (strpos($menu_item[2], 'reactifywp') !== false) {
            $found_menu = true;
            break;
        }
    }
    
    if ($found_menu) {
        echo "✅ Admin menu is registered<br>\n";
    } else {
        echo "⚠️ Admin menu may not be registered yet (check wp-admin)<br>\n";
    }
} else {
    echo "❌ Admin class does not exist<br>\n";
}

// Test 9: Check error handling
echo "<h2>Test 9: Error Handling</h2>\n";
if (class_exists('ReactifyWP\ErrorHandler')) {
    echo "✅ Error handler class exists<br>\n";
} else {
    echo "❌ Error handler class does not exist<br>\n";
}

// Test 10: Performance check
echo "<h2>Test 10: Performance Check</h2>\n";
$start_time = microtime(true);
$start_memory = memory_get_usage();

// Simulate some operations
for ($i = 0; $i < 1000; $i++) {
    $test = do_shortcode('[reactify slug="non-existent"]');
}

$end_time = microtime(true);
$end_memory = memory_get_usage();

$execution_time = ($end_time - $start_time) * 1000; // Convert to milliseconds
$memory_used = $end_memory - $start_memory;

echo "Executed 1000 shortcode calls in " . number_format($execution_time, 2) . "ms<br>\n";
echo "Memory used: " . size_format($memory_used) . "<br>\n";

if ($execution_time < 1000) { // Less than 1 second
    echo "✅ Performance is acceptable<br>\n";
} else {
    echo "⚠️ Performance may need optimization<br>\n";
}

echo "<h2>Test Summary</h2>\n";
echo "ReactifyWP functionality test completed. Check the results above for any issues.<br>\n";
echo "<br>\n";
echo "<strong>Next Steps:</strong><br>\n";
echo "1. If all tests pass, try uploading a React app ZIP file<br>\n";
echo "2. Use the shortcode [reactify slug=\"your-app-slug\"] to display it<br>\n";
echo "3. Check the browser console for any JavaScript errors<br>\n";
echo "<br>\n";
echo "<a href='" . admin_url('admin.php?page=reactifywp') . "'>Go to ReactifyWP Admin</a><br>\n";
?>
