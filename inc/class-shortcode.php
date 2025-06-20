<?php
/**
 * Shortcode functionality for ReactifyWP
 *
 * @package ReactifyWP
 */

namespace ReactifyWP;

/**
 * Shortcode class
 */
class Shortcode
{
    /**
     * Constructor
     */
    public function __construct()
    {
        add_shortcode('reactify', [$this, 'render_shortcode']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_frontend_scripts']);
    }

    /**
     * Render reactify shortcode
     *
     * @param array $atts Shortcode attributes
     * @return string Rendered output
     */
    public function render_shortcode($atts)
    {
        $atts = shortcode_atts([
            'slug' => '',
            'class' => '',
            'style' => '',
            'width' => '',
            'height' => '',
            'loading' => 'auto', // auto, lazy, eager
            'fallback' => '',
            'props' => '',
            'config' => '',
            'cache' => 'true',
            'debug' => 'false',
            'container_id' => '',
            'theme' => '',
            'responsive' => 'true',
            'preload' => 'auto',
            'error_boundary' => 'true',
            'ssr' => 'false'
        ], $atts, 'reactify');

        // Validate required attributes
        if (empty($atts['slug'])) {
            return $this->render_error(__('Project slug is required.', 'reactifywp'));
        }

        $slug = sanitize_text_field($atts['slug']);

        // Check conditional loading
        if (!$this->should_load_project($atts)) {
            return $this->render_placeholder($slug, $atts);
        }

        $project = new Project();
        $project_data = $project->get_by_slug($slug);

        if (!$project_data) {
            return $this->render_error(__('Project not found.', 'reactifywp'));
        }

        // Check project status
        if ($project_data->status !== 'active') {
            if (current_user_can('manage_options')) {
                return $this->render_error(sprintf(__('Project "%s" is not active.', 'reactifywp'), $project_data->project_name));
            }
            return ''; // Hide inactive projects from non-admins
        }

        // Generate unique container ID
        $container_id = $this->generate_container_id($slug, $atts);

        // Enqueue project assets based on loading strategy
        $this->enqueue_project_assets($project_data, $atts);

        // Generate container with enhanced features
        return $this->render_enhanced_container($slug, $project_data, $atts, $container_id);
    }

    /**
     * Enqueue frontend scripts
     */
    public function enqueue_frontend_scripts()
    {
        // Only enqueue on pages that have reactify shortcodes
        global $post;
        
        if (!$post || !has_shortcode($post->post_content, 'reactify')) {
            return;
        }

        wp_enqueue_style(
            'reactifywp-frontend',
            REACTIFYWP_PLUGIN_URL . 'assets/dist/frontend.css',
            [],
            REACTIFYWP_VERSION
        );

        wp_enqueue_script(
            'reactifywp-frontend',
            REACTIFYWP_PLUGIN_URL . 'assets/dist/frontend.js',
            [],
            REACTIFYWP_VERSION,
            true
        );

        wp_localize_script('reactifywp-frontend', 'reactifyWP', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('reactifywp_frontend'),
            'blogId' => get_current_blog_id(),
            'projects' => $this->get_page_projects($post)
        ]);
    }

    /**
     * Check if project should be loaded based on conditions
     *
     * @param array $atts Shortcode attributes
     * @return bool True if should load, false otherwise
     */
    private function should_load_project($atts)
    {
        // Check loading strategy
        if ($atts['loading'] === 'lazy') {
            // For lazy loading, we'll load via JavaScript
            return false;
        }

        // Check cache setting
        if ($atts['cache'] === 'false') {
            // Force fresh load
            return true;
        }

        // Check user capabilities for debug mode
        if ($atts['debug'] === 'true' && !current_user_can('manage_options')) {
            return false;
        }

        return true;
    }

    /**
     * Generate unique container ID
     *
     * @param string $slug Project slug
     * @param array  $atts Shortcode attributes
     * @return string Container ID
     */
    private function generate_container_id($slug, $atts)
    {
        if (!empty($atts['container_id'])) {
            return sanitize_html_class($atts['container_id']);
        }

        static $instance_counter = [];

        if (!isset($instance_counter[$slug])) {
            $instance_counter[$slug] = 0;
        }

        $instance_counter[$slug]++;

        return 'reactify-' . $slug . '-' . $instance_counter[$slug];
    }

    /**
     * Render placeholder for lazy loading
     *
     * @param string $slug Project slug
     * @param array  $atts Shortcode attributes
     * @return string Placeholder HTML
     */
    private function render_placeholder($slug, $atts)
    {
        $container_id = $this->generate_container_id($slug, $atts);
        $classes = ['reactify-placeholder', 'reactify-lazy'];

        if (!empty($atts['class'])) {
            $classes[] = sanitize_html_class($atts['class']);
        }

        $style_parts = [];
        if (!empty($atts['width'])) {
            $style_parts[] = 'width: ' . esc_attr($atts['width']);
        }
        if (!empty($atts['height'])) {
            $style_parts[] = 'height: ' . esc_attr($atts['height']);
        }
        if (!empty($atts['style'])) {
            $style_parts[] = esc_attr($atts['style']);
        }

        $style_attr = !empty($style_parts) ? ' style="' . implode('; ', $style_parts) . '"' : '';
        $class_attr = implode(' ', array_filter($classes));

        $placeholder_content = $atts['fallback'] ?: sprintf(
            '<div class="reactify-loading-indicator">
                <div class="reactify-spinner"></div>
                <p>%s</p>
            </div>',
            esc_html__('Loading React App...', 'reactifywp')
        );

        return sprintf(
            '<div class="%s" id="%s" data-reactify-slug="%s" data-reactify-lazy="true"%s>%s</div>',
            esc_attr($class_attr),
            esc_attr($container_id),
            esc_attr($slug),
            $style_attr,
            $placeholder_content
        );
    }

    /**
     * Enqueue project assets with enhanced loading strategies
     *
     * @param object $project_data Project data
     * @param array  $atts         Shortcode attributes
     */
    private function enqueue_project_assets($project_data, $atts = [])
    {
        $asset_manager = new AssetManager();
        $assets = $asset_manager->get_project_assets($project_data->id);

        if (empty($assets)) {
            return;
        }

        $settings = get_option('reactifywp_settings', []);
        $cache_busting = $settings['general']['enable_cache_busting'] ?? true;
        $defer_js = $settings['general']['defer_js_loading'] ?? true;
        $preload_critical = $settings['performance']['preload_critical_assets'] ?? true;

        $upload_dir = wp_upload_dir();
        $blog_id = get_current_blog_id();
        $base_url = $upload_dir['baseurl'] . '/reactify-projects/' . $blog_id . '/' . $project_data->slug;

        // Handle preloading strategy
        $preload_strategy = $atts['preload'] ?? 'auto';

        // Enqueue CSS files
        $css_assets = array_filter($assets, function ($asset) {
            return $asset['file_type'] === 'css';
        });

        foreach ($css_assets as $asset) {
            $handle = 'reactifywp-' . $project_data->slug . '-css-' . $asset['id'];
            $url = trailingslashit($base_url) . ltrim($asset['file_path'], '/');
            $version = $cache_busting ? $asset['file_hash'] : $project_data->version;

            // Determine loading strategy
            if ($preload_strategy === 'critical' && $asset['is_critical']) {
                $this->preload_asset($url, 'style');
            } elseif ($preload_strategy === 'all') {
                $this->preload_asset($url, 'style');
            }

            wp_enqueue_style($handle, $url, [], $version);

            // Add critical CSS inline for performance
            if ($asset['is_critical'] && $asset['file_size'] < 10240) {
                $this->maybe_inline_critical_css($handle, $project_data->file_path . '/' . $asset['file_path']);
            }
        }

        // Enqueue JS files
        $js_assets = array_filter($assets, function ($asset) {
            return $asset['file_type'] === 'js';
        });

        foreach ($js_assets as $asset) {
            $handle = 'reactifywp-' . $project_data->slug . '-js-' . $asset['id'];
            $url = trailingslashit($base_url) . ltrim($asset['file_path'], '/');
            $version = $cache_busting ? $asset['file_hash'] : $project_data->version;

            // Determine dependencies
            $deps = [];
            if (!empty($asset['dependencies'])) {
                $deps = json_decode($asset['dependencies'], true) ?: [];
            }

            // Handle preloading
            if ($preload_strategy === 'critical' && $asset['is_critical']) {
                $this->preload_asset($url, 'script');
            } elseif ($preload_strategy === 'all') {
                $this->preload_asset($url, 'script');
            }

            wp_enqueue_script($handle, $url, $deps, $version, true);

            // Add script attributes based on loading strategy
            $this->add_script_loading_attributes($handle, $asset, $atts);
        }

        // Add project configuration and mount script
        $this->add_enhanced_mount_script($project_data, $atts);
    }

    /**
     * Preload asset for performance
     *
     * @param string $url  Asset URL
     * @param string $type Asset type (script, style)
     */
    private function preload_asset($url, $type)
    {
        $as = $type === 'script' ? 'script' : 'style';

        add_action('wp_head', function () use ($url, $as) {
            printf(
                '<link rel="preload" href="%s" as="%s" crossorigin="anonymous">',
                esc_url($url),
                esc_attr($as)
            );
        }, 5);
    }

    /**
     * Maybe inline critical CSS
     *
     * @param string $handle    Style handle
     * @param string $file_path CSS file path
     */
    private function maybe_inline_critical_css($handle, $file_path)
    {
        if (file_exists($file_path)) {
            $css_content = file_get_contents($file_path);
            if ($css_content) {
                // Minify CSS for inline use
                $css_content = $this->minify_css($css_content);
                wp_add_inline_style($handle, $css_content);
                // Dequeue the external file since we're inlining it
                wp_dequeue_style($handle);
            }
        }
    }

    /**
     * Add script loading attributes
     *
     * @param string $handle Script handle
     * @param array  $asset  Asset data
     * @param array  $atts   Shortcode attributes
     */
    private function add_script_loading_attributes($handle, $asset, $atts)
    {
        $defer_js = get_option('reactifywp_settings')['general']['defer_js_loading'] ?? true;
        $loading_strategy = $atts['loading'] ?? 'auto';

        add_filter('script_loader_tag', function ($tag, $handle_filter, $src) use ($handle, $asset, $atts, $defer_js, $loading_strategy) {
            if ($handle !== $handle_filter) {
                return $tag;
            }

            $attributes = [];

            // Add defer/async based on strategy
            if ($loading_strategy === 'lazy' || (!$asset['is_critical'] && $defer_js)) {
                $attributes[] = 'defer';
            } elseif ($loading_strategy === 'eager' && $asset['is_critical']) {
                // No defer for critical eager loading
            } else {
                $attributes[] = 'defer';
            }

            // Add module type for ES modules
            if (strpos($asset['file_path'], '.mjs') !== false || strpos($asset['file_path'], 'module') !== false) {
                $attributes[] = 'type="module"';
            }

            // Add crossorigin for external scripts
            if (strpos($src, home_url()) === false) {
                $attributes[] = 'crossorigin="anonymous"';
            }

            // Add error handling
            $attributes[] = 'onerror="ReactifyWP.handleScriptError(this)"';

            if (!empty($attributes)) {
                $tag = str_replace('<script ', '<script ' . implode(' ', $attributes) . ' ', $tag);
            }

            return $tag;
        }, 10, 3);
    }

    /**
     * Add enhanced mount script with configuration
     *
     * @param object $project_data Project data
     * @param array  $atts         Shortcode attributes
     */
    private function add_enhanced_mount_script($project_data, $atts)
    {
        $container_id = $this->generate_container_id($project_data->slug, $atts);

        // Parse props and config
        $props = $this->parse_shortcode_props($atts['props'] ?? '');
        $config = $this->parse_shortcode_config($atts['config'] ?? '');

        // Build configuration object
        $app_config = [
            'slug' => $project_data->slug,
            'name' => $project_data->project_name,
            'version' => $project_data->version,
            'containerId' => $container_id,
            'props' => $props,
            'config' => $config,
            'theme' => $atts['theme'] ?? '',
            'responsive' => $atts['responsive'] === 'true',
            'debug' => $atts['debug'] === 'true' && current_user_can('manage_options'),
            'errorBoundary' => $atts['error_boundary'] === 'true',
            'ssr' => $atts['ssr'] === 'true',
            'loading' => $atts['loading'] ?? 'auto',
            'wordpress' => [
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('reactifywp_frontend'),
                'userId' => get_current_user_id(),
                'isAdmin' => current_user_can('manage_options'),
                'locale' => get_locale(),
                'homeUrl' => home_url(),
                'apiUrl' => rest_url('reactifywp/v1/')
            ]
        ];

        $script = sprintf(
            'window.ReactifyWP = window.ReactifyWP || {};
             window.ReactifyWP.apps = window.ReactifyWP.apps || {};
             window.ReactifyWP.apps["%s"] = %s;
             window.ReactifyWP.queue = window.ReactifyWP.queue || [];
             window.ReactifyWP.queue.push("%s");',
            esc_js($project_data->slug),
            wp_json_encode($app_config),
            esc_js($project_data->slug)
        );

        wp_add_inline_script('reactifywp-frontend', $script, 'before');
    }

    /**
     * Parse shortcode props parameter
     *
     * @param string $props_string Props string
     * @return array Parsed props
     */
    private function parse_shortcode_props($props_string)
    {
        if (empty($props_string)) {
            return [];
        }

        // Support JSON format: props='{"key": "value"}'
        if (strpos($props_string, '{') === 0) {
            $props = json_decode($props_string, true);
            return is_array($props) ? $props : [];
        }

        // Support query string format: props='key1=value1&key2=value2'
        parse_str($props_string, $props);
        return $props;
    }

    /**
     * Parse shortcode config parameter
     *
     * @param string $config_string Config string
     * @return array Parsed config
     */
    private function parse_shortcode_config($config_string)
    {
        if (empty($config_string)) {
            return [];
        }

        // Support JSON format
        if (strpos($config_string, '{') === 0) {
            $config = json_decode($config_string, true);
            return is_array($config) ? $config : [];
        }

        // Support simple key=value format
        parse_str($config_string, $config);
        return $config;
    }

    /**
     * Minify CSS content
     *
     * @param string $css CSS content
     * @return string Minified CSS
     */
    private function minify_css($css)
    {
        // Remove comments
        $css = preg_replace('!/\*[^*]*\*+([^/][^*]*\*+)*/!', '', $css);

        // Remove whitespace
        $css = str_replace(["\r\n", "\r", "\n", "\t", '  ', '    ', '    '], '', $css);

        // Remove unnecessary spaces
        $css = preg_replace('/\s+/', ' ', $css);
        $css = preg_replace('/;\s*}/', '}', $css);
        $css = preg_replace('/\s*{\s*/', '{', $css);
        $css = preg_replace('/;\s*/', ';', $css);
        $css = preg_replace('/:\s*/', ':', $css);

        return trim($css);
    }

    /**
     * Render enhanced container for React app
     *
     * @param string $slug         Project slug
     * @param object $project_data Project data
     * @param array  $atts         Shortcode attributes
     * @param string $container_id Container ID
     * @return string Container HTML
     */
    private function render_enhanced_container($slug, $project_data, $atts, $container_id)
    {
        $settings = get_option('reactifywp_settings', []);
        $scoped_styles = $settings['general']['enable_scoped_styles'] ?? true;

        $classes = ['reactify-app-container', 'reactify-app'];
        $data_attributes = [];

        // Add scoped styling classes
        if ($scoped_styles) {
            $classes[] = 'reactify-scoped';
            $classes[] = 'reactify-' . $slug;
        }

        // Add theme class
        if (!empty($atts['theme'])) {
            $classes[] = 'reactify-theme-' . sanitize_html_class($atts['theme']);
        }

        // Add responsive class
        if ($atts['responsive'] === 'true') {
            $classes[] = 'reactify-responsive';
        }

        // Add loading class
        $classes[] = 'reactify-loading-' . sanitize_html_class($atts['loading']);

        // Add custom class
        if (!empty($atts['class'])) {
            $classes[] = sanitize_html_class($atts['class']);
        }

        // Add debug class for admins
        if ($atts['debug'] === 'true' && current_user_can('manage_options')) {
            $classes[] = 'reactify-debug';
        }

        // Build style attribute
        $style_parts = [];
        if (!empty($atts['width'])) {
            $style_parts[] = 'width: ' . esc_attr($atts['width']);
        }
        if (!empty($atts['height'])) {
            $style_parts[] = 'height: ' . esc_attr($atts['height']);
        }
        if (!empty($atts['style'])) {
            $style_parts[] = esc_attr($atts['style']);
        }

        // Add data attributes for JavaScript
        $data_attributes['data-project'] = $slug;
        $data_attributes['data-config'] = wp_json_encode([
            'slug' => $slug,
            'version' => $project_data->version,
            'loading' => $atts['loading'],
            'debug' => $atts['debug'],
            'errorBoundary' => $atts['error_boundary'],
            'theme' => $atts['theme'],
            'responsive' => $atts['responsive']
        ]);
        $data_attributes['data-reactify-slug'] = $slug;
        $data_attributes['data-reactify-version'] = $project_data->version;
        $data_attributes['data-reactify-loading'] = $atts['loading'];
        $data_attributes['data-reactify-debug'] = $atts['debug'];
        $data_attributes['data-reactify-error-boundary'] = $atts['error_boundary'];

        // Build attributes
        $class_attr = implode(' ', array_filter($classes));
        $style_attr = !empty($style_parts) ? ' style="' . implode('; ', $style_parts) . '"' : '';
        $data_attr = '';
        foreach ($data_attributes as $key => $value) {
            $data_attr .= sprintf(' %s="%s"', esc_attr($key), esc_attr($value));
        }

        // Create main container
        $main_container = sprintf(
            '<div id="%s" class="reactify-mount-point"></div>',
            esc_attr($container_id)
        );

        // Add error boundary if enabled
        if ($atts['error_boundary'] === 'true') {
            $main_container = sprintf(
                '<div class="reactify-error-boundary">%s</div>',
                $main_container
            );
        }

        // Add loading indicator
        $loading_indicator = $this->render_loading_indicator($atts);

        $container = sprintf(
            '<div class="%s"%s%s>%s%s</div>',
            esc_attr($class_attr),
            $style_attr,
            $data_attr,
            $loading_indicator,
            $main_container
        );

        // Add scoped styles wrapper if enabled
        if ($scoped_styles) {
            $container = sprintf(
                '<div class="reactify-scope" style="all: revert;">%s</div>',
                $container
            );
        }

        // Add performance monitoring if debug is enabled
        if ($atts['debug'] === 'true' && current_user_can('manage_options')) {
            $container .= $this->render_debug_info($slug, $project_data, $atts);
        }

        return $container;
    }

    /**
     * Render loading indicator
     *
     * @param array $atts Shortcode attributes
     * @return string Loading indicator HTML
     */
    private function render_loading_indicator($atts)
    {
        if (!empty($atts['fallback'])) {
            return sprintf(
                '<div class="reactify-loading-fallback">%s</div>',
                wp_kses_post($atts['fallback'])
            );
        }

        return '
            <div class="reactify-loading-indicator">
                <div class="reactify-spinner">
                    <div class="reactify-spinner-circle"></div>
                </div>
                <p class="reactify-loading-text">' . esc_html__('Loading React App...', 'reactifywp') . '</p>
            </div>';
    }

    /**
     * Render debug information
     *
     * @param string $slug         Project slug
     * @param object $project_data Project data
     * @param array  $atts         Shortcode attributes
     * @return string Debug info HTML
     */
    private function render_debug_info($slug, $project_data, $atts)
    {
        $debug_info = [
            'Project' => $project_data->project_name,
            'Slug' => $slug,
            'Version' => $project_data->version,
            'Status' => $project_data->status,
            'Loading' => $atts['loading'],
            'Container ID' => $this->generate_container_id($slug, $atts),
            'File Size' => size_format($project_data->file_size ?? 0),
            'Created' => $project_data->created_at
        ];

        $debug_html = '<div class="reactify-debug-info" style="margin-top: 10px; padding: 10px; background: #f0f0f0; border: 1px solid #ccc; font-size: 12px;">';
        $debug_html .= '<strong>ReactifyWP Debug Info:</strong><br>';

        foreach ($debug_info as $key => $value) {
            $debug_html .= sprintf('<strong>%s:</strong> %s<br>', esc_html($key), esc_html($value));
        }

        $debug_html .= '</div>';

        return $debug_html;
    }

    /**
     * Render error message
     *
     * @param string $message Error message
     * @return string Error HTML
     */
    private function render_error($message)
    {
        if (!current_user_can('manage_options')) {
            return '<!-- ReactifyWP Error: ' . esc_html($message) . ' -->';
        }

        return sprintf(
            '<div class="reactifywp-error" style="background: #f8d7da; color: #721c24; padding: 10px; border: 1px solid #f5c6cb; border-radius: 4px; margin: 10px 0;">
                <strong>ReactifyWP Error:</strong> %s
            </div>',
            esc_html($message)
        );
    }

    /**
     * Get projects used on current page
     *
     * @param \WP_Post $post Post object
     * @return array Projects data
     */
    private function get_page_projects($post)
    {
        $projects = [];
        
        // Find all reactify shortcodes in content
        $pattern = get_shortcode_regex(['reactify']);
        
        if (preg_match_all('/' . $pattern . '/s', $post->post_content, $matches)) {
            foreach ($matches[3] as $attrs) {
                $atts = shortcode_parse_atts($attrs);
                
                if (isset($atts['slug'])) {
                    $slug = sanitize_text_field($atts['slug']);
                    $project = new Project();
                    $project_data = $project->get_by_slug($slug);
                    
                    if ($project_data) {
                        // Get project URL
                        $upload_dir = wp_upload_dir();
                        $blog_id = get_current_blog_id();
                        $project_url = $upload_dir['baseurl'] . '/reactify-projects/' . $blog_id . '/' . $slug . '/index.html';

                        $projects[$slug] = [
                            'slug' => $slug,
                            'name' => $project_data->project_name,
                            'version' => $project_data->version,
                            'url' => $project_url
                        ];
                    }
                }
            }
        }
        
        return $projects;
    }

    /**
     * Check if current page has reactify shortcodes
     *
     * @return bool True if has shortcodes, false otherwise
     */
    public function has_reactify_shortcodes()
    {
        global $post;
        
        if (!$post) {
            return false;
        }
        
        return has_shortcode($post->post_content, 'reactify');
    }

    /**
     * Get all shortcode instances on current page
     *
     * @return array Shortcode instances
     */
    public function get_shortcode_instances()
    {
        global $post;
        
        if (!$post) {
            return [];
        }
        
        $instances = [];
        $pattern = get_shortcode_regex(['reactify']);
        
        if (preg_match_all('/' . $pattern . '/s', $post->post_content, $matches)) {
            foreach ($matches[3] as $index => $attrs) {
                $atts = shortcode_parse_atts($attrs);
                $instances[] = [
                    'raw' => $matches[0][$index],
                    'attributes' => $atts,
                    'content' => $matches[5][$index] ?? ''
                ];
            }
        }
        
        return $instances;
    }

    /**
     * Validate shortcode attributes
     *
     * @param array $atts Shortcode attributes
     * @return array|\WP_Error Validated attributes or error
     */
    public function validate_shortcode_attributes($atts)
    {
        $errors = [];
        
        if (empty($atts['slug'])) {
            $errors[] = __('Project slug is required.', 'reactifywp');
        } elseif (!preg_match('/^[a-z0-9-]+$/', $atts['slug'])) {
            $errors[] = __('Project slug can only contain lowercase letters, numbers, and hyphens.', 'reactifywp');
        }
        
        if (!empty($atts['class']) && !preg_match('/^[a-zA-Z0-9\s_-]+$/', $atts['class'])) {
            $errors[] = __('Invalid CSS class name.', 'reactifywp');
        }
        
        if (!empty($errors)) {
            return new \WP_Error('invalid_attributes', implode(' ', $errors));
        }
        
        return $atts;
    }
}
