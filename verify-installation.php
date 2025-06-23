<?php
/**
 * ReactifyWP Installation Verification
 * Quick script to verify that ReactifyWP is properly installed and configured
 */

// Check if we're in WordPress context
if (!defined('ABSPATH')) {
    // Try to load WordPress
    $wp_load_paths = [
        '../../../wp-load.php',
        '../../wp-load.php', 
        '../wp-load.php',
        'wp-load.php'
    ];
    
    foreach ($wp_load_paths as $path) {
        if (file_exists($path)) {
            require_once $path;
            break;
        }
    }
    
    if (!defined('ABSPATH')) {
        die('❌ WordPress not found. Please run this script from your WordPress installation.');
    }
}

// Only allow administrators
if (!current_user_can('manage_options')) {
    die('❌ Insufficient permissions. Please log in as an administrator.');
}

?>
<!DOCTYPE html>
<html>
<head>
    <title>ReactifyWP Installation Verification</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, sans-serif; margin: 40px; line-height: 1.6; }
        .status { padding: 10px; margin: 10px 0; border-radius: 4px; }
        .success { background: #d4edda; border: 1px solid #c3e6cb; color: #155724; }
        .error { background: #f8d7da; border: 1px solid #f5c6cb; color: #721c24; }
        .warning { background: #fff3cd; border: 1px solid #ffeaa7; color: #856404; }
        .info { background: #d1ecf1; border: 1px solid #bee5eb; color: #0c5460; }
        pre { background: #f8f9fa; padding: 10px; border-radius: 4px; overflow-x: auto; }
        .grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin: 20px 0; }
        .card { border: 1px solid #ddd; padding: 15px; border-radius: 4px; }
    </style>
</head>
<body>
    <h1>🔍 ReactifyWP Installation Verification</h1>
    <p>This script checks if ReactifyWP is properly installed and configured.</p>

    <?php
    $all_good = true;
    
    // Check 1: Plugin Active
    echo "<h2>1. Plugin Status</h2>";
    if (class_exists('ReactifyWP\ReactifyWP')) {
        echo '<div class="status success">✅ ReactifyWP plugin is active and loaded</div>';
    } else {
        echo '<div class="status error">❌ ReactifyWP plugin is not active or not found</div>';
        $all_good = false;
    }
    
    // Check 2: PHP Version
    echo "<h2>2. PHP Requirements</h2>";
    $php_version = PHP_VERSION;
    if (version_compare($php_version, '7.4', '>=')) {
        echo "<div class='status success'>✅ PHP version: $php_version (meets requirement: 7.4+)</div>";
    } else {
        echo "<div class='status error'>❌ PHP version: $php_version (requires 7.4+)</div>";
        $all_good = false;
    }
    
    // Check 3: WordPress Version
    echo "<h2>3. WordPress Requirements</h2>";
    global $wp_version;
    if (version_compare($wp_version, '5.0', '>=')) {
        echo "<div class='status success'>✅ WordPress version: $wp_version (meets requirement: 5.0+)</div>";
    } else {
        echo "<div class='status error'>❌ WordPress version: $wp_version (requires 5.0+)</div>";
        $all_good = false;
    }
    
    // Check 4: Required PHP Extensions
    echo "<h2>4. PHP Extensions</h2>";
    $required_extensions = ['zip', 'json', 'mbstring'];
    foreach ($required_extensions as $ext) {
        if (extension_loaded($ext)) {
            echo "<div class='status success'>✅ $ext extension is loaded</div>";
        } else {
            echo "<div class='status error'>❌ $ext extension is missing</div>";
            $all_good = false;
        }
    }
    
    // Check 5: Database Tables
    echo "<h2>5. Database Tables</h2>";
    global $wpdb;
    $tables = [
        'reactify_projects' => 'Main projects table',
        'reactify_assets' => 'Asset tracking table', 
        'reactify_stats' => 'Usage statistics table',
        'reactify_errors' => 'Error logging table'
    ];
    
    foreach ($tables as $table => $description) {
        $full_table_name = $wpdb->prefix . $table;
        $exists = $wpdb->get_var("SHOW TABLES LIKE '$full_table_name'");
        if ($exists) {
            echo "<div class='status success'>✅ $full_table_name ($description)</div>";
        } else {
            echo "<div class='status error'>❌ $full_table_name missing ($description)</div>";
            $all_good = false;
        }
    }
    
    // Check 6: File Permissions
    echo "<h2>6. File Permissions</h2>";
    $upload_dir = wp_upload_dir();
    $blog_id = get_current_blog_id();
    $reactify_dir = $upload_dir['basedir'] . '/reactify-projects/' . $blog_id;
    
    if (!is_dir($reactify_dir)) {
        if (wp_mkdir_p($reactify_dir)) {
            echo "<div class='status success'>✅ Created upload directory: $reactify_dir</div>";
        } else {
            echo "<div class='status error'>❌ Cannot create upload directory: $reactify_dir</div>";
            $all_good = false;
        }
    } else {
        echo "<div class='status success'>✅ Upload directory exists: $reactify_dir</div>";
    }
    
    if (is_writable($reactify_dir)) {
        echo "<div class='status success'>✅ Upload directory is writable</div>";
    } else {
        echo "<div class='status error'>❌ Upload directory is not writable</div>";
        $all_good = false;
    }
    
    // Check 7: Frontend Assets
    echo "<h2>7. Frontend Assets</h2>";
    $assets = [
        'frontend.js' => REACTIFYWP_PLUGIN_DIR . 'assets/dist/frontend.js',
        'frontend.css' => REACTIFYWP_PLUGIN_DIR . 'assets/dist/frontend.css'
    ];
    
    foreach ($assets as $name => $path) {
        if (file_exists($path)) {
            $size = size_format(filesize($path));
            echo "<div class='status success'>✅ $name exists ($size)</div>";
        } else {
            echo "<div class='status error'>❌ $name missing: $path</div>";
            $all_good = false;
        }
    }
    
    // Check 8: Shortcode Registration
    echo "<h2>8. Shortcode System</h2>";
    if (shortcode_exists('reactify')) {
        echo "<div class='status success'>✅ [reactify] shortcode is registered</div>";
    } else {
        echo "<div class='status error'>❌ [reactify] shortcode is not registered</div>";
        $all_good = false;
    }
    
    // Check 9: Admin Menu
    echo "<h2>9. Admin Interface</h2>";
    $admin_url = admin_url('admin.php?page=reactifywp');
    echo "<div class='status info'>ℹ️ Admin page: <a href='$admin_url'>$admin_url</a></div>";
    
    // Final Status
    echo "<h2>🎯 Overall Status</h2>";
    if ($all_good) {
        echo '<div class="status success">
            <h3>✅ Installation Verified Successfully!</h3>
            <p>ReactifyWP is properly installed and ready to use.</p>
            <p><strong>Next steps:</strong></p>
            <ul>
                <li><a href="' . admin_url('admin.php?page=reactifywp') . '">Go to ReactifyWP Admin</a></li>
                <li><a href="' . plugin_dir_url(__FILE__) . 'create-test-zip.php">Create a test ZIP file</a></li>
                <li>Upload your first React app!</li>
            </ul>
        </div>';
    } else {
        echo '<div class="status error">
            <h3>❌ Installation Issues Found</h3>
            <p>Please fix the issues above before using ReactifyWP.</p>
            <p><strong>Common solutions:</strong></p>
            <ul>
                <li>Make sure the plugin is activated</li>
                <li>Check file permissions on the uploads directory</li>
                <li>Verify PHP extensions are installed</li>
                <li>Try deactivating and reactivating the plugin</li>
            </ul>
        </div>';
    }
    
    // System Information
    echo "<h2>📊 System Information</h2>";
    echo "<div class='grid'>";
    echo "<div class='card'>";
    echo "<h3>WordPress</h3>";
    echo "<strong>Version:</strong> $wp_version<br>";
    echo "<strong>Multisite:</strong> " . (is_multisite() ? 'Yes' : 'No') . "<br>";
    echo "<strong>Blog ID:</strong> " . get_current_blog_id() . "<br>";
    echo "<strong>Site URL:</strong> " . site_url() . "<br>";
    echo "</div>";
    
    echo "<div class='card'>";
    echo "<h3>Server</h3>";
    echo "<strong>PHP:</strong> " . PHP_VERSION . "<br>";
    echo "<strong>Server:</strong> " . ($_SERVER['SERVER_SOFTWARE'] ?? 'Unknown') . "<br>";
    echo "<strong>Memory Limit:</strong> " . ini_get('memory_limit') . "<br>";
    echo "<strong>Upload Max:</strong> " . ini_get('upload_max_filesize') . "<br>";
    echo "</div>";
    echo "</div>";
    
    echo "<hr>";
    echo "<p><small>Generated on " . date('Y-m-d H:i:s') . " | ReactifyWP Installation Verification</small></p>";
    ?>
</body>
</html>
