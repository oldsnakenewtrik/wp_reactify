<?php
/**
 * Help and documentation system for ReactifyWP
 *
 * @package ReactifyWP
 */

namespace ReactifyWP;

/**
 * Help System class
 */
class HelpSystem
{
    /**
     * Help content
     */
    const HELP_CONTENT = [
        'overview' => [
            'title' => 'ReactifyWP Overview',
            'content' => 'ReactifyWP allows you to easily deploy React applications on your WordPress site without modifying themes or server configuration. Simply upload your built React app as a ZIP file and embed it anywhere using shortcodes.',
            'video' => 'https://example.com/overview-video',
            'docs' => 'https://docs.reactifywp.com/overview'
        ],
        'upload' => [
            'title' => 'Uploading React Apps',
            'content' => 'To upload a React app: 1) Build your React app for production, 2) ZIP the build folder contents, 3) Drag and drop the ZIP file or click to browse, 4) Fill in project details, 5) Click Upload.',
            'video' => 'https://example.com/upload-video',
            'docs' => 'https://docs.reactifywp.com/upload'
        ],
        'shortcodes' => [
            'title' => 'Using Shortcodes',
            'content' => 'Embed your React apps using the [reactify slug="your-app"] shortcode. You can add custom CSS classes and styles: [reactify slug="calculator" class="my-class" style="margin: 20px;"]',
            'video' => 'https://example.com/shortcodes-video',
            'docs' => 'https://docs.reactifywp.com/shortcodes'
        ],
        'troubleshooting' => [
            'title' => 'Troubleshooting',
            'content' => 'Common issues: 1) App not loading - check console for errors, 2) Styles not working - enable scoped styles, 3) Upload fails - check file size and format, 4) Blank screen - verify React app builds correctly.',
            'video' => 'https://example.com/troubleshooting-video',
            'docs' => 'https://docs.reactifywp.com/troubleshooting'
        ]
    ];

    /**
     * Tooltips content
     */
    const TOOLTIPS = [
        'project-slug' => 'A unique identifier for your project. Use lowercase letters, numbers, and hyphens only.',
        'project-name' => 'Display name for your project. This will appear in the admin interface.',
        'shortcode-name' => 'Custom shortcode name. If empty, the project slug will be used.',
        'scoped-styles' => 'Wraps your React app in a scoped container to prevent CSS conflicts with your theme.',
        'cache-busting' => 'Adds version parameters to asset URLs to ensure browsers load the latest files.',
        'defer-js' => 'Loads JavaScript files with the defer attribute for better page performance.',
        'max-upload-size' => 'Maximum size allowed for uploaded ZIP files. Larger files may cause timeouts.'
    ];

    /**
     * Constructor
     */
    public function __construct()
    {
        add_action('admin_enqueue_scripts', [$this, 'enqueue_help_assets']);
        add_action('wp_ajax_reactifywp_get_help', [$this, 'handle_get_help']);
        add_action('wp_ajax_reactifywp_dismiss_tour', [$this, 'handle_dismiss_tour']);
        add_action('admin_footer', [$this, 'render_help_modal']);
    }

    /**
     * Enqueue help system assets
     *
     * @param string $hook Current admin page hook
     */
    public function enqueue_help_assets($hook)
    {
        if ('settings_page_reactifywp' !== $hook) {
            return;
        }

        wp_enqueue_script(
            'reactifywp-help',
            REACTIFYWP_PLUGIN_URL . 'assets/dist/help.js',
            ['jquery'],
            REACTIFYWP_VERSION,
            true
        );

        wp_enqueue_style(
            'reactifywp-help',
            REACTIFYWP_PLUGIN_URL . 'assets/dist/help.css',
            [],
            REACTIFYWP_VERSION
        );

        wp_localize_script('reactifywp-help', 'reactifyWPHelp', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('reactifywp_help'),
            'showTour' => !get_user_meta(get_current_user_id(), 'reactifywp_tour_dismissed', true),
            'strings' => [
                'helpTitle' => __('ReactifyWP Help', 'reactifywp'),
                'closeHelp' => __('Close Help', 'reactifywp'),
                'nextStep' => __('Next', 'reactifywp'),
                'prevStep' => __('Previous', 'reactifywp'),
                'finishTour' => __('Finish Tour', 'reactifywp'),
                'skipTour' => __('Skip Tour', 'reactifywp'),
                'startTour' => __('Start Tour', 'reactifywp'),
                'viewDocs' => __('View Documentation', 'reactifywp'),
                'watchVideo' => __('Watch Video', 'reactifywp')
            ],
            'tooltips' => self::TOOLTIPS,
            'tourSteps' => $this->get_tour_steps()
        ]);
    }

    /**
     * Get guided tour steps
     *
     * @return array Tour steps
     */
    private function get_tour_steps()
    {
        return [
            [
                'target' => '.reactifywp-upload-dropzone',
                'title' => __('Upload Your React App', 'reactifywp'),
                'content' => __('Start by dragging and dropping your React app ZIP file here, or click to browse for files.', 'reactifywp'),
                'position' => 'bottom'
            ],
            [
                'target' => '#reactifywp-slug',
                'title' => __('Project Slug', 'reactifywp'),
                'content' => __('Enter a unique slug for your project. This will be used in shortcodes and URLs.', 'reactifywp'),
                'position' => 'top'
            ],
            [
                'target' => '.reactifywp-projects-section',
                'title' => __('Manage Projects', 'reactifywp'),
                'content' => __('View and manage all your uploaded React projects here. You can edit, duplicate, or delete projects.', 'reactifywp'),
                'position' => 'top'
            ],
            [
                'target' => '.reactifywp-shortcode',
                'title' => __('Copy Shortcodes', 'reactifywp'),
                'content' => __('Click the copy button to copy the shortcode, then paste it in any post, page, or widget.', 'reactifywp'),
                'position' => 'left'
            ],
            [
                'target' => '.reactifywp-settings-section',
                'title' => __('Configure Settings', 'reactifywp'),
                'content' => __('Adjust global settings like upload limits, caching, and performance options.', 'reactifywp'),
                'position' => 'top'
            ]
        ];
    }

    /**
     * Handle get help AJAX request
     */
    public function handle_get_help()
    {
        check_ajax_referer('reactifywp_help', 'nonce');

        $topic = sanitize_text_field($_POST['topic'] ?? 'overview');

        if (!isset(self::HELP_CONTENT[$topic])) {
            wp_send_json_error(__('Help topic not found.', 'reactifywp'));
        }

        wp_send_json_success([
            'content' => self::HELP_CONTENT[$topic]
        ]);
    }

    /**
     * Handle dismiss tour AJAX request
     */
    public function handle_dismiss_tour()
    {
        check_ajax_referer('reactifywp_help', 'nonce');

        update_user_meta(get_current_user_id(), 'reactifywp_tour_dismissed', true);

        wp_send_json_success([
            'message' => __('Tour dismissed successfully.', 'reactifywp')
        ]);
    }

    /**
     * Render help modal
     */
    public function render_help_modal()
    {
        $screen = get_current_screen();
        
        if (!$screen || $screen->id !== 'settings_page_reactifywp') {
            return;
        }
        ?>
        <div id="reactifywp-help-modal" class="reactifywp-modal" style="display: none;">
            <div class="reactifywp-modal-content">
                <div class="reactifywp-modal-header">
                    <h2 id="reactifywp-help-title"><?php esc_html_e('ReactifyWP Help', 'reactifywp'); ?></h2>
                    <button type="button" class="reactifywp-modal-close" id="reactifywp-help-close">
                        <span class="dashicons dashicons-no"></span>
                    </button>
                </div>
                <div class="reactifywp-modal-body">
                    <div class="reactifywp-help-nav">
                        <ul>
                            <li><a href="#" data-topic="overview" class="active"><?php esc_html_e('Overview', 'reactifywp'); ?></a></li>
                            <li><a href="#" data-topic="upload"><?php esc_html_e('Uploading Apps', 'reactifywp'); ?></a></li>
                            <li><a href="#" data-topic="shortcodes"><?php esc_html_e('Using Shortcodes', 'reactifywp'); ?></a></li>
                            <li><a href="#" data-topic="troubleshooting"><?php esc_html_e('Troubleshooting', 'reactifywp'); ?></a></li>
                        </ul>
                    </div>
                    <div class="reactifywp-help-content">
                        <div id="reactifywp-help-text">
                            <?php echo wp_kses_post(self::HELP_CONTENT['overview']['content']); ?>
                        </div>
                        <div class="reactifywp-help-actions">
                            <a href="#" id="reactifywp-help-video" class="button" target="_blank">
                                <span class="dashicons dashicons-video-alt3"></span>
                                <?php esc_html_e('Watch Video', 'reactifywp'); ?>
                            </a>
                            <a href="#" id="reactifywp-help-docs" class="button button-primary" target="_blank">
                                <span class="dashicons dashicons-book"></span>
                                <?php esc_html_e('View Documentation', 'reactifywp'); ?>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div id="reactifywp-tour-overlay" class="reactifywp-tour-overlay" style="display: none;">
            <div class="reactifywp-tour-tooltip">
                <div class="reactifywp-tour-content">
                    <h3 id="reactifywp-tour-title"></h3>
                    <p id="reactifywp-tour-text"></p>
                </div>
                <div class="reactifywp-tour-actions">
                    <button type="button" id="reactifywp-tour-skip" class="button">
                        <?php esc_html_e('Skip Tour', 'reactifywp'); ?>
                    </button>
                    <div class="reactifywp-tour-navigation">
                        <button type="button" id="reactifywp-tour-prev" class="button" style="display: none;">
                            <?php esc_html_e('Previous', 'reactifywp'); ?>
                        </button>
                        <button type="button" id="reactifywp-tour-next" class="button button-primary">
                            <?php esc_html_e('Next', 'reactifywp'); ?>
                        </button>
                    </div>
                </div>
                <div class="reactifywp-tour-progress">
                    <span id="reactifywp-tour-step">1</span> / <span id="reactifywp-tour-total">5</span>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Get contextual help for specific elements
     *
     * @param string $element Element identifier
     * @return string|null Help text or null
     */
    public function get_contextual_help($element)
    {
        return self::TOOLTIPS[$element] ?? null;
    }

    /**
     * Add help button to admin page
     *
     * @param string $page_title Page title
     * @return string Modified page title with help button
     */
    public function add_help_button($page_title)
    {
        $help_button = sprintf(
            '<button type="button" class="button reactifywp-help-trigger" id="reactifywp-help-button" title="%s">
                <span class="dashicons dashicons-editor-help"></span>
                %s
            </button>',
            esc_attr__('Get Help', 'reactifywp'),
            esc_html__('Help', 'reactifywp')
        );

        return $page_title . ' ' . $help_button;
    }

    /**
     * Generate FAQ content
     *
     * @return array FAQ items
     */
    public function get_faq()
    {
        return [
            [
                'question' => __('What React apps are supported?', 'reactifywp'),
                'answer' => __('ReactifyWP supports any React application that can be built for production. This includes Create React App, Next.js static exports, Vite builds, and custom Webpack configurations.', 'reactifywp')
            ],
            [
                'question' => __('How do I prepare my React app for upload?', 'reactifywp'),
                'answer' => __('Build your React app for production using your build tool (npm run build, yarn build, etc.), then ZIP the contents of the build/dist folder (not the folder itself).', 'reactifywp')
            ],
            [
                'question' => __('Can I use multiple React apps on one page?', 'reactifywp'),
                'answer' => __('Yes! You can embed multiple React apps on the same page using different shortcodes. Each app runs independently in its own container.', 'reactifywp')
            ],
            [
                'question' => __('Will React apps conflict with my theme?', 'reactifywp'),
                'answer' => __('ReactifyWP includes scoped styles option to prevent CSS conflicts. Enable this setting to wrap each app in an isolated container.', 'reactifywp')
            ],
            [
                'question' => __('How do I update a React app?', 'reactifywp'),
                'answer' => __('Simply upload a new version with the same slug. The old version will be replaced automatically, and the shortcode will display the updated app.', 'reactifywp')
            ],
            [
                'question' => __('Is ReactifyWP compatible with page builders?', 'reactifywp'),
                'answer' => __('Yes! ReactifyWP works with Elementor, Gutenberg, and other page builders. Use the shortcode in text widgets or custom HTML blocks.', 'reactifywp')
            ]
        ];
    }

    /**
     * Generate troubleshooting guide
     *
     * @return array Troubleshooting items
     */
    public function get_troubleshooting_guide()
    {
        return [
            [
                'issue' => __('React app shows blank screen', 'reactifywp'),
                'solution' => __('Check browser console for JavaScript errors. Ensure your React app builds correctly and uses relative paths for assets.', 'reactifywp')
            ],
            [
                'issue' => __('Styles are not loading correctly', 'reactifywp'),
                'solution' => __('Enable "Scoped Styles" in settings to prevent theme conflicts. Check that CSS files are included in your build.', 'reactifywp')
            ],
            [
                'issue' => __('Upload fails with large files', 'reactifywp'),
                'solution' => __('Increase the maximum upload size in settings or optimize your React app build to reduce file size.', 'reactifywp')
            ],
            [
                'issue' => __('App works locally but not on WordPress', 'reactifywp'),
                'solution' => __('Ensure your React app uses relative paths and doesn\'t rely on specific server configurations. Check for hardcoded URLs.', 'reactifywp')
            ],
            [
                'issue' => __('Shortcode displays raw text instead of app', 'reactifywp'),
                'solution' => __('Verify the project slug is correct and the project status is "Active". Check that the shortcode syntax is correct.', 'reactifywp')
            ]
        ];
    }
}
