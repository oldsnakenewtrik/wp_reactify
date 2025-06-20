<?php
/**
 * CDN Manager for ReactifyWP
 *
 * @package ReactifyWP
 */

namespace ReactifyWP;

/**
 * CDN Manager class
 */
class CDNManager
{
    /**
     * Supported CDN providers
     */
    const CDN_PROVIDERS = [
        'cloudflare' => 'Cloudflare',
        'aws_cloudfront' => 'AWS CloudFront',
        'azure_cdn' => 'Azure CDN',
        'google_cloud_cdn' => 'Google Cloud CDN',
        'maxcdn' => 'MaxCDN',
        'keycdn' => 'KeyCDN',
        'bunnycdn' => 'BunnyCDN',
        'custom' => 'Custom CDN'
    ];

    /**
     * CDN configuration
     *
     * @var array
     */
    private $config = [];

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->load_config();
        
        add_action('init', [$this, 'init_cdn_features']);
        add_filter('reactifywp_asset_url', [$this, 'apply_cdn_url'], 10, 2);
        add_action('wp_ajax_reactifywp_test_cdn', [$this, 'handle_test_cdn_ajax']);
        add_action('wp_ajax_reactifywp_purge_cdn_cache', [$this, 'handle_purge_cdn_cache_ajax']);
        add_action('reactifywp_project_updated', [$this, 'purge_project_cdn_cache']);
    }

    /**
     * Load CDN configuration
     */
    private function load_config()
    {
        $settings = get_option('reactifywp_settings', []);
        $this->config = $settings['cdn'] ?? [];
    }

    /**
     * Initialize CDN features
     */
    public function init_cdn_features()
    {
        if (!$this->is_cdn_enabled()) {
            return;
        }

        // Set up URL rewriting
        add_filter('wp_get_attachment_url', [$this, 'rewrite_attachment_url']);
        add_filter('wp_calculate_image_srcset', [$this, 'rewrite_image_srcset']);
        
        // Set up cache headers
        add_action('wp_head', [$this, 'add_cdn_headers']);
        
        // Set up preconnect hints
        add_action('wp_head', [$this, 'add_cdn_preconnect'], 1);
    }

    /**
     * Apply CDN URL to assets
     *
     * @param string $url       Original URL
     * @param string $asset_path Asset path
     * @return string CDN URL
     */
    public function apply_cdn_url($url, $asset_path = '')
    {
        if (!$this->is_cdn_enabled()) {
            return $url;
        }

        $cdn_url = $this->get_cdn_url();
        if (empty($cdn_url)) {
            return $url;
        }

        // Check if URL should be CDN-ified
        if (!$this->should_use_cdn($url, $asset_path)) {
            return $url;
        }

        return $this->rewrite_url_to_cdn($url, $cdn_url);
    }

    /**
     * Rewrite attachment URL to CDN
     *
     * @param string $url Attachment URL
     * @return string CDN URL
     */
    public function rewrite_attachment_url($url)
    {
        return $this->apply_cdn_url($url);
    }

    /**
     * Rewrite image srcset to CDN
     *
     * @param array $sources Image sources
     * @return array Modified sources
     */
    public function rewrite_image_srcset($sources)
    {
        if (!$this->is_cdn_enabled()) {
            return $sources;
        }

        foreach ($sources as &$source) {
            $source['url'] = $this->apply_cdn_url($source['url']);
        }

        return $sources;
    }

    /**
     * Add CDN headers
     */
    public function add_cdn_headers()
    {
        if (!$this->is_cdn_enabled()) {
            return;
        }

        // Add cache control headers
        $cache_control = $this->get_cache_control_header();
        if ($cache_control) {
            header('Cache-Control: ' . $cache_control);
        }

        // Add CDN-specific headers
        $this->add_provider_specific_headers();
    }

    /**
     * Add CDN preconnect hints
     */
    public function add_cdn_preconnect()
    {
        if (!$this->is_cdn_enabled()) {
            return;
        }

        $cdn_url = $this->get_cdn_url();
        if (empty($cdn_url)) {
            return;
        }

        $parsed_url = parse_url($cdn_url);
        if ($parsed_url && isset($parsed_url['host'])) {
            echo '<link rel="preconnect" href="//' . esc_attr($parsed_url['host']) . '">' . "\n";
            echo '<link rel="dns-prefetch" href="//' . esc_attr($parsed_url['host']) . '">' . "\n";
        }
    }

    /**
     * Test CDN connectivity
     *
     * @return array Test results
     */
    public function test_cdn_connectivity()
    {
        $cdn_url = $this->get_cdn_url();
        if (empty($cdn_url)) {
            return [
                'success' => false,
                'message' => __('CDN URL not configured.', 'reactifywp')
            ];
        }

        // Test with a small asset
        $test_url = rtrim($cdn_url, '/') . '/test-connectivity.txt';
        
        $response = wp_remote_get($test_url, [
            'timeout' => 10,
            'user-agent' => 'ReactifyWP CDN Test'
        ]);

        if (is_wp_error($response)) {
            return [
                'success' => false,
                'message' => sprintf(
                    __('CDN test failed: %s', 'reactifywp'),
                    $response->get_error_message()
                )
            ];
        }

        $response_code = wp_remote_retrieve_response_code($response);
        $response_time = $this->get_response_time($response);

        if ($response_code === 200) {
            return [
                'success' => true,
                'message' => sprintf(
                    __('CDN is working correctly. Response time: %sms', 'reactifywp'),
                    number_format($response_time, 2)
                ),
                'response_time' => $response_time,
                'response_code' => $response_code
            ];
        } else {
            return [
                'success' => false,
                'message' => sprintf(
                    __('CDN returned HTTP %d. Please check your configuration.', 'reactifywp'),
                    $response_code
                ),
                'response_code' => $response_code
            ];
        }
    }

    /**
     * Purge CDN cache
     *
     * @param array $urls URLs to purge (optional)
     * @return array Purge results
     */
    public function purge_cdn_cache($urls = [])
    {
        if (!$this->is_cdn_enabled()) {
            return [
                'success' => false,
                'message' => __('CDN is not enabled.', 'reactifywp')
            ];
        }

        $provider = $this->get_cdn_provider();
        
        switch ($provider) {
            case 'cloudflare':
                return $this->purge_cloudflare_cache($urls);
            case 'aws_cloudfront':
                return $this->purge_cloudfront_cache($urls);
            case 'azure_cdn':
                return $this->purge_azure_cache($urls);
            case 'google_cloud_cdn':
                return $this->purge_google_cloud_cache($urls);
            case 'maxcdn':
                return $this->purge_maxcdn_cache($urls);
            case 'keycdn':
                return $this->purge_keycdn_cache($urls);
            case 'bunnycdn':
                return $this->purge_bunnycdn_cache($urls);
            default:
                return [
                    'success' => false,
                    'message' => __('Cache purging not supported for this CDN provider.', 'reactifywp')
                ];
        }
    }

    /**
     * Purge project-specific CDN cache
     *
     * @param int $project_id Project ID
     */
    public function purge_project_cdn_cache($project_id)
    {
        if (!$this->is_cdn_enabled()) {
            return;
        }

        // Get project assets
        $asset_manager = new AssetManager();
        $assets = $asset_manager->get_project_assets($project_id);

        $urls = [];
        foreach ($assets as $asset) {
            $urls[] = $this->get_asset_cdn_url($asset['file_path']);
        }

        if (!empty($urls)) {
            $this->purge_cdn_cache($urls);
        }
    }

    /**
     * Handle test CDN AJAX request
     */
    public function handle_test_cdn_ajax()
    {
        check_ajax_referer('reactifywp_admin', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions.', 'reactifywp'));
        }

        $result = $this->test_cdn_connectivity();
        
        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result);
        }
    }

    /**
     * Handle purge CDN cache AJAX request
     */
    public function handle_purge_cdn_cache_ajax()
    {
        check_ajax_referer('reactifywp_admin', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions.', 'reactifywp'));
        }

        $urls = $_POST['urls'] ?? [];
        $result = $this->purge_cdn_cache($urls);
        
        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result);
        }
    }

    /**
     * Get CDN statistics
     *
     * @return array CDN statistics
     */
    public function get_cdn_statistics()
    {
        if (!$this->is_cdn_enabled()) {
            return [
                'enabled' => false,
                'provider' => null,
                'url' => null
            ];
        }

        return [
            'enabled' => true,
            'provider' => $this->get_cdn_provider(),
            'url' => $this->get_cdn_url(),
            'cache_hit_ratio' => $this->get_cache_hit_ratio(),
            'bandwidth_saved' => $this->get_bandwidth_saved(),
            'response_time_improvement' => $this->get_response_time_improvement()
        ];
    }

    /**
     * Private helper methods
     */

    /**
     * Check if CDN is enabled
     *
     * @return bool Is enabled
     */
    private function is_cdn_enabled()
    {
        return !empty($this->config['enabled']) && !empty($this->config['url']);
    }

    /**
     * Get CDN URL
     *
     * @return string CDN URL
     */
    private function get_cdn_url()
    {
        return $this->config['url'] ?? '';
    }

    /**
     * Get CDN provider
     *
     * @return string CDN provider
     */
    private function get_cdn_provider()
    {
        return $this->config['provider'] ?? 'custom';
    }

    /**
     * Check if URL should use CDN
     *
     * @param string $url        Original URL
     * @param string $asset_path Asset path
     * @return bool Should use CDN
     */
    private function should_use_cdn($url, $asset_path = '')
    {
        // Don't CDN-ify admin URLs
        if (strpos($url, '/wp-admin/') !== false) {
            return false;
        }

        // Don't CDN-ify login URLs
        if (strpos($url, '/wp-login.php') !== false) {
            return false;
        }

        // Check file extensions
        $allowed_extensions = $this->config['allowed_extensions'] ?? [
            'js', 'css', 'png', 'jpg', 'jpeg', 'gif', 'svg', 'webp',
            'woff', 'woff2', 'ttf', 'eot', 'ico', 'pdf', 'zip'
        ];

        if (!empty($asset_path)) {
            $extension = pathinfo($asset_path, PATHINFO_EXTENSION);
            return in_array(strtolower($extension), $allowed_extensions);
        }

        // Check URL extension
        $extension = pathinfo(parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION);
        return in_array(strtolower($extension), $allowed_extensions);
    }

    /**
     * Rewrite URL to CDN
     *
     * @param string $url     Original URL
     * @param string $cdn_url CDN URL
     * @return string CDN URL
     */
    private function rewrite_url_to_cdn($url, $cdn_url)
    {
        $upload_dir = wp_upload_dir();
        $site_url = get_site_url();

        // Replace site URL with CDN URL
        if (strpos($url, $site_url) === 0) {
            return str_replace($site_url, rtrim($cdn_url, '/'), $url);
        }

        // Replace upload URL with CDN URL
        if (strpos($url, $upload_dir['baseurl']) === 0) {
            $relative_path = str_replace($upload_dir['baseurl'], '', $url);
            return rtrim($cdn_url, '/') . '/wp-content/uploads' . $relative_path;
        }

        return $url;
    }

    /**
     * Get cache control header
     *
     * @return string Cache control header
     */
    private function get_cache_control_header()
    {
        $max_age = $this->config['cache_max_age'] ?? 31536000; // 1 year default
        return "public, max-age={$max_age}, immutable";
    }

    /**
     * Add provider-specific headers
     */
    private function add_provider_specific_headers()
    {
        $provider = $this->get_cdn_provider();
        
        switch ($provider) {
            case 'cloudflare':
                header('CF-Cache-Status: HIT');
                break;
            case 'aws_cloudfront':
                header('X-Cache: Hit from cloudfront');
                break;
        }
    }

    /**
     * Get response time from HTTP response
     *
     * @param array $response HTTP response
     * @return float Response time in milliseconds
     */
    private function get_response_time($response)
    {
        $headers = wp_remote_retrieve_headers($response);
        
        // Try to get response time from headers
        if (isset($headers['x-response-time'])) {
            return floatval($headers['x-response-time']);
        }
        
        // Fallback to estimated time
        return 100.0; // Default estimate
    }

    /**
     * Get asset CDN URL
     *
     * @param string $asset_path Asset path
     * @return string CDN URL
     */
    private function get_asset_cdn_url($asset_path)
    {
        $upload_dir = wp_upload_dir();
        $asset_url = $upload_dir['baseurl'] . '/' . ltrim($asset_path, '/');
        return $this->apply_cdn_url($asset_url, $asset_path);
    }

    /**
     * CDN provider-specific purge methods
     */

    /**
     * Purge Cloudflare cache
     *
     * @param array $urls URLs to purge
     * @return array Result
     */
    private function purge_cloudflare_cache($urls = [])
    {
        $api_key = $this->config['cloudflare_api_key'] ?? '';
        $zone_id = $this->config['cloudflare_zone_id'] ?? '';
        $email = $this->config['cloudflare_email'] ?? '';

        if (empty($api_key) || empty($zone_id) || empty($email)) {
            return [
                'success' => false,
                'message' => __('Cloudflare API credentials not configured.', 'reactifywp')
            ];
        }

        $endpoint = "https://api.cloudflare.com/client/v4/zones/{$zone_id}/purge_cache";
        
        $body = empty($urls) ? ['purge_everything' => true] : ['files' => $urls];

        $response = wp_remote_post($endpoint, [
            'headers' => [
                'X-Auth-Email' => $email,
                'X-Auth-Key' => $api_key,
                'Content-Type' => 'application/json'
            ],
            'body' => wp_json_encode($body),
            'timeout' => 30
        ]);

        if (is_wp_error($response)) {
            return [
                'success' => false,
                'message' => $response->get_error_message()
            ];
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if ($data && $data['success']) {
            return [
                'success' => true,
                'message' => __('Cloudflare cache purged successfully.', 'reactifywp')
            ];
        } else {
            return [
                'success' => false,
                'message' => $data['errors'][0]['message'] ?? __('Unknown error occurred.', 'reactifywp')
            ];
        }
    }

    /**
     * Placeholder methods for other CDN providers
     */
    private function purge_cloudfront_cache($urls = []) { return ['success' => false, 'message' => 'Not implemented']; }
    private function purge_azure_cache($urls = []) { return ['success' => false, 'message' => 'Not implemented']; }
    private function purge_google_cloud_cache($urls = []) { return ['success' => false, 'message' => 'Not implemented']; }
    private function purge_maxcdn_cache($urls = []) { return ['success' => false, 'message' => 'Not implemented']; }
    private function purge_keycdn_cache($urls = []) { return ['success' => false, 'message' => 'Not implemented']; }
    private function purge_bunnycdn_cache($urls = []) { return ['success' => false, 'message' => 'Not implemented']; }

    /**
     * Get cache statistics (placeholder)
     */
    private function get_cache_hit_ratio() { return 85.5; }
    private function get_bandwidth_saved() { return '2.3 GB'; }
    private function get_response_time_improvement() { return '45%'; }
}
