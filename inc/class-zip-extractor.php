<?php
/**
 * Safe ZIP extraction for ReactifyWP
 *
 * @package ReactifyWP
 */

namespace ReactifyWP;

/**
 * ZIP Extractor class
 */
class ZipExtractor
{
    /**
     * Maximum extraction depth
     */
    const MAX_EXTRACTION_DEPTH = 10;

    /**
     * Allowed file extensions for React projects
     */
    const ALLOWED_REACT_EXTENSIONS = [
        'js', 'jsx', 'ts', 'tsx', 'css', 'scss', 'sass', 'less',
        'html', 'htm', 'json', 'map', 'txt', 'md', 'svg', 'png',
        'jpg', 'jpeg', 'gif', 'webp', 'ico', 'woff', 'woff2',
        'ttf', 'eot', 'otf', 'mp4', 'webm', 'ogg', 'mp3', 'wav'
    ];

    /**
     * Security validator instance
     *
     * @var SecurityValidator
     */
    private $security_validator;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->security_validator = new SecurityValidator();
    }

    /**
     * Extract ZIP file safely
     *
     * @param string $zip_path        ZIP file path
     * @param string $extract_to      Extraction destination
     * @param string $project_slug    Project slug
     * @return array|\WP_Error Extraction result
     */
    public function extract_zip($zip_path, $extract_to, $project_slug)
    {
        try {
            // Validate ZIP file first
            $validation_result = $this->security_validator->validate_file($zip_path);
            if (is_wp_error($validation_result)) {
                return $validation_result;
            }

            // Create extraction directory
            $extraction_result = $this->create_extraction_directory($extract_to);
            if (is_wp_error($extraction_result)) {
                return $extraction_result;
            }

            // Open ZIP file
            $zip = new \ZipArchive();
            $result = $zip->open($zip_path);

            if ($result !== true) {
                return new \WP_Error('zip_open_failed', $this->get_zip_error_message($result));
            }

            // Extract files safely
            $extracted_files = $this->extract_files_safely($zip, $extract_to, $project_slug);
            $zip->close();

            if (is_wp_error($extracted_files)) {
                $this->cleanup_extraction($extract_to);
                return $extracted_files;
            }

            // Post-extraction validation
            $post_validation = $this->validate_extracted_files($extract_to, $extracted_files);
            if (is_wp_error($post_validation)) {
                $this->cleanup_extraction($extract_to);
                return $post_validation;
            }

            // Generate extraction report
            $report = $this->generate_extraction_report($extract_to, $extracted_files, $project_slug);

            return [
                'success' => true,
                'extracted_files' => $extracted_files,
                'extraction_path' => $extract_to,
                'report' => $report,
                'message' => sprintf(
                    __('Successfully extracted %d files.', 'reactifywp'),
                    count($extracted_files)
                )
            ];

        } catch (\Exception $e) {
            error_log('ReactifyWP Extraction Error: ' . $e->getMessage());
            $this->cleanup_extraction($extract_to);
            return new \WP_Error('extraction_failed', __('ZIP extraction failed.', 'reactifywp'));
        }
    }

    /**
     * Create extraction directory
     *
     * @param string $extract_to Extraction path
     * @return true|\WP_Error Creation result
     */
    private function create_extraction_directory($extract_to)
    {
        // Ensure directory doesn't already exist
        if (file_exists($extract_to)) {
            return new \WP_Error('directory_exists', __('Extraction directory already exists.', 'reactifywp'));
        }

        // Create directory with secure permissions
        if (!wp_mkdir_p($extract_to)) {
            return new \WP_Error('mkdir_failed', __('Failed to create extraction directory.', 'reactifywp'));
        }

        // Set secure permissions
        chmod($extract_to, 0755);

        // Create .htaccess for security
        $this->create_security_htaccess($extract_to);

        return true;
    }

    /**
     * Extract files safely with validation
     *
     * @param \ZipArchive $zip        ZIP archive
     * @param string      $extract_to Extraction destination
     * @param string      $project_slug Project slug
     * @return array|\WP_Error Extracted files or error
     */
    private function extract_files_safely($zip, $extract_to, $project_slug)
    {
        $extracted_files = [];
        $skipped_files = [];
        $total_files = $zip->numFiles;

        for ($i = 0; $i < $total_files; $i++) {
            $filename = $zip->getNameIndex($i);
            
            if ($filename === false) {
                continue;
            }

            // Skip directories
            if (substr($filename, -1) === '/') {
                continue;
            }

            // Validate file path
            $path_validation = $this->validate_extraction_path($filename, $extract_to);
            if (is_wp_error($path_validation)) {
                $skipped_files[] = [
                    'filename' => $filename,
                    'reason' => $path_validation->get_error_message()
                ];
                continue;
            }

            // Get safe extraction path
            $safe_path = $this->get_safe_extraction_path($filename, $extract_to);
            if (is_wp_error($safe_path)) {
                $skipped_files[] = [
                    'filename' => $filename,
                    'reason' => $safe_path->get_error_message()
                ];
                continue;
            }

            // Create directory if needed
            $dir = dirname($safe_path);
            if (!is_dir($dir)) {
                wp_mkdir_p($dir);
            }

            // Extract file
            $file_content = $zip->getFromIndex($i);
            if ($file_content === false) {
                $skipped_files[] = [
                    'filename' => $filename,
                    'reason' => __('Failed to read file from ZIP.', 'reactifywp')
                ];
                continue;
            }

            // Validate file content
            $content_validation = $this->validate_file_content($filename, $file_content);
            if (is_wp_error($content_validation)) {
                $skipped_files[] = [
                    'filename' => $filename,
                    'reason' => $content_validation->get_error_message()
                ];
                continue;
            }

            // Write file safely
            $write_result = $this->write_file_safely($safe_path, $file_content);
            if (is_wp_error($write_result)) {
                $skipped_files[] = [
                    'filename' => $filename,
                    'reason' => $write_result->get_error_message()
                ];
                continue;
            }

            $extracted_files[] = [
                'original_path' => $filename,
                'extracted_path' => $safe_path,
                'size' => strlen($file_content),
                'type' => $this->get_file_type($filename),
                'hash' => hash('sha256', $file_content)
            ];
        }

        // Log skipped files if any
        if (!empty($skipped_files)) {
            $this->security_validator->log_security_event(
                'files_skipped',
                sprintf(__('%d files were skipped during extraction.', 'reactifywp'), count($skipped_files)),
                ['skipped_files' => $skipped_files, 'project_slug' => $project_slug]
            );
        }

        return $extracted_files;
    }

    /**
     * Validate extraction path for security
     *
     * @param string $filename   Original filename
     * @param string $extract_to Extraction destination
     * @return true|\WP_Error Validation result
     */
    private function validate_extraction_path($filename, $extract_to)
    {
        // Check for directory traversal
        if (strpos($filename, '../') !== false || strpos($filename, '..\\') !== false) {
            return new \WP_Error('path_traversal', __('Directory traversal detected.', 'reactifywp'));
        }

        // Check for absolute paths
        if (strpos($filename, '/') === 0 || preg_match('/^[a-zA-Z]:/', $filename)) {
            return new \WP_Error('absolute_path', __('Absolute paths not allowed.', 'reactifywp'));
        }

        // Check for null bytes
        if (strpos($filename, "\0") !== false) {
            return new \WP_Error('null_byte', __('Null bytes in filename.', 'reactifywp'));
        }

        // Check path depth
        $depth = substr_count($filename, '/');
        if ($depth > self::MAX_EXTRACTION_DEPTH) {
            return new \WP_Error('path_too_deep', __('File path too deep.', 'reactifywp'));
        }

        // Check filename length
        if (strlen(basename($filename)) > 255) {
            return new \WP_Error('filename_too_long', __('Filename too long.', 'reactifywp'));
        }

        return true;
    }

    /**
     * Get safe extraction path
     *
     * @param string $filename   Original filename
     * @param string $extract_to Extraction destination
     * @return string|\WP_Error Safe path or error
     */
    private function get_safe_extraction_path($filename, $extract_to)
    {
        // Sanitize filename
        $safe_filename = $this->sanitize_filename($filename);
        
        // Build full path
        $full_path = rtrim($extract_to, '/') . '/' . ltrim($safe_filename, '/');
        
        // Resolve path to prevent traversal
        $real_extract_to = realpath($extract_to);
        $real_full_path = realpath(dirname($full_path)) . '/' . basename($full_path);
        
        // Ensure the file is within the extraction directory
        if (strpos($real_full_path, $real_extract_to) !== 0) {
            return new \WP_Error('path_outside_target', __('File path outside target directory.', 'reactifywp'));
        }

        return $full_path;
    }

    /**
     * Sanitize filename for safe extraction
     *
     * @param string $filename Original filename
     * @return string Sanitized filename
     */
    private function sanitize_filename($filename)
    {
        // Replace dangerous characters
        $filename = str_replace(['..', '\\', ':', '*', '?', '"', '<', '>', '|'], '_', $filename);
        
        // Remove null bytes
        $filename = str_replace("\0", '', $filename);
        
        // Normalize path separators
        $filename = str_replace('\\', '/', $filename);
        
        // Remove leading/trailing dots and spaces
        $parts = explode('/', $filename);
        foreach ($parts as &$part) {
            $part = trim($part, '. ');
        }
        
        return implode('/', array_filter($parts));
    }

    /**
     * Validate file content
     *
     * @param string $filename File name
     * @param string $content  File content
     * @return true|\WP_Error Validation result
     */
    private function validate_file_content($filename, $content)
    {
        // Check file extension
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        
        $settings = get_option('reactifywp_settings', []);
        $strict_validation = $settings['security']['strict_file_validation'] ?? true;
        
        if ($strict_validation && !in_array($extension, self::ALLOWED_REACT_EXTENSIONS, true)) {
            return new \WP_Error(
                'disallowed_extension',
                sprintf(__('File extension "%s" not allowed.', 'reactifywp'), $extension)
            );
        }

        // Check for suspicious content in text files
        if ($this->is_text_file($extension)) {
            $suspicious_patterns = [
                '/\<\?php/i',
                '/\<script.*?src\s*=.*?["\']https?:\/\/(?!localhost)/i',
                '/eval\s*\(/i',
                '/document\.write\s*\(/i',
                '/innerHTML\s*=.*?["\'].*?\<script/i'
            ];

            foreach ($suspicious_patterns as $pattern) {
                if (preg_match($pattern, $content)) {
                    return new \WP_Error('suspicious_content', __('Suspicious content detected.', 'reactifywp'));
                }
            }
        }

        // Check file size
        $max_file_size = 10 * 1024 * 1024; // 10MB per file
        if (strlen($content) > $max_file_size) {
            return new \WP_Error('file_too_large', __('Individual file too large.', 'reactifywp'));
        }

        return true;
    }

    /**
     * Write file safely to disk
     *
     * @param string $file_path File path
     * @param string $content   File content
     * @return true|\WP_Error Write result
     */
    private function write_file_safely($file_path, $content)
    {
        // Use WordPress filesystem API for security
        global $wp_filesystem;
        
        if (!$wp_filesystem) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
            WP_Filesystem();
        }

        if (!$wp_filesystem->put_contents($file_path, $content, 0644)) {
            return new \WP_Error('write_failed', __('Failed to write file.', 'reactifywp'));
        }

        return true;
    }

    /**
     * Check if file is a text file
     *
     * @param string $extension File extension
     * @return bool True if text file
     */
    private function is_text_file($extension)
    {
        $text_extensions = [
            'js', 'jsx', 'ts', 'tsx', 'css', 'scss', 'sass', 'less',
            'html', 'htm', 'json', 'txt', 'md', 'xml', 'svg'
        ];

        return in_array($extension, $text_extensions, true);
    }

    /**
     * Get file type based on extension
     *
     * @param string $filename File name
     * @return string File type
     */
    private function get_file_type($filename)
    {
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

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

        return $type_map[$extension] ?? 'unknown';
    }

    /**
     * Validate extracted files
     *
     * @param string $extract_to      Extraction path
     * @param array  $extracted_files Extracted files list
     * @return true|\WP_Error Validation result
     */
    private function validate_extracted_files($extract_to, $extracted_files)
    {
        // Check if we have any files
        if (empty($extracted_files)) {
            return new \WP_Error('no_files_extracted', __('No files were extracted.', 'reactifywp'));
        }

        // Look for React app indicators
        $has_index_html = false;
        $has_js_files = false;
        $has_package_json = false;

        foreach ($extracted_files as $file) {
            $basename = basename($file['original_path']);
            $type = $file['type'];

            if ($basename === 'index.html') {
                $has_index_html = true;
            }

            if ($type === 'javascript' || $type === 'typescript') {
                $has_js_files = true;
            }

            if ($basename === 'package.json') {
                $has_package_json = true;
            }
        }

        // Validate React app structure
        if (!$has_index_html && !$has_js_files) {
            return new \WP_Error(
                'invalid_react_app',
                __('This does not appear to be a valid React application. No HTML or JavaScript files found.', 'reactifywp')
            );
        }

        // Check for common React build patterns
        $this->validate_react_build_structure($extracted_files);

        return true;
    }

    /**
     * Validate React build structure
     *
     * @param array $extracted_files Extracted files
     * @return true|\WP_Error Validation result
     */
    private function validate_react_build_structure($extracted_files)
    {
        $structure_indicators = [
            'has_static_folder' => false,
            'has_manifest_json' => false,
            'has_asset_manifest' => false,
            'has_service_worker' => false
        ];

        foreach ($extracted_files as $file) {
            $path = $file['original_path'];
            $basename = basename($path);

            if (strpos($path, 'static/') !== false) {
                $structure_indicators['has_static_folder'] = true;
            }

            if ($basename === 'manifest.json') {
                $structure_indicators['has_manifest_json'] = true;
            }

            if ($basename === 'asset-manifest.json') {
                $structure_indicators['has_asset_manifest'] = true;
            }

            if (strpos($basename, 'service-worker') !== false) {
                $structure_indicators['has_service_worker'] = true;
            }
        }

        // Log structure analysis for debugging
        $this->security_validator->log_security_event(
            'react_structure_analysis',
            'React app structure validated',
            $structure_indicators
        );

        return true;
    }

    /**
     * Generate extraction report
     *
     * @param string $extract_to      Extraction path
     * @param array  $extracted_files Extracted files
     * @param string $project_slug    Project slug
     * @return array Extraction report
     */
    private function generate_extraction_report($extract_to, $extracted_files, $project_slug)
    {
        $report = [
            'project_slug' => $project_slug,
            'extraction_time' => current_time('mysql'),
            'extraction_path' => $extract_to,
            'total_files' => count($extracted_files),
            'total_size' => 0,
            'file_types' => [],
            'structure_analysis' => []
        ];

        // Analyze extracted files
        foreach ($extracted_files as $file) {
            $report['total_size'] += $file['size'];

            $type = $file['type'];
            if (!isset($report['file_types'][$type])) {
                $report['file_types'][$type] = ['count' => 0, 'size' => 0];
            }

            $report['file_types'][$type]['count']++;
            $report['file_types'][$type]['size'] += $file['size'];
        }

        // Structure analysis
        $report['structure_analysis'] = $this->analyze_project_structure($extracted_files);

        return $report;
    }

    /**
     * Analyze project structure
     *
     * @param array $extracted_files Extracted files
     * @return array Structure analysis
     */
    private function analyze_project_structure($extracted_files)
    {
        $analysis = [
            'entry_points' => [],
            'asset_folders' => [],
            'has_source_maps' => false,
            'build_tool' => 'unknown',
            'framework_indicators' => []
        ];

        foreach ($extracted_files as $file) {
            $path = $file['original_path'];
            $basename = basename($path);
            $extension = pathinfo($path, PATHINFO_EXTENSION);

            // Identify entry points
            if ($basename === 'index.html' || $basename === 'index.js' || $basename === 'main.js') {
                $analysis['entry_points'][] = $path;
            }

            // Identify asset folders
            if (strpos($path, '/') !== false) {
                $folder = explode('/', $path)[0];
                if (!in_array($folder, $analysis['asset_folders'])) {
                    $analysis['asset_folders'][] = $folder;
                }
            }

            // Check for source maps
            if ($extension === 'map') {
                $analysis['has_source_maps'] = true;
            }

            // Identify build tool
            if (strpos($path, 'webpack') !== false) {
                $analysis['build_tool'] = 'webpack';
            } elseif (strpos($path, 'vite') !== false) {
                $analysis['build_tool'] = 'vite';
            } elseif (strpos($path, 'parcel') !== false) {
                $analysis['build_tool'] = 'parcel';
            }

            // Framework indicators
            if (strpos($basename, 'react') !== false) {
                $analysis['framework_indicators'][] = 'react';
            }
            if (strpos($basename, 'vue') !== false) {
                $analysis['framework_indicators'][] = 'vue';
            }
            if (strpos($basename, 'angular') !== false) {
                $analysis['framework_indicators'][] = 'angular';
            }
        }

        $analysis['framework_indicators'] = array_unique($analysis['framework_indicators']);

        return $analysis;
    }

    /**
     * Create security .htaccess file
     *
     * @param string $directory Directory path
     */
    private function create_security_htaccess($directory)
    {
        $htaccess_content = "# ReactifyWP Security\n";
        $htaccess_content .= "# Deny access to PHP files\n";
        $htaccess_content .= "<Files \"*.php\">\n";
        $htaccess_content .= "    Require all denied\n";
        $htaccess_content .= "</Files>\n\n";
        $htaccess_content .= "# Deny access to sensitive files\n";
        $htaccess_content .= "<FilesMatch \"\\.(log|txt|md)$\">\n";
        $htaccess_content .= "    Require all denied\n";
        $htaccess_content .= "</FilesMatch>\n\n";
        $htaccess_content .= "# Set proper MIME types\n";
        $htaccess_content .= "AddType application/javascript .js\n";
        $htaccess_content .= "AddType text/css .css\n";
        $htaccess_content .= "AddType application/json .json\n";

        file_put_contents($directory . '/.htaccess', $htaccess_content);
    }

    /**
     * Clean up extraction directory
     *
     * @param string $extract_to Extraction path
     */
    private function cleanup_extraction($extract_to)
    {
        if (is_dir($extract_to)) {
            $this->delete_directory_recursive($extract_to);
        }
    }

    /**
     * Delete directory recursively
     *
     * @param string $dir Directory path
     */
    private function delete_directory_recursive($dir)
    {
        if (!is_dir($dir)) {
            return;
        }

        $files = array_diff(scandir($dir), ['.', '..']);

        foreach ($files as $file) {
            $path = $dir . '/' . $file;

            if (is_dir($path)) {
                $this->delete_directory_recursive($path);
            } else {
                wp_delete_file($path);
            }
        }

        rmdir($dir);
    }

    /**
     * Get ZIP error message
     *
     * @param int $error_code ZIP error code
     * @return string Error message
     */
    private function get_zip_error_message($error_code)
    {
        switch ($error_code) {
            case \ZipArchive::ER_NOZIP:
                return __('Not a valid ZIP archive.', 'reactifywp');
            case \ZipArchive::ER_INCONS:
                return __('ZIP archive is inconsistent.', 'reactifywp');
            case \ZipArchive::ER_CRC:
                return __('ZIP archive has CRC error.', 'reactifywp');
            case \ZipArchive::ER_MEMORY:
                return __('Memory allocation failure.', 'reactifywp');
            case \ZipArchive::ER_READ:
                return __('Read error.', 'reactifywp');
            case \ZipArchive::ER_SEEK:
                return __('Seek error.', 'reactifywp');
            default:
                return sprintf(__('ZIP error code: %d', 'reactifywp'), $error_code);
        }
    }
}
