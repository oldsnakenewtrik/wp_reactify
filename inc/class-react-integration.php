<?php
/**
 * React App Integration for ReactifyWP
 *
 * @package ReactifyWP
 */

namespace ReactifyWP;

/**
 * React Integration class
 */
class ReactIntegration
{
    /**
     * WordPress data bridge
     *
     * @var array
     */
    private $wp_bridge_data = [];

    /**
     * React app configurations
     *
     * @var array
     */
    private $app_configs = [];

    /**
     * Constructor
     */
    public function __construct()
    {
        add_action('wp_enqueue_scripts', [$this, 'enqueue_integration_scripts'], 5);
        add_action('wp_head', [$this, 'output_wp_bridge_data'], 1);
        add_action('wp_footer', [$this, 'output_app_initializers'], 999);
        
        // AJAX endpoints for React-WordPress communication
        add_action('wp_ajax_reactifywp_get_wp_data', [$this, 'handle_get_wp_data']);
        add_action('wp_ajax_nopriv_reactifywp_get_wp_data', [$this, 'handle_get_wp_data']);
        add_action('wp_ajax_reactifywp_update_wp_data', [$this, 'handle_update_wp_data']);
        add_action('wp_ajax_nopriv_reactifywp_update_wp_data', [$this, 'handle_update_wp_data']);
        add_action('wp_ajax_reactifywp_app_event', [$this, 'handle_app_event']);
        add_action('wp_ajax_nopriv_reactifywp_app_event', [$this, 'handle_app_event']);

        // WordPress hooks for React apps
        add_action('wp_loaded', [$this, 'setup_wp_bridge']);
        add_action('template_redirect', [$this, 'handle_spa_routing']);
    }

    /**
     * Register React app configuration
     *
     * @param string $slug   App slug
     * @param array  $config App configuration
     */
    public function register_app($slug, $config)
    {
        $default_config = [
            'name' => $slug,
            'version' => '1.0.0',
            'type' => 'spa', // spa, mpa, widget
            'routing' => false,
            'api_endpoints' => [],
            'wp_integration' => [
                'posts' => false,
                'users' => false,
                'media' => false,
                'menus' => false,
                'customizer' => false,
                'comments' => false
            ],
            'permissions' => [
                'read' => true,
                'write' => false,
                'admin' => false
            ],
            'error_boundary' => true,
            'ssr' => false,
            'hydration' => false
        ];

        $this->app_configs[$slug] = array_merge($default_config, $config);
    }

    /**
     * Set up WordPress data bridge
     */
    public function setup_wp_bridge()
    {
        global $wp_query, $post;

        $this->wp_bridge_data = [
            'site' => [
                'name' => get_bloginfo('name'),
                'description' => get_bloginfo('description'),
                'url' => home_url(),
                'admin_url' => admin_url(),
                'ajax_url' => admin_url('admin-ajax.php'),
                'api_url' => rest_url('wp/v2/'),
                'reactify_api_url' => rest_url('reactifywp/v1/'),
                'language' => get_locale(),
                'timezone' => get_option('timezone_string'),
                'date_format' => get_option('date_format'),
                'time_format' => get_option('time_format')
            ],
            'user' => [
                'id' => get_current_user_id(),
                'logged_in' => is_user_logged_in(),
                'capabilities' => $this->get_user_capabilities(),
                'display_name' => wp_get_current_user()->display_name ?? '',
                'avatar_url' => get_avatar_url(get_current_user_id())
            ],
            'page' => [
                'id' => get_queried_object_id(),
                'type' => get_post_type(),
                'title' => wp_get_document_title(),
                'url' => get_permalink(),
                'is_front_page' => is_front_page(),
                'is_home' => is_home(),
                'is_single' => is_single(),
                'is_page' => is_page(),
                'is_archive' => is_archive(),
                'is_search' => is_search(),
                'is_404' => is_404()
            ],
            'query' => [
                'vars' => $wp_query->query_vars ?? [],
                'found_posts' => $wp_query->found_posts ?? 0,
                'max_num_pages' => $wp_query->max_num_pages ?? 0,
                'current_page' => get_query_var('paged') ?: 1
            ],
            'theme' => [
                'name' => get_template(),
                'version' => wp_get_theme()->get('Version'),
                'supports' => $this->get_theme_supports()
            ],
            'nonces' => [
                'wp_rest' => wp_create_nonce('wp_rest'),
                'reactifywp' => wp_create_nonce('reactifywp_frontend')
            ]
        ];

        // Add post data if available
        if ($post && !is_wp_error($post)) {
            $this->wp_bridge_data['post'] = [
                'id' => $post->ID,
                'title' => get_the_title($post),
                'content' => apply_filters('the_content', $post->post_content),
                'excerpt' => get_the_excerpt($post),
                'author' => [
                    'id' => $post->post_author,
                    'name' => get_the_author_meta('display_name', $post->post_author),
                    'url' => get_author_posts_url($post->post_author)
                ],
                'date' => get_the_date('c', $post),
                'modified' => get_the_modified_date('c', $post),
                'status' => $post->post_status,
                'type' => $post->post_type,
                'slug' => $post->post_name,
                'permalink' => get_permalink($post),
                'featured_image' => get_the_post_thumbnail_url($post, 'full'),
                'categories' => wp_get_post_categories($post->ID, ['fields' => 'all']),
                'tags' => wp_get_post_tags($post->ID, ['fields' => 'all']),
                'meta' => $this->get_safe_post_meta($post->ID)
            ];
        }

        // Add menu data
        $this->wp_bridge_data['menus'] = $this->get_menu_data();

        // Add customizer data
        $this->wp_bridge_data['customizer'] = $this->get_customizer_data();
    }

    /**
     * Enqueue integration scripts
     */
    public function enqueue_integration_scripts()
    {
        // Enqueue WordPress integration bridge
        wp_enqueue_script(
            'reactifywp-wp-bridge',
            REACTIFYWP_PLUGIN_URL . 'assets/js/wp-bridge.js',
            ['jquery'],
            REACTIFYWP_VERSION,
            true
        );

        // Enqueue React integration utilities
        wp_enqueue_script(
            'reactifywp-react-integration',
            REACTIFYWP_PLUGIN_URL . 'assets/js/react-integration.js',
            ['reactifywp-wp-bridge'],
            REACTIFYWP_VERSION,
            true
        );

        // Localize script with configuration
        wp_localize_script('reactifywp-react-integration', 'ReactifyWPIntegration', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'apiUrl' => rest_url('reactifywp/v1/'),
            'nonce' => wp_create_nonce('reactifywp_frontend'),
            'debug' => defined('WP_DEBUG') && WP_DEBUG,
            'apps' => $this->app_configs
        ]);
    }

    /**
     * Output WordPress bridge data
     */
    public function output_wp_bridge_data()
    {
        echo '<script type="text/javascript">';
        echo 'window.ReactifyWP = window.ReactifyWP || {};';
        echo 'window.ReactifyWP.wpBridge = ' . wp_json_encode($this->wp_bridge_data) . ';';
        echo 'window.ReactifyWP.apps = window.ReactifyWP.apps || {};';
        echo '</script>';
    }

    /**
     * Output app initializers
     */
    public function output_app_initializers()
    {
        if (empty($this->app_configs)) {
            return;
        }

        echo '<script type="text/javascript">';
        echo 'document.addEventListener("DOMContentLoaded", function() {';
        echo '  if (window.ReactifyWP && window.ReactifyWP.initializeApps) {';
        echo '    window.ReactifyWP.initializeApps();';
        echo '  }';
        echo '});';
        echo '</script>';
    }

    /**
     * Handle SPA routing
     */
    public function handle_spa_routing()
    {
        // Check if this is a SPA route
        $spa_apps = array_filter($this->app_configs, function ($config) {
            return $config['type'] === 'spa' && $config['routing'] === true;
        });

        if (empty($spa_apps)) {
            return;
        }

        $current_url = $_SERVER['REQUEST_URI'] ?? '';
        
        foreach ($spa_apps as $slug => $config) {
            if (isset($config['routes']) && is_array($config['routes'])) {
                foreach ($config['routes'] as $route) {
                    if (preg_match($route['pattern'], $current_url)) {
                        $this->handle_spa_route($slug, $route, $current_url);
                        return;
                    }
                }
            }
        }
    }

    /**
     * Handle SPA route
     *
     * @param string $app_slug    App slug
     * @param array  $route       Route configuration
     * @param string $current_url Current URL
     */
    private function handle_spa_route($app_slug, $route, $current_url)
    {
        // Set up SPA environment
        add_filter('wp_title', function () use ($route) {
            return $route['title'] ?? get_bloginfo('name');
        });

        add_action('wp_head', function () use ($app_slug, $route) {
            echo '<meta name="reactifywp-spa" content="' . esc_attr($app_slug) . '">';
            echo '<meta name="reactifywp-route" content="' . esc_attr($route['name'] ?? '') . '">';
        });

        // Prevent 404
        global $wp_query;
        $wp_query->is_404 = false;
        status_header(200);
    }

    /**
     * Handle get WordPress data AJAX request
     */
    public function handle_get_wp_data()
    {
        $nonce = $_REQUEST['nonce'] ?? '';
        if (!wp_verify_nonce($nonce, 'reactifywp_frontend')) {
            wp_send_json_error('Invalid nonce');
        }

        $data_type = sanitize_text_field($_REQUEST['type'] ?? '');
        $params = $_REQUEST['params'] ?? [];

        switch ($data_type) {
            case 'posts':
                $data = $this->get_posts_data($params);
                break;
            case 'users':
                $data = $this->get_users_data($params);
                break;
            case 'media':
                $data = $this->get_media_data($params);
                break;
            case 'menus':
                $data = $this->get_menu_data($params);
                break;
            case 'customizer':
                $data = $this->get_customizer_data($params);
                break;
            case 'comments':
                $data = $this->get_comments_data($params);
                break;
            default:
                wp_send_json_error('Invalid data type');
        }

        wp_send_json_success($data);
    }

    /**
     * Handle update WordPress data AJAX request
     */
    public function handle_update_wp_data()
    {
        $nonce = $_REQUEST['nonce'] ?? '';
        if (!wp_verify_nonce($nonce, 'reactifywp_frontend')) {
            wp_send_json_error('Invalid nonce');
        }

        if (!is_user_logged_in()) {
            wp_send_json_error('User not logged in');
        }

        $data_type = sanitize_text_field($_REQUEST['type'] ?? '');
        $data = $_REQUEST['data'] ?? [];
        $action_type = sanitize_text_field($_REQUEST['action'] ?? 'update');

        // Check permissions
        if (!$this->check_update_permissions($data_type, $action_type)) {
            wp_send_json_error('Insufficient permissions');
        }

        switch ($data_type) {
            case 'post':
                $result = $this->update_post_data($data, $action_type);
                break;
            case 'comment':
                $result = $this->update_comment_data($data, $action_type);
                break;
            case 'user_meta':
                $result = $this->update_user_meta_data($data, $action_type);
                break;
            default:
                wp_send_json_error('Invalid data type for update');
        }

        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }

        wp_send_json_success($result);
    }

    /**
     * Handle app event AJAX request
     */
    public function handle_app_event()
    {
        $nonce = $_REQUEST['nonce'] ?? '';
        if (!wp_verify_nonce($nonce, 'reactifywp_frontend')) {
            wp_send_json_error('Invalid nonce');
        }

        $app_slug = sanitize_text_field($_REQUEST['app'] ?? '');
        $event_type = sanitize_text_field($_REQUEST['event'] ?? '');
        $event_data = $_REQUEST['data'] ?? [];

        if (!isset($this->app_configs[$app_slug])) {
            wp_send_json_error('Invalid app');
        }

        // Process app event
        $result = $this->process_app_event($app_slug, $event_type, $event_data);

        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }

        wp_send_json_success($result);
    }

    /**
     * Get user capabilities
     *
     * @return array User capabilities
     */
    private function get_user_capabilities()
    {
        if (!is_user_logged_in()) {
            return [];
        }

        $user = wp_get_current_user();
        $capabilities = [];

        // Common capabilities
        $check_caps = [
            'read', 'edit_posts', 'edit_pages', 'edit_others_posts',
            'publish_posts', 'manage_categories', 'moderate_comments',
            'manage_options', 'upload_files', 'edit_theme_options'
        ];

        foreach ($check_caps as $cap) {
            $capabilities[$cap] = user_can($user, $cap);
        }

        return $capabilities;
    }

    /**
     * Get theme supports
     *
     * @return array Theme supports
     */
    private function get_theme_supports()
    {
        $supports = [];
        $features = [
            'post-thumbnails', 'custom-logo', 'custom-header',
            'custom-background', 'menus', 'widgets', 'html5',
            'post-formats', 'customize-selective-refresh-widgets'
        ];

        foreach ($features as $feature) {
            $supports[$feature] = current_theme_supports($feature);
        }

        return $supports;
    }

    /**
     * Get safe post meta
     *
     * @param int $post_id Post ID
     * @return array Safe post meta
     */
    private function get_safe_post_meta($post_id)
    {
        $meta = get_post_meta($post_id);
        $safe_meta = [];

        // Only include non-sensitive meta
        $allowed_keys = [
            '_thumbnail_id', '_wp_page_template', '_edit_last',
            'custom_field_', 'seo_', 'yoast_', '_yoast_'
        ];

        foreach ($meta as $key => $value) {
            // Skip private meta that starts with _
            if (strpos($key, '_') === 0) {
                $allowed = false;
                foreach ($allowed_keys as $allowed_key) {
                    if (strpos($key, $allowed_key) === 0) {
                        $allowed = true;
                        break;
                    }
                }
                if (!$allowed) {
                    continue;
                }
            }

            $safe_meta[$key] = is_array($value) && count($value) === 1 ? $value[0] : $value;
        }

        return $safe_meta;
    }

    /**
     * Get menu data
     *
     * @param array $params Parameters
     * @return array Menu data
     */
    private function get_menu_data($params = [])
    {
        $menus = [];
        $locations = get_nav_menu_locations();

        foreach ($locations as $location => $menu_id) {
            if ($menu_id) {
                $menu_items = wp_get_nav_menu_items($menu_id);
                $menus[$location] = $this->build_menu_tree($menu_items);
            }
        }

        return $menus;
    }

    /**
     * Build menu tree structure
     *
     * @param array $menu_items Menu items
     * @return array Menu tree
     */
    private function build_menu_tree($menu_items)
    {
        $menu_tree = [];
        $menu_items_by_parent = [];

        // Group items by parent
        foreach ($menu_items as $item) {
            $parent_id = $item->menu_item_parent;
            if (!isset($menu_items_by_parent[$parent_id])) {
                $menu_items_by_parent[$parent_id] = [];
            }
            $menu_items_by_parent[$parent_id][] = [
                'id' => $item->ID,
                'title' => $item->title,
                'url' => $item->url,
                'target' => $item->target,
                'classes' => $item->classes,
                'description' => $item->description,
                'object_id' => $item->object_id,
                'object' => $item->object,
                'type' => $item->type,
                'children' => []
            ];
        }

        // Build tree recursively
        return $this->build_menu_children($menu_items_by_parent, 0);
    }

    /**
     * Build menu children recursively
     *
     * @param array $items_by_parent Items grouped by parent
     * @param int   $parent_id       Parent ID
     * @return array Menu children
     */
    private function build_menu_children($items_by_parent, $parent_id)
    {
        $children = [];

        if (isset($items_by_parent[$parent_id])) {
            foreach ($items_by_parent[$parent_id] as $item) {
                $item['children'] = $this->build_menu_children($items_by_parent, $item['id']);
                $children[] = $item;
            }
        }

        return $children;
    }

    /**
     * Get customizer data
     *
     * @param array $params Parameters
     * @return array Customizer data
     */
    private function get_customizer_data($params = [])
    {
        $customizer_data = [];

        // Site identity
        $customizer_data['site_identity'] = [
            'site_title' => get_bloginfo('name'),
            'tagline' => get_bloginfo('description'),
            'site_icon' => get_site_icon_url(),
            'custom_logo' => get_theme_mod('custom_logo'),
            'custom_logo_url' => wp_get_attachment_image_url(get_theme_mod('custom_logo'), 'full')
        ];

        // Colors
        $customizer_data['colors'] = [
            'header_textcolor' => get_header_textcolor(),
            'background_color' => get_background_color()
        ];

        // Background
        $customizer_data['background'] = [
            'background_color' => get_background_color(),
            'background_image' => get_background_image(),
            'background_repeat' => get_theme_mod('background_repeat'),
            'background_position_x' => get_theme_mod('background_position_x'),
            'background_attachment' => get_theme_mod('background_attachment')
        ];

        return $customizer_data;
    }

    /**
     * Get posts data
     *
     * @param array $params Query parameters
     * @return array Posts data
     */
    private function get_posts_data($params = [])
    {
        $default_params = [
            'post_type' => 'post',
            'posts_per_page' => 10,
            'post_status' => 'publish'
        ];

        $query_params = array_merge($default_params, $params);
        $posts_query = new \WP_Query($query_params);

        $posts = [];
        while ($posts_query->have_posts()) {
            $posts_query->the_post();
            $post = get_post();

            $posts[] = [
                'id' => $post->ID,
                'title' => get_the_title(),
                'content' => get_the_content(),
                'excerpt' => get_the_excerpt(),
                'permalink' => get_permalink(),
                'date' => get_the_date('c'),
                'modified' => get_the_modified_date('c'),
                'author' => [
                    'id' => $post->post_author,
                    'name' => get_the_author(),
                    'url' => get_author_posts_url($post->post_author)
                ],
                'featured_image' => get_the_post_thumbnail_url($post, 'full'),
                'categories' => wp_get_post_categories($post->ID, ['fields' => 'all']),
                'tags' => wp_get_post_tags($post->ID, ['fields' => 'all'])
            ];
        }

        wp_reset_postdata();

        return [
            'posts' => $posts,
            'found_posts' => $posts_query->found_posts,
            'max_num_pages' => $posts_query->max_num_pages
        ];
    }

    /**
     * Get users data
     *
     * @param array $params Query parameters
     * @return array Users data
     */
    private function get_users_data($params = [])
    {
        if (!current_user_can('list_users')) {
            return [];
        }

        $default_params = [
            'number' => 10,
            'fields' => 'all'
        ];

        $query_params = array_merge($default_params, $params);
        $users = get_users($query_params);

        $users_data = [];
        foreach ($users as $user) {
            $users_data[] = [
                'id' => $user->ID,
                'login' => $user->user_login,
                'display_name' => $user->display_name,
                'email' => $user->user_email,
                'url' => $user->user_url,
                'registered' => $user->user_registered,
                'avatar_url' => get_avatar_url($user->ID),
                'roles' => $user->roles
            ];
        }

        return $users_data;
    }

    /**
     * Get media data
     *
     * @param array $params Query parameters
     * @return array Media data
     */
    private function get_media_data($params = [])
    {
        $default_params = [
            'post_type' => 'attachment',
            'post_status' => 'inherit',
            'posts_per_page' => 20
        ];

        $query_params = array_merge($default_params, $params);
        $media_query = new \WP_Query($query_params);

        $media = [];
        while ($media_query->have_posts()) {
            $media_query->the_post();
            $attachment = get_post();

            $media[] = [
                'id' => $attachment->ID,
                'title' => get_the_title(),
                'url' => wp_get_attachment_url($attachment->ID),
                'type' => get_post_mime_type($attachment->ID),
                'sizes' => wp_get_attachment_metadata($attachment->ID)['sizes'] ?? [],
                'alt' => get_post_meta($attachment->ID, '_wp_attachment_image_alt', true),
                'caption' => $attachment->post_excerpt,
                'description' => $attachment->post_content,
                'date' => get_the_date('c')
            ];
        }

        wp_reset_postdata();

        return $media;
    }

    /**
     * Get comments data
     *
     * @param array $params Query parameters
     * @return array Comments data
     */
    private function get_comments_data($params = [])
    {
        $default_params = [
            'status' => 'approve',
            'number' => 20
        ];

        $query_params = array_merge($default_params, $params);
        $comments = get_comments($query_params);

        $comments_data = [];
        foreach ($comments as $comment) {
            $comments_data[] = [
                'id' => $comment->comment_ID,
                'post_id' => $comment->comment_post_ID,
                'author' => $comment->comment_author,
                'author_email' => $comment->comment_author_email,
                'author_url' => $comment->comment_author_url,
                'content' => $comment->comment_content,
                'date' => $comment->comment_date,
                'approved' => $comment->comment_approved,
                'parent' => $comment->comment_parent,
                'avatar_url' => get_avatar_url($comment->comment_author_email)
            ];
        }

        return $comments_data;
    }

    /**
     * Check update permissions
     *
     * @param string $data_type   Data type
     * @param string $action_type Action type
     * @return bool Has permission
     */
    private function check_update_permissions($data_type, $action_type)
    {
        switch ($data_type) {
            case 'post':
                return current_user_can('edit_posts');
            case 'comment':
                return current_user_can('moderate_comments');
            case 'user_meta':
                return current_user_can('edit_users');
            default:
                return false;
        }
    }

    /**
     * Update post data
     *
     * @param array  $data        Post data
     * @param string $action_type Action type
     * @return array|\WP_Error Update result
     */
    private function update_post_data($data, $action_type)
    {
        switch ($action_type) {
            case 'create':
                $post_id = wp_insert_post($data);
                break;
            case 'update':
                $post_id = wp_update_post($data);
                break;
            case 'delete':
                $post_id = wp_delete_post($data['ID'], true);
                break;
            default:
                return new \WP_Error('invalid_action', 'Invalid action type');
        }

        if (is_wp_error($post_id)) {
            return $post_id;
        }

        return ['id' => $post_id, 'action' => $action_type];
    }

    /**
     * Update comment data
     *
     * @param array  $data        Comment data
     * @param string $action_type Action type
     * @return array|\WP_Error Update result
     */
    private function update_comment_data($data, $action_type)
    {
        switch ($action_type) {
            case 'create':
                $comment_id = wp_insert_comment($data);
                break;
            case 'update':
                $comment_id = wp_update_comment($data);
                break;
            case 'approve':
                $comment_id = wp_set_comment_status($data['comment_ID'], 'approve');
                break;
            case 'unapprove':
                $comment_id = wp_set_comment_status($data['comment_ID'], 'hold');
                break;
            case 'spam':
                $comment_id = wp_spam_comment($data['comment_ID']);
                break;
            case 'delete':
                $comment_id = wp_delete_comment($data['comment_ID'], true);
                break;
            default:
                return new \WP_Error('invalid_action', 'Invalid action type');
        }

        if (is_wp_error($comment_id) || $comment_id === false) {
            return new \WP_Error('comment_update_failed', 'Failed to update comment');
        }

        return ['id' => $comment_id, 'action' => $action_type];
    }

    /**
     * Update user meta data
     *
     * @param array  $data        User meta data
     * @param string $action_type Action type
     * @return array|\WP_Error Update result
     */
    private function update_user_meta_data($data, $action_type)
    {
        $user_id = $data['user_id'] ?? get_current_user_id();
        $meta_key = $data['meta_key'] ?? '';
        $meta_value = $data['meta_value'] ?? '';

        if (empty($meta_key)) {
            return new \WP_Error('invalid_meta_key', 'Meta key is required');
        }

        // Only allow safe meta keys
        $allowed_meta_keys = [
            'description', 'nickname', 'first_name', 'last_name',
            'user_url', 'aim', 'yim', 'jabber'
        ];

        if (!in_array($meta_key, $allowed_meta_keys)) {
            return new \WP_Error('forbidden_meta_key', 'Meta key not allowed');
        }

        switch ($action_type) {
            case 'update':
                $result = update_user_meta($user_id, $meta_key, $meta_value);
                break;
            case 'delete':
                $result = delete_user_meta($user_id, $meta_key);
                break;
            default:
                return new \WP_Error('invalid_action', 'Invalid action type');
        }

        return ['user_id' => $user_id, 'meta_key' => $meta_key, 'action' => $action_type];
    }

    /**
     * Process app event
     *
     * @param string $app_slug    App slug
     * @param string $event_type  Event type
     * @param array  $event_data  Event data
     * @return array|\WP_Error Process result
     */
    private function process_app_event($app_slug, $event_type, $event_data)
    {
        // Log app event
        do_action('reactifywp_app_event', $app_slug, $event_type, $event_data);

        switch ($event_type) {
            case 'page_view':
                return $this->track_page_view($app_slug, $event_data);
            case 'user_interaction':
                return $this->track_user_interaction($app_slug, $event_data);
            case 'error':
                return $this->handle_app_error($app_slug, $event_data);
            case 'performance':
                return $this->track_performance($app_slug, $event_data);
            default:
                return ['status' => 'event_received', 'type' => $event_type];
        }
    }

    /**
     * Track page view
     *
     * @param string $app_slug   App slug
     * @param array  $event_data Event data
     * @return array Track result
     */
    private function track_page_view($app_slug, $event_data)
    {
        // Basic page view tracking
        $page_data = [
            'app' => $app_slug,
            'url' => $event_data['url'] ?? '',
            'title' => $event_data['title'] ?? '',
            'referrer' => $event_data['referrer'] ?? '',
            'user_id' => get_current_user_id(),
            'timestamp' => current_time('mysql')
        ];

        // Store or process page view data
        do_action('reactifywp_track_page_view', $page_data);

        return ['status' => 'tracked', 'type' => 'page_view'];
    }

    /**
     * Track user interaction
     *
     * @param string $app_slug   App slug
     * @param array  $event_data Event data
     * @return array Track result
     */
    private function track_user_interaction($app_slug, $event_data)
    {
        $interaction_data = [
            'app' => $app_slug,
            'action' => $event_data['action'] ?? '',
            'element' => $event_data['element'] ?? '',
            'value' => $event_data['value'] ?? '',
            'user_id' => get_current_user_id(),
            'timestamp' => current_time('mysql')
        ];

        do_action('reactifywp_track_interaction', $interaction_data);

        return ['status' => 'tracked', 'type' => 'user_interaction'];
    }

    /**
     * Handle app error
     *
     * @param string $app_slug   App slug
     * @param array  $event_data Event data
     * @return array Handle result
     */
    private function handle_app_error($app_slug, $event_data)
    {
        $error_data = [
            'app' => $app_slug,
            'message' => $event_data['message'] ?? '',
            'stack' => $event_data['stack'] ?? '',
            'url' => $event_data['url'] ?? '',
            'user_id' => get_current_user_id(),
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            'timestamp' => current_time('mysql')
        ];

        // Log error using error handler
        if (class_exists('ReactifyWP\ErrorHandler')) {
            $error_handler = new ErrorHandler();
            $error_handler->log_error('react_app', $error_data['message'], $error_data, 'medium');
        }

        do_action('reactifywp_app_error', $error_data);

        return ['status' => 'logged', 'type' => 'error'];
    }

    /**
     * Track performance
     *
     * @param string $app_slug   App slug
     * @param array  $event_data Event data
     * @return array Track result
     */
    private function track_performance($app_slug, $event_data)
    {
        $performance_data = [
            'app' => $app_slug,
            'metrics' => $event_data['metrics'] ?? [],
            'user_id' => get_current_user_id(),
            'timestamp' => current_time('mysql')
        ];

        do_action('reactifywp_track_performance', $performance_data);

        return ['status' => 'tracked', 'type' => 'performance'];
    }
}
