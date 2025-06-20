<?php
/**
 * File management system for ReactifyWP
 *
 * @package ReactifyWP
 */

namespace ReactifyWP;

/**
 * File Manager class
 */
class FileManager
{
    /**
     * Base upload directory
     */
    const BASE_UPLOAD_DIR = 'reactify-projects';

    /**
     * Backup directory
     */
    const BACKUP_DIR = 'reactify-backups';

    /**
     * Maximum backup retention days
     */
    const MAX_BACKUP_DAYS = 30;

    /**
     * Constructor
     */
    public function __construct()
    {
        add_action('reactifywp_daily_cleanup', [$this, 'daily_cleanup']);
        add_action('reactifywp_weekly_maintenance', [$this, 'weekly_maintenance']);
    }

    /**
     * Organize uploaded project files
     *
     * @param string $temp_path    Temporary extraction path
     * @param string $project_slug Project slug
     * @param int    $project_id   Project ID
     * @return array|\WP_Error Organization result
     */
    public function organize_project_files($temp_path, $project_slug, $project_id)
    {
        try {
            // Get target directory
            $target_dir = $this->get_project_directory($project_slug);
            
            // Handle existing project (backup if needed)
            if (is_dir($target_dir)) {
                $backup_result = $this->backup_existing_project($target_dir, $project_slug);
                if (is_wp_error($backup_result)) {
                    return $backup_result;
                }
            }

            // Create target directory
            if (!wp_mkdir_p($target_dir)) {
                return new \WP_Error('mkdir_failed', __('Failed to create project directory.', 'reactifywp'));
            }

            // Move files from temp to target
            $move_result = $this->move_files_to_target($temp_path, $target_dir);
            if (is_wp_error($move_result)) {
                return $move_result;
            }

            // Set proper permissions
            $this->set_directory_permissions($target_dir);

            // Create security files
            $this->create_security_files($target_dir);

            // Generate file manifest
            $manifest = $this->generate_file_manifest($target_dir, $project_id);

            // Clean up temp directory
            $this->cleanup_directory($temp_path);

            return [
                'success' => true,
                'project_directory' => $target_dir,
                'manifest' => $manifest,
                'message' => __('Project files organized successfully.', 'reactifywp')
            ];

        } catch (\Exception $e) {
            error_log('ReactifyWP File Organization Error: ' . $e->getMessage());
            return new \WP_Error('organization_failed', __('File organization failed.', 'reactifywp'));
        }
    }

    /**
     * Get project directory path
     *
     * @param string $project_slug Project slug
     * @return string Directory path
     */
    public function get_project_directory($project_slug)
    {
        $upload_dir = wp_upload_dir();
        $blog_id = get_current_blog_id();
        
        return $upload_dir['basedir'] . '/' . self::BASE_UPLOAD_DIR . '/' . $blog_id . '/' . $project_slug;
    }

    /**
     * Get project URL
     *
     * @param string $project_slug Project slug
     * @return string Project URL
     */
    public function get_project_url($project_slug)
    {
        $upload_dir = wp_upload_dir();
        $blog_id = get_current_blog_id();
        
        return $upload_dir['baseurl'] . '/' . self::BASE_UPLOAD_DIR . '/' . $blog_id . '/' . $project_slug;
    }

    /**
     * Backup existing project
     *
     * @param string $project_dir  Project directory
     * @param string $project_slug Project slug
     * @return true|\WP_Error Backup result
     */
    private function backup_existing_project($project_dir, $project_slug)
    {
        $backup_dir = $this->get_backup_directory();
        
        if (!wp_mkdir_p($backup_dir)) {
            return new \WP_Error('backup_dir_failed', __('Failed to create backup directory.', 'reactifywp'));
        }

        $timestamp = date('Y-m-d_H-i-s');
        $backup_path = $backup_dir . '/' . $project_slug . '_' . $timestamp;

        // Copy directory recursively
        $copy_result = $this->copy_directory_recursive($project_dir, $backup_path);
        if (!$copy_result) {
            return new \WP_Error('backup_failed', __('Failed to backup existing project.', 'reactifywp'));
        }

        // Create backup metadata
        $metadata = [
            'project_slug' => $project_slug,
            'backup_time' => current_time('mysql'),
            'original_path' => $project_dir,
            'backup_path' => $backup_path,
            'size' => $this->get_directory_size($backup_path)
        ];

        file_put_contents($backup_path . '/backup_metadata.json', wp_json_encode($metadata, JSON_PRETTY_PRINT));

        return true;
    }

    /**
     * Move files from temp to target directory
     *
     * @param string $temp_path   Temporary path
     * @param string $target_dir  Target directory
     * @return true|\WP_Error Move result
     */
    private function move_files_to_target($temp_path, $target_dir)
    {
        if (!is_dir($temp_path)) {
            return new \WP_Error('temp_dir_missing', __('Temporary directory not found.', 'reactifywp'));
        }

        // Remove existing target directory
        if (is_dir($target_dir)) {
            $this->cleanup_directory($target_dir);
        }

        // Move directory
        if (!rename($temp_path, $target_dir)) {
            // Fallback: copy and delete
            $copy_result = $this->copy_directory_recursive($temp_path, $target_dir);
            if (!$copy_result) {
                return new \WP_Error('move_failed', __('Failed to move files to target directory.', 'reactifywp'));
            }
            
            $this->cleanup_directory($temp_path);
        }

        return true;
    }

    /**
     * Set proper directory permissions
     *
     * @param string $directory Directory path
     */
    private function set_directory_permissions($directory)
    {
        // Set directory permissions
        chmod($directory, 0755);

        // Set file permissions recursively
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if ($file->isFile()) {
                chmod($file->getPathname(), 0644);
            } elseif ($file->isDir()) {
                chmod($file->getPathname(), 0755);
            }
        }
    }

    /**
     * Create security files
     *
     * @param string $directory Directory path
     */
    private function create_security_files($directory)
    {
        // Create .htaccess
        $htaccess_content = "# ReactifyWP Security\n";
        $htaccess_content .= "Options -Indexes\n";
        $htaccess_content .= "Options -ExecCGI\n\n";
        $htaccess_content .= "# Deny access to PHP files\n";
        $htaccess_content .= "<Files \"*.php\">\n";
        $htaccess_content .= "    Require all denied\n";
        $htaccess_content .= "</Files>\n\n";
        $htaccess_content .= "# Allow specific file types\n";
        $htaccess_content .= "<FilesMatch \"\\.(js|css|html|json|png|jpg|jpeg|gif|svg|woff|woff2|ttf|eot|ico|mp4|webm|ogg|mp3|wav)$\">\n";
        $htaccess_content .= "    Require all granted\n";
        $htaccess_content .= "</FilesMatch>\n\n";
        $htaccess_content .= "# Set proper MIME types\n";
        $htaccess_content .= "AddType application/javascript .js\n";
        $htaccess_content .= "AddType text/css .css\n";
        $htaccess_content .= "AddType application/json .json\n";
        $htaccess_content .= "AddType image/svg+xml .svg\n";

        file_put_contents($directory . '/.htaccess', $htaccess_content);

        // Create index.php to prevent directory listing
        $index_content = "<?php\n// Silence is golden.\n";
        file_put_contents($directory . '/index.php', $index_content);
    }

    /**
     * Generate file manifest
     *
     * @param string $directory  Project directory
     * @param int    $project_id Project ID
     * @return array File manifest
     */
    private function generate_file_manifest($directory, $project_id)
    {
        $manifest = [
            'project_id' => $project_id,
            'generated_at' => current_time('mysql'),
            'directory' => $directory,
            'files' => [],
            'total_size' => 0,
            'file_count' => 0
        ];

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $relative_path = str_replace($directory . '/', '', $file->getPathname());
                $file_size = $file->getSize();
                
                $file_info = [
                    'path' => $relative_path,
                    'size' => $file_size,
                    'type' => $this->get_file_type($file->getExtension()),
                    'hash' => hash_file('sha256', $file->getPathname()),
                    'modified' => date('Y-m-d H:i:s', $file->getMTime())
                ];

                $manifest['files'][] = $file_info;
                $manifest['total_size'] += $file_size;
                $manifest['file_count']++;
            }
        }

        // Save manifest to file
        file_put_contents($directory . '/reactify-manifest.json', wp_json_encode($manifest, JSON_PRETTY_PRINT));

        return $manifest;
    }

    /**
     * Get file type based on extension
     *
     * @param string $extension File extension
     * @return string File type
     */
    private function get_file_type($extension)
    {
        $type_map = [
            'js' => 'javascript',
            'jsx' => 'javascript',
            'ts' => 'typescript',
            'tsx' => 'typescript',
            'css' => 'stylesheet',
            'scss' => 'stylesheet',
            'sass' => 'stylesheet',
            'less' => 'stylesheet',
            'html' => 'markup',
            'htm' => 'markup',
            'json' => 'data',
            'map' => 'sourcemap',
            'txt' => 'text',
            'md' => 'markdown',
            'svg' => 'image',
            'png' => 'image',
            'jpg' => 'image',
            'jpeg' => 'image',
            'gif' => 'image',
            'webp' => 'image',
            'ico' => 'image',
            'woff' => 'font',
            'woff2' => 'font',
            'ttf' => 'font',
            'eot' => 'font',
            'otf' => 'font',
            'mp4' => 'video',
            'webm' => 'video',
            'ogg' => 'audio',
            'mp3' => 'audio',
            'wav' => 'audio'
        ];

        return $type_map[strtolower($extension)] ?? 'unknown';
    }

    /**
     * Get backup directory
     *
     * @return string Backup directory path
     */
    private function get_backup_directory()
    {
        $upload_dir = wp_upload_dir();
        return $upload_dir['basedir'] . '/' . self::BACKUP_DIR;
    }

    /**
     * Copy directory recursively
     *
     * @param string $source      Source directory
     * @param string $destination Destination directory
     * @return bool Success status
     */
    private function copy_directory_recursive($source, $destination)
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
            $target = $destination . '/' . $iterator->getSubPathName();
            
            if ($item->isDir()) {
                wp_mkdir_p($target);
            } else {
                copy($item->getPathname(), $target);
            }
        }

        return true;
    }

    /**
     * Get directory size
     *
     * @param string $directory Directory path
     * @return int Directory size in bytes
     */
    private function get_directory_size($directory)
    {
        $size = 0;
        
        if (!is_dir($directory)) {
            return $size;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $size += $file->getSize();
            }
        }

        return $size;
    }

    /**
     * Clean up directory
     *
     * @param string $directory Directory path
     */
    private function cleanup_directory($directory)
    {
        if (!is_dir($directory)) {
            return;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($iterator as $file) {
            if ($file->isDir()) {
                rmdir($file->getPathname());
            } else {
                wp_delete_file($file->getPathname());
            }
        }

        rmdir($directory);
    }

    /**
     * Delete project files
     *
     * @param string $project_slug Project slug
     * @return true|\WP_Error Deletion result
     */
    public function delete_project($project_slug)
    {
        $project_dir = $this->get_project_directory($project_slug);

        if (!is_dir($project_dir)) {
            return new \WP_Error('project_not_found', __('Project directory not found.', 'reactifywp'));
        }

        // Create backup before deletion
        $backup_result = $this->backup_existing_project($project_dir, $project_slug . '_deleted');
        if (is_wp_error($backup_result)) {
            return $backup_result;
        }

        // Delete project directory
        $this->cleanup_directory($project_dir);

        return true;
    }

    /**
     * Duplicate project files
     *
     * @param string $source_slug Source project slug
     * @param string $target_slug Target project slug
     * @return true|\WP_Error Duplication result
     */
    public function duplicate_project($source_slug, $target_slug)
    {
        $source_dir = $this->get_project_directory($source_slug);
        $target_dir = $this->get_project_directory($target_slug);

        if (!is_dir($source_dir)) {
            return new \WP_Error('source_not_found', __('Source project not found.', 'reactifywp'));
        }

        if (is_dir($target_dir)) {
            return new \WP_Error('target_exists', __('Target project already exists.', 'reactifywp'));
        }

        // Copy project files
        $copy_result = $this->copy_directory_recursive($source_dir, $target_dir);
        if (!$copy_result) {
            return new \WP_Error('copy_failed', __('Failed to duplicate project.', 'reactifywp'));
        }

        // Update manifest
        $manifest_path = $target_dir . '/reactify-manifest.json';
        if (file_exists($manifest_path)) {
            $manifest = json_decode(file_get_contents($manifest_path), true);
            $manifest['duplicated_from'] = $source_slug;
            $manifest['duplicated_at'] = current_time('mysql');
            file_put_contents($manifest_path, wp_json_encode($manifest, JSON_PRETTY_PRINT));
        }

        return true;
    }

    /**
     * Get project statistics
     *
     * @param string $project_slug Project slug
     * @return array|\WP_Error Project statistics
     */
    public function get_project_stats($project_slug)
    {
        $project_dir = $this->get_project_directory($project_slug);

        if (!is_dir($project_dir)) {
            return new \WP_Error('project_not_found', __('Project not found.', 'reactifywp'));
        }

        $stats = [
            'project_slug' => $project_slug,
            'directory' => $project_dir,
            'total_size' => $this->get_directory_size($project_dir),
            'file_count' => 0,
            'directory_count' => 0,
            'file_types' => [],
            'last_modified' => 0
        ];

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($project_dir, \RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $stats['file_count']++;

                $extension = strtolower($file->getExtension());
                if (!isset($stats['file_types'][$extension])) {
                    $stats['file_types'][$extension] = 0;
                }
                $stats['file_types'][$extension]++;

                $mtime = $file->getMTime();
                if ($mtime > $stats['last_modified']) {
                    $stats['last_modified'] = $mtime;
                }
            } elseif ($file->isDir()) {
                $stats['directory_count']++;
            }
        }

        $stats['last_modified'] = date('Y-m-d H:i:s', $stats['last_modified']);

        return $stats;
    }

    /**
     * Daily cleanup routine
     */
    public function daily_cleanup()
    {
        // Clean up old temporary files
        $this->cleanup_old_temp_files();

        // Clean up old backups
        $this->cleanup_old_backups();

        // Log cleanup activity
        error_log('ReactifyWP: Daily cleanup completed');
    }

    /**
     * Weekly maintenance routine
     */
    public function weekly_maintenance()
    {
        // Optimize file storage
        $this->optimize_file_storage();

        // Generate storage report
        $this->generate_storage_report();

        // Log maintenance activity
        error_log('ReactifyWP: Weekly maintenance completed');
    }

    /**
     * Clean up old temporary files
     */
    private function cleanup_old_temp_files()
    {
        $upload_dir = wp_upload_dir();
        $temp_dir = $upload_dir['basedir'] . '/reactify-temp';

        if (!is_dir($temp_dir)) {
            return;
        }

        $current_time = time();
        $max_age = 24 * 60 * 60; // 24 hours

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($temp_dir, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($iterator as $file) {
            if (($current_time - $file->getMTime()) > $max_age) {
                if ($file->isDir()) {
                    rmdir($file->getPathname());
                } else {
                    wp_delete_file($file->getPathname());
                }
            }
        }
    }

    /**
     * Clean up old backups
     */
    private function cleanup_old_backups()
    {
        $backup_dir = $this->get_backup_directory();

        if (!is_dir($backup_dir)) {
            return;
        }

        $current_time = time();
        $max_age = self::MAX_BACKUP_DAYS * 24 * 60 * 60;

        $backups = glob($backup_dir . '/*', GLOB_ONLYDIR);

        foreach ($backups as $backup) {
            if (($current_time - filemtime($backup)) > $max_age) {
                $this->cleanup_directory($backup);
            }
        }
    }

    /**
     * Optimize file storage
     */
    private function optimize_file_storage()
    {
        // This could include:
        // - Compressing old files
        // - Removing duplicate files
        // - Optimizing file organization

        $upload_dir = wp_upload_dir();
        $projects_dir = $upload_dir['basedir'] . '/' . self::BASE_UPLOAD_DIR;

        if (!is_dir($projects_dir)) {
            return;
        }

        // Find and remove duplicate files
        $this->remove_duplicate_files($projects_dir);
    }

    /**
     * Remove duplicate files
     *
     * @param string $directory Directory to scan
     */
    private function remove_duplicate_files($directory)
    {
        $file_hashes = [];
        $duplicates = [];

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \RecursiveDirectoryIterator::SKIP_DOTS)
        );

        // Find duplicates
        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getSize() > 1024) { // Only check files > 1KB
                $hash = hash_file('md5', $file->getPathname());

                if (isset($file_hashes[$hash])) {
                    $duplicates[] = $file->getPathname();
                } else {
                    $file_hashes[$hash] = $file->getPathname();
                }
            }
        }

        // Log duplicates found
        if (!empty($duplicates)) {
            error_log('ReactifyWP: Found ' . count($duplicates) . ' duplicate files');
        }
    }

    /**
     * Generate storage report
     */
    private function generate_storage_report()
    {
        $upload_dir = wp_upload_dir();
        $projects_dir = $upload_dir['basedir'] . '/' . self::BASE_UPLOAD_DIR;
        $backup_dir = $this->get_backup_directory();

        $report = [
            'generated_at' => current_time('mysql'),
            'projects' => [
                'directory' => $projects_dir,
                'size' => is_dir($projects_dir) ? $this->get_directory_size($projects_dir) : 0,
                'count' => 0
            ],
            'backups' => [
                'directory' => $backup_dir,
                'size' => is_dir($backup_dir) ? $this->get_directory_size($backup_dir) : 0,
                'count' => 0
            ],
            'total_size' => 0
        ];

        // Count projects
        if (is_dir($projects_dir)) {
            $projects = glob($projects_dir . '/*/*', GLOB_ONLYDIR);
            $report['projects']['count'] = count($projects);
        }

        // Count backups
        if (is_dir($backup_dir)) {
            $backups = glob($backup_dir . '/*', GLOB_ONLYDIR);
            $report['backups']['count'] = count($backups);
        }

        $report['total_size'] = $report['projects']['size'] + $report['backups']['size'];

        // Save report
        $report_file = $upload_dir['basedir'] . '/reactify-storage-report.json';
        file_put_contents($report_file, wp_json_encode($report, JSON_PRETTY_PRINT));

        return $report;
    }

    /**
     * Get storage usage summary
     *
     * @return array Storage usage summary
     */
    public function get_storage_usage()
    {
        $upload_dir = wp_upload_dir();
        $projects_dir = $upload_dir['basedir'] . '/' . self::BASE_UPLOAD_DIR;
        $backup_dir = $this->get_backup_directory();

        return [
            'projects_size' => is_dir($projects_dir) ? $this->get_directory_size($projects_dir) : 0,
            'backups_size' => is_dir($backup_dir) ? $this->get_directory_size($backup_dir) : 0,
            'temp_size' => is_dir($upload_dir['basedir'] . '/reactify-temp') ? $this->get_directory_size($upload_dir['basedir'] . '/reactify-temp') : 0,
            'total_size' => 0
        ];
    }
}
