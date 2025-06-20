<?php
/**
 * Security validation system for ReactifyWP
 *
 * @package ReactifyWP
 */

namespace ReactifyWP;

/**
 * Security Validator class
 */
class SecurityValidator
{
    /**
     * Dangerous file extensions
     */
    const DANGEROUS_EXTENSIONS = [
        'php', 'php3', 'php4', 'php5', 'phtml', 'phps',
        'asp', 'aspx', 'jsp', 'jspx',
        'exe', 'com', 'bat', 'cmd', 'scr', 'pif',
        'vbs', 'vbe', 'js', 'jse', 'ws', 'wsf', 'wsc', 'wsh',
        'ps1', 'ps1xml', 'ps2', 'ps2xml', 'psc1', 'psc2',
        'msh', 'msh1', 'msh2', 'mshxml', 'msh1xml', 'msh2xml',
        'scf', 'lnk', 'inf', 'reg', 'dll', 'so', 'dylib'
    ];

    /**
     * Suspicious file patterns
     */
    const SUSPICIOUS_PATTERNS = [
        '/\<\?php/i',
        '/\<script/i',
        '/eval\s*\(/i',
        '/exec\s*\(/i',
        '/system\s*\(/i',
        '/shell_exec\s*\(/i',
        '/passthru\s*\(/i',
        '/base64_decode\s*\(/i',
        '/file_get_contents\s*\(/i',
        '/file_put_contents\s*\(/i',
        '/fopen\s*\(/i',
        '/fwrite\s*\(/i',
        '/curl_exec\s*\(/i',
        '/wget\s+/i',
        '/\$_GET\[/i',
        '/\$_POST\[/i',
        '/\$_REQUEST\[/i',
        '/\$_COOKIE\[/i',
        '/\$_SERVER\[/i'
    ];

    /**
     * Maximum files in ZIP
     */
    const MAX_FILES_DEFAULT = 1000;

    /**
     * Maximum uncompressed size (500MB)
     */
    const MAX_UNCOMPRESSED_SIZE = 524288000;

    /**
     * Maximum path depth
     */
    const MAX_PATH_DEPTH = 10;

    /**
     * Constructor
     */
    public function __construct()
    {
        // Initialize security settings
        $this->init_security_settings();
    }

    /**
     * Validate uploaded file
     *
     * @param string $file_path Path to uploaded file
     * @return true|\WP_Error Validation result
     */
    public function validate_file($file_path)
    {
        if (!file_exists($file_path)) {
            return new \WP_Error('file_not_found', __('File not found for validation.', 'reactifywp'));
        }

        // Basic file validation
        $basic_validation = $this->validate_basic_file_properties($file_path);
        if (is_wp_error($basic_validation)) {
            return $basic_validation;
        }

        // ZIP-specific validation
        $zip_validation = $this->validate_zip_contents($file_path);
        if (is_wp_error($zip_validation)) {
            return $zip_validation;
        }

        // Content scanning
        $content_validation = $this->scan_zip_contents($file_path);
        if (is_wp_error($content_validation)) {
            return $content_validation;
        }

        // Path traversal protection
        $path_validation = $this->validate_zip_paths($file_path);
        if (is_wp_error($path_validation)) {
            return $path_validation;
        }

        return true;
    }

    /**
     * Validate basic file properties
     *
     * @param string $file_path File path
     * @return true|\WP_Error Validation result
     */
    private function validate_basic_file_properties($file_path)
    {
        // Check file size
        $file_size = filesize($file_path);
        $max_size = $this->get_max_file_size();
        
        if ($file_size > $max_size) {
            return new \WP_Error(
                'file_too_large',
                sprintf(
                    __('File size (%s) exceeds maximum allowed size (%s).', 'reactifywp'),
                    size_format($file_size),
                    size_format($max_size)
                )
            );
        }

        // Check if file is readable
        if (!is_readable($file_path)) {
            return new \WP_Error('file_not_readable', __('File is not readable.', 'reactifywp'));
        }

        // Check file permissions
        $perms = fileperms($file_path);
        if (($perms & 0111) !== 0) {
            return new \WP_Error('executable_file', __('Executable files are not allowed.', 'reactifywp'));
        }

        return true;
    }

    /**
     * Validate ZIP contents
     *
     * @param string $file_path ZIP file path
     * @return true|\WP_Error Validation result
     */
    private function validate_zip_contents($file_path)
    {
        $zip = new \ZipArchive();
        $result = $zip->open($file_path, \ZipArchive::CHECKCONS);

        if ($result !== true) {
            return new \WP_Error('invalid_zip', $this->get_zip_error_message($result));
        }

        // Check number of files
        $num_files = $zip->numFiles;
        $max_files = $this->get_max_files_in_zip();
        
        if ($num_files > $max_files) {
            $zip->close();
            return new \WP_Error(
                'too_many_files',
                sprintf(
                    __('ZIP contains too many files (%d). Maximum allowed: %d', 'reactifywp'),
                    $num_files,
                    $max_files
                )
            );
        }

        // Check uncompressed size
        $total_uncompressed = 0;
        $max_uncompressed = $this->get_max_uncompressed_size();

        for ($i = 0; $i < $num_files; $i++) {
            $stat = $zip->statIndex($i);
            if ($stat === false) {
                $zip->close();
                return new \WP_Error('zip_stat_error', __('Failed to read ZIP file statistics.', 'reactifywp'));
            }

            $total_uncompressed += $stat['size'];
            
            if ($total_uncompressed > $max_uncompressed) {
                $zip->close();
                return new \WP_Error(
                    'uncompressed_too_large',
                    sprintf(
                        __('Uncompressed size (%s) exceeds maximum allowed (%s).', 'reactifywp'),
                        size_format($total_uncompressed),
                        size_format($max_uncompressed)
                    )
                );
            }
        }

        $zip->close();
        return true;
    }

    /**
     * Scan ZIP contents for malicious files
     *
     * @param string $file_path ZIP file path
     * @return true|\WP_Error Scan result
     */
    private function scan_zip_contents($file_path)
    {
        $zip = new \ZipArchive();
        $result = $zip->open($file_path);

        if ($result !== true) {
            return new \WP_Error('zip_open_error', __('Failed to open ZIP for scanning.', 'reactifywp'));
        }

        $dangerous_files = [];
        $suspicious_files = [];

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $filename = $zip->getNameIndex($i);
            
            if ($filename === false) {
                continue;
            }

            // Check for dangerous extensions
            $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
            if (in_array($extension, self::DANGEROUS_EXTENSIONS, true)) {
                $dangerous_files[] = $filename;
                continue;
            }

            // Check file content for suspicious patterns
            $content = $zip->getFromIndex($i);
            if ($content !== false && $this->contains_suspicious_content($content)) {
                $suspicious_files[] = $filename;
            }
        }

        $zip->close();

        // Report dangerous files
        if (!empty($dangerous_files)) {
            return new \WP_Error(
                'dangerous_files',
                sprintf(
                    __('ZIP contains dangerous files: %s', 'reactifywp'),
                    implode(', ', array_slice($dangerous_files, 0, 5))
                )
            );
        }

        // Report suspicious files (if blocking is enabled)
        $settings = get_option('reactifywp_settings', []);
        if (($settings['security']['block_suspicious_files'] ?? true) && !empty($suspicious_files)) {
            return new \WP_Error(
                'suspicious_files',
                sprintf(
                    __('ZIP contains suspicious files: %s', 'reactifywp'),
                    implode(', ', array_slice($suspicious_files, 0, 5))
                )
            );
        }

        return true;
    }

    /**
     * Validate ZIP file paths for directory traversal
     *
     * @param string $file_path ZIP file path
     * @return true|\WP_Error Validation result
     */
    private function validate_zip_paths($file_path)
    {
        $zip = new \ZipArchive();
        $result = $zip->open($file_path);

        if ($result !== true) {
            return new \WP_Error('zip_open_error', __('Failed to open ZIP for path validation.', 'reactifywp'));
        }

        $dangerous_paths = [];

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $filename = $zip->getNameIndex($i);
            
            if ($filename === false) {
                continue;
            }

            // Check for directory traversal
            if ($this->is_dangerous_path($filename)) {
                $dangerous_paths[] = $filename;
            }

            // Check path depth
            $depth = substr_count($filename, '/');
            if ($depth > self::MAX_PATH_DEPTH) {
                $dangerous_paths[] = $filename . ' (too deep)';
            }
        }

        $zip->close();

        if (!empty($dangerous_paths)) {
            return new \WP_Error(
                'dangerous_paths',
                sprintf(
                    __('ZIP contains dangerous file paths: %s', 'reactifywp'),
                    implode(', ', array_slice($dangerous_paths, 0, 5))
                )
            );
        }

        return true;
    }

    /**
     * Check if content contains suspicious patterns
     *
     * @param string $content File content
     * @return bool True if suspicious
     */
    private function contains_suspicious_content($content)
    {
        // Skip binary files
        if (!mb_check_encoding($content, 'UTF-8') && !mb_check_encoding($content, 'ASCII')) {
            return false;
        }

        // Check for suspicious patterns
        foreach (self::SUSPICIOUS_PATTERNS as $pattern) {
            if (preg_match($pattern, $content)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if path is dangerous (directory traversal)
     *
     * @param string $path File path
     * @return bool True if dangerous
     */
    private function is_dangerous_path($path)
    {
        // Normalize path separators
        $normalized = str_replace('\\', '/', $path);
        
        // Check for directory traversal patterns
        $dangerous_patterns = [
            '../',
            '..\\',
            '/..',
            '\\..',
            '//',
            '\\\\',
            '~/',
            '~\\',
        ];

        foreach ($dangerous_patterns as $pattern) {
            if (strpos($normalized, $pattern) !== false) {
                return true;
            }
        }

        // Check for absolute paths
        if (strpos($normalized, '/') === 0 || preg_match('/^[a-zA-Z]:/', $normalized)) {
            return true;
        }

        // Check for null bytes
        if (strpos($path, "\0") !== false) {
            return true;
        }

        return false;
    }

    /**
     * Initialize security settings
     */
    private function init_security_settings()
    {
        // Set up security defaults if not configured
        $settings = get_option('reactifywp_settings', []);

        if (!isset($settings['security'])) {
            $settings['security'] = [
                'max_files_per_zip' => self::MAX_FILES_DEFAULT,
                'max_uncompressed_size' => '500MB',
                'block_suspicious_files' => true,
                'enable_path_validation' => true,
                'enable_virus_scanning' => false
            ];

            update_option('reactifywp_settings', $settings);
        }
    }

    /**
     * Get maximum file size
     *
     * @return int Maximum file size in bytes
     */
    private function get_max_file_size()
    {
        $settings = get_option('reactifywp_settings', []);
        $max_size_setting = $settings['general']['max_upload_size'] ?? '50MB';

        return $this->parse_size_setting($max_size_setting);
    }

    /**
     * Get maximum files in ZIP
     *
     * @return int Maximum number of files
     */
    private function get_max_files_in_zip()
    {
        $settings = get_option('reactifywp_settings', []);
        return $settings['security']['max_files_per_zip'] ?? self::MAX_FILES_DEFAULT;
    }

    /**
     * Get maximum uncompressed size
     *
     * @return int Maximum uncompressed size in bytes
     */
    private function get_max_uncompressed_size()
    {
        $settings = get_option('reactifywp_settings', []);
        $max_size_setting = $settings['security']['max_uncompressed_size'] ?? '500MB';

        return $this->parse_size_setting($max_size_setting);
    }

    /**
     * Parse size setting string to bytes
     *
     * @param string $size_setting Size setting (e.g., "50MB")
     * @return int Size in bytes
     */
    private function parse_size_setting($size_setting)
    {
        if (preg_match('/^(\d+)\s*(B|KB|MB|GB)$/i', $size_setting, $matches)) {
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

        return self::MAX_UNCOMPRESSED_SIZE;
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

    /**
     * Perform virus scan (if enabled and available)
     *
     * @param string $file_path File path
     * @return true|\WP_Error Scan result
     */
    public function virus_scan($file_path)
    {
        $settings = get_option('reactifywp_settings', []);

        if (!($settings['security']['enable_virus_scanning'] ?? false)) {
            return true;
        }

        // Check if ClamAV is available
        if ($this->is_clamav_available()) {
            return $this->clamav_scan($file_path);
        }

        // Check if other scanners are available
        // This could be extended to support other antivirus solutions

        return true;
    }

    /**
     * Check if ClamAV is available
     *
     * @return bool True if ClamAV is available
     */
    private function is_clamav_available()
    {
        // Check if clamscan command exists
        $output = [];
        $return_var = 0;
        exec('which clamscan 2>/dev/null', $output, $return_var);

        return $return_var === 0 && !empty($output);
    }

    /**
     * Perform ClamAV scan
     *
     * @param string $file_path File path
     * @return true|\WP_Error Scan result
     */
    private function clamav_scan($file_path)
    {
        $escaped_path = escapeshellarg($file_path);
        $command = "clamscan --no-summary --infected {$escaped_path} 2>&1";

        $output = [];
        $return_var = 0;
        exec($command, $output, $return_var);

        // ClamAV returns 1 if virus found, 0 if clean
        if ($return_var === 1) {
            return new \WP_Error(
                'virus_detected',
                sprintf(
                    __('Virus detected: %s', 'reactifywp'),
                    implode(' ', $output)
                )
            );
        } elseif ($return_var !== 0) {
            return new \WP_Error(
                'scan_error',
                sprintf(
                    __('Virus scan failed with error code %d', 'reactifywp'),
                    $return_var
                )
            );
        }

        return true;
    }

    /**
     * Generate security report for file
     *
     * @param string $file_path File path
     * @return array Security report
     */
    public function generate_security_report($file_path)
    {
        $report = [
            'file_path' => $file_path,
            'file_size' => filesize($file_path),
            'scan_time' => current_time('mysql'),
            'checks' => [],
            'warnings' => [],
            'errors' => [],
            'status' => 'unknown'
        ];

        // Basic file validation
        $basic_check = $this->validate_basic_file_properties($file_path);
        $report['checks']['basic_validation'] = !is_wp_error($basic_check);
        if (is_wp_error($basic_check)) {
            $report['errors'][] = $basic_check->get_error_message();
        }

        // ZIP validation
        $zip_check = $this->validate_zip_contents($file_path);
        $report['checks']['zip_validation'] = !is_wp_error($zip_check);
        if (is_wp_error($zip_check)) {
            $report['errors'][] = $zip_check->get_error_message();
        }

        // Content scanning
        $content_check = $this->scan_zip_contents($file_path);
        $report['checks']['content_scan'] = !is_wp_error($content_check);
        if (is_wp_error($content_check)) {
            $report['warnings'][] = $content_check->get_error_message();
        }

        // Path validation
        $path_check = $this->validate_zip_paths($file_path);
        $report['checks']['path_validation'] = !is_wp_error($path_check);
        if (is_wp_error($path_check)) {
            $report['errors'][] = $path_check->get_error_message();
        }

        // Virus scan
        $virus_check = $this->virus_scan($file_path);
        $report['checks']['virus_scan'] = !is_wp_error($virus_check);
        if (is_wp_error($virus_check)) {
            $report['errors'][] = $virus_check->get_error_message();
        }

        // Determine overall status
        if (!empty($report['errors'])) {
            $report['status'] = 'failed';
        } elseif (!empty($report['warnings'])) {
            $report['status'] = 'warning';
        } else {
            $report['status'] = 'passed';
        }

        return $report;
    }

    /**
     * Log security event
     *
     * @param string $event_type Event type
     * @param string $message    Event message
     * @param array  $context    Additional context
     */
    public function log_security_event($event_type, $message, $context = [])
    {
        $settings = get_option('reactifywp_settings', []);

        if (!($settings['general']['enable_error_logging'] ?? true)) {
            return;
        }

        $log_entry = [
            'timestamp' => current_time('mysql'),
            'event_type' => $event_type,
            'message' => $message,
            'context' => $context,
            'user_id' => get_current_user_id(),
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
        ];

        // Log to WordPress error log
        error_log('ReactifyWP Security: ' . wp_json_encode($log_entry));

        // Store in database for admin review
        $this->store_security_log($log_entry);
    }

    /**
     * Store security log in database
     *
     * @param array $log_entry Log entry data
     */
    private function store_security_log($log_entry)
    {
        global $wpdb;

        $table_name = $wpdb->prefix . 'reactifywp_security_logs';

        // Create table if it doesn't exist
        $this->create_security_log_table();

        $wpdb->insert(
            $table_name,
            [
                'timestamp' => $log_entry['timestamp'],
                'event_type' => $log_entry['event_type'],
                'message' => $log_entry['message'],
                'context' => wp_json_encode($log_entry['context']),
                'user_id' => $log_entry['user_id'],
                'ip_address' => $log_entry['ip_address']
            ],
            ['%s', '%s', '%s', '%s', '%d', '%s']
        );
    }

    /**
     * Create security log table
     */
    private function create_security_log_table()
    {
        global $wpdb;

        $table_name = $wpdb->prefix . 'reactifywp_security_logs';

        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            timestamp datetime NOT NULL,
            event_type varchar(50) NOT NULL,
            message text NOT NULL,
            context longtext,
            user_id bigint(20) unsigned,
            ip_address varchar(45),
            PRIMARY KEY (id),
            KEY timestamp (timestamp),
            KEY event_type (event_type),
            KEY user_id (user_id)
        ) $charset_collate;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    }
}
