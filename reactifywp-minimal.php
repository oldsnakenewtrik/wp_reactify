<?php
/**
 * Plugin Name: ReactifyWP (Minimal)
 * Plugin URI: https://github.com/oldsnakenewtrik/wp_reactify
 * Description: Minimal version of ReactifyWP - Seamlessly integrate React applications into WordPress
 * Version: 1.0.2-minimal
 * Author: ReactifyWP Team
 * License: GPL v2 or later
 * Text Domain: reactifywp
 * Domain Path: /languages
 * Requires at least: 5.0
 * Tested up to: 6.4
 * Requires PHP: 7.4
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Plugin constants
define('REACTIFYWP_VERSION', '1.0.2-minimal');
define('REACTIFYWP_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('REACTIFYWP_PLUGIN_URL', plugin_dir_url(__FILE__));
define('REACTIFYWP_PLUGIN_BASENAME', plugin_basename(__FILE__));

/**
 * Minimal ReactifyWP Plugin Class
 */
class ReactifyWP_Minimal
{
    private static $instance = null;

    public static function instance()
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct()
    {
        add_action('init', [$this, 'init']);
        register_activation_hook(__FILE__, [$this, 'activate']);
        register_deactivation_hook(__FILE__, [$this, 'deactivate']);
    }

    public function init()
    {
        // Add admin menu
        add_action('admin_menu', [$this, 'admin_menu']);
        
        // Register shortcode
        add_shortcode('reactify', [$this, 'shortcode']);
        
        // Enqueue frontend assets
        add_action('wp_enqueue_scripts', [$this, 'enqueue_frontend_assets']);
    }

    public function activate()
    {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        // Simple projects table
        $table_name = $wpdb->prefix . 'reactify_projects';
        $sql = "CREATE TABLE $table_name (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            blog_id bigint(20) unsigned NOT NULL DEFAULT 1,
            slug varchar(100) NOT NULL,
            project_name varchar(255) NOT NULL,
            file_path text NOT NULL,
            version varchar(50) DEFAULT '1.0.0',
            status varchar(20) DEFAULT 'active',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY unique_slug (blog_id, slug)
        ) $charset_collate;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
        
        // Create upload directory
        $upload_dir = wp_upload_dir();
        $reactify_dir = $upload_dir['basedir'] . '/reactify-projects/' . get_current_blog_id();
        wp_mkdir_p($reactify_dir);
    }

    public function deactivate()
    {
        // Nothing to do on deactivation
    }

    public function admin_menu()
    {
        add_menu_page(
            'ReactifyWP',
            'ReactifyWP',
            'manage_options',
            'reactifywp',
            [$this, 'admin_page'],
            'dashicons-admin-generic',
            30
        );
    }

    public function admin_page()
    {
        if (isset($_POST['upload_project']) && wp_verify_nonce($_POST['_wpnonce'], 'upload_project')) {
            $this->handle_upload();
        }

        if (isset($_POST['delete_project']) && wp_verify_nonce($_POST['_wpnonce'], 'delete_project')) {
            $this->handle_delete();
        }

        ?>
        <div class="wrap">
            <h1>ReactifyWP - Minimal Version</h1>

            <div class="notice notice-info">
                <p><strong>This is a minimal version of ReactifyWP.</strong> It provides basic functionality for uploading and displaying React apps.</p>
            </div>
            
            <h2>Upload React App</h2>
            <form method="post" enctype="multipart/form-data">
                <?php wp_nonce_field('upload_project'); ?>
                <table class="form-table">
                    <tr>
                        <th><label for="project_name">Project Name</label></th>
                        <td><input type="text" id="project_name" name="project_name" class="regular-text" required /></td>
                    </tr>
                    <tr>
                        <th><label for="slug">Slug</label></th>
                        <td><input type="text" id="slug" name="slug" class="regular-text" required /></td>
                    </tr>
                    <tr>
                        <th><label for="zip_file">ZIP File</label></th>
                        <td><input type="file" id="zip_file" name="zip_file" accept=".zip" required /></td>
                    </tr>
                </table>
                <p class="submit">
                    <input type="submit" name="upload_project" class="button-primary" value="Upload Project" />
                </p>
            </form>
            
            <h2>Existing Projects</h2>
            <?php $this->list_projects(); ?>
            
            <h2>Usage</h2>
            <p>Use the shortcode: <code>[reactify slug="your-project-slug"]</code></p>
        </div>
        <?php
    }

    private function handle_upload()
    {
        if (!isset($_FILES['zip_file']) || $_FILES['zip_file']['error'] !== UPLOAD_ERR_OK) {
            echo '<div class="notice notice-error"><p>Upload failed.</p></div>';
            return;
        }

        $slug = sanitize_text_field($_POST['slug']);
        $project_name = sanitize_text_field($_POST['project_name']);

        // Check for duplicate slug
        global $wpdb;
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}reactify_projects WHERE blog_id = %d AND slug = %s",
            get_current_blog_id(),
            $slug
        ));

        if ($existing > 0) {
            echo '<div class="notice notice-error"><p><strong>Error:</strong> A project with slug "' . esc_html($slug) . '" already exists. Please choose a different slug.</p></div>';
            return;
        }

        // Extract ZIP
        $upload_dir = wp_upload_dir();
        $project_dir = $upload_dir['basedir'] . '/reactify-projects/' . get_current_blog_id() . '/' . $slug;

        if (!wp_mkdir_p($project_dir)) {
            echo '<div class="notice notice-error"><p>Could not create project directory.</p></div>';
            return;
        }

        $zip = new ZipArchive();
        if ($zip->open($_FILES['zip_file']['tmp_name']) === TRUE) {
            $zip->extractTo($project_dir);
            $zip->close();

            // Save to database
            $result = $wpdb->insert(
                $wpdb->prefix . 'reactify_projects',
                [
                    'blog_id' => get_current_blog_id(),
                    'slug' => $slug,
                    'project_name' => $project_name,
                    'file_path' => $project_dir,
                    'status' => 'active'
                ]
            );

            if ($result !== false) {
                echo '<div class="notice notice-success"><p>Project uploaded successfully!</p></div>';
            } else {
                echo '<div class="notice notice-error"><p>Database error: Could not save project.</p></div>';
            }
        } else {
            echo '<div class="notice notice-error"><p>Could not extract ZIP file.</p></div>';
        }
    }

    private function handle_delete()
    {
        $project_id = intval($_POST['project_id']);

        global $wpdb;
        $project = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}reactify_projects WHERE id = %d AND blog_id = %d",
            $project_id,
            get_current_blog_id()
        ));

        if (!$project) {
            echo '<div class="notice notice-error"><p>Project not found.</p></div>';
            return;
        }

        // Delete files
        if (is_dir($project->file_path)) {
            $this->delete_directory($project->file_path);
        }

        // Delete from database
        $result = $wpdb->delete(
            $wpdb->prefix . 'reactify_projects',
            ['id' => $project_id, 'blog_id' => get_current_blog_id()],
            ['%d', '%d']
        );

        if ($result !== false) {
            echo '<div class="notice notice-success"><p>Project "' . esc_html($project->project_name) . '" deleted successfully!</p></div>';
        } else {
            echo '<div class="notice notice-error"><p>Could not delete project from database.</p></div>';
        }
    }

    private function delete_directory($dir)
    {
        if (!is_dir($dir)) {
            return false;
        }

        $files = array_diff(scandir($dir), array('.', '..'));
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            if (is_dir($path)) {
                $this->delete_directory($path);
            } else {
                unlink($path);
            }
        }
        return rmdir($dir);
    }

    private function list_projects()
    {
        global $wpdb;
        $projects = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}reactify_projects WHERE blog_id = %d ORDER BY created_at DESC",
            get_current_blog_id()
        ));
        
        if (empty($projects)) {
            echo '<p>No projects uploaded yet.</p>';
            return;
        }
        
        echo '<table class="wp-list-table widefat fixed striped">';
        echo '<thead><tr><th>Name</th><th>Slug</th><th>Status</th><th>Created</th><th>Shortcode</th><th>Actions</th></tr></thead>';
        echo '<tbody>';
        foreach ($projects as $project) {
            echo '<tr>';
            echo '<td>' . esc_html($project->project_name) . '</td>';
            echo '<td>' . esc_html($project->slug) . '</td>';
            echo '<td>' . esc_html($project->status) . '</td>';
            echo '<td>' . esc_html($project->created_at) . '</td>';
            echo '<td><code>[reactify slug="' . esc_attr($project->slug) . '"]</code></td>';
            echo '<td>';
            echo '<form method="post" style="display: inline;" onsubmit="return confirm(\'Are you sure you want to delete this project?\');">';
            wp_nonce_field('delete_project');
            echo '<input type="hidden" name="project_id" value="' . esc_attr($project->id) . '" />';
            echo '<input type="submit" name="delete_project" class="button button-small" value="Delete" style="background: #dc3232; color: white;" />';
            echo '</form>';
            echo '</td>';
            echo '</tr>';
        }
        echo '</tbody></table>';
    }

    public function shortcode($atts)
    {
        $atts = shortcode_atts(['slug' => ''], $atts);
        
        if (empty($atts['slug'])) {
            return '<div style="background: #f8d7da; color: #721c24; padding: 10px; border-radius: 4px;">ReactifyWP Error: Project slug is required.</div>';
        }
        
        global $wpdb;
        $project = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}reactify_projects WHERE blog_id = %d AND slug = %s",
            get_current_blog_id(),
            $atts['slug']
        ));
        
        if (!$project) {
            return '<div style="background: #f8d7da; color: #721c24; padding: 10px; border-radius: 4px;">ReactifyWP Error: Project not found.</div>';
        }
        
        $upload_dir = wp_upload_dir();
        $project_url = $upload_dir['baseurl'] . '/reactify-projects/' . get_current_blog_id() . '/' . $project->slug . '/index.html';
        
        return '<iframe src="' . esc_url($project_url) . '" style="width: 100%; height: 400px; border: none; border-radius: 8px;"></iframe>';
    }

    public function enqueue_frontend_assets()
    {
        // Basic CSS for iframe styling
        wp_add_inline_style('wp-block-library', '
            .reactify-container iframe {
                width: 100%;
                min-height: 400px;
                border: none;
                border-radius: 8px;
                box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            }
        ');
    }
}

// Initialize the plugin
function reactifywp_minimal()
{
    return ReactifyWP_Minimal::instance();
}

// Start the plugin
reactifywp_minimal();
?>
