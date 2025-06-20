<?php
/**
 * Error handling and recovery system for ReactifyWP
 *
 * @package ReactifyWP
 */

namespace ReactifyWP;

/**
 * Error Handler class
 */
class ErrorHandler
{
    /**
     * Error log file path
     */
    private $log_file;

    /**
     * Maximum log file size (10MB)
     */
    const MAX_LOG_SIZE = 10485760;

    /**
     * Error types
     */
    const ERROR_TYPES = [
        'upload' => 'Upload Error',
        'validation' => 'Validation Error',
        'extraction' => 'Extraction Error',
        'security' => 'Security Error',
        'filesystem' => 'Filesystem Error',
        'database' => 'Database Error',
        'network' => 'Network Error',
        'permission' => 'Permission Error',
        'configuration' => 'Configuration Error',
        'general' => 'General Error'
    ];

    /**
     * Recovery strategies
     */
    private $recovery_strategies = [];

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->init_log_file();
        $this->register_recovery_strategies();
        $this->setup_error_handlers();
        
        add_action('wp_ajax_reactifywp_get_error_logs', [$this, 'handle_get_error_logs']);
        add_action('wp_ajax_reactifywp_clear_error_logs', [$this, 'handle_clear_error_logs']);
        add_action('wp_ajax_reactifywp_retry_failed_operation', [$this, 'handle_retry_operation']);
        add_action('wp_ajax_reactifywp_recover_upload', [$this, 'handle_recover_upload']);
    }

    /**
     * Initialize log file
     */
    private function init_log_file()
    {
        $upload_dir = wp_upload_dir();
        $log_dir = $upload_dir['basedir'] . '/reactify-logs';
        
        if (!wp_mkdir_p($log_dir)) {
            error_log('ReactifyWP: Failed to create log directory');
            return;
        }

        $this->log_file = $log_dir . '/error.log';
        
        // Create .htaccess to protect log files
        $htaccess_file = $log_dir . '/.htaccess';
        if (!file_exists($htaccess_file)) {
            file_put_contents($htaccess_file, "Require all denied\n");
        }
    }

    /**
     * Register recovery strategies
     */
    private function register_recovery_strategies()
    {
        $this->recovery_strategies = [
            'upload_failed' => [$this, 'recover_failed_upload'],
            'extraction_failed' => [$this, 'recover_failed_extraction'],
            'validation_failed' => [$this, 'recover_failed_validation'],
            'filesystem_error' => [$this, 'recover_filesystem_error'],
            'database_error' => [$this, 'recover_database_error'],
            'permission_error' => [$this, 'recover_permission_error']
        ];
    }

    /**
     * Set up error handlers
     */
    private function setup_error_handlers()
    {
        // Register shutdown function to catch fatal errors
        register_shutdown_function([$this, 'handle_fatal_error']);
        
        // Set custom error handler for non-fatal errors
        set_error_handler([$this, 'handle_php_error'], E_ALL & ~E_NOTICE);
        
        // Set exception handler
        set_exception_handler([$this, 'handle_exception']);
    }

    /**
     * Log error with context
     *
     * @param string $type        Error type
     * @param string $message     Error message
     * @param array  $context     Additional context
     * @param string $severity    Error severity (low, medium, high, critical)
     * @return string Error ID
     */
    public function log_error($type, $message, $context = [], $severity = 'medium')
    {
        $error_id = $this->generate_error_id();
        
        $error_data = [
            'id' => $error_id,
            'timestamp' => current_time('mysql'),
            'type' => $type,
            'severity' => $severity,
            'message' => $message,
            'context' => $context,
            'user_id' => get_current_user_id(),
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
            'request_uri' => $_SERVER['REQUEST_URI'] ?? 'unknown',
            'stack_trace' => $this->get_stack_trace()
        ];

        // Write to log file
        $this->write_to_log($error_data);

        // Store in database for admin interface
        $this->store_error_in_database($error_data);

        // Send notifications for critical errors
        if ($severity === 'critical') {
            $this->send_critical_error_notification($error_data);
        }

        // Attempt automatic recovery
        $this->attempt_recovery($type, $error_data);

        return $error_id;
    }

    /**
     * Handle WordPress errors
     *
     * @param \WP_Error $wp_error WordPress error object
     * @param array     $context  Additional context
     * @return string Error ID
     */
    public function handle_wp_error($wp_error, $context = [])
    {
        if (!is_wp_error($wp_error)) {
            return null;
        }

        $error_code = $wp_error->get_error_code();
        $error_message = $wp_error->get_error_message();
        $error_data = $wp_error->get_error_data();

        $full_context = array_merge($context, [
            'wp_error_code' => $error_code,
            'wp_error_data' => $error_data
        ]);

        $severity = $this->determine_severity($error_code);
        $type = $this->determine_error_type($error_code);

        return $this->log_error($type, $error_message, $full_context, $severity);
    }

    /**
     * Handle PHP errors
     *
     * @param int    $errno   Error number
     * @param string $errstr  Error string
     * @param string $errfile Error file
     * @param int    $errline Error line
     * @return bool
     */
    public function handle_php_error($errno, $errstr, $errfile, $errline)
    {
        // Don't handle errors that are suppressed with @
        if (!(error_reporting() & $errno)) {
            return false;
        }

        $severity = $this->php_error_to_severity($errno);
        $type = 'general';

        $context = [
            'php_error_number' => $errno,
            'file' => $errfile,
            'line' => $errline
        ];

        $this->log_error($type, $errstr, $context, $severity);

        // Don't execute PHP internal error handler
        return true;
    }

    /**
     * Handle exceptions
     *
     * @param \Throwable $exception Exception object
     */
    public function handle_exception($exception)
    {
        $context = [
            'exception_class' => get_class($exception),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'trace' => $exception->getTraceAsString()
        ];

        $this->log_error('general', $exception->getMessage(), $context, 'critical');
    }

    /**
     * Handle fatal errors
     */
    public function handle_fatal_error()
    {
        $error = error_get_last();
        
        if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
            $context = [
                'php_error_type' => $error['type'],
                'file' => $error['file'],
                'line' => $error['line']
            ];

            $this->log_error('general', $error['message'], $context, 'critical');
        }
    }

    /**
     * Attempt automatic recovery
     *
     * @param string $error_type Error type
     * @param array  $error_data Error data
     */
    private function attempt_recovery($error_type, $error_data)
    {
        $recovery_key = $error_type . '_failed';
        
        if (isset($this->recovery_strategies[$recovery_key])) {
            try {
                $strategy = $this->recovery_strategies[$recovery_key];
                $result = call_user_func($strategy, $error_data);
                
                if ($result) {
                    $this->log_recovery_success($error_data['id'], $recovery_key);
                }
            } catch (\Exception $e) {
                $this->log_recovery_failure($error_data['id'], $recovery_key, $e->getMessage());
            }
        }
    }

    /**
     * Recover failed upload
     *
     * @param array $error_data Error data
     * @return bool Recovery success
     */
    private function recover_failed_upload($error_data)
    {
        $context = $error_data['context'];
        
        if (!isset($context['upload_id'])) {
            return false;
        }

        $upload_id = $context['upload_id'];
        
        // Clean up partial upload files
        $file_uploader = new FileUploader();
        $file_uploader->cleanup_partial_upload($upload_id);
        
        // Reset upload state in database if exists
        global $wpdb;
        $table_name = $wpdb->prefix . 'reactifywp_uploads';
        
        $wpdb->update(
            $table_name,
            ['status' => 'failed', 'error_message' => $error_data['message']],
            ['upload_id' => $upload_id],
            ['%s', '%s'],
            ['%s']
        );

        return true;
    }

    /**
     * Recover failed extraction
     *
     * @param array $error_data Error data
     * @return bool Recovery success
     */
    private function recover_failed_extraction($error_data)
    {
        $context = $error_data['context'];
        
        if (!isset($context['extraction_path'])) {
            return false;
        }

        $extraction_path = $context['extraction_path'];
        
        // Clean up partial extraction
        if (is_dir($extraction_path)) {
            $zip_extractor = new ZipExtractor();
            $zip_extractor->cleanup_extraction($extraction_path);
        }

        return true;
    }

    /**
     * Recover failed validation
     *
     * @param array $error_data Error data
     * @return bool Recovery success
     */
    private function recover_failed_validation($error_data)
    {
        $context = $error_data['context'];
        
        if (!isset($context['file_path'])) {
            return false;
        }

        $file_path = $context['file_path'];
        
        // Clean up invalid file
        if (file_exists($file_path)) {
            wp_delete_file($file_path);
        }

        return true;
    }

    /**
     * Recover filesystem error
     *
     * @param array $error_data Error data
     * @return bool Recovery success
     */
    private function recover_filesystem_error($error_data)
    {
        $context = $error_data['context'];
        
        // Try to create missing directories
        if (isset($context['missing_directory'])) {
            return wp_mkdir_p($context['missing_directory']);
        }

        // Try to fix permissions
        if (isset($context['permission_file'])) {
            $file = $context['permission_file'];
            if (file_exists($file)) {
                return chmod($file, is_dir($file) ? 0755 : 0644);
            }
        }

        return false;
    }

    /**
     * Recover database error
     *
     * @param array $error_data Error data
     * @return bool Recovery success
     */
    private function recover_database_error($error_data)
    {
        // Try to recreate missing tables
        $database = new Database();
        $database->create_tables();

        return true;
    }

    /**
     * Recover permission error
     *
     * @param array $error_data Error data
     * @return bool Recovery success
     */
    private function recover_permission_error($error_data)
    {
        $context = $error_data['context'];
        
        if (isset($context['file_path'])) {
            $file_path = $context['file_path'];
            
            if (file_exists($file_path)) {
                $permissions = is_dir($file_path) ? 0755 : 0644;
                return chmod($file_path, $permissions);
            }
        }

        return false;
    }

    /**
     * Get error logs for admin interface
     *
     * @param array $filters Filters to apply
     * @param int   $limit   Number of logs to return
     * @param int   $offset  Offset for pagination
     * @return array Error logs
     */
    public function get_error_logs($filters = [], $limit = 50, $offset = 0)
    {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'reactifywp_error_logs';
        
        $where_clauses = ['1=1'];
        $where_values = [];

        // Apply filters
        if (!empty($filters['type'])) {
            $where_clauses[] = 'type = %s';
            $where_values[] = $filters['type'];
        }

        if (!empty($filters['severity'])) {
            $where_clauses[] = 'severity = %s';
            $where_values[] = $filters['severity'];
        }

        if (!empty($filters['date_from'])) {
            $where_clauses[] = 'timestamp >= %s';
            $where_values[] = $filters['date_from'];
        }

        if (!empty($filters['date_to'])) {
            $where_clauses[] = 'timestamp <= %s';
            $where_values[] = $filters['date_to'];
        }

        $where_clause = implode(' AND ', $where_clauses);
        
        $query = $wpdb->prepare(
            "SELECT * FROM {$table_name} WHERE {$where_clause} ORDER BY timestamp DESC LIMIT %d OFFSET %d",
            array_merge($where_values, [$limit, $offset])
        );

        return $wpdb->get_results($query, ARRAY_A);
    }

    /**
     * Get error statistics
     *
     * @param string $period Time period (day, week, month)
     * @return array Error statistics
     */
    public function get_error_statistics($period = 'week')
    {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'reactifywp_error_logs';
        
        $date_format = match ($period) {
            'day' => '%Y-%m-%d %H:00:00',
            'week' => '%Y-%m-%d',
            'month' => '%Y-%m',
            default => '%Y-%m-%d'
        };

        $interval = match ($period) {
            'day' => 'INTERVAL 24 HOUR',
            'week' => 'INTERVAL 7 DAY',
            'month' => 'INTERVAL 30 DAY',
            default => 'INTERVAL 7 DAY'
        };

        // Get error counts by type
        $type_stats = $wpdb->get_results($wpdb->prepare(
            "SELECT type, COUNT(*) as count 
             FROM {$table_name} 
             WHERE timestamp >= DATE_SUB(NOW(), {$interval})
             GROUP BY type 
             ORDER BY count DESC"
        ), ARRAY_A);

        // Get error counts by severity
        $severity_stats = $wpdb->get_results($wpdb->prepare(
            "SELECT severity, COUNT(*) as count 
             FROM {$table_name} 
             WHERE timestamp >= DATE_SUB(NOW(), {$interval})
             GROUP BY severity 
             ORDER BY FIELD(severity, 'critical', 'high', 'medium', 'low')"
        ), ARRAY_A);

        // Get error trends
        $trend_stats = $wpdb->get_results($wpdb->prepare(
            "SELECT DATE_FORMAT(timestamp, '{$date_format}') as period, COUNT(*) as count
             FROM {$table_name} 
             WHERE timestamp >= DATE_SUB(NOW(), {$interval})
             GROUP BY period 
             ORDER BY period"
        ), ARRAY_A);

        return [
            'by_type' => $type_stats,
            'by_severity' => $severity_stats,
            'trends' => $trend_stats,
            'total_errors' => array_sum(array_column($type_stats, 'count'))
        ];
    }

    /**
     * Handle AJAX request to get error logs
     */
    public function handle_get_error_logs()
    {
        check_ajax_referer('reactifywp_admin', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions.', 'reactifywp'));
        }

        $filters = [
            'type' => sanitize_text_field($_POST['type'] ?? ''),
            'severity' => sanitize_text_field($_POST['severity'] ?? ''),
            'date_from' => sanitize_text_field($_POST['date_from'] ?? ''),
            'date_to' => sanitize_text_field($_POST['date_to'] ?? '')
        ];

        $limit = intval($_POST['limit'] ?? 50);
        $offset = intval($_POST['offset'] ?? 0);

        $logs = $this->get_error_logs($filters, $limit, $offset);
        $stats = $this->get_error_statistics();

        wp_send_json_success([
            'logs' => $logs,
            'statistics' => $stats
        ]);
    }

    /**
     * Handle AJAX request to clear error logs
     */
    public function handle_clear_error_logs()
    {
        check_ajax_referer('reactifywp_admin', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions.', 'reactifywp'));
        }

        $this->clear_error_logs();

        wp_send_json_success([
            'message' => __('Error logs cleared successfully.', 'reactifywp')
        ]);
    }

    /**
     * Handle AJAX request to retry failed operation
     */
    public function handle_retry_operation()
    {
        check_ajax_referer('reactifywp_admin', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions.', 'reactifywp'));
        }

        $error_id = sanitize_text_field($_POST['error_id'] ?? '');

        if (empty($error_id)) {
            wp_send_json_error(__('Error ID is required.', 'reactifywp'));
        }

        $result = $this->retry_failed_operation($error_id);

        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }

        wp_send_json_success([
            'message' => __('Operation retried successfully.', 'reactifywp')
        ]);
    }

    /**
     * Handle AJAX request to recover upload
     */
    public function handle_recover_upload()
    {
        check_ajax_referer('reactifywp_admin', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions.', 'reactifywp'));
        }

        $upload_id = sanitize_text_field($_POST['upload_id'] ?? '');

        if (empty($upload_id)) {
            wp_send_json_error(__('Upload ID is required.', 'reactifywp'));
        }

        $result = $this->recover_upload($upload_id);

        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }

        wp_send_json_success([
            'message' => __('Upload recovered successfully.', 'reactifywp')
        ]);
    }

    /**
     * Clear error logs
     */
    public function clear_error_logs()
    {
        global $wpdb;

        $table_name = $wpdb->prefix . 'reactifywp_error_logs';
        $wpdb->query("TRUNCATE TABLE {$table_name}");

        // Clear log file
        if (file_exists($this->log_file)) {
            file_put_contents($this->log_file, '');
        }
    }

    /**
     * Retry failed operation
     *
     * @param string $error_id Error ID
     * @return true|\WP_Error Retry result
     */
    private function retry_failed_operation($error_id)
    {
        global $wpdb;

        $table_name = $wpdb->prefix . 'reactifywp_error_logs';
        $error_data = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table_name} WHERE id = %s",
            $error_id
        ), ARRAY_A);

        if (!$error_data) {
            return new \WP_Error('error_not_found', __('Error not found.', 'reactifywp'));
        }

        $context = json_decode($error_data['context'], true);

        // Attempt recovery based on error type
        $recovery_result = $this->attempt_recovery($error_data['type'], $error_data);

        if ($recovery_result) {
            // Mark error as resolved
            $wpdb->update(
                $table_name,
                ['resolved' => 1, 'resolved_at' => current_time('mysql')],
                ['id' => $error_id],
                ['%d', '%s'],
                ['%s']
            );
        }

        return $recovery_result;
    }

    /**
     * Recover specific upload
     *
     * @param string $upload_id Upload ID
     * @return true|\WP_Error Recovery result
     */
    private function recover_upload($upload_id)
    {
        // Find related errors
        global $wpdb;

        $table_name = $wpdb->prefix . 'reactifywp_error_logs';
        $errors = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$table_name} WHERE JSON_EXTRACT(context, '$.upload_id') = %s AND resolved = 0",
            $upload_id
        ), ARRAY_A);

        if (empty($errors)) {
            return new \WP_Error('no_errors_found', __('No unresolved errors found for this upload.', 'reactifywp'));
        }

        $recovery_count = 0;

        foreach ($errors as $error) {
            $result = $this->attempt_recovery($error['type'], $error);
            if ($result) {
                $recovery_count++;

                // Mark as resolved
                $wpdb->update(
                    $table_name,
                    ['resolved' => 1, 'resolved_at' => current_time('mysql')],
                    ['id' => $error['id']],
                    ['%d', '%s'],
                    ['%s']
                );
            }
        }

        if ($recovery_count === 0) {
            return new \WP_Error('recovery_failed', __('Failed to recover any errors.', 'reactifywp'));
        }

        return true;
    }

    /**
     * Write error to log file
     *
     * @param array $error_data Error data
     */
    private function write_to_log($error_data)
    {
        if (!$this->log_file) {
            return;
        }

        // Check log file size and rotate if necessary
        if (file_exists($this->log_file) && filesize($this->log_file) > self::MAX_LOG_SIZE) {
            $this->rotate_log_file();
        }

        $log_entry = sprintf(
            "[%s] %s: %s - %s\n",
            $error_data['timestamp'],
            strtoupper($error_data['severity']),
            $error_data['type'],
            $error_data['message']
        );

        file_put_contents($this->log_file, $log_entry, FILE_APPEND | LOCK_EX);
    }

    /**
     * Store error in database
     *
     * @param array $error_data Error data
     */
    private function store_error_in_database($error_data)
    {
        global $wpdb;

        $table_name = $wpdb->prefix . 'reactifywp_error_logs';

        // Create table if it doesn't exist
        $this->create_error_log_table();

        $wpdb->insert(
            $table_name,
            [
                'id' => $error_data['id'],
                'timestamp' => $error_data['timestamp'],
                'type' => $error_data['type'],
                'severity' => $error_data['severity'],
                'message' => $error_data['message'],
                'context' => wp_json_encode($error_data['context']),
                'user_id' => $error_data['user_id'],
                'ip_address' => $error_data['ip_address'],
                'user_agent' => $error_data['user_agent'],
                'request_uri' => $error_data['request_uri'],
                'stack_trace' => $error_data['stack_trace'],
                'resolved' => 0
            ],
            ['%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%d']
        );
    }

    /**
     * Create error log table
     */
    private function create_error_log_table()
    {
        global $wpdb;

        $table_name = $wpdb->prefix . 'reactifywp_error_logs';
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id varchar(50) NOT NULL,
            timestamp datetime NOT NULL,
            type varchar(50) NOT NULL,
            severity varchar(20) NOT NULL,
            message text NOT NULL,
            context longtext,
            user_id bigint(20) unsigned,
            ip_address varchar(45),
            user_agent text,
            request_uri text,
            stack_trace longtext,
            resolved tinyint(1) DEFAULT 0,
            resolved_at datetime NULL,
            PRIMARY KEY (id),
            KEY timestamp (timestamp),
            KEY type (type),
            KEY severity (severity),
            KEY resolved (resolved),
            KEY user_id (user_id)
        ) $charset_collate;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    }

    /**
     * Generate unique error ID
     *
     * @return string Error ID
     */
    private function generate_error_id()
    {
        return 'err_' . date('Ymd_His') . '_' . wp_generate_uuid4();
    }

    /**
     * Get stack trace
     *
     * @return string Stack trace
     */
    private function get_stack_trace()
    {
        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);

        // Remove error handler calls from trace
        $filtered_trace = array_filter($trace, function ($frame) {
            return !isset($frame['class']) || $frame['class'] !== __CLASS__;
        });

        return wp_json_encode(array_values($filtered_trace));
    }

    /**
     * Determine error severity from WordPress error code
     *
     * @param string $error_code Error code
     * @return string Severity level
     */
    private function determine_severity($error_code)
    {
        $critical_errors = ['db_connect_fail', 'filesystem_error', 'security_violation'];
        $high_errors = ['upload_failed', 'extraction_failed', 'validation_failed'];
        $medium_errors = ['file_not_found', 'permission_denied', 'invalid_format'];

        if (in_array($error_code, $critical_errors)) {
            return 'critical';
        } elseif (in_array($error_code, $high_errors)) {
            return 'high';
        } elseif (in_array($error_code, $medium_errors)) {
            return 'medium';
        }

        return 'low';
    }

    /**
     * Determine error type from WordPress error code
     *
     * @param string $error_code Error code
     * @return string Error type
     */
    private function determine_error_type($error_code)
    {
        $type_mapping = [
            'upload_' => 'upload',
            'validation_' => 'validation',
            'extraction_' => 'extraction',
            'security_' => 'security',
            'filesystem_' => 'filesystem',
            'db_' => 'database',
            'network_' => 'network',
            'permission_' => 'permission',
            'config_' => 'configuration'
        ];

        foreach ($type_mapping as $prefix => $type) {
            if (strpos($error_code, $prefix) === 0) {
                return $type;
            }
        }

        return 'general';
    }

    /**
     * Convert PHP error number to severity
     *
     * @param int $errno PHP error number
     * @return string Severity level
     */
    private function php_error_to_severity($errno)
    {
        switch ($errno) {
            case E_ERROR:
            case E_PARSE:
            case E_CORE_ERROR:
            case E_COMPILE_ERROR:
                return 'critical';
            case E_WARNING:
            case E_CORE_WARNING:
            case E_COMPILE_WARNING:
                return 'high';
            case E_NOTICE:
            case E_USER_NOTICE:
                return 'low';
            default:
                return 'medium';
        }
    }

    /**
     * Rotate log file
     */
    private function rotate_log_file()
    {
        if (!file_exists($this->log_file)) {
            return;
        }

        $backup_file = $this->log_file . '.' . date('Y-m-d-H-i-s');
        rename($this->log_file, $backup_file);

        // Keep only last 5 backup files
        $log_dir = dirname($this->log_file);
        $backup_files = glob($log_dir . '/error.log.*');

        if (count($backup_files) > 5) {
            usort($backup_files, function ($a, $b) {
                return filemtime($a) - filemtime($b);
            });

            $files_to_delete = array_slice($backup_files, 0, count($backup_files) - 5);
            foreach ($files_to_delete as $file) {
                wp_delete_file($file);
            }
        }
    }

    /**
     * Send critical error notification
     *
     * @param array $error_data Error data
     */
    private function send_critical_error_notification($error_data)
    {
        $admin_email = get_option('admin_email');

        if (!$admin_email) {
            return;
        }

        $subject = sprintf(
            __('[%s] Critical ReactifyWP Error', 'reactifywp'),
            get_bloginfo('name')
        );

        $message = sprintf(
            __("A critical error occurred in ReactifyWP:\n\nError ID: %s\nType: %s\nMessage: %s\nTime: %s\n\nPlease check the admin panel for more details.", 'reactifywp'),
            $error_data['id'],
            $error_data['type'],
            $error_data['message'],
            $error_data['timestamp']
        );

        wp_mail($admin_email, $subject, $message);
    }

    /**
     * Log recovery success
     *
     * @param string $error_id      Error ID
     * @param string $recovery_type Recovery type
     */
    private function log_recovery_success($error_id, $recovery_type)
    {
        $this->log_error(
            'recovery',
            sprintf(__('Successfully recovered from error %s using strategy %s', 'reactifywp'), $error_id, $recovery_type),
            ['original_error_id' => $error_id, 'recovery_strategy' => $recovery_type],
            'low'
        );
    }

    /**
     * Log recovery failure
     *
     * @param string $error_id      Error ID
     * @param string $recovery_type Recovery type
     * @param string $failure_reason Failure reason
     */
    private function log_recovery_failure($error_id, $recovery_type, $failure_reason)
    {
        $this->log_error(
            'recovery',
            sprintf(__('Failed to recover from error %s using strategy %s: %s', 'reactifywp'), $error_id, $recovery_type, $failure_reason),
            ['original_error_id' => $error_id, 'recovery_strategy' => $recovery_type, 'failure_reason' => $failure_reason],
            'medium'
        );
    }
}
