<?php
/**
 * Project management for ReactifyWP
 *
 * @package ReactifyWP
 */

namespace ReactifyWP;

/**
 * Project class
 */
class Project
{
    /**
     * Upload project from request
     *
     * @return array|\WP_Error Project data or error
     */
    public function upload_from_request()
    {
        // Validate request
        $validation = $this->validate_upload_request();
        if (is_wp_error($validation)) {
            return $validation;
        }

        $slug = sanitize_text_field($_POST['slug']);
        $shortcode = sanitize_text_field($_POST['shortcode'] ?? $slug);
        $project_name = sanitize_text_field($_POST['project_name'] ?? $slug);

        // Check if project already exists
        if ($this->project_exists($slug)) {
            return new \WP_Error('project_exists', __('A project with this slug already exists.', 'reactifywp'));
        }

        // Handle file upload
        $upload_result = $this->handle_file_upload();
        if (is_wp_error($upload_result)) {
            return $upload_result;
        }

        // Extract ZIP file
        $extract_result = $this->extract_zip($upload_result['file'], $slug);
        if (is_wp_error($extract_result)) {
            // Clean up uploaded file
            unlink($upload_result['file']);
            return $extract_result;
        }

        // Save project to database
        $project_data = [
            'slug' => $slug,
            'shortcode' => $shortcode,
            'project_name' => $project_name,
            'file_path' => $extract_result['path'],
            'file_size' => $this->calculate_directory_size($extract_result['path']),
            'version' => $this->generate_version($extract_result['path'])
        ];

        $save_result = $this->save_project($project_data);
        if (is_wp_error($save_result)) {
            // Clean up extracted files
            $this->remove_directory($extract_result['path']);
            unlink($upload_result['file']);
            return $save_result;
        }

        // Get the project ID for asset cataloging
        $project_id = $this->get_project_id_by_slug($slug);

        // Catalog assets using AssetManager
        $asset_manager = new AssetManager();
        $assets_result = $asset_manager->catalog_assets($extract_result['path'], $project_id);

        if (is_wp_error($assets_result)) {
            // Log asset cataloging error but don't fail the upload
            error_log('ReactifyWP: Asset cataloging failed for project ' . $slug . ': ' . $assets_result->get_error_message());
        }

        // Clean up uploaded ZIP file
        unlink($upload_result['file']);

        return $project_data;
    }

    /**
     * Delete project
     *
     * @param string $slug Project slug
     * @return bool|\WP_Error True on success, error on failure
     */
    public function delete($slug)
    {
        global $wpdb;

        $table_name = $wpdb->prefix . 'reactify_projects';
        $blog_id = get_current_blog_id();

        // Get project data
        $project = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table_name} WHERE blog_id = %d AND slug = %s",
            $blog_id,
            $slug
        ));

        if (!$project) {
            return new \WP_Error('project_not_found', __('Project not found.', 'reactifywp'));
        }

        // Remove files
        if (file_exists($project->file_path)) {
            $this->remove_directory($project->file_path);
        }

        // Remove from database
        $deleted = $wpdb->delete(
            $table_name,
            [
                'blog_id' => $blog_id,
                'slug' => $slug
            ],
            ['%d', '%s']
        );

        if ($deleted === false) {
            return new \WP_Error('delete_failed', __('Failed to delete project from database.', 'reactifywp'));
        }

        return true;
    }

    /**
     * Get project by slug
     *
     * @param string $slug Project slug
     * @return object|null Project data or null
     */
    public function get_by_slug($slug)
    {
        global $wpdb;

        $table_name = $wpdb->prefix . 'reactify_projects';
        $blog_id = get_current_blog_id();

        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table_name} WHERE blog_id = %d AND slug = %s",
            $blog_id,
            $slug
        ));
    }

    /**
     * Get project ID by slug
     *
     * @param string $slug Project slug
     * @return int|null Project ID or null
     */
    public function get_project_id_by_slug($slug)
    {
        global $wpdb;

        $table_name = $wpdb->prefix . 'reactify_projects';
        $blog_id = get_current_blog_id();

        return $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$table_name} WHERE blog_id = %d AND slug = %s",
            $blog_id,
            $slug
        ));
    }

    /**
     * Get all projects for current blog
     *
     * @return array Projects list
     */
    public function get_all()
    {
        global $wpdb;

        $table_name = $wpdb->prefix . 'reactify_projects';
        $blog_id = get_current_blog_id();

        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$table_name} WHERE blog_id = %d ORDER BY created_at DESC",
            $blog_id
        ));
    }

    /**
     * Get project assets
     *
     * @param string $slug Project slug
     * @return array|\WP_Error Assets array or error
     */
    public function get_assets($slug)
    {
        $project = $this->get_by_slug($slug);
        
        if (!$project) {
            return new \WP_Error('project_not_found', __('Project not found.', 'reactifywp'));
        }

        $assets = [
            'js' => [],
            'css' => []
        ];

        // Check for asset manifest
        $manifest_path = $project->file_path . '/asset-manifest.json';
        if (file_exists($manifest_path)) {
            $manifest = json_decode(file_get_contents($manifest_path), true);
            if ($manifest && isset($manifest['files'])) {
                return $this->parse_manifest_assets($manifest['files'], $project);
            }
        }

        // Fallback: scan directories
        return $this->scan_directory_assets($project);
    }

    /**
     * Validate upload request
     *
     * @return bool|\WP_Error True if valid, error otherwise
     */
    private function validate_upload_request()
    {
        if (empty($_FILES['file'])) {
            return new \WP_Error('no_file', __('No file uploaded.', 'reactifywp'));
        }

        if (empty($_POST['slug'])) {
            return new \WP_Error('no_slug', __('Project slug is required.', 'reactifywp'));
        }

        $slug = sanitize_text_field($_POST['slug']);
        if (!preg_match('/^[a-z0-9-]+$/', $slug)) {
            return new \WP_Error('invalid_slug', __('Project slug can only contain lowercase letters, numbers, and hyphens.', 'reactifywp'));
        }

        return true;
    }

    /**
     * Handle file upload
     *
     * @return array|\WP_Error Upload result or error
     */
    private function handle_file_upload()
    {
        $file = $_FILES['file'];

        // Validate file type
        $file_type = wp_check_filetype($file['name']);
        if ($file_type['ext'] !== 'zip') {
            return new \WP_Error('invalid_file_type', __('Only ZIP files are allowed.', 'reactifywp'));
        }

        // Validate file size
        $max_size = $this->get_max_upload_size_bytes();
        if ($file['size'] > $max_size) {
            return new \WP_Error('file_too_large', sprintf(
                __('File size exceeds maximum allowed size of %s.', 'reactifywp'),
                size_format($max_size)
            ));
        }

        // Move uploaded file
        $upload_dir = wp_upload_dir();
        $temp_file = $upload_dir['basedir'] . '/reactify-temp-' . uniqid() . '.zip';

        if (!move_uploaded_file($file['tmp_name'], $temp_file)) {
            return new \WP_Error('upload_failed', __('Failed to move uploaded file.', 'reactifywp'));
        }

        return [
            'file' => $temp_file,
            'original_name' => $file['name']
        ];
    }

    /**
     * Extract ZIP file
     *
     * @param string $zip_file Path to ZIP file
     * @param string $slug     Project slug
     * @return array|\WP_Error Extraction result or error
     */
    private function extract_zip($zip_file, $slug)
    {
        if (!class_exists('ZipArchive')) {
            return new \WP_Error('zip_not_supported', __('ZIP extraction is not supported on this server.', 'reactifywp'));
        }

        $zip = new \ZipArchive();
        $result = $zip->open($zip_file);

        if ($result !== true) {
            return new \WP_Error('zip_open_failed', __('Failed to open ZIP file.', 'reactifywp'));
        }

        // Security check: prevent ZIP bombs and path traversal
        $security_check = $this->validate_zip_contents($zip);
        if (is_wp_error($security_check)) {
            $zip->close();
            return $security_check;
        }

        // Create extraction directory
        $upload_dir = wp_upload_dir();
        $blog_id = get_current_blog_id();
        $extract_path = $upload_dir['basedir'] . '/reactify-projects/' . $blog_id . '/' . $slug;

        if (!wp_mkdir_p($extract_path)) {
            $zip->close();
            return new \WP_Error('mkdir_failed', __('Failed to create project directory.', 'reactifywp'));
        }

        // Extract files
        if (!$zip->extractTo($extract_path)) {
            $zip->close();
            $this->remove_directory($extract_path);
            return new \WP_Error('extract_failed', __('Failed to extract ZIP file.', 'reactifywp'));
        }

        $zip->close();

        // Validate extracted contents
        $validation = $this->validate_extracted_contents($extract_path);
        if (is_wp_error($validation)) {
            $this->remove_directory($extract_path);
            return $validation;
        }

        return [
            'path' => $extract_path
        ];
    }

    /**
     * Check if project exists
     *
     * @param string $slug Project slug
     * @return bool True if exists, false otherwise
     */
    private function project_exists($slug)
    {
        return $this->get_by_slug($slug) !== null;
    }

    /**
     * Get maximum upload size in bytes
     *
     * @return int Maximum upload size in bytes
     */
    private function get_max_upload_size_bytes()
    {
        $options = get_option('reactifywp_options', []);
        $max_size = $options['max_upload_size'] ?? '50MB';

        // Convert to bytes
        $size = trim($max_size);
        $unit = strtoupper(substr($size, -2));
        $value = (int) substr($size, 0, -2);

        switch ($unit) {
            case 'GB':
                return $value * 1024 * 1024 * 1024;
            case 'MB':
                return $value * 1024 * 1024;
            case 'KB':
                return $value * 1024;
            default:
                return (int) $size;
        }
    }

    /**
     * Validate ZIP contents for security
     *
     * @param \ZipArchive $zip ZIP archive
     * @return bool|\WP_Error True if valid, error otherwise
     */
    private function validate_zip_contents($zip)
    {
        $total_size = 0;
        $max_files = 1000;
        $max_total_size = 500 * 1024 * 1024; // 500MB uncompressed

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $stat = $zip->statIndex($i);

            if ($stat === false) {
                continue;
            }

            // Check for path traversal
            if (strpos($stat['name'], '..') !== false) {
                return new \WP_Error('path_traversal', __('ZIP file contains invalid paths.', 'reactifywp'));
            }

            // Check file count
            if ($i > $max_files) {
                return new \WP_Error('too_many_files', __('ZIP file contains too many files.', 'reactifywp'));
            }

            // Check total uncompressed size
            $total_size += $stat['size'];
            if ($total_size > $max_total_size) {
                return new \WP_Error('zip_bomb', __('ZIP file is too large when uncompressed.', 'reactifywp'));
            }
        }

        return true;
    }

    /**
     * Validate extracted contents
     *
     * @param string $path Extraction path
     * @return bool|\WP_Error True if valid, error otherwise
     */
    private function validate_extracted_contents($path)
    {
        // Check for index.html
        if (!file_exists($path . '/index.html')) {
            return new \WP_Error('no_index', __('ZIP file must contain an index.html file.', 'reactifywp'));
        }

        // Check for suspicious files
        $suspicious_files = ['.php', '.exe', '.bat', '.sh', '.py', '.rb'];
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($path));

        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $extension = '.' . strtolower($file->getExtension());
                if (in_array($extension, $suspicious_files, true)) {
                    return new \WP_Error('suspicious_file', sprintf(
                        __('ZIP file contains suspicious file: %s', 'reactifywp'),
                        $file->getFilename()
                    ));
                }
            }
        }

        return true;
    }

    /**
     * Save project to database
     *
     * @param array $project_data Project data
     * @return bool|\WP_Error True on success, error on failure
     */
    private function save_project($project_data)
    {
        global $wpdb;

        $table_name = $wpdb->prefix . 'reactify_projects';
        $blog_id = get_current_blog_id();

        $data = [
            'blog_id' => $blog_id,
            'slug' => $project_data['slug'],
            'shortcode' => $project_data['shortcode'],
            'project_name' => $project_data['project_name'],
            'file_path' => $project_data['file_path'],
            'version' => $project_data['version'],
            'created_at' => current_time('mysql'),
            'updated_at' => current_time('mysql')
        ];

        $result = $wpdb->insert($table_name, $data);

        if ($result === false) {
            return new \WP_Error('save_failed', __('Failed to save project to database.', 'reactifywp'));
        }

        return true;
    }

    /**
     * Generate version hash for project
     *
     * @param string $path Project path
     * @return string Version hash
     */
    private function generate_version($path)
    {
        $files = [];
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($path));

        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $files[] = $file->getMTime() . $file->getSize();
            }
        }

        return md5(implode('', $files));
    }

    /**
     * Parse assets from manifest
     *
     * @param array  $files   Manifest files
     * @param object $project Project data
     * @return array Assets array
     */
    private function parse_manifest_assets($files, $project)
    {
        $upload_dir = wp_upload_dir();
        $blog_id = get_current_blog_id();
        $base_url = $upload_dir['baseurl'] . '/reactify-projects/' . $blog_id . '/' . $project->slug;

        $assets = [
            'js' => [],
            'css' => []
        ];

        foreach ($files as $key => $file) {
            $url = trailingslashit($base_url) . ltrim($file, '/');

            if (strpos($file, '.js') !== false) {
                $assets['js'][] = $url;
            } elseif (strpos($file, '.css') !== false) {
                $assets['css'][] = $url;
            }
        }

        return $assets;
    }

    /**
     * Scan directory for assets
     *
     * @param object $project Project data
     * @return array Assets array
     */
    private function scan_directory_assets($project)
    {
        $upload_dir = wp_upload_dir();
        $blog_id = get_current_blog_id();
        $base_url = $upload_dir['baseurl'] . '/reactify-projects/' . $blog_id . '/' . $project->slug;

        $assets = [
            'js' => [],
            'css' => []
        ];

        // Scan static directory
        $static_path = $project->file_path . '/static';
        if (is_dir($static_path)) {
            $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($static_path));

            foreach ($iterator as $file) {
                if ($file->isFile()) {
                    $extension = strtolower($file->getExtension());
                    $relative_path = str_replace($project->file_path, '', $file->getPathname());
                    $url = $base_url . str_replace('\\', '/', $relative_path);

                    if ($extension === 'js') {
                        $assets['js'][] = $url;
                    } elseif ($extension === 'css') {
                        $assets['css'][] = $url;
                    }
                }
            }
        }

        return $assets;
    }

    /**
     * Calculate directory size
     *
     * @param string $directory Directory path
     * @return int Size in bytes
     */
    private function calculate_directory_size($directory)
    {
        if (!is_dir($directory)) {
            return 0;
        }

        $size = 0;
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($directory));

        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $size += $file->getSize();
            }
        }

        return $size;
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
