<?php
/**
 * Admin functionality for ReactifyWP
 *
 * @package ReactifyWP
 */

namespace ReactifyWP;

/**
 * Admin class
 */
class Admin
{
    /**
     * Constructor
     */
    public function __construct()
    {
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('admin_init', [$this, 'register_settings']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_scripts']);
        add_action('wp_ajax_reactifywp_upload', [$this, 'handle_upload']);
        add_action('wp_ajax_reactifywp_delete', [$this, 'handle_delete']);
        add_action('wp_ajax_reactifywp_get_projects', [$this, 'handle_get_projects']);
        add_action('wp_ajax_reactifywp_toggle_status', [$this, 'handle_toggle_status']);
        add_action('wp_ajax_reactifywp_edit_project', [$this, 'handle_edit_project']);
        add_action('wp_ajax_reactifywp_update_project', [$this, 'handle_update_project']);
        add_action('wp_ajax_reactifywp_duplicate_project', [$this, 'handle_duplicate_project']);
        add_action('wp_ajax_reactifywp_bulk_action', [$this, 'handle_bulk_action']);
        add_action('wp_ajax_reactifywp_export_project', [$this, 'handle_export_project']);
        add_action('wp_ajax_reactifywp_import_project', [$this, 'handle_import_project']);
    }

    /**
     * Add admin menu
     */
    public function add_admin_menu()
    {
        add_options_page(
            __('ReactifyWP Settings', 'reactifywp'),
            __('ReactifyWP', 'reactifywp'),
            'manage_options',
            'reactifywp',
            [$this, 'admin_page']
        );
    }

    /**
     * Register settings
     */
    public function register_settings()
    {
        register_setting('reactifywp_options', 'reactifywp_options', [
            'sanitize_callback' => [$this, 'sanitize_options']
        ]);

        add_settings_section(
            'reactifywp_general',
            __('General Settings', 'reactifywp'),
            [$this, 'general_section_callback'],
            'reactifywp'
        );

        add_settings_field(
            'max_upload_size',
            __('Max Upload Size', 'reactifywp'),
            [$this, 'max_upload_size_callback'],
            'reactifywp',
            'reactifywp_general'
        );

        add_settings_field(
            'enable_scoped_styles',
            __('Enable Scoped Styles', 'reactifywp'),
            [$this, 'enable_scoped_styles_callback'],
            'reactifywp',
            'reactifywp_general'
        );

        add_settings_field(
            'enable_cache_busting',
            __('Enable Cache Busting', 'reactifywp'),
            [$this, 'enable_cache_busting_callback'],
            'reactifywp',
            'reactifywp_general'
        );

        add_settings_field(
            'defer_js_loading',
            __('Defer JS Loading', 'reactifywp'),
            [$this, 'defer_js_loading_callback'],
            'reactifywp',
            'reactifywp_general'
        );
    }

    /**
     * Enqueue admin scripts and styles
     *
     * @param string $hook Current admin page hook
     */
    public function enqueue_admin_scripts($hook)
    {
        if ('settings_page_reactifywp' !== $hook) {
            return;
        }

        wp_enqueue_script(
            'reactifywp-admin',
            REACTIFYWP_PLUGIN_URL . 'assets/dist/admin.js',
            ['jquery', 'wp-util'],
            REACTIFYWP_VERSION,
            true
        );

        wp_enqueue_style(
            'reactifywp-admin',
            REACTIFYWP_PLUGIN_URL . 'assets/dist/admin.css',
            [],
            REACTIFYWP_VERSION
        );

        wp_localize_script('reactifywp-admin', 'reactifyWPAdmin', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('reactifywp_admin'),
            'strings' => [
                'uploadSuccess' => __('Project uploaded successfully!', 'reactifywp'),
                'uploadError' => __('Upload failed. Please try again.', 'reactifywp'),
                'deleteConfirm' => __('Are you sure you want to delete this project?', 'reactifywp'),
                'processing' => __('Processing...', 'reactifywp'),
                'invalidFile' => __('Please select a valid ZIP file.', 'reactifywp'),
                'slugRequired' => __('Project slug is required.', 'reactifywp'),
                'slugInvalid' => __('Project slug can only contain letters, numbers, and hyphens.', 'reactifywp')
            ],
            'maxUploadSize' => $this->get_max_upload_size(),
            'allowedTypes' => ['zip']
        ]);
    }

    /**
     * Admin page callback
     */
    public function admin_page()
    {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'reactifywp'));
        }

        $projects = $this->get_projects();
        
        include REACTIFYWP_PLUGIN_DIR . 'templates/admin-page.php';
    }

    /**
     * General section callback
     */
    public function general_section_callback()
    {
        echo '<p>' . esc_html__('Configure ReactifyWP settings below.', 'reactifywp') . '</p>';
    }

    /**
     * Max upload size callback
     */
    public function max_upload_size_callback()
    {
        $options = get_option('reactifywp_options', []);
        $value = $options['max_upload_size'] ?? '50MB';
        
        echo '<input type="text" name="reactifywp_options[max_upload_size]" value="' . esc_attr($value) . '" />';
        echo '<p class="description">' . esc_html__('Maximum size for uploaded ZIP files (e.g., 50MB, 100MB).', 'reactifywp') . '</p>';
    }

    /**
     * Enable scoped styles callback
     */
    public function enable_scoped_styles_callback()
    {
        $options = get_option('reactifywp_options', []);
        $checked = $options['enable_scoped_styles'] ?? true;
        
        echo '<input type="checkbox" name="reactifywp_options[enable_scoped_styles]" value="1" ' . checked($checked, true, false) . ' />';
        echo '<p class="description">' . esc_html__('Wrap React apps in scoped containers to prevent style conflicts.', 'reactifywp') . '</p>';
    }

    /**
     * Enable cache busting callback
     */
    public function enable_cache_busting_callback()
    {
        $options = get_option('reactifywp_options', []);
        $checked = $options['enable_cache_busting'] ?? true;
        
        echo '<input type="checkbox" name="reactifywp_options[enable_cache_busting]" value="1" ' . checked($checked, true, false) . ' />';
        echo '<p class="description">' . esc_html__('Add version parameters to asset URLs for cache busting.', 'reactifywp') . '</p>';
    }

    /**
     * Defer JS loading callback
     */
    public function defer_js_loading_callback()
    {
        $options = get_option('reactifywp_options', []);
        $checked = $options['defer_js_loading'] ?? true;
        
        echo '<input type="checkbox" name="reactifywp_options[defer_js_loading]" value="1" ' . checked($checked, true, false) . ' />';
        echo '<p class="description">' . esc_html__('Load JavaScript files with defer attribute for better performance.', 'reactifywp') . '</p>';
    }

    /**
     * Sanitize options
     *
     * @param array $input Raw input data
     * @return array Sanitized options
     */
    public function sanitize_options($input)
    {
        $sanitized = [];
        
        if (isset($input['max_upload_size'])) {
            $sanitized['max_upload_size'] = sanitize_text_field($input['max_upload_size']);
        }
        
        $sanitized['enable_scoped_styles'] = isset($input['enable_scoped_styles']);
        $sanitized['enable_cache_busting'] = isset($input['enable_cache_busting']);
        $sanitized['defer_js_loading'] = isset($input['defer_js_loading']);
        
        return $sanitized;
    }

    /**
     * Handle upload AJAX request
     */
    public function handle_upload()
    {
        check_ajax_referer('reactifywp_admin', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions.', 'reactifywp'));
        }

        $project = new Project();
        $result = $project->upload_from_request();
        
        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }
        
        wp_send_json_success([
            'message' => __('Project uploaded successfully!', 'reactifywp'),
            'project' => $result
        ]);
    }

    /**
     * Handle delete AJAX request
     */
    public function handle_delete()
    {
        check_ajax_referer('reactifywp_admin', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions.', 'reactifywp'));
        }

        $slug = sanitize_text_field($_POST['slug'] ?? '');
        
        if (empty($slug)) {
            wp_send_json_error(__('Project slug is required.', 'reactifywp'));
        }

        $project = new Project();
        $result = $project->delete($slug);
        
        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }
        
        wp_send_json_success([
            'message' => __('Project deleted successfully!', 'reactifywp')
        ]);
    }

    /**
     * Handle get projects AJAX request
     */
    public function handle_get_projects()
    {
        check_ajax_referer('reactifywp_admin', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions.', 'reactifywp'));
        }

        $projects = $this->get_projects();
        
        wp_send_json_success($projects);
    }

    /**
     * Get all projects
     *
     * @return array Projects list
     */
    private function get_projects()
    {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'reactify_projects';
        $blog_id = get_current_blog_id();
        
        $projects = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$table_name} WHERE blog_id = %d ORDER BY created_at DESC",
            $blog_id
        ), ARRAY_A);
        
        return $projects ?: [];
    }

    /**
     * Handle toggle status AJAX request
     */
    public function handle_toggle_status()
    {
        check_ajax_referer('reactifywp_admin', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions.', 'reactifywp'));
        }

        $slug = sanitize_text_field($_POST['slug'] ?? '');
        $new_status = sanitize_text_field($_POST['status'] ?? '');

        if (empty($slug) || empty($new_status)) {
            wp_send_json_error(__('Invalid parameters.', 'reactifywp'));
        }

        $result = $this->update_project_status($slug, $new_status);

        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }

        wp_send_json_success([
            'message' => __('Project status updated successfully!', 'reactifywp'),
            'status' => $new_status
        ]);
    }

    /**
     * Handle edit project AJAX request
     */
    public function handle_edit_project()
    {
        check_ajax_referer('reactifywp_admin', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions.', 'reactifywp'));
        }

        $slug = sanitize_text_field($_POST['slug'] ?? '');

        if (empty($slug)) {
            wp_send_json_error(__('Project slug is required.', 'reactifywp'));
        }

        $project = new Project();
        $project_data = $project->get_by_slug($slug);

        if (!$project_data) {
            wp_send_json_error(__('Project not found.', 'reactifywp'));
        }

        wp_send_json_success([
            'project' => $project_data
        ]);
    }

    /**
     * Handle update project AJAX request
     */
    public function handle_update_project()
    {
        check_ajax_referer('reactifywp_admin', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions.', 'reactifywp'));
        }

        $slug = sanitize_text_field($_POST['slug'] ?? '');
        $project_name = sanitize_text_field($_POST['project_name'] ?? '');
        $description = sanitize_textarea_field($_POST['description'] ?? '');
        $shortcode = sanitize_text_field($_POST['shortcode'] ?? '');

        if (empty($slug) || empty($project_name)) {
            wp_send_json_error(__('Project slug and name are required.', 'reactifywp'));
        }

        $result = $this->update_project_details($slug, [
            'project_name' => $project_name,
            'description' => $description,
            'shortcode' => $shortcode
        ]);

        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }

        wp_send_json_success([
            'message' => __('Project updated successfully!', 'reactifywp')
        ]);
    }

    /**
     * Handle duplicate project AJAX request
     */
    public function handle_duplicate_project()
    {
        check_ajax_referer('reactifywp_admin', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions.', 'reactifywp'));
        }

        $slug = sanitize_text_field($_POST['slug'] ?? '');

        if (empty($slug)) {
            wp_send_json_error(__('Project slug is required.', 'reactifywp'));
        }

        $result = $this->duplicate_project($slug);

        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }

        wp_send_json_success([
            'message' => __('Project duplicated successfully!', 'reactifywp'),
            'new_slug' => $result['slug']
        ]);
    }

    /**
     * Handle bulk action AJAX request
     */
    public function handle_bulk_action()
    {
        check_ajax_referer('reactifywp_admin', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions.', 'reactifywp'));
        }

        $action = sanitize_text_field($_POST['action_type'] ?? '');
        $projects = array_map('sanitize_text_field', $_POST['projects'] ?? []);

        if (empty($action) || empty($projects)) {
            wp_send_json_error(__('Invalid parameters.', 'reactifywp'));
        }

        $result = $this->perform_bulk_action($action, $projects);

        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }

        wp_send_json_success([
            'message' => sprintf(__('%d projects processed successfully!', 'reactifywp'), count($projects))
        ]);
    }

    /**
     * Get maximum upload size
     *
     * @return string Maximum upload size
     */
    private function get_max_upload_size()
    {
        $options = get_option('reactifywp_options', []);
        return $options['max_upload_size'] ?? '50MB';
    }

    /**
     * Update project status
     *
     * @param string $slug   Project slug
     * @param string $status New status
     * @return bool|\WP_Error True on success, error on failure
     */
    private function update_project_status($slug, $status)
    {
        global $wpdb;

        $table_name = $wpdb->prefix . 'reactify_projects';
        $blog_id = get_current_blog_id();

        $valid_statuses = ['active', 'inactive', 'error'];
        if (!in_array($status, $valid_statuses, true)) {
            return new \WP_Error('invalid_status', __('Invalid status.', 'reactifywp'));
        }

        $updated = $wpdb->update(
            $table_name,
            ['status' => $status, 'updated_at' => current_time('mysql')],
            ['blog_id' => $blog_id, 'slug' => $slug],
            ['%s', '%s'],
            ['%d', '%s']
        );

        if ($updated === false) {
            return new \WP_Error('update_failed', __('Failed to update project status.', 'reactifywp'));
        }

        return true;
    }

    /**
     * Update project details
     *
     * @param string $slug Project slug
     * @param array  $data Project data
     * @return bool|\WP_Error True on success, error on failure
     */
    private function update_project_details($slug, $data)
    {
        global $wpdb;

        $table_name = $wpdb->prefix . 'reactify_projects';
        $blog_id = get_current_blog_id();

        $data['updated_at'] = current_time('mysql');

        $updated = $wpdb->update(
            $table_name,
            $data,
            ['blog_id' => $blog_id, 'slug' => $slug],
            array_fill(0, count($data), '%s'),
            ['%d', '%s']
        );

        if ($updated === false) {
            return new \WP_Error('update_failed', __('Failed to update project.', 'reactifywp'));
        }

        return true;
    }

    /**
     * Duplicate project
     *
     * @param string $slug Original project slug
     * @return array|\WP_Error New project data or error
     */
    private function duplicate_project($slug)
    {
        global $wpdb;

        $project = new Project();
        $original = $project->get_by_slug($slug);

        if (!$original) {
            return new \WP_Error('project_not_found', __('Original project not found.', 'reactifywp'));
        }

        // Generate new slug
        $new_slug = $slug . '-copy';
        $counter = 1;
        while ($project->get_by_slug($new_slug)) {
            $new_slug = $slug . '-copy-' . $counter;
            $counter++;
        }

        // Copy project directory
        $upload_dir = wp_upload_dir();
        $blog_id = get_current_blog_id();
        $original_path = $original->file_path;
        $new_path = $upload_dir['basedir'] . '/reactify-projects/' . $blog_id . '/' . $new_slug;

        if (!$this->copy_directory($original_path, $new_path)) {
            return new \WP_Error('copy_failed', __('Failed to copy project files.', 'reactifywp'));
        }

        // Create new project record
        $table_name = $wpdb->prefix . 'reactify_projects';
        $new_data = [
            'blog_id' => $original->blog_id,
            'slug' => $new_slug,
            'shortcode' => $new_slug,
            'project_name' => $original->project_name . ' (Copy)',
            'description' => $original->description,
            'file_path' => $new_path,
            'file_size' => $original->file_size,
            'version' => $original->version,
            'status' => 'inactive',
            'settings' => $original->settings,
            'created_at' => current_time('mysql'),
            'updated_at' => current_time('mysql')
        ];

        $result = $wpdb->insert($table_name, $new_data);

        if ($result === false) {
            // Clean up copied files
            $this->remove_directory($new_path);
            return new \WP_Error('insert_failed', __('Failed to create duplicate project record.', 'reactifywp'));
        }

        // Copy assets
        $new_project_id = $wpdb->insert_id;
        $this->duplicate_project_assets($original->id, $new_project_id);

        return [
            'slug' => $new_slug,
            'name' => $new_data['project_name']
        ];
    }

    /**
     * Perform bulk action on projects
     *
     * @param string $action   Bulk action
     * @param array  $projects Project slugs
     * @return bool|\WP_Error True on success, error on failure
     */
    private function perform_bulk_action($action, $projects)
    {
        $project = new Project();
        $errors = [];

        foreach ($projects as $slug) {
            switch ($action) {
                case 'activate':
                    $result = $this->update_project_status($slug, 'active');
                    break;
                case 'deactivate':
                    $result = $this->update_project_status($slug, 'inactive');
                    break;
                case 'delete':
                    $result = $project->delete($slug);
                    break;
                case 'export':
                    // Export will be handled separately
                    $result = true;
                    break;
                default:
                    $result = new \WP_Error('invalid_action', __('Invalid bulk action.', 'reactifywp'));
            }

            if (is_wp_error($result)) {
                $errors[] = sprintf(__('Failed to %s project %s: %s', 'reactifywp'), $action, $slug, $result->get_error_message());
            }
        }

        if (!empty($errors)) {
            return new \WP_Error('bulk_action_errors', implode('<br>', $errors));
        }

        return true;
    }

    /**
     * Copy directory recursively
     *
     * @param string $source      Source directory
     * @param string $destination Destination directory
     * @return bool True on success, false on failure
     */
    private function copy_directory($source, $destination)
    {
        if (!is_dir($source)) {
            return false;
        }

        if (!wp_mkdir_p($destination)) {
            return false;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($source, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $item) {
            $target = $destination . DIRECTORY_SEPARATOR . $iterator->getSubPathName();

            if ($item->isDir()) {
                if (!wp_mkdir_p($target)) {
                    return false;
                }
            } else {
                if (!copy($item, $target)) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * Duplicate project assets
     *
     * @param int $original_project_id Original project ID
     * @param int $new_project_id      New project ID
     */
    private function duplicate_project_assets($original_project_id, $new_project_id)
    {
        global $wpdb;

        $assets_table = $wpdb->prefix . 'reactify_assets';

        $assets = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$assets_table} WHERE project_id = %d",
            $original_project_id
        ), ARRAY_A);

        foreach ($assets as $asset) {
            unset($asset['id']);
            $asset['project_id'] = $new_project_id;
            $asset['created_at'] = current_time('mysql');

            $wpdb->insert($assets_table, $asset);
        }
    }

    /**
     * Remove directory recursively
     *
     * @param string $dir Directory path
     */
    private function remove_directory($dir)
    {
        if (!is_dir($dir)) {
            return;
        }

        $files = array_diff(scandir($dir), ['.', '..']);

        foreach ($files as $file) {
            $path = $dir . '/' . $file;

            if (is_dir($path)) {
                $this->remove_directory($path);
            } else {
                unlink($path);
            }
        }

        rmdir($dir);
    }
}
