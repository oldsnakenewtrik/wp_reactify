<?php
/**
 * Advanced settings management for ReactifyWP
 *
 * @package ReactifyWP
 */

namespace ReactifyWP;

/**
 * Settings class
 */
class Settings
{
    /**
     * Settings option key
     */
    const OPTION_KEY = 'reactifywp_settings';

    /**
     * Default settings
     */
    const DEFAULTS = [
        'general' => [
            'max_upload_size' => '50MB',
            'enable_scoped_styles' => true,
            'enable_cache_busting' => true,
            'defer_js_loading' => true,
            'auto_cleanup_days' => 90,
            'enable_error_logging' => true
        ],
        'security' => [
            'allowed_file_types' => ['zip'],
            'max_files_per_zip' => 1000,
            'max_uncompressed_size' => '500MB',
            'enable_path_validation' => true,
            'block_suspicious_files' => true,
            'enable_virus_scanning' => false
        ],
        'performance' => [
            'enable_asset_minification' => false,
            'enable_gzip_compression' => true,
            'enable_browser_caching' => true,
            'preload_critical_assets' => true,
            'lazy_load_non_critical' => true,
            'enable_cdn_support' => false,
            'cdn_url' => ''
        ],
        'advanced' => [
            'enable_debug_mode' => false,
            'log_level' => 'error',
            'enable_multisite_sync' => false,
            'custom_upload_path' => '',
            'enable_api_access' => false,
            'api_rate_limit' => 100
        ]
    ];

    /**
     * Constructor
     */
    public function __construct()
    {
        add_action('admin_init', [$this, 'register_settings']);
        add_action('wp_ajax_reactifywp_export_settings', [$this, 'handle_export_settings']);
        add_action('wp_ajax_reactifywp_import_settings', [$this, 'handle_import_settings']);
        add_action('wp_ajax_reactifywp_reset_settings', [$this, 'handle_reset_settings']);
        add_action('wp_ajax_reactifywp_validate_setting', [$this, 'handle_validate_setting']);
    }

    /**
     * Register settings
     */
    public function register_settings()
    {
        register_setting(
            'reactifywp_settings',
            self::OPTION_KEY,
            [
                'sanitize_callback' => [$this, 'sanitize_settings'],
                'default' => self::DEFAULTS
            ]
        );

        // General Settings Section
        add_settings_section(
            'reactifywp_general',
            __('General Settings', 'reactifywp'),
            [$this, 'general_section_callback'],
            'reactifywp_settings'
        );

        $this->add_general_fields();

        // Security Settings Section
        add_settings_section(
            'reactifywp_security',
            __('Security Settings', 'reactifywp'),
            [$this, 'security_section_callback'],
            'reactifywp_settings'
        );

        $this->add_security_fields();

        // Performance Settings Section
        add_settings_section(
            'reactifywp_performance',
            __('Performance Settings', 'reactifywp'),
            [$this, 'performance_section_callback'],
            'reactifywp_settings'
        );

        $this->add_performance_fields();

        // Advanced Settings Section
        add_settings_section(
            'reactifywp_advanced',
            __('Advanced Settings', 'reactifywp'),
            [$this, 'advanced_section_callback'],
            'reactifywp_settings'
        );

        $this->add_advanced_fields();
    }

    /**
     * Add general settings fields
     */
    private function add_general_fields()
    {
        add_settings_field(
            'max_upload_size',
            __('Maximum Upload Size', 'reactifywp'),
            [$this, 'text_field_callback'],
            'reactifywp_settings',
            'reactifywp_general',
            [
                'key' => 'general.max_upload_size',
                'description' => __('Maximum size for uploaded ZIP files (e.g., 50MB, 100MB).', 'reactifywp'),
                'placeholder' => '50MB'
            ]
        );

        add_settings_field(
            'enable_scoped_styles',
            __('Enable Scoped Styles', 'reactifywp'),
            [$this, 'checkbox_field_callback'],
            'reactifywp_settings',
            'reactifywp_general',
            [
                'key' => 'general.enable_scoped_styles',
                'description' => __('Wrap React apps in scoped containers to prevent style conflicts.', 'reactifywp')
            ]
        );

        add_settings_field(
            'enable_cache_busting',
            __('Enable Cache Busting', 'reactifywp'),
            [$this, 'checkbox_field_callback'],
            'reactifywp_settings',
            'reactifywp_general',
            [
                'key' => 'general.enable_cache_busting',
                'description' => __('Add version parameters to asset URLs for cache busting.', 'reactifywp')
            ]
        );

        add_settings_field(
            'defer_js_loading',
            __('Defer JavaScript Loading', 'reactifywp'),
            [$this, 'checkbox_field_callback'],
            'reactifywp_settings',
            'reactifywp_general',
            [
                'key' => 'general.defer_js_loading',
                'description' => __('Load JavaScript files with defer attribute for better performance.', 'reactifywp')
            ]
        );

        add_settings_field(
            'auto_cleanup_days',
            __('Auto Cleanup (Days)', 'reactifywp'),
            [$this, 'number_field_callback'],
            'reactifywp_settings',
            'reactifywp_general',
            [
                'key' => 'general.auto_cleanup_days',
                'description' => __('Automatically clean up old statistics and error logs after this many days.', 'reactifywp'),
                'min' => 1,
                'max' => 365
            ]
        );
    }

    /**
     * Add security settings fields
     */
    private function add_security_fields()
    {
        add_settings_field(
            'max_files_per_zip',
            __('Max Files Per ZIP', 'reactifywp'),
            [$this, 'number_field_callback'],
            'reactifywp_settings',
            'reactifywp_security',
            [
                'key' => 'security.max_files_per_zip',
                'description' => __('Maximum number of files allowed in a ZIP archive.', 'reactifywp'),
                'min' => 1,
                'max' => 10000
            ]
        );

        add_settings_field(
            'max_uncompressed_size',
            __('Max Uncompressed Size', 'reactifywp'),
            [$this, 'text_field_callback'],
            'reactifywp_settings',
            'reactifywp_security',
            [
                'key' => 'security.max_uncompressed_size',
                'description' => __('Maximum total size when ZIP is uncompressed (prevents ZIP bombs).', 'reactifywp'),
                'placeholder' => '500MB'
            ]
        );

        add_settings_field(
            'block_suspicious_files',
            __('Block Suspicious Files', 'reactifywp'),
            [$this, 'checkbox_field_callback'],
            'reactifywp_settings',
            'reactifywp_security',
            [
                'key' => 'security.block_suspicious_files',
                'description' => __('Block uploads containing executable files or scripts.', 'reactifywp')
            ]
        );

        add_settings_field(
            'enable_path_validation',
            __('Enable Path Validation', 'reactifywp'),
            [$this, 'checkbox_field_callback'],
            'reactifywp_settings',
            'reactifywp_security',
            [
                'key' => 'security.enable_path_validation',
                'description' => __('Validate file paths to prevent directory traversal attacks.', 'reactifywp')
            ]
        );
    }

    /**
     * Add performance settings fields
     */
    private function add_performance_fields()
    {
        add_settings_field(
            'enable_asset_minification',
            __('Enable Asset Minification', 'reactifywp'),
            [$this, 'checkbox_field_callback'],
            'reactifywp_settings',
            'reactifywp_performance',
            [
                'key' => 'performance.enable_asset_minification',
                'description' => __('Automatically minify CSS and JavaScript assets.', 'reactifywp')
            ]
        );

        add_settings_field(
            'enable_gzip_compression',
            __('Enable Gzip Compression', 'reactifywp'),
            [$this, 'checkbox_field_callback'],
            'reactifywp_settings',
            'reactifywp_performance',
            [
                'key' => 'performance.enable_gzip_compression',
                'description' => __('Compress assets with Gzip for faster loading.', 'reactifywp')
            ]
        );

        add_settings_field(
            'preload_critical_assets',
            __('Preload Critical Assets', 'reactifywp'),
            [$this, 'checkbox_field_callback'],
            'reactifywp_settings',
            'reactifywp_performance',
            [
                'key' => 'performance.preload_critical_assets',
                'description' => __('Preload critical CSS and JavaScript for faster rendering.', 'reactifywp')
            ]
        );

        add_settings_field(
            'enable_cdn_support',
            __('Enable CDN Support', 'reactifywp'),
            [$this, 'checkbox_field_callback'],
            'reactifywp_settings',
            'reactifywp_performance',
            [
                'key' => 'performance.enable_cdn_support',
                'description' => __('Serve assets from a Content Delivery Network.', 'reactifywp')
            ]
        );

        add_settings_field(
            'cdn_url',
            __('CDN URL', 'reactifywp'),
            [$this, 'url_field_callback'],
            'reactifywp_settings',
            'reactifywp_performance',
            [
                'key' => 'performance.cdn_url',
                'description' => __('Base URL for your CDN (e.g., https://cdn.example.com).', 'reactifywp'),
                'placeholder' => 'https://cdn.example.com'
            ]
        );
    }

    /**
     * Add advanced settings fields
     */
    private function add_advanced_fields()
    {
        add_settings_field(
            'enable_debug_mode',
            __('Enable Debug Mode', 'reactifywp'),
            [$this, 'checkbox_field_callback'],
            'reactifywp_settings',
            'reactifywp_advanced',
            [
                'key' => 'advanced.enable_debug_mode',
                'description' => __('Enable detailed logging and debugging information.', 'reactifywp')
            ]
        );

        add_settings_field(
            'log_level',
            __('Log Level', 'reactifywp'),
            [$this, 'select_field_callback'],
            'reactifywp_settings',
            'reactifywp_advanced',
            [
                'key' => 'advanced.log_level',
                'description' => __('Minimum level for logging messages.', 'reactifywp'),
                'options' => [
                    'error' => __('Error', 'reactifywp'),
                    'warning' => __('Warning', 'reactifywp'),
                    'info' => __('Info', 'reactifywp'),
                    'debug' => __('Debug', 'reactifywp')
                ]
            ]
        );

        add_settings_field(
            'custom_upload_path',
            __('Custom Upload Path', 'reactifywp'),
            [$this, 'text_field_callback'],
            'reactifywp_settings',
            'reactifywp_advanced',
            [
                'key' => 'advanced.custom_upload_path',
                'description' => __('Custom path for storing React projects (relative to uploads directory).', 'reactifywp'),
                'placeholder' => 'reactify-projects'
            ]
        );

        add_settings_field(
            'enable_api_access',
            __('Enable API Access', 'reactifywp'),
            [$this, 'checkbox_field_callback'],
            'reactifywp_settings',
            'reactifywp_advanced',
            [
                'key' => 'advanced.enable_api_access',
                'description' => __('Enable REST API endpoints for external integrations.', 'reactifywp')
            ]
        );
    }

    /**
     * Section callbacks
     */
    public function general_section_callback()
    {
        echo '<p>' . esc_html__('Configure general ReactifyWP settings.', 'reactifywp') . '</p>';
    }

    public function security_section_callback()
    {
        echo '<p>' . esc_html__('Security settings to protect your site from malicious uploads.', 'reactifywp') . '</p>';
    }

    public function performance_section_callback()
    {
        echo '<p>' . esc_html__('Performance optimization settings for faster loading.', 'reactifywp') . '</p>';
    }

    public function advanced_section_callback()
    {
        echo '<p>' . esc_html__('Advanced settings for developers and power users.', 'reactifywp') . '</p>';
    }

    /**
     * Field callbacks
     */
    public function text_field_callback($args)
    {
        $value = $this->get_setting($args['key']);
        $placeholder = $args['placeholder'] ?? '';
        $description = $args['description'] ?? '';

        printf(
            '<input type="text" name="%s[%s]" value="%s" placeholder="%s" class="regular-text" />',
            esc_attr(self::OPTION_KEY),
            esc_attr($args['key']),
            esc_attr($value),
            esc_attr($placeholder)
        );

        if ($description) {
            printf('<p class="description">%s</p>', esc_html($description));
        }
    }

    public function number_field_callback($args)
    {
        $value = $this->get_setting($args['key']);
        $min = $args['min'] ?? '';
        $max = $args['max'] ?? '';
        $description = $args['description'] ?? '';

        printf(
            '<input type="number" name="%s[%s]" value="%s" min="%s" max="%s" class="small-text" />',
            esc_attr(self::OPTION_KEY),
            esc_attr($args['key']),
            esc_attr($value),
            esc_attr($min),
            esc_attr($max)
        );

        if ($description) {
            printf('<p class="description">%s</p>', esc_html($description));
        }
    }

    public function checkbox_field_callback($args)
    {
        $value = $this->get_setting($args['key']);
        $description = $args['description'] ?? '';

        printf(
            '<input type="checkbox" name="%s[%s]" value="1" %s />',
            esc_attr(self::OPTION_KEY),
            esc_attr($args['key']),
            checked($value, true, false)
        );

        if ($description) {
            printf('<p class="description">%s</p>', esc_html($description));
        }
    }

    public function select_field_callback($args)
    {
        $value = $this->get_setting($args['key']);
        $options = $args['options'] ?? [];
        $description = $args['description'] ?? '';

        printf('<select name="%s[%s]">', esc_attr(self::OPTION_KEY), esc_attr($args['key']));

        foreach ($options as $option_value => $option_label) {
            printf(
                '<option value="%s" %s>%s</option>',
                esc_attr($option_value),
                selected($value, $option_value, false),
                esc_html($option_label)
            );
        }

        echo '</select>';

        if ($description) {
            printf('<p class="description">%s</p>', esc_html($description));
        }
    }

    public function url_field_callback($args)
    {
        $value = $this->get_setting($args['key']);
        $placeholder = $args['placeholder'] ?? '';
        $description = $args['description'] ?? '';

        printf(
            '<input type="url" name="%s[%s]" value="%s" placeholder="%s" class="regular-text" />',
            esc_attr(self::OPTION_KEY),
            esc_attr($args['key']),
            esc_attr($value),
            esc_attr($placeholder)
        );

        if ($description) {
            printf('<p class="description">%s</p>', esc_html($description));
        }
    }

    /**
     * Get setting value
     *
     * @param string $key Setting key (dot notation supported)
     * @return mixed Setting value
     */
    public function get_setting($key)
    {
        $settings = get_option(self::OPTION_KEY, self::DEFAULTS);

        if (strpos($key, '.') !== false) {
            $keys = explode('.', $key);
            $value = $settings;

            foreach ($keys as $k) {
                if (isset($value[$k])) {
                    $value = $value[$k];
                } else {
                    return $this->get_default_setting($key);
                }
            }

            return $value;
        }

        return $settings[$key] ?? $this->get_default_setting($key);
    }

    /**
     * Get default setting value
     *
     * @param string $key Setting key
     * @return mixed Default value
     */
    private function get_default_setting($key)
    {
        if (strpos($key, '.') !== false) {
            $keys = explode('.', $key);
            $value = self::DEFAULTS;

            foreach ($keys as $k) {
                if (isset($value[$k])) {
                    $value = $value[$k];
                } else {
                    return null;
                }
            }

            return $value;
        }

        return self::DEFAULTS[$key] ?? null;
    }

    /**
     * Sanitize settings
     *
     * @param array $input Raw input data
     * @return array Sanitized settings
     */
    public function sanitize_settings($input)
    {
        $sanitized = [];

        // Sanitize general settings
        if (isset($input['general.max_upload_size'])) {
            $sanitized['general']['max_upload_size'] = $this->sanitize_file_size($input['general.max_upload_size']);
        }

        $sanitized['general']['enable_scoped_styles'] = isset($input['general.enable_scoped_styles']);
        $sanitized['general']['enable_cache_busting'] = isset($input['general.enable_cache_busting']);
        $sanitized['general']['defer_js_loading'] = isset($input['general.defer_js_loading']);
        $sanitized['general']['enable_error_logging'] = isset($input['general.enable_error_logging']);

        if (isset($input['general.auto_cleanup_days'])) {
            $sanitized['general']['auto_cleanup_days'] = max(1, min(365, intval($input['general.auto_cleanup_days'])));
        }

        // Sanitize security settings
        if (isset($input['security.max_files_per_zip'])) {
            $sanitized['security']['max_files_per_zip'] = max(1, min(10000, intval($input['security.max_files_per_zip'])));
        }

        if (isset($input['security.max_uncompressed_size'])) {
            $sanitized['security']['max_uncompressed_size'] = $this->sanitize_file_size($input['security.max_uncompressed_size']);
        }

        $sanitized['security']['enable_path_validation'] = isset($input['security.enable_path_validation']);
        $sanitized['security']['block_suspicious_files'] = isset($input['security.block_suspicious_files']);
        $sanitized['security']['enable_virus_scanning'] = isset($input['security.enable_virus_scanning']);

        // Sanitize performance settings
        $sanitized['performance']['enable_asset_minification'] = isset($input['performance.enable_asset_minification']);
        $sanitized['performance']['enable_gzip_compression'] = isset($input['performance.enable_gzip_compression']);
        $sanitized['performance']['enable_browser_caching'] = isset($input['performance.enable_browser_caching']);
        $sanitized['performance']['preload_critical_assets'] = isset($input['performance.preload_critical_assets']);
        $sanitized['performance']['lazy_load_non_critical'] = isset($input['performance.lazy_load_non_critical']);
        $sanitized['performance']['enable_cdn_support'] = isset($input['performance.enable_cdn_support']);

        if (isset($input['performance.cdn_url'])) {
            $sanitized['performance']['cdn_url'] = esc_url_raw($input['performance.cdn_url']);
        }

        // Sanitize advanced settings
        $sanitized['advanced']['enable_debug_mode'] = isset($input['advanced.enable_debug_mode']);
        $sanitized['advanced']['enable_multisite_sync'] = isset($input['advanced.enable_multisite_sync']);
        $sanitized['advanced']['enable_api_access'] = isset($input['advanced.enable_api_access']);

        if (isset($input['advanced.log_level'])) {
            $valid_levels = ['error', 'warning', 'info', 'debug'];
            $sanitized['advanced']['log_level'] = in_array($input['advanced.log_level'], $valid_levels, true)
                ? $input['advanced.log_level']
                : 'error';
        }

        if (isset($input['advanced.custom_upload_path'])) {
            $sanitized['advanced']['custom_upload_path'] = sanitize_text_field($input['advanced.custom_upload_path']);
        }

        if (isset($input['advanced.api_rate_limit'])) {
            $sanitized['advanced']['api_rate_limit'] = max(1, min(1000, intval($input['advanced.api_rate_limit'])));
        }

        return array_merge($this->get_all_settings(), $sanitized);
    }

    /**
     * Get all settings
     *
     * @return array All settings
     */
    public function get_all_settings()
    {
        return get_option(self::OPTION_KEY, self::DEFAULTS);
    }

    /**
     * Sanitize file size
     *
     * @param string $size File size string
     * @return string Sanitized file size
     */
    private function sanitize_file_size($size)
    {
        $size = trim($size);

        if (preg_match('/^(\d+)\s*(B|KB|MB|GB)$/i', $size, $matches)) {
            $value = intval($matches[1]);
            $unit = strtoupper($matches[2]);

            // Ensure reasonable limits
            switch ($unit) {
                case 'GB':
                    $value = min($value, 10); // Max 10GB
                    break;
                case 'MB':
                    $value = min($value, 1024); // Max 1024MB
                    break;
                case 'KB':
                    $value = min($value, 1048576); // Max 1048576KB
                    break;
                default:
                    $value = min($value, 1073741824); // Max 1GB in bytes
            }

            return $value . $unit;
        }

        return '50MB'; // Default fallback
    }

    /**
     * Handle export settings AJAX request
     */
    public function handle_export_settings()
    {
        check_ajax_referer('reactifywp_admin', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions.', 'reactifywp'));
        }

        $settings = $this->get_all_settings();
        $export_data = [
            'version' => REACTIFYWP_VERSION,
            'timestamp' => current_time('mysql'),
            'site_url' => home_url(),
            'settings' => $settings
        ];

        $filename = 'reactifywp-settings-' . date('Y-m-d-H-i-s') . '.json';

        header('Content-Type: application/json');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: no-cache, must-revalidate');
        header('Expires: Sat, 26 Jul 1997 05:00:00 GMT');

        echo wp_json_encode($export_data, JSON_PRETTY_PRINT);
        exit;
    }

    /**
     * Handle import settings AJAX request
     */
    public function handle_import_settings()
    {
        check_ajax_referer('reactifywp_admin', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions.', 'reactifywp'));
        }

        if (empty($_FILES['settings_file'])) {
            wp_send_json_error(__('No file uploaded.', 'reactifywp'));
        }

        $file = $_FILES['settings_file'];

        if ($file['error'] !== UPLOAD_ERR_OK) {
            wp_send_json_error(__('File upload error.', 'reactifywp'));
        }

        $content = file_get_contents($file['tmp_name']);
        $data = json_decode($content, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            wp_send_json_error(__('Invalid JSON file.', 'reactifywp'));
        }

        if (!isset($data['settings']) || !is_array($data['settings'])) {
            wp_send_json_error(__('Invalid settings file format.', 'reactifywp'));
        }

        // Validate and sanitize imported settings
        $sanitized_settings = $this->sanitize_settings($this->flatten_settings($data['settings']));

        update_option(self::OPTION_KEY, $sanitized_settings);

        wp_send_json_success([
            'message' => __('Settings imported successfully!', 'reactifywp')
        ]);
    }

    /**
     * Handle reset settings AJAX request
     */
    public function handle_reset_settings()
    {
        check_ajax_referer('reactifywp_admin', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions.', 'reactifywp'));
        }

        delete_option(self::OPTION_KEY);

        wp_send_json_success([
            'message' => __('Settings reset to defaults successfully!', 'reactifywp')
        ]);
    }

    /**
     * Handle validate setting AJAX request
     */
    public function handle_validate_setting()
    {
        check_ajax_referer('reactifywp_admin', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions.', 'reactifywp'));
        }

        $key = sanitize_text_field($_POST['key'] ?? '');
        $value = $_POST['value'] ?? '';

        $validation_result = $this->validate_setting($key, $value);

        if (is_wp_error($validation_result)) {
            wp_send_json_error($validation_result->get_error_message());
        }

        wp_send_json_success([
            'message' => __('Setting is valid.', 'reactifywp'),
            'sanitized_value' => $validation_result
        ]);
    }

    /**
     * Validate individual setting
     *
     * @param string $key   Setting key
     * @param mixed  $value Setting value
     * @return mixed|\WP_Error Sanitized value or error
     */
    private function validate_setting($key, $value)
    {
        switch ($key) {
            case 'general.max_upload_size':
            case 'security.max_uncompressed_size':
                if (!preg_match('/^\d+\s*(B|KB|MB|GB)$/i', $value)) {
                    return new \WP_Error('invalid_size', __('Invalid file size format. Use format like "50MB".', 'reactifywp'));
                }
                return $this->sanitize_file_size($value);

            case 'performance.cdn_url':
                if (!empty($value) && !filter_var($value, FILTER_VALIDATE_URL)) {
                    return new \WP_Error('invalid_url', __('Invalid URL format.', 'reactifywp'));
                }
                return esc_url_raw($value);

            case 'general.auto_cleanup_days':
            case 'security.max_files_per_zip':
            case 'advanced.api_rate_limit':
                $int_value = intval($value);
                if ($int_value < 1) {
                    return new \WP_Error('invalid_number', __('Value must be greater than 0.', 'reactifywp'));
                }
                return $int_value;

            default:
                return sanitize_text_field($value);
        }
    }

    /**
     * Flatten nested settings array for form processing
     *
     * @param array $settings Nested settings array
     * @return array Flattened settings array
     */
    private function flatten_settings($settings)
    {
        $flattened = [];

        foreach ($settings as $section => $section_settings) {
            if (is_array($section_settings)) {
                foreach ($section_settings as $key => $value) {
                    $flattened[$section . '.' . $key] = $value;
                }
            }
        }

        return $flattened;
    }

    /**
     * Get environment-specific settings
     *
     * @return array Environment settings
     */
    public function get_environment_settings()
    {
        $environment = wp_get_environment_type();

        $env_settings = [
            'development' => [
                'advanced.enable_debug_mode' => true,
                'advanced.log_level' => 'debug',
                'general.enable_error_logging' => true
            ],
            'staging' => [
                'advanced.enable_debug_mode' => true,
                'advanced.log_level' => 'info',
                'general.enable_error_logging' => true
            ],
            'production' => [
                'advanced.enable_debug_mode' => false,
                'advanced.log_level' => 'error',
                'performance.enable_gzip_compression' => true,
                'performance.enable_browser_caching' => true
            ]
        ];

        return $env_settings[$environment] ?? [];
    }

    /**
     * Apply environment-specific settings
     */
    public function apply_environment_settings()
    {
        $env_settings = $this->get_environment_settings();

        if (empty($env_settings)) {
            return;
        }

        $current_settings = $this->get_all_settings();
        $updated = false;

        foreach ($env_settings as $key => $value) {
            if ($this->get_setting($key) !== $value) {
                $this->set_setting($key, $value);
                $updated = true;
            }
        }

        if ($updated) {
            update_option(self::OPTION_KEY, $current_settings);
        }
    }

    /**
     * Set individual setting value
     *
     * @param string $key   Setting key
     * @param mixed  $value Setting value
     */
    private function set_setting($key, $value)
    {
        $settings = $this->get_all_settings();

        if (strpos($key, '.') !== false) {
            $keys = explode('.', $key);
            $current = &$settings;

            foreach ($keys as $k) {
                if (!isset($current[$k])) {
                    $current[$k] = [];
                }
                $current = &$current[$k];
            }

            $current = $value;
        } else {
            $settings[$key] = $value;
        }

        update_option(self::OPTION_KEY, $settings);
    }
}
