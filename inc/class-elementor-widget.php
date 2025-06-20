<?php
/**
 * Elementor Widget for ReactifyWP
 *
 * @package ReactifyWP
 */

namespace ReactifyWP;

use Elementor\Widget_Base;
use Elementor\Controls_Manager;
use Elementor\Group_Control_Typography;
use Elementor\Group_Control_Background;
use Elementor\Group_Control_Border;
use Elementor\Group_Control_Box_Shadow;

/**
 * Elementor Widget class
 */
class ElementorWidget extends Widget_Base
{
    /**
     * Get widget name
     *
     * @return string Widget name
     */
    public function get_name()
    {
        return 'reactifywp-react-app';
    }

    /**
     * Get widget title
     *
     * @return string Widget title
     */
    public function get_title()
    {
        return __('ReactifyWP App', 'reactifywp');
    }

    /**
     * Get widget icon
     *
     * @return string Widget icon
     */
    public function get_icon()
    {
        return 'eicon-code';
    }

    /**
     * Get widget categories
     *
     * @return array Widget categories
     */
    public function get_categories()
    {
        return ['general'];
    }

    /**
     * Get widget keywords
     *
     * @return array Widget keywords
     */
    public function get_keywords()
    {
        return ['react', 'app', 'spa', 'javascript', 'reactifywp'];
    }

    /**
     * Register widget controls
     */
    protected function register_controls()
    {
        // Content Section
        $this->start_controls_section(
            'content_section',
            [
                'label' => __('React App', 'reactifywp'),
                'tab' => Controls_Manager::TAB_CONTENT,
            ]
        );

        // Project Selection
        $this->add_control(
            'project_slug',
            [
                'label' => __('Select Project', 'reactifywp'),
                'type' => Controls_Manager::SELECT,
                'options' => $this->get_projects_options(),
                'default' => '',
                'description' => __('Choose a React project to display', 'reactifywp'),
            ]
        );

        // Project Info
        $this->add_control(
            'project_info',
            [
                'type' => Controls_Manager::RAW_HTML,
                'raw' => '<div id="reactifywp-project-info"></div>',
                'condition' => [
                    'project_slug!' => '',
                ],
            ]
        );

        $this->end_controls_section();

        // Display Settings Section
        $this->start_controls_section(
            'display_section',
            [
                'label' => __('Display Settings', 'reactifywp'),
                'tab' => Controls_Manager::TAB_CONTENT,
            ]
        );

        $this->add_responsive_control(
            'width',
            [
                'label' => __('Width', 'reactifywp'),
                'type' => Controls_Manager::SLIDER,
                'size_units' => ['px', '%', 'vw'],
                'range' => [
                    'px' => [
                        'min' => 100,
                        'max' => 2000,
                    ],
                    '%' => [
                        'min' => 10,
                        'max' => 100,
                    ],
                ],
                'default' => [
                    'unit' => '%',
                    'size' => 100,
                ],
                'selectors' => [
                    '{{WRAPPER}} .reactifywp-elementor-widget' => 'width: {{SIZE}}{{UNIT}};',
                ],
            ]
        );

        $this->add_responsive_control(
            'height',
            [
                'label' => __('Height', 'reactifywp'),
                'type' => Controls_Manager::SLIDER,
                'size_units' => ['px', 'vh'],
                'range' => [
                    'px' => [
                        'min' => 100,
                        'max' => 1000,
                    ],
                    'vh' => [
                        'min' => 10,
                        'max' => 100,
                    ],
                ],
                'selectors' => [
                    '{{WRAPPER}} .reactifywp-elementor-widget' => 'height: {{SIZE}}{{UNIT}};',
                ],
            ]
        );

        $this->add_control(
            'theme',
            [
                'label' => __('Theme', 'reactifywp'),
                'type' => Controls_Manager::SELECT,
                'options' => [
                    'default' => __('Default', 'reactifywp'),
                    'light' => __('Light', 'reactifywp'),
                    'dark' => __('Dark', 'reactifywp'),
                    'auto' => __('Auto (System)', 'reactifywp'),
                ],
                'default' => 'default',
            ]
        );

        $this->add_control(
            'responsive',
            [
                'label' => __('Responsive', 'reactifywp'),
                'type' => Controls_Manager::SWITCHER,
                'label_on' => __('Yes', 'reactifywp'),
                'label_off' => __('No', 'reactifywp'),
                'return_value' => 'yes',
                'default' => 'yes',
                'description' => __('Make the app responsive to screen size', 'reactifywp'),
            ]
        );

        $this->end_controls_section();

        // Advanced Settings Section
        $this->start_controls_section(
            'advanced_section',
            [
                'label' => __('Advanced Settings', 'reactifywp'),
                'tab' => Controls_Manager::TAB_CONTENT,
            ]
        );

        $this->add_control(
            'loading',
            [
                'label' => __('Loading Strategy', 'reactifywp'),
                'type' => Controls_Manager::SELECT,
                'options' => [
                    'auto' => __('Auto', 'reactifywp'),
                    'lazy' => __('Lazy', 'reactifywp'),
                    'eager' => __('Eager', 'reactifywp'),
                ],
                'default' => 'auto',
                'description' => __('How the app should be loaded', 'reactifywp'),
            ]
        );

        $this->add_control(
            'fallback',
            [
                'label' => __('Fallback Content', 'reactifywp'),
                'type' => Controls_Manager::TEXTAREA,
                'placeholder' => __('Loading...', 'reactifywp'),
                'description' => __('HTML to show while loading or on error', 'reactifywp'),
            ]
        );

        $this->add_control(
            'container_id',
            [
                'label' => __('Container ID', 'reactifywp'),
                'type' => Controls_Manager::TEXT,
                'description' => __('Custom ID for the container element', 'reactifywp'),
            ]
        );

        $this->add_control(
            'error_boundary',
            [
                'label' => __('Error Boundary', 'reactifywp'),
                'type' => Controls_Manager::SWITCHER,
                'label_on' => __('Yes', 'reactifywp'),
                'label_off' => __('No', 'reactifywp'),
                'return_value' => 'yes',
                'default' => 'yes',
                'description' => __('Show user-friendly errors instead of breaking', 'reactifywp'),
            ]
        );

        $this->add_control(
            'preload',
            [
                'label' => __('Preload Assets', 'reactifywp'),
                'type' => Controls_Manager::SWITCHER,
                'label_on' => __('Yes', 'reactifywp'),
                'label_off' => __('No', 'reactifywp'),
                'return_value' => 'yes',
                'default' => '',
                'description' => __('Preload app assets for faster loading', 'reactifywp'),
            ]
        );

        $this->add_control(
            'cache',
            [
                'label' => __('Enable Caching', 'reactifywp'),
                'type' => Controls_Manager::SWITCHER,
                'label_on' => __('Yes', 'reactifywp'),
                'label_off' => __('No', 'reactifywp'),
                'return_value' => 'yes',
                'default' => 'yes',
                'description' => __('Cache app assets for better performance', 'reactifywp'),
            ]
        );

        $this->add_control(
            'ssr',
            [
                'label' => __('Server-Side Rendering', 'reactifywp'),
                'type' => Controls_Manager::SWITCHER,
                'label_on' => __('Yes', 'reactifywp'),
                'label_off' => __('No', 'reactifywp'),
                'return_value' => 'yes',
                'default' => '',
                'description' => __('Enable SSR if supported by the app', 'reactifywp'),
            ]
        );

        $this->add_control(
            'debug',
            [
                'label' => __('Debug Mode', 'reactifywp'),
                'type' => Controls_Manager::SWITCHER,
                'label_on' => __('Yes', 'reactifywp'),
                'label_off' => __('No', 'reactifywp'),
                'return_value' => 'yes',
                'default' => '',
                'description' => __('Show debug information (admin only)', 'reactifywp'),
            ]
        );

        $this->add_control(
            'props',
            [
                'label' => __('Props (JSON)', 'reactifywp'),
                'type' => Controls_Manager::TEXTAREA,
                'placeholder' => '{"key": "value"}',
                'description' => __('JSON object with props to pass to the app', 'reactifywp'),
            ]
        );

        $this->add_control(
            'config',
            [
                'label' => __('Config (JSON)', 'reactifywp'),
                'type' => Controls_Manager::TEXTAREA,
                'placeholder' => '{"option": "value"}',
                'description' => __('JSON object with configuration options', 'reactifywp'),
            ]
        );

        $this->end_controls_section();

        // Style Section
        $this->start_controls_section(
            'style_section',
            [
                'label' => __('Container Style', 'reactifywp'),
                'tab' => Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_group_control(
            Group_Control_Background::get_type(),
            [
                'name' => 'background',
                'label' => __('Background', 'reactifywp'),
                'types' => ['classic', 'gradient'],
                'selector' => '{{WRAPPER}} .reactifywp-elementor-widget',
            ]
        );

        $this->add_group_control(
            Group_Control_Border::get_type(),
            [
                'name' => 'border',
                'label' => __('Border', 'reactifywp'),
                'selector' => '{{WRAPPER}} .reactifywp-elementor-widget',
            ]
        );

        $this->add_responsive_control(
            'border_radius',
            [
                'label' => __('Border Radius', 'reactifywp'),
                'type' => Controls_Manager::DIMENSIONS,
                'size_units' => ['px', '%'],
                'selectors' => [
                    '{{WRAPPER}} .reactifywp-elementor-widget' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );

        $this->add_group_control(
            Group_Control_Box_Shadow::get_type(),
            [
                'name' => 'box_shadow',
                'label' => __('Box Shadow', 'reactifywp'),
                'selector' => '{{WRAPPER}} .reactifywp-elementor-widget',
            ]
        );

        $this->add_responsive_control(
            'padding',
            [
                'label' => __('Padding', 'reactifywp'),
                'type' => Controls_Manager::DIMENSIONS,
                'size_units' => ['px', 'em', '%'],
                'selectors' => [
                    '{{WRAPPER}} .reactifywp-elementor-widget' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );

        $this->add_responsive_control(
            'margin',
            [
                'label' => __('Margin', 'reactifywp'),
                'type' => Controls_Manager::DIMENSIONS,
                'size_units' => ['px', 'em', '%'],
                'selectors' => [
                    '{{WRAPPER}} .reactifywp-elementor-widget' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );

        $this->end_controls_section();
    }

    /**
     * Render widget output on the frontend
     */
    protected function render()
    {
        $settings = $this->get_settings_for_display();

        if (empty($settings['project_slug'])) {
            if (\Elementor\Plugin::$instance->editor->is_edit_mode()) {
                echo '<div class="reactifywp-elementor-placeholder">';
                echo '<div class="placeholder-icon">';
                echo '<i class="eicon-code" style="font-size: 48px; color: #ccc;"></i>';
                echo '</div>';
                echo '<p>' . __('Select a React project to display', 'reactifywp') . '</p>';
                echo '</div>';
            }
            return;
        }

        // Build shortcode attributes
        $shortcode_atts = [
            'slug' => $settings['project_slug']
        ];

        // Map Elementor settings to shortcode attributes
        $mapping = [
            'theme' => 'theme',
            'loading' => 'loading',
            'fallback' => 'fallback',
            'container_id' => 'container_id',
            'props' => 'props',
            'config' => 'config'
        ];

        foreach ($mapping as $elementor_key => $shortcode_key) {
            if (!empty($settings[$elementor_key])) {
                $shortcode_atts[$shortcode_key] = $settings[$elementor_key];
            }
        }

        // Handle boolean settings
        $boolean_mapping = [
            'responsive' => 'responsive',
            'error_boundary' => 'error_boundary',
            'preload' => 'preload',
            'cache' => 'cache',
            'ssr' => 'ssr',
            'debug' => 'debug'
        ];

        foreach ($boolean_mapping as $elementor_key => $shortcode_key) {
            if ($settings[$elementor_key] === 'yes') {
                $shortcode_atts[$shortcode_key] = 'true';
            } elseif (isset($settings[$elementor_key])) {
                $shortcode_atts[$shortcode_key] = 'false';
            }
        }

        // Build shortcode string
        $shortcode_string = '[reactify';
        foreach ($shortcode_atts as $key => $value) {
            $shortcode_string .= ' ' . $key . '="' . esc_attr($value) . '"';
        }
        $shortcode_string .= ']';

        // Render with wrapper
        echo '<div class="reactifywp-elementor-widget">';
        echo do_shortcode($shortcode_string);
        echo '</div>';
    }

    /**
     * Get projects options for select control
     *
     * @return array Projects options
     */
    private function get_projects_options()
    {
        $options = ['' => __('— Select Project —', 'reactifywp')];

        $project = new Project();
        $projects = $project->get_all();

        foreach ($projects as $proj) {
            $options[$proj->slug] = $proj->name;
        }

        return $options;
    }

    /**
     * Render widget output in the editor
     */
    protected function content_template()
    {
        ?>
        <#
        if (settings.project_slug) {
            var shortcodeAtts = 'slug="' + settings.project_slug + '"';
            
            if (settings.theme && settings.theme !== 'default') {
                shortcodeAtts += ' theme="' + settings.theme + '"';
            }
            
            if (settings.loading && settings.loading !== 'auto') {
                shortcodeAtts += ' loading="' + settings.loading + '"';
            }
            
            if (settings.responsive === 'yes') {
                shortcodeAtts += ' responsive="true"';
            }
            
            if (settings.error_boundary === 'yes') {
                shortcodeAtts += ' error_boundary="true"';
            }
            
            if (settings.preload === 'yes') {
                shortcodeAtts += ' preload="true"';
            }
            
            if (settings.debug === 'yes') {
                shortcodeAtts += ' debug="true"';
            }
        #>
            <div class="reactifywp-elementor-widget">
                <div class="reactifywp-elementor-preview">
                    <div class="preview-icon">
                        <i class="eicon-code"></i>
                    </div>
                    <h4>ReactifyWP App</h4>
                    <p>Project: {{ settings.project_slug }}</p>
                    <small>Shortcode: [reactify {{ shortcodeAtts }}]</small>
                </div>
            </div>
        <# } else { #>
            <div class="reactifywp-elementor-placeholder">
                <div class="placeholder-icon">
                    <i class="eicon-code"></i>
                </div>
                <p><?php echo __('Select a React project to display', 'reactifywp'); ?></p>
            </div>
        <# } #>
        <?php
    }
}
