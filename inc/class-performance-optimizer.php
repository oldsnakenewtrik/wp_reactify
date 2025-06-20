<?php
/**
 * Performance Optimizer for ReactifyWP
 *
 * @package ReactifyWP
 */

namespace ReactifyWP;

/**
 * Performance Optimizer class
 */
class PerformanceOptimizer
{
    /**
     * Cache groups
     */
    const CACHE_GROUPS = [
        'assets' => 'reactifywp_assets',
        'manifests' => 'reactifywp_manifests',
        'projects' => 'reactifywp_projects',
        'templates' => 'reactifywp_templates',
        'api' => 'reactifywp_api'
    ];

    /**
     * Cache expiration times (in seconds)
     */
    const CACHE_EXPIRATION = [
        'assets' => 3600,      // 1 hour
        'manifests' => 1800,   // 30 minutes
        'projects' => 900,     // 15 minutes
        'templates' => 7200,   // 2 hours
        'api' => 300           // 5 minutes
    ];

    /**
     * Performance metrics
     *
     * @var array
     */
    private $metrics = [];

    /**
     * Constructor
     */
    public function __construct()
    {
        add_action('init', [$this, 'init_performance_features']);
        add_action('wp_enqueue_scripts', [$this, 'optimize_asset_loading'], 5);
        add_action('wp_head', [$this, 'add_performance_hints'], 1);
        add_action('wp_footer', [$this, 'add_performance_monitoring'], 999);
        add_action('template_redirect', [$this, 'handle_asset_requests']);
        
        // Cache management
        add_action('reactifywp_clear_cache', [$this, 'clear_all_cache']);
        add_action('reactifywp_project_updated', [$this, 'clear_project_cache']);
        add_action('wp_ajax_reactifywp_clear_cache', [$this, 'handle_clear_cache_ajax']);
        
        // Performance monitoring
        add_action('wp_ajax_reactifywp_get_performance_metrics', [$this, 'handle_get_metrics_ajax']);
        add_action('wp_ajax_nopriv_reactifywp_track_performance', [$this, 'handle_track_performance_ajax']);
        
        // Cleanup
        add_action('wp_scheduled_delete', [$this, 'cleanup_expired_cache']);
    }

    /**
     * Initialize performance features
     */
    public function init_performance_features()
    {
        // Set up object cache groups
        foreach (self::CACHE_GROUPS as $group) {
            wp_cache_add_global_groups($group);
        }

        // Initialize performance monitoring
        $this->init_performance_monitoring();

        // Set up CDN support
        $this->init_cdn_support();

        // Configure asset optimization
        $this->init_asset_optimization();
    }

    /**
     * Initialize performance monitoring
     */
    private function init_performance_monitoring()
    {
        if (!$this->is_performance_monitoring_enabled()) {
            return;
        }

        // Start timing
        $this->metrics['start_time'] = microtime(true);
        $this->metrics['memory_start'] = memory_get_usage();

        // Track database queries
        if (defined('SAVEQUERIES') && SAVEQUERIES) {
            $this->metrics['queries_start'] = get_num_queries();
        }
    }

    /**
     * Initialize CDN support
     */
    private function init_cdn_support()
    {
        $settings = get_option('reactifywp_settings', []);
        $cdn_url = $settings['performance']['cdn_url'] ?? '';

        if (!empty($cdn_url)) {
            add_filter('reactifywp_asset_url', [$this, 'apply_cdn_url'], 10, 2);
        }
    }

    /**
     * Initialize asset optimization
     */
    private function init_asset_optimization()
    {
        $settings = get_option('reactifywp_settings', []);
        
        if ($settings['performance']['enable_minification'] ?? false) {
            add_filter('reactifywp_asset_content', [$this, 'minify_asset'], 10, 2);
        }

        if ($settings['performance']['enable_compression'] ?? false) {
            add_filter('reactifywp_asset_headers', [$this, 'add_compression_headers']);
        }

        if ($settings['performance']['enable_preloading'] ?? false) {
            add_action('wp_head', [$this, 'add_preload_hints'], 2);
        }
    }

    /**
     * Optimize asset loading
     */
    public function optimize_asset_loading()
    {
        // Defer non-critical scripts
        add_filter('script_loader_tag', [$this, 'defer_non_critical_scripts'], 10, 3);

        // Preload critical assets
        $this->preload_critical_assets();

        // Lazy load non-critical assets
        $this->setup_lazy_loading();
    }

    /**
     * Add performance hints to head
     */
    public function add_performance_hints()
    {
        $settings = get_option('reactifywp_settings', []);
        
        // DNS prefetch
        if ($settings['performance']['enable_dns_prefetch'] ?? true) {
            $this->add_dns_prefetch_hints();
        }

        // Preconnect to external domains
        if ($settings['performance']['enable_preconnect'] ?? true) {
            $this->add_preconnect_hints();
        }

        // Resource hints
        $this->add_resource_hints();
    }

    /**
     * Add performance monitoring script
     */
    public function add_performance_monitoring()
    {
        if (!$this->is_performance_monitoring_enabled()) {
            return;
        }

        $this->calculate_final_metrics();
        $this->output_performance_script();
    }

    /**
     * Handle asset requests with caching
     */
    public function handle_asset_requests()
    {
        if (!$this->is_asset_request()) {
            return;
        }

        $asset_path = $this->get_requested_asset_path();
        $cached_asset = $this->get_cached_asset($asset_path);

        if ($cached_asset) {
            $this->serve_cached_asset($cached_asset);
            exit;
        }

        // Process and cache asset
        $asset_content = $this->process_asset($asset_path);
        if ($asset_content) {
            $this->cache_asset($asset_path, $asset_content);
            $this->serve_asset($asset_content, $asset_path);
            exit;
        }
    }

    /**
     * Get cached asset
     *
     * @param string $asset_path Asset path
     * @return array|false Cached asset data or false
     */
    public function get_cached_asset($asset_path)
    {
        $cache_key = $this->get_asset_cache_key($asset_path);
        return wp_cache_get($cache_key, self::CACHE_GROUPS['assets']);
    }

    /**
     * Cache asset
     *
     * @param string $asset_path Asset path
     * @param array  $asset_data Asset data
     */
    public function cache_asset($asset_path, $asset_data)
    {
        $cache_key = $this->get_asset_cache_key($asset_path);
        wp_cache_set(
            $cache_key,
            $asset_data,
            self::CACHE_GROUPS['assets'],
            self::CACHE_EXPIRATION['assets']
        );
    }

    /**
     * Get project manifest with caching
     *
     * @param int $project_id Project ID
     * @return array|false Manifest data or false
     */
    public function get_cached_manifest($project_id)
    {
        $cache_key = "manifest_{$project_id}";
        $manifest = wp_cache_get($cache_key, self::CACHE_GROUPS['manifests']);

        if ($manifest === false) {
            $asset_manager = new AssetManager();
            $manifest = $asset_manager->get_project_manifest($project_id);
            
            if ($manifest) {
                wp_cache_set(
                    $cache_key,
                    $manifest,
                    self::CACHE_GROUPS['manifests'],
                    self::CACHE_EXPIRATION['manifests']
                );
            }
        }

        return $manifest;
    }

    /**
     * Get cached project data
     *
     * @param string $project_slug Project slug
     * @return object|false Project data or false
     */
    public function get_cached_project($project_slug)
    {
        $cache_key = "project_{$project_slug}";
        $project = wp_cache_get($cache_key, self::CACHE_GROUPS['projects']);

        if ($project === false) {
            $project_manager = new Project();
            $project = $project_manager->get_by_slug($project_slug);
            
            if ($project) {
                wp_cache_set(
                    $cache_key,
                    $project,
                    self::CACHE_GROUPS['projects'],
                    self::CACHE_EXPIRATION['projects']
                );
            }
        }

        return $project;
    }

    /**
     * Cache API response
     *
     * @param string $endpoint API endpoint
     * @param array  $params   Request parameters
     * @param mixed  $response Response data
     */
    public function cache_api_response($endpoint, $params, $response)
    {
        $cache_key = $this->get_api_cache_key($endpoint, $params);
        wp_cache_set(
            $cache_key,
            $response,
            self::CACHE_GROUPS['api'],
            self::CACHE_EXPIRATION['api']
        );
    }

    /**
     * Get cached API response
     *
     * @param string $endpoint API endpoint
     * @param array  $params   Request parameters
     * @return mixed|false Cached response or false
     */
    public function get_cached_api_response($endpoint, $params)
    {
        $cache_key = $this->get_api_cache_key($endpoint, $params);
        return wp_cache_get($cache_key, self::CACHE_GROUPS['api']);
    }

    /**
     * Apply CDN URL to assets
     *
     * @param string $url       Original URL
     * @param string $asset_path Asset path
     * @return string Modified URL
     */
    public function apply_cdn_url($url, $asset_path)
    {
        $settings = get_option('reactifywp_settings', []);
        $cdn_url = $settings['performance']['cdn_url'] ?? '';

        if (empty($cdn_url)) {
            return $url;
        }

        // Only apply CDN to static assets
        $static_extensions = ['js', 'css', 'png', 'jpg', 'jpeg', 'gif', 'svg', 'woff', 'woff2'];
        $extension = pathinfo($asset_path, PATHINFO_EXTENSION);

        if (in_array(strtolower($extension), $static_extensions)) {
            $upload_dir = wp_upload_dir();
            $relative_path = str_replace($upload_dir['baseurl'], '', $url);
            return rtrim($cdn_url, '/') . $relative_path;
        }

        return $url;
    }

    /**
     * Minify asset content
     *
     * @param string $content    Asset content
     * @param string $asset_type Asset type (js, css)
     * @return string Minified content
     */
    public function minify_asset($content, $asset_type)
    {
        switch ($asset_type) {
            case 'js':
                return $this->minify_javascript($content);
            case 'css':
                return $this->minify_css($content);
            default:
                return $content;
        }
    }

    /**
     * Add compression headers
     *
     * @param array $headers Existing headers
     * @return array Modified headers
     */
    public function add_compression_headers($headers)
    {
        $headers['Content-Encoding'] = 'gzip';
        $headers['Vary'] = 'Accept-Encoding';
        return $headers;
    }

    /**
     * Defer non-critical scripts
     *
     * @param string $tag    Script tag
     * @param string $handle Script handle
     * @param string $src    Script source
     * @return string Modified script tag
     */
    public function defer_non_critical_scripts($tag, $handle, $src)
    {
        // Don't defer critical scripts
        $critical_scripts = [
            'jquery-core',
            'wp-polyfill',
            'reactifywp-critical'
        ];

        if (in_array($handle, $critical_scripts)) {
            return $tag;
        }

        // Don't defer scripts with dependencies on critical scripts
        if (strpos($handle, 'reactifywp-') === 0) {
            return str_replace('<script ', '<script defer ', $tag);
        }

        return $tag;
    }

    /**
     * Preload critical assets
     */
    private function preload_critical_assets()
    {
        $critical_assets = $this->get_critical_assets();
        
        foreach ($critical_assets as $asset) {
            $this->add_preload_link($asset['url'], $asset['type']);
        }
    }

    /**
     * Setup lazy loading for non-critical assets
     */
    private function setup_lazy_loading()
    {
        add_action('wp_footer', function() {
            echo '<script>
                document.addEventListener("DOMContentLoaded", function() {
                    if ("IntersectionObserver" in window) {
                        ReactifyWP.LazyLoader.init();
                    }
                });
            </script>';
        });
    }

    /**
     * Add DNS prefetch hints
     */
    private function add_dns_prefetch_hints()
    {
        $domains = $this->get_external_domains();
        
        foreach ($domains as $domain) {
            echo '<link rel="dns-prefetch" href="//' . esc_attr($domain) . '">' . "\n";
        }
    }

    /**
     * Add preconnect hints
     */
    private function add_preconnect_hints()
    {
        $settings = get_option('reactifywp_settings', []);
        $cdn_url = $settings['performance']['cdn_url'] ?? '';

        if (!empty($cdn_url)) {
            $parsed_url = parse_url($cdn_url);
            if ($parsed_url && isset($parsed_url['host'])) {
                echo '<link rel="preconnect" href="//' . esc_attr($parsed_url['host']) . '">' . "\n";
            }
        }

        // Preconnect to common external services
        $external_services = [
            'fonts.googleapis.com',
            'fonts.gstatic.com',
            'cdnjs.cloudflare.com'
        ];

        foreach ($external_services as $service) {
            echo '<link rel="preconnect" href="//' . esc_attr($service) . '">' . "\n";
        }
    }

    /**
     * Add resource hints
     */
    private function add_resource_hints()
    {
        // Add modulepreload for ES modules
        $es_modules = $this->get_es_modules();
        
        foreach ($es_modules as $module) {
            echo '<link rel="modulepreload" href="' . esc_url($module) . '">' . "\n";
        }
    }

    /**
     * Add preload hints
     */
    public function add_preload_hints()
    {
        $preload_assets = $this->get_preload_assets();
        
        foreach ($preload_assets as $asset) {
            $this->add_preload_link($asset['url'], $asset['type'], $asset['as'] ?? null);
        }
    }

    /**
     * Add preload link
     *
     * @param string $url  Asset URL
     * @param string $type Asset type
     * @param string $as   Resource type
     */
    private function add_preload_link($url, $type, $as = null)
    {
        $as_attr = $as ? ' as="' . esc_attr($as) . '"' : '';
        $type_attr = $type ? ' type="' . esc_attr($type) . '"' : '';
        
        echo '<link rel="preload" href="' . esc_url($url) . '"' . $as_attr . $type_attr . '>' . "\n";
    }

    /**
     * Clear all cache
     */
    public function clear_all_cache()
    {
        foreach (self::CACHE_GROUPS as $group) {
            wp_cache_flush_group($group);
        }

        // Clear file-based cache
        $this->clear_file_cache();

        do_action('reactifywp_cache_cleared');
    }

    /**
     * Clear project-specific cache
     *
     * @param int $project_id Project ID
     */
    public function clear_project_cache($project_id)
    {
        // Clear manifest cache
        $manifest_key = "manifest_{$project_id}";
        wp_cache_delete($manifest_key, self::CACHE_GROUPS['manifests']);

        // Clear project cache
        $project = new Project();
        $project_data = $project->get($project_id);
        if ($project_data) {
            $project_key = "project_{$project_data->slug}";
            wp_cache_delete($project_key, self::CACHE_GROUPS['projects']);
        }

        // Clear related asset cache
        $this->clear_project_asset_cache($project_id);

        do_action('reactifywp_project_cache_cleared', $project_id);
    }

    /**
     * Handle clear cache AJAX request
     */
    public function handle_clear_cache_ajax()
    {
        check_ajax_referer('reactifywp_admin', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions.', 'reactifywp'));
        }

        $cache_type = sanitize_text_field($_POST['cache_type'] ?? 'all');

        switch ($cache_type) {
            case 'all':
                $this->clear_all_cache();
                $message = __('All cache cleared successfully.', 'reactifywp');
                break;
            case 'assets':
                wp_cache_flush_group(self::CACHE_GROUPS['assets']);
                $message = __('Asset cache cleared successfully.', 'reactifywp');
                break;
            case 'projects':
                wp_cache_flush_group(self::CACHE_GROUPS['projects']);
                wp_cache_flush_group(self::CACHE_GROUPS['manifests']);
                $message = __('Project cache cleared successfully.', 'reactifywp');
                break;
            default:
                wp_send_json_error(__('Invalid cache type.', 'reactifywp'));
        }

        wp_send_json_success(['message' => $message]);
    }

    /**
     * Handle get performance metrics AJAX request
     */
    public function handle_get_metrics_ajax()
    {
        check_ajax_referer('reactifywp_admin', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions.', 'reactifywp'));
        }

        $metrics = $this->get_performance_metrics();
        wp_send_json_success($metrics);
    }

    /**
     * Handle track performance AJAX request
     */
    public function handle_track_performance_ajax()
    {
        $nonce = $_POST['nonce'] ?? '';
        if (!wp_verify_nonce($nonce, 'reactifywp_frontend')) {
            wp_send_json_error('Invalid nonce');
        }

        $metrics = $_POST['metrics'] ?? [];
        $this->track_frontend_performance($metrics);

        wp_send_json_success();
    }

    /**
     * Get performance metrics
     *
     * @return array Performance metrics
     */
    public function get_performance_metrics()
    {
        return [
            'cache_stats' => $this->get_cache_statistics(),
            'asset_stats' => $this->get_asset_statistics(),
            'performance_scores' => $this->get_performance_scores(),
            'optimization_suggestions' => $this->get_optimization_suggestions()
        ];
    }

    /**
     * Track frontend performance
     *
     * @param array $metrics Frontend metrics
     */
    private function track_frontend_performance($metrics)
    {
        $performance_data = [
            'timestamp' => current_time('mysql'),
            'url' => sanitize_url($metrics['url'] ?? ''),
            'user_agent' => sanitize_text_field($_SERVER['HTTP_USER_AGENT'] ?? ''),
            'metrics' => [
                'load_time' => floatval($metrics['loadTime'] ?? 0),
                'dom_ready' => floatval($metrics['domReady'] ?? 0),
                'first_paint' => floatval($metrics['firstPaint'] ?? 0),
                'first_contentful_paint' => floatval($metrics['firstContentfulPaint'] ?? 0),
                'largest_contentful_paint' => floatval($metrics['largestContentfulPaint'] ?? 0),
                'cumulative_layout_shift' => floatval($metrics['cumulativeLayoutShift'] ?? 0),
                'first_input_delay' => floatval($metrics['firstInputDelay'] ?? 0)
            ]
        ];

        // Store in database or send to analytics service
        $this->store_performance_data($performance_data);
    }

    /**
     * Calculate final metrics
     */
    private function calculate_final_metrics()
    {
        $this->metrics['end_time'] = microtime(true);
        $this->metrics['memory_end'] = memory_get_usage();
        $this->metrics['memory_peak'] = memory_get_peak_usage();

        $this->metrics['execution_time'] = $this->metrics['end_time'] - $this->metrics['start_time'];
        $this->metrics['memory_used'] = $this->metrics['memory_end'] - $this->metrics['memory_start'];

        if (defined('SAVEQUERIES') && SAVEQUERIES) {
            $this->metrics['queries_total'] = get_num_queries() - ($this->metrics['queries_start'] ?? 0);
        }
    }

    /**
     * Output performance monitoring script
     */
    private function output_performance_script()
    {
        $settings = get_option('reactifywp_settings', []);

        if (!($settings['performance']['enable_frontend_monitoring'] ?? false)) {
            return;
        }

        ?>
        <script>
        (function() {
            'use strict';

            // Performance monitoring
            window.ReactifyWP = window.ReactifyWP || {};
            window.ReactifyWP.Performance = {
                metrics: <?php echo wp_json_encode($this->metrics); ?>,

                track: function(eventName, data) {
                    if (!window.performance || !window.performance.mark) {
                        return;
                    }

                    window.performance.mark('reactifywp-' + eventName);

                    if (data) {
                        this.sendMetrics(eventName, data);
                    }
                },

                sendMetrics: function(eventName, data) {
                    if (!navigator.sendBeacon) {
                        return;
                    }

                    var payload = new FormData();
                    payload.append('action', 'reactifywp_track_performance');
                    payload.append('nonce', '<?php echo wp_create_nonce('reactifywp_frontend'); ?>');
                    payload.append('event', eventName);
                    payload.append('metrics', JSON.stringify(data));

                    navigator.sendBeacon('<?php echo admin_url('admin-ajax.php'); ?>', payload);
                },

                measureWebVitals: function() {
                    if (!window.performance || !window.PerformanceObserver) {
                        return;
                    }

                    var metrics = {};

                    // Largest Contentful Paint
                    new PerformanceObserver(function(list) {
                        var entries = list.getEntries();
                        var lastEntry = entries[entries.length - 1];
                        metrics.largestContentfulPaint = lastEntry.startTime;
                    }).observe({entryTypes: ['largest-contentful-paint']});

                    // First Input Delay
                    new PerformanceObserver(function(list) {
                        var entries = list.getEntries();
                        entries.forEach(function(entry) {
                            metrics.firstInputDelay = entry.processingStart - entry.startTime;
                        });
                    }).observe({entryTypes: ['first-input']});

                    // Cumulative Layout Shift
                    var clsValue = 0;
                    new PerformanceObserver(function(list) {
                        list.getEntries().forEach(function(entry) {
                            if (!entry.hadRecentInput) {
                                clsValue += entry.value;
                                metrics.cumulativeLayoutShift = clsValue;
                            }
                        });
                    }).observe({entryTypes: ['layout-shift']});

                    // Send metrics after page load
                    window.addEventListener('load', function() {
                        setTimeout(function() {
                            metrics.url = window.location.href;
                            metrics.loadTime = window.performance.timing.loadEventEnd - window.performance.timing.navigationStart;
                            metrics.domReady = window.performance.timing.domContentLoadedEventEnd - window.performance.timing.navigationStart;

                            if (window.performance.getEntriesByType) {
                                var paintEntries = window.performance.getEntriesByType('paint');
                                paintEntries.forEach(function(entry) {
                                    if (entry.name === 'first-paint') {
                                        metrics.firstPaint = entry.startTime;
                                    } else if (entry.name === 'first-contentful-paint') {
                                        metrics.firstContentfulPaint = entry.startTime;
                                    }
                                });
                            }

                            ReactifyWP.Performance.sendMetrics('page-load', metrics);
                        }, 1000);
                    });
                }
            };

            // Initialize Web Vitals measurement
            ReactifyWP.Performance.measureWebVitals();

        })();
        </script>
        <?php
    }

    /**
     * Get cache statistics
     *
     * @return array Cache statistics
     */
    private function get_cache_statistics()
    {
        $stats = [];

        foreach (self::CACHE_GROUPS as $name => $group) {
            $stats[$name] = [
                'hits' => wp_cache_get_stats($group)['hits'] ?? 0,
                'misses' => wp_cache_get_stats($group)['misses'] ?? 0,
                'size' => $this->get_cache_group_size($group)
            ];
        }

        return $stats;
    }

    /**
     * Get asset statistics
     *
     * @return array Asset statistics
     */
    private function get_asset_statistics()
    {
        $upload_dir = wp_upload_dir();
        $reactify_dir = $upload_dir['basedir'] . '/reactify-projects';

        if (!is_dir($reactify_dir)) {
            return [
                'total_size' => 0,
                'file_count' => 0,
                'projects' => 0
            ];
        }

        $total_size = 0;
        $file_count = 0;
        $projects = 0;

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($reactify_dir, \RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $total_size += $file->getSize();
                $file_count++;
            } elseif ($file->isDir() && $iterator->getDepth() === 1) {
                $projects++;
            }
        }

        return [
            'total_size' => $total_size,
            'file_count' => $file_count,
            'projects' => $projects,
            'formatted_size' => size_format($total_size)
        ];
    }

    /**
     * Get performance scores
     *
     * @return array Performance scores
     */
    private function get_performance_scores()
    {
        // This would integrate with actual performance monitoring
        // For now, return mock data
        return [
            'page_speed' => 85,
            'cache_efficiency' => 92,
            'asset_optimization' => 78,
            'overall_score' => 85
        ];
    }

    /**
     * Get optimization suggestions
     *
     * @return array Optimization suggestions
     */
    private function get_optimization_suggestions()
    {
        $suggestions = [];
        $settings = get_option('reactifywp_settings', []);

        // Check CDN
        if (empty($settings['performance']['cdn_url'])) {
            $suggestions[] = [
                'type' => 'cdn',
                'priority' => 'high',
                'title' => __('Enable CDN', 'reactifywp'),
                'description' => __('Configure a CDN to improve asset loading speed globally.', 'reactifywp')
            ];
        }

        // Check caching
        if (!($settings['performance']['enable_caching'] ?? true)) {
            $suggestions[] = [
                'type' => 'caching',
                'priority' => 'high',
                'title' => __('Enable Caching', 'reactifywp'),
                'description' => __('Enable caching to reduce server load and improve response times.', 'reactifywp')
            ];
        }

        // Check minification
        if (!($settings['performance']['enable_minification'] ?? false)) {
            $suggestions[] = [
                'type' => 'minification',
                'priority' => 'medium',
                'title' => __('Enable Minification', 'reactifywp'),
                'description' => __('Minify CSS and JavaScript files to reduce file sizes.', 'reactifywp')
            ];
        }

        // Check preloading
        if (!($settings['performance']['enable_preloading'] ?? false)) {
            $suggestions[] = [
                'type' => 'preloading',
                'priority' => 'medium',
                'title' => __('Enable Preloading', 'reactifywp'),
                'description' => __('Preload critical assets to improve perceived performance.', 'reactifywp')
            ];
        }

        return $suggestions;
    }

    /**
     * Utility methods
     */

    /**
     * Check if performance monitoring is enabled
     *
     * @return bool Is enabled
     */
    private function is_performance_monitoring_enabled()
    {
        $settings = get_option('reactifywp_settings', []);
        return $settings['performance']['enable_monitoring'] ?? false;
    }

    /**
     * Check if current request is for an asset
     *
     * @return bool Is asset request
     */
    private function is_asset_request()
    {
        $request_uri = $_SERVER['REQUEST_URI'] ?? '';
        return strpos($request_uri, '/reactify-assets/') !== false;
    }

    /**
     * Get requested asset path
     *
     * @return string Asset path
     */
    private function get_requested_asset_path()
    {
        $request_uri = $_SERVER['REQUEST_URI'] ?? '';
        $path = parse_url($request_uri, PHP_URL_PATH);
        return ltrim($path, '/');
    }

    /**
     * Get asset cache key
     *
     * @param string $asset_path Asset path
     * @return string Cache key
     */
    private function get_asset_cache_key($asset_path)
    {
        return 'asset_' . md5($asset_path);
    }

    /**
     * Get API cache key
     *
     * @param string $endpoint API endpoint
     * @param array  $params   Request parameters
     * @return string Cache key
     */
    private function get_api_cache_key($endpoint, $params)
    {
        return 'api_' . md5($endpoint . serialize($params));
    }

    /**
     * Process asset for optimization
     *
     * @param string $asset_path Asset path
     * @return array|false Processed asset data or false
     */
    private function process_asset($asset_path)
    {
        // Implementation would process and optimize the asset
        return false;
    }

    /**
     * Serve cached asset
     *
     * @param array $cached_asset Cached asset data
     */
    private function serve_cached_asset($cached_asset)
    {
        // Set headers
        foreach ($cached_asset['headers'] as $header => $value) {
            header($header . ': ' . $value);
        }

        // Output content
        echo $cached_asset['content'];
    }

    /**
     * Serve asset
     *
     * @param array  $asset_content Asset content
     * @param string $asset_path    Asset path
     */
    private function serve_asset($asset_content, $asset_path)
    {
        // Set appropriate headers and serve content
        $mime_type = $this->get_mime_type($asset_path);
        header('Content-Type: ' . $mime_type);
        echo $asset_content['content'];
    }

    /**
     * Get MIME type for asset
     *
     * @param string $asset_path Asset path
     * @return string MIME type
     */
    private function get_mime_type($asset_path)
    {
        $extension = pathinfo($asset_path, PATHINFO_EXTENSION);

        $mime_types = [
            'js' => 'application/javascript',
            'css' => 'text/css',
            'json' => 'application/json',
            'png' => 'image/png',
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'gif' => 'image/gif',
            'svg' => 'image/svg+xml',
            'woff' => 'font/woff',
            'woff2' => 'font/woff2'
        ];

        return $mime_types[strtolower($extension)] ?? 'application/octet-stream';
    }

    /**
     * Get critical assets for preloading
     *
     * @return array Critical assets
     */
    private function get_critical_assets()
    {
        // Implementation would determine critical assets
        return [];
    }

    /**
     * Get external domains for DNS prefetch
     *
     * @return array External domains
     */
    private function get_external_domains()
    {
        $domains = [];
        $settings = get_option('reactifywp_settings', []);

        // Add CDN domain
        $cdn_url = $settings['performance']['cdn_url'] ?? '';
        if (!empty($cdn_url)) {
            $parsed = parse_url($cdn_url);
            if ($parsed && isset($parsed['host'])) {
                $domains[] = $parsed['host'];
            }
        }

        return array_unique($domains);
    }

    /**
     * Get ES modules for modulepreload
     *
     * @return array ES modules
     */
    private function get_es_modules()
    {
        // Implementation would identify ES modules
        return [];
    }

    /**
     * Get assets for preloading
     *
     * @return array Preload assets
     */
    private function get_preload_assets()
    {
        // Implementation would determine preload assets
        return [];
    }

    /**
     * Minify JavaScript
     *
     * @param string $content JavaScript content
     * @return string Minified content
     */
    private function minify_javascript($content)
    {
        // Basic minification - remove comments and extra whitespace
        $content = preg_replace('/\/\*[\s\S]*?\*\//', '', $content);
        $content = preg_replace('/\/\/.*$/', '', $content);
        $content = preg_replace('/\s+/', ' ', $content);
        return trim($content);
    }

    /**
     * Minify CSS
     *
     * @param string $content CSS content
     * @return string Minified content
     */
    private function minify_css($content)
    {
        // Basic minification
        $content = preg_replace('/\/\*[\s\S]*?\*\//', '', $content);
        $content = preg_replace('/\s+/', ' ', $content);
        $content = str_replace(['; ', ' {', '{ ', ' }', '} ', ': '], [';', '{', '{', '}', '}', ':'], $content);
        return trim($content);
    }

    /**
     * Clear file-based cache
     */
    private function clear_file_cache()
    {
        $upload_dir = wp_upload_dir();
        $cache_dir = $upload_dir['basedir'] . '/reactify-cache';

        if (is_dir($cache_dir)) {
            $this->delete_directory_recursive($cache_dir);
        }
    }

    /**
     * Clear project asset cache
     *
     * @param int $project_id Project ID
     */
    private function clear_project_asset_cache($project_id)
    {
        // Implementation would clear project-specific asset cache
    }

    /**
     * Get cache group size
     *
     * @param string $group Cache group
     * @return int Cache size in bytes
     */
    private function get_cache_group_size($group)
    {
        // Implementation would calculate cache group size
        return 0;
    }

    /**
     * Store performance data
     *
     * @param array $performance_data Performance data
     */
    private function store_performance_data($performance_data)
    {
        // Implementation would store performance data in database
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
     * Cleanup expired cache
     */
    public function cleanup_expired_cache()
    {
        // Implementation would clean up expired cache entries
        do_action('reactifywp_cache_cleanup');
    }
}
