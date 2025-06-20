<?php
/**
 * Frontend asset optimization for ReactifyWP
 *
 * @package ReactifyWP
 */

namespace ReactifyWP;

/**
 * Frontend Optimizer class
 */
class FrontendOptimizer
{
    /**
     * Cache group for asset optimization
     */
    const CACHE_GROUP = 'reactifywp_assets';

    /**
     * Cache expiration time (24 hours)
     */
    const CACHE_EXPIRATION = 86400;

    /**
     * Constructor
     */
    public function __construct()
    {
        add_action('wp_enqueue_scripts', [$this, 'optimize_assets'], 999);
        add_action('wp_head', [$this, 'add_preload_hints'], 1);
        add_action('wp_head', [$this, 'add_dns_prefetch'], 1);
        add_action('wp_footer', [$this, 'add_resource_hints'], 1);
        add_filter('script_loader_tag', [$this, 'optimize_script_tags'], 10, 3);
        add_filter('style_loader_tag', [$this, 'optimize_style_tags'], 10, 4);
        add_action('wp_ajax_reactifywp_preload_assets', [$this, 'handle_preload_assets']);
        add_action('wp_ajax_nopriv_reactifywp_preload_assets', [$this, 'handle_preload_assets']);
    }

    /**
     * Optimize assets based on current page
     */
    public function optimize_assets()
    {
        global $post;

        if (!$post || !has_shortcode($post->post_content, 'reactify')) {
            return;
        }

        $settings = get_option('reactifywp_settings', []);
        
        // Get projects used on current page
        $projects = $this->get_page_projects($post);
        
        foreach ($projects as $project_slug) {
            $this->optimize_project_assets($project_slug, $settings);
        }

        // Add critical CSS inline
        if ($settings['performance']['preload_critical_assets'] ?? true) {
            $this->inline_critical_css($projects);
        }

        // Set up lazy loading for non-critical assets
        if ($settings['performance']['lazy_load_non_critical'] ?? true) {
            $this->setup_lazy_loading($projects);
        }
    }

    /**
     * Optimize assets for a specific project
     *
     * @param string $project_slug Project slug
     * @param array  $settings     Plugin settings
     */
    private function optimize_project_assets($project_slug, $settings)
    {
        $asset_manager = new AssetManager();
        $project = new Project();
        $project_data = $project->get_by_slug($project_slug);

        if (!$project_data) {
            return;
        }

        $assets = $asset_manager->get_project_assets($project_data->id);
        
        if (empty($assets)) {
            return;
        }

        // Group assets by type and criticality
        $critical_css = [];
        $non_critical_css = [];
        $critical_js = [];
        $non_critical_js = [];

        foreach ($assets as $asset) {
            if ($asset['file_type'] === 'css') {
                if ($asset['is_critical']) {
                    $critical_css[] = $asset;
                } else {
                    $non_critical_css[] = $asset;
                }
            } elseif ($asset['file_type'] === 'js') {
                if ($asset['is_critical']) {
                    $critical_js[] = $asset;
                } else {
                    $non_critical_js[] = $asset;
                }
            }
        }

        // Optimize CSS loading
        $this->optimize_css_loading($project_slug, $critical_css, $non_critical_css, $settings);

        // Optimize JavaScript loading
        $this->optimize_js_loading($project_slug, $critical_js, $non_critical_js, $settings);
    }

    /**
     * Optimize CSS loading strategy
     *
     * @param string $project_slug    Project slug
     * @param array  $critical_css    Critical CSS assets
     * @param array  $non_critical_css Non-critical CSS assets
     * @param array  $settings        Plugin settings
     */
    private function optimize_css_loading($project_slug, $critical_css, $non_critical_css, $settings)
    {
        $upload_dir = wp_upload_dir();
        $blog_id = get_current_blog_id();
        $base_url = $upload_dir['baseurl'] . '/reactify-projects/' . $blog_id . '/' . $project_slug;

        // Handle critical CSS
        foreach ($critical_css as $asset) {
            $url = trailingslashit($base_url) . ltrim($asset['file_path'], '/');
            
            // Inline small critical CSS files
            if ($asset['file_size'] < 10240) { // Less than 10KB
                $this->inline_css_asset($asset, $project_slug);
            } else {
                // Preload larger critical CSS
                $this->add_preload_link($url, 'style');
            }
        }

        // Handle non-critical CSS with lazy loading
        foreach ($non_critical_css as $asset) {
            $url = trailingslashit($base_url) . ltrim($asset['file_path'], '/');
            $this->add_lazy_css($url, $project_slug, $asset);
        }
    }

    /**
     * Optimize JavaScript loading strategy
     *
     * @param string $project_slug    Project slug
     * @param array  $critical_js     Critical JS assets
     * @param array  $non_critical_js Non-critical JS assets
     * @param array  $settings        Plugin settings
     */
    private function optimize_js_loading($project_slug, $critical_js, $non_critical_js, $settings)
    {
        $upload_dir = wp_upload_dir();
        $blog_id = get_current_blog_id();
        $base_url = $upload_dir['baseurl'] . '/reactify-projects/' . $blog_id . '/' . $project_slug;

        // Handle critical JavaScript
        foreach ($critical_js as $asset) {
            $url = trailingslashit($base_url) . ltrim($asset['file_path'], '/');
            
            // Preload critical JS
            $this->add_preload_link($url, 'script');
            
            // Add to high priority queue
            wp_enqueue_script(
                'reactifywp-critical-' . $project_slug . '-' . $asset['id'],
                $url,
                [],
                $asset['file_hash'],
                false // Load in head for critical scripts
            );
        }

        // Handle non-critical JavaScript
        foreach ($non_critical_js as $asset) {
            $url = trailingslashit($base_url) . ltrim($asset['file_path'], '/');
            $this->add_lazy_js($url, $project_slug, $asset);
        }
    }

    /**
     * Add preload hints to page head
     */
    public function add_preload_hints()
    {
        $preload_links = get_transient('reactifywp_preload_' . get_the_ID());
        
        if ($preload_links) {
            foreach ($preload_links as $link) {
                echo $link . "\n";
            }
        }
    }

    /**
     * Add DNS prefetch hints
     */
    public function add_dns_prefetch()
    {
        $settings = get_option('reactifywp_settings', []);
        
        // Add CDN prefetch if enabled
        if ($settings['performance']['enable_cdn_support'] ?? false) {
            $cdn_url = $settings['performance']['cdn_url'] ?? '';
            if ($cdn_url) {
                $domain = parse_url($cdn_url, PHP_URL_HOST);
                if ($domain) {
                    printf('<link rel="dns-prefetch" href="//%s">' . "\n", esc_attr($domain));
                }
            }
        }

        // Add common React CDN prefetch
        echo '<link rel="dns-prefetch" href="//unpkg.com">' . "\n";
        echo '<link rel="dns-prefetch" href="//cdn.jsdelivr.net">' . "\n";
    }

    /**
     * Add resource hints to page footer
     */
    public function add_resource_hints()
    {
        // Add prefetch for likely next pages
        $this->add_prefetch_hints();
    }

    /**
     * Optimize script tags
     *
     * @param string $tag    Script tag
     * @param string $handle Script handle
     * @param string $src    Script source
     * @return string Modified script tag
     */
    public function optimize_script_tags($tag, $handle, $src)
    {
        // Skip if not a ReactifyWP script
        if (strpos($handle, 'reactifywp-') !== 0) {
            return $tag;
        }

        $settings = get_option('reactifywp_settings', []);
        $attributes = [];

        // Add defer for non-critical scripts
        if (strpos($handle, 'critical') === false) {
            $attributes[] = 'defer';
        }

        // Add async for lazy-loaded scripts
        if (strpos($handle, 'lazy') !== false) {
            $attributes[] = 'async';
        }

        // Add crossorigin for external scripts
        if (strpos($src, home_url()) === false) {
            $attributes[] = 'crossorigin="anonymous"';
        }

        // Add integrity if available
        $integrity = $this->get_script_integrity($src);
        if ($integrity) {
            $attributes[] = 'integrity="' . esc_attr($integrity) . '"';
        }

        // Add error handling
        $attributes[] = 'onerror="ReactifyWP.handleScriptError(this)"';

        if (!empty($attributes)) {
            $tag = str_replace('<script ', '<script ' . implode(' ', $attributes) . ' ', $tag);
        }

        return $tag;
    }

    /**
     * Optimize style tags
     *
     * @param string $tag    Style tag
     * @param string $handle Style handle
     * @param string $href   Style href
     * @param string $media  Style media
     * @return string Modified style tag
     */
    public function optimize_style_tags($tag, $handle, $href, $media)
    {
        // Skip if not a ReactifyWP style
        if (strpos($handle, 'reactifywp-') !== 0) {
            return $tag;
        }

        $settings = get_option('reactifywp_settings', []);

        // Add preload for critical CSS
        if (strpos($handle, 'critical') !== false) {
            $tag = str_replace(
                '<link ',
                '<link rel="preload" as="style" onload="this.onload=null;this.rel=\'stylesheet\'" ',
                $tag
            );
        }

        // Add crossorigin for external styles
        if (strpos($href, home_url()) === false) {
            $tag = str_replace('<link ', '<link crossorigin="anonymous" ', $tag);
        }

        return $tag;
    }

    /**
     * Handle preload assets AJAX request
     */
    public function handle_preload_assets()
    {
        check_ajax_referer('reactifywp_frontend', 'nonce');

        $project_slug = sanitize_text_field($_POST['project'] ?? '');
        
        if (empty($project_slug)) {
            wp_send_json_error('Project slug required');
        }

        $assets = $this->get_preloadable_assets($project_slug);
        
        wp_send_json_success([
            'assets' => $assets,
            'preload_script' => $this->generate_preload_script($assets)
        ]);
    }

    /**
     * Get projects used on current page
     *
     * @param \WP_Post $post Post object
     * @return array Project slugs
     */
    private function get_page_projects($post)
    {
        $projects = [];
        $pattern = get_shortcode_regex(['reactify']);

        if (preg_match_all('/' . $pattern . '/s', $post->post_content, $matches)) {
            foreach ($matches[3] as $attrs) {
                $atts = shortcode_parse_atts($attrs);
                if (isset($atts['slug'])) {
                    $projects[] = sanitize_text_field($atts['slug']);
                }
            }
        }

        return array_unique($projects);
    }

    /**
     * Add preload link
     *
     * @param string $url  Asset URL
     * @param string $type Asset type
     */
    private function add_preload_link($url, $type)
    {
        $post_id = get_the_ID();
        $preload_links = get_transient('reactifywp_preload_' . $post_id) ?: [];

        $as = $type === 'script' ? 'script' : 'style';
        $preload_links[] = sprintf(
            '<link rel="preload" href="%s" as="%s" crossorigin="anonymous">',
            esc_url($url),
            esc_attr($as)
        );

        set_transient('reactifywp_preload_' . $post_id, $preload_links, self::CACHE_EXPIRATION);
    }

    /**
     * Inline CSS asset
     *
     * @param array  $asset       Asset data
     * @param string $project_slug Project slug
     */
    private function inline_css_asset($asset, $project_slug)
    {
        $project = new Project();
        $project_data = $project->get_by_slug($project_slug);

        if (!$project_data) {
            return;
        }

        $file_path = $project_data->file_path . '/' . $asset['file_path'];

        if (file_exists($file_path)) {
            $css_content = file_get_contents($file_path);
            if ($css_content) {
                $minified_css = $this->minify_css($css_content);

                add_action('wp_head', function () use ($minified_css) {
                    printf('<style id="reactifywp-critical-css">%s</style>', $minified_css);
                }, 5);
            }
        }
    }

    /**
     * Add lazy CSS loading
     *
     * @param string $url          Asset URL
     * @param string $project_slug Project slug
     * @param array  $asset        Asset data
     */
    private function add_lazy_css($url, $project_slug, $asset)
    {
        add_action('wp_footer', function () use ($url, $project_slug, $asset) {
            printf(
                '<script>ReactifyWP.loadCSS("%s", "%s", %s);</script>',
                esc_url($url),
                esc_js($project_slug),
                wp_json_encode($asset)
            );
        });
    }

    /**
     * Add lazy JavaScript loading
     *
     * @param string $url          Asset URL
     * @param string $project_slug Project slug
     * @param array  $asset        Asset data
     */
    private function add_lazy_js($url, $project_slug, $asset)
    {
        add_action('wp_footer', function () use ($url, $project_slug, $asset) {
            printf(
                '<script>ReactifyWP.loadJS("%s", "%s", %s);</script>',
                esc_url($url),
                esc_js($project_slug),
                wp_json_encode($asset)
            );
        });
    }

    /**
     * Inline critical CSS for projects
     *
     * @param array $projects Project slugs
     */
    private function inline_critical_css($projects)
    {
        $critical_css = '';

        foreach ($projects as $project_slug) {
            $css = $this->get_critical_css($project_slug);
            if ($css) {
                $critical_css .= $css;
            }
        }

        if ($critical_css) {
            add_action('wp_head', function () use ($critical_css) {
                printf('<style id="reactifywp-critical">%s</style>', $this->minify_css($critical_css));
            }, 5);
        }
    }

    /**
     * Get critical CSS for project
     *
     * @param string $project_slug Project slug
     * @return string Critical CSS
     */
    private function get_critical_css($project_slug)
    {
        $cache_key = 'critical_css_' . $project_slug;
        $critical_css = wp_cache_get($cache_key, self::CACHE_GROUP);

        if ($critical_css !== false) {
            return $critical_css;
        }

        $asset_manager = new AssetManager();
        $project = new Project();
        $project_data = $project->get_by_slug($project_slug);

        if (!$project_data) {
            return '';
        }

        $assets = $asset_manager->get_assets_by_type($project_data->id, 'css');
        $critical_css = '';

        foreach ($assets as $asset) {
            if ($asset['is_critical'] && $asset['file_size'] < 10240) {
                $file_path = $project_data->file_path . '/' . $asset['file_path'];
                if (file_exists($file_path)) {
                    $css_content = file_get_contents($file_path);
                    if ($css_content) {
                        $critical_css .= $css_content . "\n";
                    }
                }
            }
        }

        wp_cache_set($cache_key, $critical_css, self::CACHE_GROUP, self::CACHE_EXPIRATION);
        return $critical_css;
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
}
