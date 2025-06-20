<?php
/**
 * Gutenberg Block for ReactifyWP
 *
 * @package ReactifyWP
 */

namespace ReactifyWP;

/**
 * Gutenberg Block class
 */
class GutenbergBlock
{
    /**
     * Block name
     */
    const BLOCK_NAME = 'reactifywp/react-app';

    /**
     * Constructor
     */
    public function __construct()
    {
        add_action('init', [$this, 'register_block']);
        add_action('enqueue_block_editor_assets', [$this, 'enqueue_block_editor_assets']);
        add_action('wp_ajax_reactifywp_get_projects_for_block', [$this, 'handle_get_projects']);
        add_action('wp_ajax_reactifywp_preview_project', [$this, 'handle_preview_project']);
    }

    /**
     * Register Gutenberg block
     */
    public function register_block()
    {
        if (!function_exists('register_block_type')) {
            return;
        }

        register_block_type(self::BLOCK_NAME, [
            'editor_script' => 'reactifywp-gutenberg-block',
            'editor_style' => 'reactifywp-gutenberg-block-editor',
            'style' => 'reactifywp-gutenberg-block-frontend',
            'render_callback' => [$this, 'render_block'],
            'attributes' => [
                'projectSlug' => [
                    'type' => 'string',
                    'default' => ''
                ],
                'width' => [
                    'type' => 'string',
                    'default' => '100%'
                ],
                'height' => [
                    'type' => 'string',
                    'default' => 'auto'
                ],
                'loading' => [
                    'type' => 'string',
                    'default' => 'auto',
                    'enum' => ['auto', 'lazy', 'eager']
                ],
                'fallback' => [
                    'type' => 'string',
                    'default' => ''
                ],
                'errorBoundary' => [
                    'type' => 'boolean',
                    'default' => true
                ],
                'debug' => [
                    'type' => 'boolean',
                    'default' => false
                ],
                'containerId' => [
                    'type' => 'string',
                    'default' => ''
                ],
                'theme' => [
                    'type' => 'string',
                    'default' => 'default'
                ],
                'responsive' => [
                    'type' => 'boolean',
                    'default' => true
                ],
                'preload' => [
                    'type' => 'boolean',
                    'default' => false
                ],
                'ssr' => [
                    'type' => 'boolean',
                    'default' => false
                ],
                'props' => [
                    'type' => 'string',
                    'default' => '{}'
                ],
                'config' => [
                    'type' => 'string',
                    'default' => '{}'
                ],
                'cache' => [
                    'type' => 'boolean',
                    'default' => true
                ],
                'alignment' => [
                    'type' => 'string',
                    'default' => 'none'
                ],
                'className' => [
                    'type' => 'string',
                    'default' => ''
                ]
            ],
            'supports' => [
                'align' => ['left', 'center', 'right', 'wide', 'full'],
                'anchor' => true,
                'className' => true,
                'customClassName' => true,
                'spacing' => [
                    'margin' => true,
                    'padding' => true
                ],
                'color' => [
                    'background' => true,
                    'text' => true,
                    'gradients' => true
                ]
            ]
        ]);
    }

    /**
     * Enqueue block editor assets
     */
    public function enqueue_block_editor_assets()
    {
        // Enqueue block editor script
        wp_enqueue_script(
            'reactifywp-gutenberg-block',
            REACTIFYWP_PLUGIN_URL . 'assets/js/gutenberg-block.js',
            ['wp-blocks', 'wp-element', 'wp-editor', 'wp-components', 'wp-i18n', 'wp-api-fetch'],
            REACTIFYWP_VERSION,
            true
        );

        // Enqueue block editor styles
        wp_enqueue_style(
            'reactifywp-gutenberg-block-editor',
            REACTIFYWP_PLUGIN_URL . 'assets/css/gutenberg-block-editor.css',
            ['wp-edit-blocks'],
            REACTIFYWP_VERSION
        );

        // Enqueue frontend styles for editor preview
        wp_enqueue_style(
            'reactifywp-gutenberg-block-frontend',
            REACTIFYWP_PLUGIN_URL . 'assets/css/gutenberg-block-frontend.css',
            [],
            REACTIFYWP_VERSION
        );

        // Localize script with data
        wp_localize_script('reactifywp-gutenberg-block', 'ReactifyWPBlock', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('reactifywp_admin'),
            'pluginUrl' => REACTIFYWP_PLUGIN_URL,
            'projects' => $this->get_projects_for_block(),
            'themes' => $this->get_available_themes(),
            'i18n' => [
                'blockTitle' => __('ReactifyWP App', 'reactifywp'),
                'blockDescription' => __('Embed a React application', 'reactifywp'),
                'selectProject' => __('Select a React project', 'reactifywp'),
                'noProjects' => __('No React projects found. Create one first.', 'reactifywp'),
                'projectSettings' => __('Project Settings', 'reactifywp'),
                'displaySettings' => __('Display Settings', 'reactifywp'),
                'advancedSettings' => __('Advanced Settings', 'reactifywp'),
                'preview' => __('Preview', 'reactifywp'),
                'loading' => __('Loading...', 'reactifywp'),
                'error' => __('Error loading project', 'reactifywp')
            ]
        ]);
    }

    /**
     * Render block on frontend
     *
     * @param array $attributes Block attributes
     * @return string Block HTML
     */
    public function render_block($attributes)
    {
        if (empty($attributes['projectSlug'])) {
            return $this->render_placeholder(__('No React project selected.', 'reactifywp'));
        }

        // Build shortcode attributes
        $shortcode_atts = [];
        
        foreach ($attributes as $key => $value) {
            if ($key === 'projectSlug') {
                $shortcode_atts['slug'] = $value;
            } elseif ($key === 'className') {
                // Handle className separately
                continue;
            } elseif (is_bool($value)) {
                $shortcode_atts[$key] = $value ? 'true' : 'false';
            } elseif (!empty($value) && $value !== 'default' && $value !== 'auto') {
                $shortcode_atts[$key] = $value;
            }
        }

        // Build shortcode string
        $shortcode_string = '[reactify';
        foreach ($shortcode_atts as $key => $value) {
            $shortcode_string .= ' ' . $key . '="' . esc_attr($value) . '"';
        }
        $shortcode_string .= ']';

        // Add wrapper with block classes
        $wrapper_classes = ['wp-block-reactifywp-react-app'];
        
        if (!empty($attributes['className'])) {
            $wrapper_classes[] = $attributes['className'];
        }

        if (!empty($attributes['alignment'])) {
            $wrapper_classes[] = 'align' . $attributes['alignment'];
        }

        $wrapper_attributes = [
            'class' => implode(' ', $wrapper_classes)
        ];

        // Add custom styles if needed
        $styles = [];
        if (!empty($attributes['width']) && $attributes['width'] !== '100%') {
            $styles[] = 'width: ' . esc_attr($attributes['width']);
        }
        if (!empty($attributes['height']) && $attributes['height'] !== 'auto') {
            $styles[] = 'height: ' . esc_attr($attributes['height']);
        }

        if (!empty($styles)) {
            $wrapper_attributes['style'] = implode('; ', $styles);
        }

        // Build wrapper attributes string
        $wrapper_attrs_string = '';
        foreach ($wrapper_attributes as $attr => $value) {
            $wrapper_attrs_string .= ' ' . $attr . '="' . esc_attr($value) . '"';
        }

        // Execute shortcode and wrap in block container
        $shortcode_output = do_shortcode($shortcode_string);
        
        return sprintf(
            '<div%s>%s</div>',
            $wrapper_attrs_string,
            $shortcode_output
        );
    }

    /**
     * Render placeholder for editor
     *
     * @param string $message Placeholder message
     * @return string Placeholder HTML
     */
    private function render_placeholder($message)
    {
        return sprintf(
            '<div class="reactifywp-block-placeholder">
                <div class="reactifywp-block-placeholder-icon">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M12 2L2 7L12 12L22 7L12 2Z" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/>
                        <path d="M2 17L12 22L22 17" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/>
                        <path d="M2 12L12 17L22 12" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/>
                    </svg>
                </div>
                <p>%s</p>
            </div>',
            esc_html($message)
        );
    }

    /**
     * Get projects for block selector
     *
     * @return array Projects data
     */
    private function get_projects_for_block()
    {
        $project = new Project();
        $projects = $project->get_all();

        $projects_data = [];
        foreach ($projects as $proj) {
            $projects_data[] = [
                'value' => $proj->slug,
                'label' => $proj->name,
                'description' => $proj->description,
                'version' => $proj->version,
                'status' => $proj->status,
                'created_at' => $proj->created_at
            ];
        }

        return $projects_data;
    }

    /**
     * Get available themes
     *
     * @return array Available themes
     */
    private function get_available_themes()
    {
        return [
            ['value' => 'default', 'label' => __('Default', 'reactifywp')],
            ['value' => 'light', 'label' => __('Light', 'reactifywp')],
            ['value' => 'dark', 'label' => __('Dark', 'reactifywp')],
            ['value' => 'auto', 'label' => __('Auto (System)', 'reactifywp')]
        ];
    }

    /**
     * Handle AJAX request to get projects
     */
    public function handle_get_projects()
    {
        check_ajax_referer('reactifywp_admin', 'nonce');

        if (!current_user_can('edit_posts')) {
            wp_send_json_error(__('Insufficient permissions.', 'reactifywp'));
        }

        $projects = $this->get_projects_for_block();
        wp_send_json_success($projects);
    }

    /**
     * Handle AJAX request to preview project
     */
    public function handle_preview_project()
    {
        check_ajax_referer('reactifywp_admin', 'nonce');

        if (!current_user_can('edit_posts')) {
            wp_send_json_error(__('Insufficient permissions.', 'reactifywp'));
        }

        $project_slug = sanitize_text_field($_POST['project_slug'] ?? '');
        
        if (empty($project_slug)) {
            wp_send_json_error(__('Project slug is required.', 'reactifywp'));
        }

        $project = new Project();
        $project_data = $project->get_by_slug($project_slug);

        if (!$project_data) {
            wp_send_json_error(__('Project not found.', 'reactifywp'));
        }

        // Generate preview data
        $preview_data = [
            'name' => $project_data->name,
            'description' => $project_data->description,
            'version' => $project_data->version,
            'status' => $project_data->status,
            'file_count' => $this->get_project_file_count($project_data->id),
            'size' => $this->get_project_size($project_data->id),
            'last_modified' => $project_data->updated_at,
            'preview_url' => $this->get_project_preview_url($project_slug),
            'has_index' => $this->project_has_index_file($project_data->id),
            'framework' => $this->detect_project_framework($project_data->id)
        ];

        wp_send_json_success($preview_data);
    }

    /**
     * Get project file count
     *
     * @param int $project_id Project ID
     * @return int File count
     */
    private function get_project_file_count($project_id)
    {
        $asset_manager = new AssetManager();
        $assets = $asset_manager->get_project_assets($project_id);
        return count($assets);
    }

    /**
     * Get project size
     *
     * @param int $project_id Project ID
     * @return string Formatted size
     */
    private function get_project_size($project_id)
    {
        $asset_manager = new AssetManager();
        $assets = $asset_manager->get_project_assets($project_id);
        
        $total_size = 0;
        foreach ($assets as $asset) {
            $total_size += $asset['file_size'];
        }

        return size_format($total_size);
    }

    /**
     * Get project preview URL
     *
     * @param string $project_slug Project slug
     * @return string Preview URL
     */
    private function get_project_preview_url($project_slug)
    {
        $file_manager = new FileManager();
        return $file_manager->get_project_url($project_slug);
    }

    /**
     * Check if project has index file
     *
     * @param int $project_id Project ID
     * @return bool Has index file
     */
    private function project_has_index_file($project_id)
    {
        $asset_manager = new AssetManager();
        $assets = $asset_manager->get_project_assets($project_id);
        
        foreach ($assets as $asset) {
            if (basename($asset['file_path']) === 'index.html') {
                return true;
            }
        }

        return false;
    }

    /**
     * Detect project framework
     *
     * @param int $project_id Project ID
     * @return string Framework name
     */
    private function detect_project_framework($project_id)
    {
        $asset_manager = new AssetManager();
        $assets = $asset_manager->get_project_assets($project_id);
        
        foreach ($assets as $asset) {
            $filename = basename($asset['file_path']);
            
            if (strpos($filename, 'react') !== false) {
                return 'React';
            } elseif (strpos($filename, 'vue') !== false) {
                return 'Vue.js';
            } elseif (strpos($filename, 'angular') !== false) {
                return 'Angular';
            } elseif (strpos($filename, 'svelte') !== false) {
                return 'Svelte';
            }
        }

        return 'Unknown';
    }
}
