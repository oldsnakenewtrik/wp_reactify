<?php
/**
 * Page Builder Integration for ReactifyWP
 *
 * @package ReactifyWP
 */

namespace ReactifyWP;

/**
 * Page Builder Integration class
 */
class PageBuilderIntegration
{
    /**
     * Supported page builders
     */
    const SUPPORTED_BUILDERS = [
        'gutenberg' => 'Gutenberg',
        'elementor' => 'Elementor',
        'beaver-builder' => 'Beaver Builder',
        'divi' => 'Divi Builder',
        'visual-composer' => 'WPBakery Page Builder',
        'oxygen' => 'Oxygen Builder',
        'bricks' => 'Bricks Builder'
    ];

    /**
     * Active integrations
     *
     * @var array
     */
    private $active_integrations = [];

    /**
     * Constructor
     */
    public function __construct()
    {
        add_action('init', [$this, 'detect_page_builders'], 20);
        add_action('init', [$this, 'initialize_integrations'], 30);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_page_builder_assets']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
    }

    /**
     * Detect available page builders
     */
    public function detect_page_builders()
    {
        $detected = [];

        // Gutenberg (WordPress 5.0+)
        if (function_exists('register_block_type')) {
            $detected['gutenberg'] = [
                'name' => 'Gutenberg',
                'version' => get_bloginfo('version'),
                'active' => true,
                'class' => 'GutenbergBlock'
            ];
        }

        // Elementor
        if (defined('ELEMENTOR_VERSION')) {
            $detected['elementor'] = [
                'name' => 'Elementor',
                'version' => ELEMENTOR_VERSION,
                'active' => class_exists('\Elementor\Plugin'),
                'class' => 'ElementorWidget'
            ];
        }

        // Beaver Builder
        if (class_exists('FLBuilder')) {
            $detected['beaver-builder'] = [
                'name' => 'Beaver Builder',
                'version' => FL_BUILDER_VERSION ?? 'Unknown',
                'active' => true,
                'class' => 'BeaverBuilderModule'
            ];
        }

        // Divi Builder
        if (function_exists('et_divi_builder_init_plugin') || defined('ET_BUILDER_VERSION')) {
            $detected['divi'] = [
                'name' => 'Divi Builder',
                'version' => ET_BUILDER_VERSION ?? 'Unknown',
                'active' => true,
                'class' => 'DiviModule'
            ];
        }

        // WPBakery Page Builder (Visual Composer)
        if (defined('WPB_VC_VERSION')) {
            $detected['visual-composer'] = [
                'name' => 'WPBakery Page Builder',
                'version' => WPB_VC_VERSION,
                'active' => true,
                'class' => 'VisualComposerElement'
            ];
        }

        // Oxygen Builder
        if (defined('CT_VERSION')) {
            $detected['oxygen'] = [
                'name' => 'Oxygen Builder',
                'version' => CT_VERSION,
                'active' => true,
                'class' => 'OxygenElement'
            ];
        }

        // Bricks Builder
        if (defined('BRICKS_VERSION')) {
            $detected['bricks'] = [
                'name' => 'Bricks Builder',
                'version' => BRICKS_VERSION,
                'active' => true,
                'class' => 'BricksElement'
            ];
        }

        $this->active_integrations = $detected;

        // Store detected builders in option
        update_option('reactifywp_detected_builders', $detected);
    }

    /**
     * Initialize page builder integrations
     */
    public function initialize_integrations()
    {
        foreach ($this->active_integrations as $builder => $info) {
            if ($info['active']) {
                $this->initialize_builder_integration($builder, $info);
            }
        }
    }

    /**
     * Initialize specific builder integration
     *
     * @param string $builder Builder key
     * @param array  $info    Builder info
     */
    private function initialize_builder_integration($builder, $info)
    {
        switch ($builder) {
            case 'gutenberg':
                new GutenbergBlock();
                break;

            case 'elementor':
                add_action('elementor/widgets/widgets_registered', [$this, 'register_elementor_widget']);
                add_action('elementor/elements/categories_registered', [$this, 'add_elementor_category']);
                break;

            case 'beaver-builder':
                add_action('init', [$this, 'register_beaver_builder_module']);
                break;

            case 'divi':
                add_action('et_builder_ready', [$this, 'register_divi_module']);
                break;

            case 'visual-composer':
                add_action('vc_before_init', [$this, 'register_visual_composer_element']);
                break;

            case 'oxygen':
                add_action('oxygen_add_plus_sections', [$this, 'register_oxygen_element']);
                break;

            case 'bricks':
                add_action('init', [$this, 'register_bricks_element']);
                break;
        }

        do_action('reactifywp_builder_integration_initialized', $builder, $info);
    }

    /**
     * Register Elementor widget
     */
    public function register_elementor_widget()
    {
        \Elementor\Plugin::instance()->widgets_manager->register_widget_type(new ElementorWidget());
    }

    /**
     * Add Elementor category
     *
     * @param \Elementor\Elements_Manager $elements_manager Elements manager
     */
    public function add_elementor_category($elements_manager)
    {
        $elements_manager->add_category(
            'reactifywp',
            [
                'title' => __('ReactifyWP', 'reactifywp'),
                'icon' => 'fa fa-code',
            ]
        );
    }

    /**
     * Register Beaver Builder module
     */
    public function register_beaver_builder_module()
    {
        if (class_exists('FLBuilder')) {
            require_once REACTIFYWP_PLUGIN_DIR . 'inc/page-builders/class-beaver-builder-module.php';
        }
    }

    /**
     * Register Divi module
     */
    public function register_divi_module()
    {
        if (class_exists('ET_Builder_Module')) {
            require_once REACTIFYWP_PLUGIN_DIR . 'inc/page-builders/class-divi-module.php';
        }
    }

    /**
     * Register Visual Composer element
     */
    public function register_visual_composer_element()
    {
        if (function_exists('vc_map')) {
            require_once REACTIFYWP_PLUGIN_DIR . 'inc/page-builders/class-visual-composer-element.php';
        }
    }

    /**
     * Register Oxygen element
     */
    public function register_oxygen_element()
    {
        if (class_exists('OxyEl')) {
            require_once REACTIFYWP_PLUGIN_DIR . 'inc/page-builders/class-oxygen-element.php';
        }
    }

    /**
     * Register Bricks element
     */
    public function register_bricks_element()
    {
        if (class_exists('\Bricks\Elements')) {
            require_once REACTIFYWP_PLUGIN_DIR . 'inc/page-builders/class-bricks-element.php';
        }
    }

    /**
     * Enqueue page builder assets
     */
    public function enqueue_page_builder_assets()
    {
        // Enqueue assets for frontend page builder compatibility
        foreach ($this->active_integrations as $builder => $info) {
            if ($info['active']) {
                $this->enqueue_builder_assets($builder);
            }
        }
    }

    /**
     * Enqueue admin assets
     */
    public function enqueue_admin_assets()
    {
        $screen = get_current_screen();
        
        // Only load on relevant admin pages
        if (!$screen || !in_array($screen->id, ['post', 'page', 'reactifywp_page_reactifywp-settings'])) {
            return;
        }

        wp_enqueue_style(
            'reactifywp-page-builders',
            REACTIFYWP_PLUGIN_URL . 'assets/css/page-builders.css',
            [],
            REACTIFYWP_VERSION
        );

        wp_enqueue_script(
            'reactifywp-page-builders',
            REACTIFYWP_PLUGIN_URL . 'assets/js/page-builders.js',
            ['jquery'],
            REACTIFYWP_VERSION,
            true
        );

        wp_localize_script('reactifywp-page-builders', 'ReactifyWPPageBuilders', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('reactifywp_admin'),
            'builders' => $this->active_integrations,
            'i18n' => [
                'loading' => __('Loading...', 'reactifywp'),
                'error' => __('Error loading data', 'reactifywp'),
                'noProjects' => __('No React projects found', 'reactifywp'),
                'selectProject' => __('Select a React project', 'reactifywp')
            ]
        ]);
    }

    /**
     * Enqueue builder-specific assets
     *
     * @param string $builder Builder key
     */
    private function enqueue_builder_assets($builder)
    {
        switch ($builder) {
            case 'elementor':
                if (\Elementor\Plugin::$instance->preview->is_preview_mode()) {
                    wp_enqueue_style(
                        'reactifywp-elementor-preview',
                        REACTIFYWP_PLUGIN_URL . 'assets/css/elementor-preview.css',
                        [],
                        REACTIFYWP_VERSION
                    );
                }
                break;

            case 'beaver-builder':
                if (FLBuilderModel::is_builder_active()) {
                    wp_enqueue_style(
                        'reactifywp-beaver-builder',
                        REACTIFYWP_PLUGIN_URL . 'assets/css/beaver-builder.css',
                        [],
                        REACTIFYWP_VERSION
                    );
                }
                break;

            case 'divi':
                if (function_exists('et_core_is_fb_enabled') && et_core_is_fb_enabled()) {
                    wp_enqueue_style(
                        'reactifywp-divi-builder',
                        REACTIFYWP_PLUGIN_URL . 'assets/css/divi-builder.css',
                        [],
                        REACTIFYWP_VERSION
                    );
                }
                break;
        }
    }

    /**
     * Get active page builders
     *
     * @return array Active builders
     */
    public function get_active_builders()
    {
        return $this->active_integrations;
    }

    /**
     * Check if specific builder is active
     *
     * @param string $builder Builder key
     * @return bool Is active
     */
    public function is_builder_active($builder)
    {
        return isset($this->active_integrations[$builder]) && $this->active_integrations[$builder]['active'];
    }

    /**
     * Get builder info
     *
     * @param string $builder Builder key
     * @return array|null Builder info
     */
    public function get_builder_info($builder)
    {
        return $this->active_integrations[$builder] ?? null;
    }

    /**
     * Register custom shortcode for page builders
     *
     * @param string $builder Builder key
     * @param array  $config  Shortcode config
     */
    public function register_builder_shortcode($builder, $config)
    {
        $shortcode_tag = 'reactifywp_' . $builder;
        
        add_shortcode($shortcode_tag, function ($atts) use ($config) {
            // Convert builder-specific attributes to ReactifyWP shortcode
            $reactify_atts = $this->convert_builder_attributes($atts, $config);
            
            // Generate ReactifyWP shortcode
            $shortcode_string = '[reactify';
            foreach ($reactify_atts as $key => $value) {
                $shortcode_string .= ' ' . $key . '="' . esc_attr($value) . '"';
            }
            $shortcode_string .= ']';
            
            return do_shortcode($shortcode_string);
        });
    }

    /**
     * Convert builder attributes to ReactifyWP format
     *
     * @param array $atts   Builder attributes
     * @param array $config Conversion config
     * @return array ReactifyWP attributes
     */
    private function convert_builder_attributes($atts, $config)
    {
        $reactify_atts = [];
        
        foreach ($config['attribute_mapping'] as $builder_attr => $reactify_attr) {
            if (isset($atts[$builder_attr])) {
                $reactify_atts[$reactify_attr] = $atts[$builder_attr];
            }
        }
        
        return $reactify_atts;
    }

    /**
     * Get page builder compatibility info
     *
     * @return array Compatibility info
     */
    public function get_compatibility_info()
    {
        $compatibility = [];
        
        foreach (self::SUPPORTED_BUILDERS as $key => $name) {
            $is_active = $this->is_builder_active($key);
            $info = $this->get_builder_info($key);
            
            $compatibility[$key] = [
                'name' => $name,
                'detected' => $is_active,
                'version' => $info['version'] ?? 'Unknown',
                'integration_status' => $is_active ? 'active' : 'not_detected',
                'features' => $this->get_builder_features($key)
            ];
        }
        
        return $compatibility;
    }

    /**
     * Get builder-specific features
     *
     * @param string $builder Builder key
     * @return array Features
     */
    private function get_builder_features($builder)
    {
        $features = [
            'gutenberg' => [
                'block_editor' => true,
                'live_preview' => true,
                'responsive_controls' => true,
                'custom_css' => true,
                'dynamic_content' => true
            ],
            'elementor' => [
                'drag_drop' => true,
                'live_preview' => true,
                'responsive_controls' => true,
                'custom_css' => true,
                'animations' => true,
                'dynamic_content' => true
            ],
            'beaver-builder' => [
                'drag_drop' => true,
                'live_preview' => true,
                'responsive_controls' => true,
                'custom_css' => true,
                'templates' => true
            ],
            'divi' => [
                'visual_builder' => true,
                'live_preview' => true,
                'responsive_controls' => true,
                'custom_css' => true,
                'animations' => true,
                'split_testing' => true
            ],
            'visual-composer' => [
                'drag_drop' => true,
                'frontend_editor' => true,
                'responsive_controls' => true,
                'custom_css' => true,
                'templates' => true
            ],
            'oxygen' => [
                'visual_builder' => true,
                'live_preview' => true,
                'responsive_controls' => true,
                'custom_css' => true,
                'dynamic_data' => true
            ],
            'bricks' => [
                'visual_builder' => true,
                'live_preview' => true,
                'responsive_controls' => true,
                'custom_css' => true,
                'dynamic_data' => true
            ]
        ];
        
        return $features[$builder] ?? [];
    }

    /**
     * Generate page builder documentation
     *
     * @return array Documentation
     */
    public function get_documentation()
    {
        $docs = [];
        
        foreach ($this->active_integrations as $builder => $info) {
            $docs[$builder] = [
                'name' => $info['name'],
                'setup_guide' => $this->get_setup_guide($builder),
                'usage_examples' => $this->get_usage_examples($builder),
                'troubleshooting' => $this->get_troubleshooting_tips($builder)
            ];
        }
        
        return $docs;
    }

    /**
     * Get setup guide for builder
     *
     * @param string $builder Builder key
     * @return array Setup guide
     */
    private function get_setup_guide($builder)
    {
        $guides = [
            'gutenberg' => [
                'title' => __('Using ReactifyWP with Gutenberg', 'reactifywp'),
                'steps' => [
                    __('Create or edit a post/page', 'reactifywp'),
                    __('Click the "+" button to add a new block', 'reactifywp'),
                    __('Search for "ReactifyWP App" block', 'reactifywp'),
                    __('Select your React project from the dropdown', 'reactifywp'),
                    __('Configure display and advanced settings in the sidebar', 'reactifywp'),
                    __('Preview or publish your content', 'reactifywp')
                ]
            ],
            'elementor' => [
                'title' => __('Using ReactifyWP with Elementor', 'reactifywp'),
                'steps' => [
                    __('Edit a page with Elementor', 'reactifywp'),
                    __('Drag the "ReactifyWP App" widget from the General category', 'reactifywp'),
                    __('Select your React project in the Content tab', 'reactifywp'),
                    __('Adjust display settings and styling', 'reactifywp'),
                    __('Preview and publish your page', 'reactifywp')
                ]
            ]
        ];
        
        return $guides[$builder] ?? [];
    }

    /**
     * Get usage examples for builder
     *
     * @param string $builder Builder key
     * @return array Usage examples
     */
    private function get_usage_examples($builder)
    {
        // Implementation would return builder-specific examples
        return [];
    }

    /**
     * Get troubleshooting tips for builder
     *
     * @param string $builder Builder key
     * @return array Troubleshooting tips
     */
    private function get_troubleshooting_tips($builder)
    {
        // Implementation would return builder-specific troubleshooting tips
        return [];
    }
}
