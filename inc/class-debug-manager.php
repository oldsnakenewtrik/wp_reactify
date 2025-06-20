<?php
/**
 * Debug Manager for ReactifyWP
 *
 * @package ReactifyWP
 */

namespace ReactifyWP;

/**
 * Debug Manager class
 */
class DebugManager
{
    /**
     * Debug levels
     */
    const DEBUG_LEVELS = [
        'emergency' => 0,
        'alert' => 1,
        'critical' => 2,
        'error' => 3,
        'warning' => 4,
        'notice' => 5,
        'info' => 6,
        'debug' => 7
    ];

    /**
     * Debug log file
     */
    const LOG_FILE = 'reactifywp-debug.log';

    /**
     * Debug data
     *
     * @var array
     */
    private $debug_data = [];

    /**
     * Performance markers
     *
     * @var array
     */
    private $performance_markers = [];

    /**
     * Constructor
     */
    public function __construct()
    {
        add_action('init', [$this, 'init_debug_features']);
        add_action('wp_footer', [$this, 'output_debug_panel'], 999);
        add_action('wp_ajax_reactifywp_get_debug_info', [$this, 'handle_get_debug_info_ajax']);
        add_action('wp_ajax_reactifywp_clear_debug_log', [$this, 'handle_clear_debug_log_ajax']);
        add_action('wp_ajax_reactifywp_export_debug_info', [$this, 'handle_export_debug_info_ajax']);
        
        // Error handling
        add_action('wp_ajax_reactifywp_test_error_handling', [$this, 'handle_test_error_handling_ajax']);
        
        // Performance monitoring
        add_action('wp_head', [$this, 'start_performance_monitoring'], 1);
        add_action('wp_footer', [$this, 'end_performance_monitoring'], 1);
    }

    /**
     * Initialize debug features
     */
    public function init_debug_features()
    {
        if (!$this->is_debug_enabled()) {
            return;
        }

        // Set up error handlers
        $this->setup_error_handlers();

        // Initialize debug data collection
        $this->init_debug_data_collection();

        // Set up JavaScript error tracking
        add_action('wp_head', [$this, 'add_js_error_tracking']);
    }

    /**
     * Log debug message
     *
     * @param string $level   Debug level
     * @param string $message Debug message
     * @param array  $context Additional context
     */
    public function log($level, $message, $context = [])
    {
        if (!$this->is_debug_enabled()) {
            return;
        }

        $log_entry = [
            'timestamp' => current_time('mysql'),
            'level' => $level,
            'message' => $message,
            'context' => $context,
            'backtrace' => $this->get_debug_backtrace(),
            'memory_usage' => memory_get_usage(true),
            'peak_memory' => memory_get_peak_usage(true)
        ];

        // Add to debug data
        $this->debug_data[] = $log_entry;

        // Write to log file
        $this->write_to_log_file($log_entry);

        // Trigger action for external logging
        do_action('reactifywp_debug_log', $log_entry);
    }

    /**
     * Add performance marker
     *
     * @param string $name   Marker name
     * @param array  $data   Additional data
     */
    public function mark_performance($name, $data = [])
    {
        if (!$this->is_debug_enabled()) {
            return;
        }

        $this->performance_markers[] = [
            'name' => $name,
            'timestamp' => microtime(true),
            'memory' => memory_get_usage(true),
            'data' => $data
        ];
    }

    /**
     * Get debug information
     *
     * @return array Debug information
     */
    public function get_debug_info()
    {
        return [
            'system_info' => $this->get_system_info(),
            'plugin_info' => $this->get_plugin_info(),
            'wordpress_info' => $this->get_wordpress_info(),
            'server_info' => $this->get_server_info(),
            'debug_log' => $this->get_recent_debug_entries(),
            'performance_data' => $this->get_performance_data(),
            'error_summary' => $this->get_error_summary(),
            'configuration' => $this->get_configuration_info()
        ];
    }

    /**
     * Start performance monitoring
     */
    public function start_performance_monitoring()
    {
        if (!$this->is_debug_enabled()) {
            return;
        }

        $this->mark_performance('page_start', [
            'url' => $_SERVER['REQUEST_URI'] ?? '',
            'method' => $_SERVER['REQUEST_METHOD'] ?? '',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? ''
        ]);
    }

    /**
     * End performance monitoring
     */
    public function end_performance_monitoring()
    {
        if (!$this->is_debug_enabled()) {
            return;
        }

        $this->mark_performance('page_end', [
            'queries' => get_num_queries(),
            'query_time' => $this->get_total_query_time()
        ]);
    }

    /**
     * Output debug panel
     */
    public function output_debug_panel()
    {
        if (!$this->should_show_debug_panel()) {
            return;
        }

        $debug_info = $this->get_debug_info();
        $this->render_debug_panel($debug_info);
    }

    /**
     * Add JavaScript error tracking
     */
    public function add_js_error_tracking()
    {
        if (!$this->is_debug_enabled()) {
            return;
        }

        ?>
        <script>
        (function() {
            'use strict';
            
            // Global error handler
            window.addEventListener('error', function(event) {
                ReactifyWP.DebugManager.logJSError({
                    type: 'javascript_error',
                    message: event.message,
                    filename: event.filename,
                    lineno: event.lineno,
                    colno: event.colno,
                    stack: event.error ? event.error.stack : null,
                    url: window.location.href,
                    userAgent: navigator.userAgent,
                    timestamp: new Date().toISOString()
                });
            });
            
            // Unhandled promise rejection handler
            window.addEventListener('unhandledrejection', function(event) {
                ReactifyWP.DebugManager.logJSError({
                    type: 'promise_rejection',
                    message: event.reason ? event.reason.toString() : 'Unhandled Promise Rejection',
                    stack: event.reason ? event.reason.stack : null,
                    url: window.location.href,
                    userAgent: navigator.userAgent,
                    timestamp: new Date().toISOString()
                });
            });
            
            // ReactifyWP Debug Manager
            window.ReactifyWP = window.ReactifyWP || {};
            window.ReactifyWP.DebugManager = {
                errors: [],
                
                logJSError: function(errorData) {
                    this.errors.push(errorData);
                    
                    // Send to server if enabled
                    if (this.shouldSendToServer()) {
                        this.sendErrorToServer(errorData);
                    }
                    
                    // Log to console in debug mode
                    if (<?php echo wp_json_encode($this->is_debug_mode_verbose()); ?>) {
                        console.error('ReactifyWP JS Error:', errorData);
                    }
                },
                
                shouldSendToServer: function() {
                    return <?php echo wp_json_encode($this->should_send_js_errors_to_server()); ?>;
                },
                
                sendErrorToServer: function(errorData) {
                    if (!navigator.sendBeacon) {
                        return;
                    }
                    
                    var formData = new FormData();
                    formData.append('action', 'reactifywp_log_js_error');
                    formData.append('nonce', '<?php echo wp_create_nonce('reactifywp_debug'); ?>');
                    formData.append('error_data', JSON.stringify(errorData));
                    
                    navigator.sendBeacon('<?php echo admin_url('admin-ajax.php'); ?>', formData);
                },
                
                getErrors: function() {
                    return this.errors;
                },
                
                clearErrors: function() {
                    this.errors = [];
                }
            };
        })();
        </script>
        <?php
    }

    /**
     * Handle get debug info AJAX request
     */
    public function handle_get_debug_info_ajax()
    {
        check_ajax_referer('reactifywp_admin', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions.', 'reactifywp'));
        }

        $debug_info = $this->get_debug_info();
        wp_send_json_success($debug_info);
    }

    /**
     * Handle clear debug log AJAX request
     */
    public function handle_clear_debug_log_ajax()
    {
        check_ajax_referer('reactifywp_admin', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions.', 'reactifywp'));
        }

        $this->clear_debug_log();
        wp_send_json_success(['message' => __('Debug log cleared successfully.', 'reactifywp')]);
    }

    /**
     * Handle export debug info AJAX request
     */
    public function handle_export_debug_info_ajax()
    {
        check_ajax_referer('reactifywp_admin', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions.', 'reactifywp'));
        }

        $debug_info = $this->get_debug_info();
        $export_data = $this->prepare_export_data($debug_info);

        wp_send_json_success([
            'filename' => 'reactifywp-debug-' . date('Y-m-d-H-i-s') . '.json',
            'data' => $export_data
        ]);
    }

    /**
     * Handle test error handling AJAX request
     */
    public function handle_test_error_handling_ajax()
    {
        check_ajax_referer('reactifywp_admin', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions.', 'reactifywp'));
        }

        $test_type = sanitize_text_field($_POST['test_type'] ?? 'warning');

        switch ($test_type) {
            case 'warning':
                $this->log('warning', 'Test warning message', ['test' => true]);
                break;
            case 'error':
                $this->log('error', 'Test error message', ['test' => true]);
                break;
            case 'critical':
                $this->log('critical', 'Test critical message', ['test' => true]);
                break;
            case 'exception':
                try {
                    throw new \Exception('Test exception for debugging');
                } catch (\Exception $e) {
                    $this->log_exception($e, ['test' => true]);
                }
                break;
        }

        wp_send_json_success(['message' => "Test {$test_type} logged successfully."]);
    }

    /**
     * Log exception
     *
     * @param \Exception $exception Exception to log
     * @param array      $context   Additional context
     */
    public function log_exception($exception, $context = [])
    {
        $this->log('error', $exception->getMessage(), array_merge($context, [
            'exception_class' => get_class($exception),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'trace' => $exception->getTraceAsString()
        ]));
    }

    /**
     * Private helper methods
     */

    /**
     * Check if debug is enabled
     *
     * @return bool Is enabled
     */
    private function is_debug_enabled()
    {
        $settings = get_option('reactifywp_settings', []);
        return $settings['debug']['enabled'] ?? (defined('WP_DEBUG') && WP_DEBUG);
    }

    /**
     * Check if debug mode is verbose
     *
     * @return bool Is verbose
     */
    private function is_debug_mode_verbose()
    {
        $settings = get_option('reactifywp_settings', []);
        return $settings['debug']['verbose'] ?? false;
    }

    /**
     * Check if should send JS errors to server
     *
     * @return bool Should send
     */
    private function should_send_js_errors_to_server()
    {
        $settings = get_option('reactifywp_settings', []);
        return $settings['debug']['js_error_reporting'] ?? true;
    }

    /**
     * Check if should show debug panel
     *
     * @return bool Should show
     */
    private function should_show_debug_panel()
    {
        if (!$this->is_debug_enabled()) {
            return false;
        }

        if (!current_user_can('manage_options')) {
            return false;
        }

        $settings = get_option('reactifywp_settings', []);
        return $settings['debug']['show_panel'] ?? false;
    }

    /**
     * Set up error handlers
     */
    private function setup_error_handlers()
    {
        // PHP error handler
        set_error_handler([$this, 'handle_php_error']);
        
        // Exception handler
        set_exception_handler([$this, 'handle_php_exception']);
        
        // Shutdown handler for fatal errors
        register_shutdown_function([$this, 'handle_shutdown']);
    }

    /**
     * Handle PHP error
     *
     * @param int    $errno   Error number
     * @param string $errstr  Error string
     * @param string $errfile Error file
     * @param int    $errline Error line
     */
    public function handle_php_error($errno, $errstr, $errfile, $errline)
    {
        $error_types = [
            E_ERROR => 'error',
            E_WARNING => 'warning',
            E_PARSE => 'critical',
            E_NOTICE => 'notice',
            E_CORE_ERROR => 'critical',
            E_CORE_WARNING => 'warning',
            E_COMPILE_ERROR => 'critical',
            E_COMPILE_WARNING => 'warning',
            E_USER_ERROR => 'error',
            E_USER_WARNING => 'warning',
            E_USER_NOTICE => 'notice',
            E_STRICT => 'notice',
            E_RECOVERABLE_ERROR => 'error',
            E_DEPRECATED => 'notice',
            E_USER_DEPRECATED => 'notice'
        ];

        $level = $error_types[$errno] ?? 'error';

        $this->log($level, $errstr, [
            'errno' => $errno,
            'file' => $errfile,
            'line' => $errline,
            'type' => 'php_error'
        ]);

        // Don't execute PHP internal error handler
        return true;
    }

    /**
     * Handle PHP exception
     *
     * @param \Exception $exception Exception
     */
    public function handle_php_exception($exception)
    {
        $this->log_exception($exception, ['type' => 'uncaught_exception']);
    }

    /**
     * Handle shutdown (for fatal errors)
     */
    public function handle_shutdown()
    {
        $error = error_get_last();
        
        if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
            $this->log('critical', $error['message'], [
                'file' => $error['file'],
                'line' => $error['line'],
                'type' => 'fatal_error'
            ]);
        }
    }

    /**
     * Initialize debug data collection
     */
    private function init_debug_data_collection()
    {
        $this->debug_data = [];
        $this->performance_markers = [];
        
        // Start collecting data
        $this->mark_performance('debug_init');
    }

    /**
     * Get debug backtrace
     *
     * @return array Backtrace
     */
    private function get_debug_backtrace()
    {
        $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 10);
        
        // Remove this method from backtrace
        array_shift($backtrace);
        
        return array_map(function($trace) {
            return [
                'file' => $trace['file'] ?? 'unknown',
                'line' => $trace['line'] ?? 0,
                'function' => $trace['function'] ?? 'unknown',
                'class' => $trace['class'] ?? null
            ];
        }, $backtrace);
    }

    /**
     * Write to log file
     *
     * @param array $log_entry Log entry
     */
    private function write_to_log_file($log_entry)
    {
        $upload_dir = wp_upload_dir();
        $log_file = $upload_dir['basedir'] . '/' . self::LOG_FILE;
        
        $log_line = sprintf(
            "[%s] %s: %s %s\n",
            $log_entry['timestamp'],
            strtoupper($log_entry['level']),
            $log_entry['message'],
            !empty($log_entry['context']) ? wp_json_encode($log_entry['context']) : ''
        );
        
        error_log($log_line, 3, $log_file);
    }

    /**
     * Get recent debug entries
     *
     * @param int $limit Number of entries to return
     * @return array Recent debug entries
     */
    private function get_recent_debug_entries($limit = 50)
    {
        return array_slice($this->debug_data, -$limit);
    }

    /**
     * Get performance data
     *
     * @return array Performance data
     */
    private function get_performance_data()
    {
        $data = [
            'markers' => $this->performance_markers,
            'memory_usage' => memory_get_usage(true),
            'peak_memory' => memory_get_peak_usage(true),
            'execution_time' => 0
        ];
        
        if (count($this->performance_markers) >= 2) {
            $start = $this->performance_markers[0]['timestamp'];
            $end = end($this->performance_markers)['timestamp'];
            $data['execution_time'] = $end - $start;
        }
        
        return $data;
    }

    /**
     * Get error summary
     *
     * @return array Error summary
     */
    private function get_error_summary()
    {
        $summary = [
            'total' => 0,
            'by_level' => [],
            'recent_errors' => []
        ];
        
        foreach ($this->debug_data as $entry) {
            $summary['total']++;
            $level = $entry['level'];
            $summary['by_level'][$level] = ($summary['by_level'][$level] ?? 0) + 1;
            
            if (in_array($level, ['error', 'critical', 'emergency'])) {
                $summary['recent_errors'][] = $entry;
            }
        }
        
        // Keep only last 10 recent errors
        $summary['recent_errors'] = array_slice($summary['recent_errors'], -10);
        
        return $summary;
    }

    /**
     * Get system information
     *
     * @return array System information
     */
    private function get_system_info()
    {
        return [
            'php_version' => PHP_VERSION,
            'php_sapi' => php_sapi_name(),
            'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
            'operating_system' => PHP_OS,
            'memory_limit' => ini_get('memory_limit'),
            'max_execution_time' => ini_get('max_execution_time'),
            'upload_max_filesize' => ini_get('upload_max_filesize'),
            'post_max_size' => ini_get('post_max_size'),
            'max_input_vars' => ini_get('max_input_vars'),
            'timezone' => date_default_timezone_get(),
            'extensions' => get_loaded_extensions()
        ];
    }

    /**
     * Get plugin information
     *
     * @return array Plugin information
     */
    private function get_plugin_info()
    {
        return [
            'version' => REACTIFYWP_VERSION,
            'plugin_dir' => REACTIFYWP_PLUGIN_DIR,
            'plugin_url' => REACTIFYWP_PLUGIN_URL,
            'settings' => get_option('reactifywp_settings', []),
            'active_features' => $this->get_active_features(),
            'database_version' => get_option('reactifywp_db_version', '1.0.0')
        ];
    }

    /**
     * Get WordPress information
     *
     * @return array WordPress information
     */
    private function get_wordpress_info()
    {
        global $wp_version;

        return [
            'version' => $wp_version,
            'multisite' => is_multisite(),
            'debug' => defined('WP_DEBUG') && WP_DEBUG,
            'debug_log' => defined('WP_DEBUG_LOG') && WP_DEBUG_LOG,
            'debug_display' => defined('WP_DEBUG_DISPLAY') && WP_DEBUG_DISPLAY,
            'script_debug' => defined('SCRIPT_DEBUG') && SCRIPT_DEBUG,
            'memory_limit' => WP_MEMORY_LIMIT,
            'max_memory_limit' => WP_MAX_MEMORY_LIMIT,
            'active_theme' => wp_get_theme()->get('Name'),
            'active_plugins' => get_option('active_plugins', []),
            'permalink_structure' => get_option('permalink_structure'),
            'home_url' => home_url(),
            'site_url' => site_url()
        ];
    }

    /**
     * Get server information
     *
     * @return array Server information
     */
    private function get_server_info()
    {
        return [
            'document_root' => $_SERVER['DOCUMENT_ROOT'] ?? 'Unknown',
            'server_admin' => $_SERVER['SERVER_ADMIN'] ?? 'Unknown',
            'server_port' => $_SERVER['SERVER_PORT'] ?? 'Unknown',
            'https' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown',
            'remote_addr' => $_SERVER['REMOTE_ADDR'] ?? 'Unknown',
            'request_method' => $_SERVER['REQUEST_METHOD'] ?? 'Unknown',
            'request_uri' => $_SERVER['REQUEST_URI'] ?? 'Unknown',
            'query_string' => $_SERVER['QUERY_STRING'] ?? '',
            'disk_free_space' => disk_free_space('.'),
            'disk_total_space' => disk_total_space('.')
        ];
    }

    /**
     * Get configuration information
     *
     * @return array Configuration information
     */
    private function get_configuration_info()
    {
        $settings = get_option('reactifywp_settings', []);

        return [
            'debug_enabled' => $this->is_debug_enabled(),
            'debug_level' => $settings['debug']['level'] ?? 'error',
            'log_file_size' => $this->get_log_file_size(),
            'cache_enabled' => $settings['performance']['enable_caching'] ?? true,
            'cdn_enabled' => !empty($settings['cdn']['enabled']),
            'security_level' => $settings['security']['level'] ?? 'medium',
            'upload_max_size' => $settings['upload']['max_file_size'] ?? '50MB'
        ];
    }

    /**
     * Get active features
     *
     * @return array Active features
     */
    private function get_active_features()
    {
        $features = [];

        // Check if various components are active
        if (class_exists('ReactifyWP\FileUploader')) {
            $features[] = 'file_upload';
        }

        if (class_exists('ReactifyWP\SecurityValidator')) {
            $features[] = 'security_validation';
        }

        if (class_exists('ReactifyWP\PerformanceOptimizer')) {
            $features[] = 'performance_optimization';
        }

        if (class_exists('ReactifyWP\CDNManager')) {
            $features[] = 'cdn_management';
        }

        if (class_exists('ReactifyWP\PageBuilderIntegration')) {
            $features[] = 'page_builder_integration';
        }

        return $features;
    }

    /**
     * Get total query time
     *
     * @return float Total query time
     */
    private function get_total_query_time()
    {
        global $wpdb;

        if (!defined('SAVEQUERIES') || !SAVEQUERIES) {
            return 0;
        }

        $total_time = 0;
        foreach ($wpdb->queries as $query) {
            $total_time += $query[1];
        }

        return $total_time;
    }

    /**
     * Get log file size
     *
     * @return int Log file size in bytes
     */
    private function get_log_file_size()
    {
        $upload_dir = wp_upload_dir();
        $log_file = $upload_dir['basedir'] . '/' . self::LOG_FILE;

        return file_exists($log_file) ? filesize($log_file) : 0;
    }

    /**
     * Clear debug log
     */
    private function clear_debug_log()
    {
        $upload_dir = wp_upload_dir();
        $log_file = $upload_dir['basedir'] . '/' . self::LOG_FILE;

        if (file_exists($log_file)) {
            wp_delete_file($log_file);
        }

        $this->debug_data = [];
        $this->performance_markers = [];
    }

    /**
     * Prepare export data
     *
     * @param array $debug_info Debug information
     * @return string JSON export data
     */
    private function prepare_export_data($debug_info)
    {
        $export_data = [
            'export_timestamp' => current_time('mysql'),
            'site_url' => home_url(),
            'plugin_version' => REACTIFYWP_VERSION,
            'debug_info' => $debug_info
        ];

        return wp_json_encode($export_data, JSON_PRETTY_PRINT);
    }

    /**
     * Render debug panel
     *
     * @param array $debug_info Debug information
     */
    private function render_debug_panel($debug_info)
    {
        ?>
        <div id="reactifywp-debug-panel" style="
            position: fixed;
            bottom: 0;
            right: 0;
            width: 400px;
            max-height: 500px;
            background: #1e1e1e;
            color: #e0e0e0;
            border: 1px solid #333;
            border-radius: 8px 0 0 0;
            font-family: 'Courier New', monospace;
            font-size: 12px;
            z-index: 999999;
            overflow: hidden;
            box-shadow: -2px -2px 10px rgba(0,0,0,0.3);
        ">
            <div style="
                background: #333;
                padding: 8px 12px;
                border-bottom: 1px solid #555;
                display: flex;
                justify-content: space-between;
                align-items: center;
            ">
                <strong>ReactifyWP Debug Panel</strong>
                <button onclick="document.getElementById('reactifywp-debug-panel').style.display='none'" style="
                    background: #666;
                    border: none;
                    color: #fff;
                    padding: 2px 6px;
                    border-radius: 3px;
                    cursor: pointer;
                ">×</button>
            </div>

            <div style="padding: 12px; overflow-y: auto; max-height: 450px;">
                <!-- Performance Summary -->
                <div style="margin-bottom: 16px;">
                    <h4 style="margin: 0 0 8px 0; color: #4CAF50;">Performance</h4>
                    <div style="font-size: 11px; line-height: 1.4;">
                        <div>Execution Time: <?php echo number_format($debug_info['performance_data']['execution_time'] * 1000, 2); ?>ms</div>
                        <div>Memory Usage: <?php echo size_format($debug_info['performance_data']['memory_usage']); ?></div>
                        <div>Peak Memory: <?php echo size_format($debug_info['performance_data']['peak_memory']); ?></div>
                        <div>DB Queries: <?php echo get_num_queries(); ?></div>
                    </div>
                </div>

                <!-- Error Summary -->
                <?php if (!empty($debug_info['error_summary']['recent_errors'])): ?>
                <div style="margin-bottom: 16px;">
                    <h4 style="margin: 0 0 8px 0; color: #f44336;">Recent Errors</h4>
                    <div style="font-size: 11px; line-height: 1.4;">
                        <?php foreach (array_slice($debug_info['error_summary']['recent_errors'], -3) as $error): ?>
                        <div style="margin-bottom: 4px; padding: 4px; background: #2a1a1a; border-left: 3px solid #f44336;">
                            <div style="color: #ff6b6b;"><?php echo esc_html($error['level']); ?>: <?php echo esc_html($error['message']); ?></div>
                            <div style="color: #888; font-size: 10px;"><?php echo esc_html($error['timestamp']); ?></div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>

                <!-- System Info -->
                <div style="margin-bottom: 16px;">
                    <h4 style="margin: 0 0 8px 0; color: #2196F3;">System</h4>
                    <div style="font-size: 11px; line-height: 1.4;">
                        <div>PHP: <?php echo esc_html($debug_info['system_info']['php_version']); ?></div>
                        <div>WordPress: <?php echo esc_html($debug_info['wordpress_info']['version']); ?></div>
                        <div>Plugin: <?php echo esc_html($debug_info['plugin_info']['version']); ?></div>
                        <div>Memory Limit: <?php echo esc_html($debug_info['system_info']['memory_limit']); ?></div>
                    </div>
                </div>

                <!-- Debug Log -->
                <?php if (!empty($debug_info['debug_log'])): ?>
                <div>
                    <h4 style="margin: 0 0 8px 0; color: #FF9800;">Debug Log (Last 5)</h4>
                    <div style="font-size: 10px; line-height: 1.3; max-height: 150px; overflow-y: auto;">
                        <?php foreach (array_slice($debug_info['debug_log'], -5) as $entry): ?>
                        <div style="margin-bottom: 4px; padding: 3px; background: #2a2a2a; border-radius: 2px;">
                            <div style="color: #<?php echo $this->get_level_color($entry['level']); ?>;">
                                [<?php echo esc_html($entry['level']); ?>] <?php echo esc_html($entry['message']); ?>
                            </div>
                            <div style="color: #666; font-size: 9px;"><?php echo esc_html($entry['timestamp']); ?></div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <style>
        #reactifywp-debug-panel::-webkit-scrollbar {
            width: 6px;
        }
        #reactifywp-debug-panel::-webkit-scrollbar-track {
            background: #2a2a2a;
        }
        #reactifywp-debug-panel::-webkit-scrollbar-thumb {
            background: #555;
            border-radius: 3px;
        }
        #reactifywp-debug-panel::-webkit-scrollbar-thumb:hover {
            background: #777;
        }
        </style>
        <?php
    }

    /**
     * Get color for debug level
     *
     * @param string $level Debug level
     * @return string Hex color
     */
    private function get_level_color($level)
    {
        $colors = [
            'emergency' => 'ff1744',
            'alert' => 'ff5722',
            'critical' => 'f44336',
            'error' => 'ff6b6b',
            'warning' => 'ff9800',
            'notice' => '2196f3',
            'info' => '4caf50',
            'debug' => '9e9e9e'
        ];

        return $colors[$level] ?? '9e9e9e';
    }

    /**
     * Create debug report
     *
     * @return array Debug report
     */
    public function create_debug_report()
    {
        $debug_info = $this->get_debug_info();

        return [
            'report_id' => uniqid('reactifywp_debug_'),
            'timestamp' => current_time('mysql'),
            'site_info' => [
                'url' => home_url(),
                'name' => get_bloginfo('name'),
                'admin_email' => get_option('admin_email')
            ],
            'debug_data' => $debug_info,
            'recommendations' => $this->get_debug_recommendations($debug_info)
        ];
    }

    /**
     * Get debug recommendations
     *
     * @param array $debug_info Debug information
     * @return array Recommendations
     */
    private function get_debug_recommendations($debug_info)
    {
        $recommendations = [];

        // Memory usage recommendations
        $memory_usage = $debug_info['performance_data']['memory_usage'];
        $memory_limit = wp_convert_hr_to_bytes($debug_info['system_info']['memory_limit']);

        if ($memory_usage > $memory_limit * 0.8) {
            $recommendations[] = [
                'type' => 'memory',
                'priority' => 'high',
                'title' => 'High Memory Usage',
                'description' => 'Memory usage is approaching the limit. Consider increasing memory_limit or optimizing code.',
                'current_value' => size_format($memory_usage),
                'recommended_value' => size_format($memory_limit * 2)
            ];
        }

        // Query count recommendations
        $query_count = get_num_queries();
        if ($query_count > 50) {
            $recommendations[] = [
                'type' => 'queries',
                'priority' => 'medium',
                'title' => 'High Database Query Count',
                'description' => 'Consider implementing caching or optimizing database queries.',
                'current_value' => $query_count,
                'recommended_value' => '< 30'
            ];
        }

        // Error count recommendations
        $error_count = $debug_info['error_summary']['total'];
        if ($error_count > 10) {
            $recommendations[] = [
                'type' => 'errors',
                'priority' => 'high',
                'title' => 'High Error Count',
                'description' => 'Multiple errors detected. Review error log and fix underlying issues.',
                'current_value' => $error_count,
                'recommended_value' => '0'
            ];
        }

        // PHP version recommendations
        if (version_compare(PHP_VERSION, '8.0', '<')) {
            $recommendations[] = [
                'type' => 'php_version',
                'priority' => 'medium',
                'title' => 'Outdated PHP Version',
                'description' => 'Consider upgrading to PHP 8.0+ for better performance and security.',
                'current_value' => PHP_VERSION,
                'recommended_value' => '8.0+'
            ];
        }

        return $recommendations;
    }

    /**
     * Get debug statistics
     *
     * @return array Debug statistics
     */
    public function get_debug_statistics()
    {
        return [
            'total_log_entries' => count($this->debug_data),
            'log_file_size' => $this->get_log_file_size(),
            'error_rate' => $this->calculate_error_rate(),
            'performance_score' => $this->calculate_performance_score(),
            'last_error' => $this->get_last_error(),
            'uptime' => $this->get_debug_uptime()
        ];
    }

    /**
     * Calculate error rate
     *
     * @return float Error rate percentage
     */
    private function calculate_error_rate()
    {
        $total_entries = count($this->debug_data);
        if ($total_entries === 0) {
            return 0;
        }

        $error_entries = 0;
        foreach ($this->debug_data as $entry) {
            if (in_array($entry['level'], ['error', 'critical', 'emergency'])) {
                $error_entries++;
            }
        }

        return ($error_entries / $total_entries) * 100;
    }

    /**
     * Calculate performance score
     *
     * @return int Performance score (0-100)
     */
    private function calculate_performance_score()
    {
        $score = 100;

        // Deduct points for high memory usage
        $memory_usage = memory_get_usage(true);
        $memory_limit = wp_convert_hr_to_bytes(ini_get('memory_limit'));
        $memory_percentage = ($memory_usage / $memory_limit) * 100;

        if ($memory_percentage > 80) {
            $score -= 20;
        } elseif ($memory_percentage > 60) {
            $score -= 10;
        }

        // Deduct points for high query count
        $query_count = get_num_queries();
        if ($query_count > 100) {
            $score -= 30;
        } elseif ($query_count > 50) {
            $score -= 15;
        }

        // Deduct points for errors
        $error_rate = $this->calculate_error_rate();
        $score -= min($error_rate * 2, 40);

        return max(0, $score);
    }

    /**
     * Get last error
     *
     * @return array|null Last error entry
     */
    private function get_last_error()
    {
        foreach (array_reverse($this->debug_data) as $entry) {
            if (in_array($entry['level'], ['error', 'critical', 'emergency'])) {
                return $entry;
            }
        }

        return null;
    }

    /**
     * Get debug uptime
     *
     * @return string Debug uptime
     */
    private function get_debug_uptime()
    {
        if (empty($this->performance_markers)) {
            return '0s';
        }

        $start_time = $this->performance_markers[0]['timestamp'];
        $uptime = microtime(true) - $start_time;

        return number_format($uptime, 2) . 's';
    }
}
