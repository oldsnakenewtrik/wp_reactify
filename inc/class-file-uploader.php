<?php
/**
 * Secure file upload handler for ReactifyWP
 *
 * @package ReactifyWP
 */

namespace ReactifyWP;

/**
 * File Uploader class
 */
class FileUploader
{
    /**
     * Maximum file size (default 50MB)
     */
    const DEFAULT_MAX_SIZE = 52428800;

    /**
     * Allowed MIME types
     */
    const ALLOWED_MIME_TYPES = [
        'application/zip',
        'application/x-zip-compressed',
        'multipart/x-zip'
    ];

    /**
     * Allowed file extensions
     */
    const ALLOWED_EXTENSIONS = ['zip'];

    /**
     * Upload chunk size for large files (1MB)
     */
    const CHUNK_SIZE = 1048576;

    /**
     * Constructor
     */
    public function __construct()
    {
        add_action('wp_ajax_reactifywp_upload_file', [$this, 'handle_upload']);
        add_action('wp_ajax_reactifywp_upload_chunk', [$this, 'handle_chunk_upload']);
        add_action('wp_ajax_reactifywp_finalize_upload', [$this, 'handle_finalize_upload']);
        add_action('wp_ajax_reactifywp_cancel_upload', [$this, 'handle_cancel_upload']);
        add_filter('upload_mimes', [$this, 'add_zip_mime_type']);
    }

    /**
     * Handle file upload
     */
    public function handle_upload()
    {
        try {
            // Verify nonce and permissions
            $this->verify_upload_request();

            // Check if this is a chunked upload
            if (isset($_POST['chunk']) && isset($_POST['chunks'])) {
                $this->handle_chunk_upload();
                return;
            }

            // Handle single file upload
            $result = $this->process_single_upload();
            
            if (is_wp_error($result)) {
                wp_send_json_error($result->get_error_message());
            }

            wp_send_json_success($result);

        } catch (Exception $e) {
            error_log('ReactifyWP Upload Error: ' . $e->getMessage());
            wp_send_json_error(__('Upload failed. Please try again.', 'reactifywp'));
        }
    }

    /**
     * Handle chunked file upload
     */
    public function handle_chunk_upload()
    {
        try {
            $this->verify_upload_request();

            $chunk = intval($_POST['chunk'] ?? 0);
            $chunks = intval($_POST['chunks'] ?? 1);
            $upload_id = sanitize_text_field($_POST['upload_id'] ?? '');
            $filename = sanitize_file_name($_POST['filename'] ?? '');

            if (empty($upload_id) || empty($filename)) {
                wp_send_json_error(__('Invalid upload parameters.', 'reactifywp'));
            }

            // Validate chunk parameters
            if ($chunk < 0 || $chunk >= $chunks) {
                wp_send_json_error(__('Invalid chunk parameters.', 'reactifywp'));
            }

            // Process chunk
            $result = $this->process_chunk($upload_id, $filename, $chunk, $chunks);
            
            if (is_wp_error($result)) {
                wp_send_json_error($result->get_error_message());
            }

            wp_send_json_success($result);

        } catch (Exception $e) {
            error_log('ReactifyWP Chunk Upload Error: ' . $e->getMessage());
            wp_send_json_error(__('Chunk upload failed.', 'reactifywp'));
        }
    }

    /**
     * Handle upload finalization
     */
    public function handle_finalize_upload()
    {
        try {
            $this->verify_upload_request();

            $upload_id = sanitize_text_field($_POST['upload_id'] ?? '');
            $filename = sanitize_file_name($_POST['filename'] ?? '');
            $total_chunks = intval($_POST['total_chunks'] ?? 0);

            if (empty($upload_id) || empty($filename)) {
                wp_send_json_error(__('Invalid upload parameters.', 'reactifywp'));
            }

            // Finalize chunked upload
            $result = $this->finalize_chunked_upload($upload_id, $filename, $total_chunks);
            
            if (is_wp_error($result)) {
                wp_send_json_error($result->get_error_message());
            }

            wp_send_json_success($result);

        } catch (Exception $e) {
            error_log('ReactifyWP Finalize Upload Error: ' . $e->getMessage());
            wp_send_json_error(__('Upload finalization failed.', 'reactifywp'));
        }
    }

    /**
     * Handle upload cancellation
     */
    public function handle_cancel_upload()
    {
        try {
            $this->verify_upload_request();

            $upload_id = sanitize_text_field($_POST['upload_id'] ?? '');

            if (empty($upload_id)) {
                wp_send_json_error(__('Invalid upload ID.', 'reactifywp'));
            }

            // Clean up partial upload
            $this->cleanup_partial_upload($upload_id);

            wp_send_json_success(['message' => __('Upload cancelled successfully.', 'reactifywp')]);

        } catch (Exception $e) {
            error_log('ReactifyWP Cancel Upload Error: ' . $e->getMessage());
            wp_send_json_error(__('Upload cancellation failed.', 'reactifywp'));
        }
    }

    /**
     * Verify upload request
     */
    private function verify_upload_request()
    {
        // Check nonce
        if (!check_ajax_referer('reactifywp_admin', 'nonce', false)) {
            throw new \Exception('Invalid nonce');
        }

        // Check user permissions
        if (!current_user_can('manage_options')) {
            throw new \Exception('Insufficient permissions');
        }

        // Check if uploads are enabled
        if (!$this->are_uploads_enabled()) {
            throw new \Exception('File uploads are disabled');
        }
    }

    /**
     * Process single file upload
     *
     * @return array|\WP_Error Upload result
     */
    private function process_single_upload()
    {
        if (empty($_FILES['file'])) {
            return new \WP_Error('no_file', __('No file uploaded.', 'reactifywp'));
        }

        $file = $_FILES['file'];

        // Validate file
        $validation_result = $this->validate_uploaded_file($file);
        if (is_wp_error($validation_result)) {
            return $validation_result;
        }

        // Generate upload ID
        $upload_id = $this->generate_upload_id();

        // Move file to temporary location
        $temp_file = $this->move_to_temp_location($file, $upload_id);
        if (is_wp_error($temp_file)) {
            return $temp_file;
        }

        // Perform security validation
        $security_validator = new SecurityValidator();
        $security_result = $security_validator->validate_file($temp_file);
        
        if (is_wp_error($security_result)) {
            $this->cleanup_temp_file($temp_file);
            return $security_result;
        }

        return [
            'upload_id' => $upload_id,
            'filename' => $file['name'],
            'size' => $file['size'],
            'temp_file' => $temp_file,
            'status' => 'uploaded',
            'message' => __('File uploaded successfully.', 'reactifywp')
        ];
    }

    /**
     * Process upload chunk
     *
     * @param string $upload_id Upload ID
     * @param string $filename  Original filename
     * @param int    $chunk     Current chunk number
     * @param int    $chunks    Total chunks
     * @return array|\WP_Error Chunk result
     */
    private function process_chunk($upload_id, $filename, $chunk, $chunks)
    {
        if (empty($_FILES['file'])) {
            return new \WP_Error('no_chunk', __('No chunk data received.', 'reactifywp'));
        }

        $chunk_file = $_FILES['file'];

        // Validate chunk
        if ($chunk_file['error'] !== UPLOAD_ERR_OK) {
            return new \WP_Error('chunk_error', $this->get_upload_error_message($chunk_file['error']));
        }

        // Get chunk directory
        $chunk_dir = $this->get_chunk_directory($upload_id);
        if (!wp_mkdir_p($chunk_dir)) {
            return new \WP_Error('chunk_dir_error', __('Failed to create chunk directory.', 'reactifywp'));
        }

        // Save chunk
        $chunk_path = $chunk_dir . '/' . sprintf('%04d.chunk', $chunk);
        
        if (!move_uploaded_file($chunk_file['tmp_name'], $chunk_path)) {
            return new \WP_Error('chunk_save_error', __('Failed to save chunk.', 'reactifywp'));
        }

        // Check if all chunks are uploaded
        $uploaded_chunks = glob($chunk_dir . '/*.chunk');
        $progress = (count($uploaded_chunks) / $chunks) * 100;

        return [
            'upload_id' => $upload_id,
            'chunk' => $chunk,
            'chunks' => $chunks,
            'uploaded_chunks' => count($uploaded_chunks),
            'progress' => round($progress, 2),
            'status' => count($uploaded_chunks) === $chunks ? 'complete' : 'uploading',
            'message' => sprintf(__('Chunk %d of %d uploaded.', 'reactifywp'), $chunk + 1, $chunks)
        ];
    }

    /**
     * Finalize chunked upload
     *
     * @param string $upload_id    Upload ID
     * @param string $filename     Original filename
     * @param int    $total_chunks Total chunks
     * @return array|\WP_Error Finalization result
     */
    private function finalize_chunked_upload($upload_id, $filename, $total_chunks)
    {
        $chunk_dir = $this->get_chunk_directory($upload_id);
        
        if (!is_dir($chunk_dir)) {
            return new \WP_Error('chunk_dir_missing', __('Chunk directory not found.', 'reactifywp'));
        }

        // Verify all chunks are present
        $chunks = glob($chunk_dir . '/*.chunk');
        if (count($chunks) !== $total_chunks) {
            return new \WP_Error('incomplete_chunks', __('Some chunks are missing.', 'reactifywp'));
        }

        // Sort chunks by filename
        sort($chunks);

        // Combine chunks into final file
        $temp_file = $this->get_temp_file_path($upload_id, $filename);
        $final_file = fopen($temp_file, 'wb');
        
        if (!$final_file) {
            return new \WP_Error('file_create_error', __('Failed to create final file.', 'reactifywp'));
        }

        foreach ($chunks as $chunk_path) {
            $chunk_data = file_get_contents($chunk_path);
            if ($chunk_data === false) {
                fclose($final_file);
                return new \WP_Error('chunk_read_error', __('Failed to read chunk.', 'reactifywp'));
            }
            
            fwrite($final_file, $chunk_data);
        }

        fclose($final_file);

        // Clean up chunks
        $this->cleanup_chunks($chunk_dir);

        // Validate final file
        $file_info = [
            'name' => $filename,
            'tmp_name' => $temp_file,
            'size' => filesize($temp_file),
            'error' => UPLOAD_ERR_OK
        ];

        $validation_result = $this->validate_uploaded_file($file_info);
        if (is_wp_error($validation_result)) {
            $this->cleanup_temp_file($temp_file);
            return $validation_result;
        }

        // Perform security validation
        $security_validator = new SecurityValidator();
        $security_result = $security_validator->validate_file($temp_file);
        
        if (is_wp_error($security_result)) {
            $this->cleanup_temp_file($temp_file);
            return $security_result;
        }

        return [
            'upload_id' => $upload_id,
            'filename' => $filename,
            'size' => filesize($temp_file),
            'temp_file' => $temp_file,
            'status' => 'finalized',
            'message' => __('File upload completed successfully.', 'reactifywp')
        ];
    }

    /**
     * Validate uploaded file
     *
     * @param array $file File information
     * @return true|\WP_Error Validation result
     */
    private function validate_uploaded_file($file)
    {
        // Check for upload errors
        if ($file['error'] !== UPLOAD_ERR_OK) {
            return new \WP_Error('upload_error', $this->get_upload_error_message($file['error']));
        }

        // Check file size
        $max_size = $this->get_max_upload_size();
        if ($file['size'] > $max_size) {
            return new \WP_Error(
                'file_too_large',
                sprintf(
                    __('File size (%s) exceeds maximum allowed size (%s).', 'reactifywp'),
                    size_format($file['size']),
                    size_format($max_size)
                )
            );
        }

        // Check file extension
        $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($file_ext, self::ALLOWED_EXTENSIONS, true)) {
            return new \WP_Error(
                'invalid_extension',
                sprintf(
                    __('File type not allowed. Allowed types: %s', 'reactifywp'),
                    implode(', ', self::ALLOWED_EXTENSIONS)
                )
            );
        }

        // Check MIME type
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime_type = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        if (!in_array($mime_type, self::ALLOWED_MIME_TYPES, true)) {
            return new \WP_Error(
                'invalid_mime_type',
                sprintf(
                    __('Invalid file type detected: %s', 'reactifywp'),
                    $mime_type
                )
            );
        }

        // Check if file is actually a ZIP
        if (!$this->is_valid_zip($file['tmp_name'])) {
            return new \WP_Error('invalid_zip', __('File is not a valid ZIP archive.', 'reactifywp'));
        }

        return true;
    }

    /**
     * Check if file is a valid ZIP archive
     *
     * @param string $file_path File path
     * @return bool True if valid ZIP
     */
    private function is_valid_zip($file_path)
    {
        $zip = new \ZipArchive();
        $result = $zip->open($file_path, \ZipArchive::CHECKCONS);

        if ($result === true) {
            $zip->close();
            return true;
        }

        return false;
    }

    /**
     * Get maximum upload size
     *
     * @return int Maximum size in bytes
     */
    private function get_max_upload_size()
    {
        $settings = get_option('reactifywp_settings', []);
        $max_size_setting = $settings['general']['max_upload_size'] ?? '50MB';

        // Parse size setting
        if (preg_match('/^(\d+)\s*(B|KB|MB|GB)$/i', $max_size_setting, $matches)) {
            $value = intval($matches[1]);
            $unit = strtoupper($matches[2]);

            switch ($unit) {
                case 'GB':
                    return $value * 1024 * 1024 * 1024;
                case 'MB':
                    return $value * 1024 * 1024;
                case 'KB':
                    return $value * 1024;
                default:
                    return $value;
            }
        }

        return self::DEFAULT_MAX_SIZE;
    }

    /**
     * Generate unique upload ID
     *
     * @return string Upload ID
     */
    private function generate_upload_id()
    {
        return wp_generate_uuid4();
    }

    /**
     * Move file to temporary location
     *
     * @param array  $file      File information
     * @param string $upload_id Upload ID
     * @return string|\WP_Error Temporary file path or error
     */
    private function move_to_temp_location($file, $upload_id)
    {
        $temp_dir = $this->get_temp_directory();

        if (!wp_mkdir_p($temp_dir)) {
            return new \WP_Error('temp_dir_error', __('Failed to create temporary directory.', 'reactifywp'));
        }

        $temp_file = $this->get_temp_file_path($upload_id, $file['name']);

        if (!move_uploaded_file($file['tmp_name'], $temp_file)) {
            return new \WP_Error('move_error', __('Failed to move uploaded file.', 'reactifywp'));
        }

        return $temp_file;
    }

    /**
     * Get temporary directory
     *
     * @return string Temporary directory path
     */
    private function get_temp_directory()
    {
        $upload_dir = wp_upload_dir();
        return $upload_dir['basedir'] . '/reactify-temp';
    }

    /**
     * Get chunk directory
     *
     * @param string $upload_id Upload ID
     * @return string Chunk directory path
     */
    private function get_chunk_directory($upload_id)
    {
        return $this->get_temp_directory() . '/chunks/' . $upload_id;
    }

    /**
     * Get temporary file path
     *
     * @param string $upload_id Upload ID
     * @param string $filename  Original filename
     * @return string Temporary file path
     */
    private function get_temp_file_path($upload_id, $filename)
    {
        $safe_filename = sanitize_file_name($filename);
        return $this->get_temp_directory() . '/' . $upload_id . '_' . $safe_filename;
    }

    /**
     * Clean up temporary file
     *
     * @param string $file_path File path
     */
    private function cleanup_temp_file($file_path)
    {
        if (file_exists($file_path)) {
            wp_delete_file($file_path);
        }
    }

    /**
     * Clean up chunks
     *
     * @param string $chunk_dir Chunk directory
     */
    private function cleanup_chunks($chunk_dir)
    {
        if (is_dir($chunk_dir)) {
            $files = glob($chunk_dir . '/*');
            foreach ($files as $file) {
                if (is_file($file)) {
                    wp_delete_file($file);
                }
            }
            rmdir($chunk_dir);
        }
    }

    /**
     * Clean up partial upload
     *
     * @param string $upload_id Upload ID
     */
    private function cleanup_partial_upload($upload_id)
    {
        // Clean up chunks
        $chunk_dir = $this->get_chunk_directory($upload_id);
        $this->cleanup_chunks($chunk_dir);

        // Clean up temporary files
        $temp_dir = $this->get_temp_directory();
        $temp_files = glob($temp_dir . '/' . $upload_id . '_*');

        foreach ($temp_files as $temp_file) {
            $this->cleanup_temp_file($temp_file);
        }
    }

    /**
     * Get upload error message
     *
     * @param int $error_code PHP upload error code
     * @return string Error message
     */
    private function get_upload_error_message($error_code)
    {
        switch ($error_code) {
            case UPLOAD_ERR_INI_SIZE:
                return __('File exceeds the maximum allowed size.', 'reactifywp');
            case UPLOAD_ERR_FORM_SIZE:
                return __('File exceeds the form maximum size.', 'reactifywp');
            case UPLOAD_ERR_PARTIAL:
                return __('File was only partially uploaded.', 'reactifywp');
            case UPLOAD_ERR_NO_FILE:
                return __('No file was uploaded.', 'reactifywp');
            case UPLOAD_ERR_NO_TMP_DIR:
                return __('Missing temporary folder.', 'reactifywp');
            case UPLOAD_ERR_CANT_WRITE:
                return __('Failed to write file to disk.', 'reactifywp');
            case UPLOAD_ERR_EXTENSION:
                return __('File upload stopped by extension.', 'reactifywp');
            default:
                return __('Unknown upload error.', 'reactifywp');
        }
    }

    /**
     * Check if uploads are enabled
     *
     * @return bool True if uploads are enabled
     */
    private function are_uploads_enabled()
    {
        // Check WordPress setting
        if (!get_option('uploads_use_yearmonth_folders', true)) {
            return false;
        }

        // Check plugin setting
        $settings = get_option('reactifywp_settings', []);
        return $settings['general']['enable_uploads'] ?? true;
    }

    /**
     * Add ZIP MIME type to allowed uploads
     *
     * @param array $mimes Existing MIME types
     * @return array Modified MIME types
     */
    public function add_zip_mime_type($mimes)
    {
        $mimes['zip'] = 'application/zip';
        return $mimes;
    }

    /**
     * Get upload statistics
     *
     * @return array Upload statistics
     */
    public function get_upload_stats()
    {
        $temp_dir = $this->get_temp_directory();
        $stats = [
            'temp_files' => 0,
            'temp_size' => 0,
            'chunk_dirs' => 0,
            'total_chunks' => 0
        ];

        if (is_dir($temp_dir)) {
            // Count temporary files
            $temp_files = glob($temp_dir . '/*.zip');
            $stats['temp_files'] = count($temp_files);

            foreach ($temp_files as $file) {
                $stats['temp_size'] += filesize($file);
            }

            // Count chunk directories
            $chunk_base = $temp_dir . '/chunks';
            if (is_dir($chunk_base)) {
                $chunk_dirs = glob($chunk_base . '/*', GLOB_ONLYDIR);
                $stats['chunk_dirs'] = count($chunk_dirs);

                foreach ($chunk_dirs as $dir) {
                    $chunks = glob($dir . '/*.chunk');
                    $stats['total_chunks'] += count($chunks);
                }
            }
        }

        return $stats;
    }

    /**
     * Clean up old temporary files
     *
     * @param int $max_age Maximum age in seconds (default 24 hours)
     */
    public function cleanup_old_temp_files($max_age = 86400)
    {
        $temp_dir = $this->get_temp_directory();

        if (!is_dir($temp_dir)) {
            return;
        }

        $current_time = time();
        $files = glob($temp_dir . '/*');

        foreach ($files as $file) {
            if (is_file($file) && ($current_time - filemtime($file)) > $max_age) {
                wp_delete_file($file);
            } elseif (is_dir($file) && ($current_time - filemtime($file)) > $max_age) {
                $this->cleanup_chunks($file);
            }
        }
    }
}
