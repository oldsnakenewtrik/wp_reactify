<?php
/**
 * Plugin Name: ReactifyWP
 * Plugin URI: https://github.com/your-username/reactifywp
 * Description: Democratise React on WordPress: one-click deployment of any compiled React SPA/MPA without touching the theme or server.
 * Version: 1.0.0
 * Author: Your Name
 * Author URI: https://yourwebsite.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: reactifywp
 * Domain Path: /languages
 * Requires at least: 6.5
 * Tested up to: 6.7
 * Requires PHP: 7.4
 * Network: true
 *
 * @package ReactifyWP
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('REACTIFYWP_VERSION', '1.0.0');
define('REACTIFYWP_PLUGIN_FILE', __FILE__);
define('REACTIFYWP_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('REACTIFYWP_PLUGIN_URL', plugin_dir_url(__FILE__));
define('REACTIFYWP_PLUGIN_BASENAME', plugin_basename(__FILE__));

// Autoloader - try Composer first, fallback to manual loading
if (file_exists(REACTIFYWP_PLUGIN_DIR . 'vendor/autoload.php')) {
    require_once REACTIFYWP_PLUGIN_DIR . 'vendor/autoload.php';
} else {
    // Manual class loading for production
    spl_autoload_register(function ($class) {
        // Only load ReactifyWP classes
        if (strpos($class, 'ReactifyWP\\') !== 0) {
            return;
        }

        // Convert class name to file path
        $class_name = str_replace('ReactifyWP\\', '', $class);

        // Handle different naming patterns
        $possible_files = [
            'class-' . strtolower(str_replace('_', '-', preg_replace('/([a-z])([A-Z])/', '$1-$2', $class_name))) . '.php',
            'class-' . strtolower($class_name) . '.php',
            strtolower($class_name) . '.php'
        ];

        foreach ($possible_files as $file_name) {
            $file_path = REACTIFYWP_PLUGIN_DIR . 'inc/' . $file_name;
            if (file_exists($file_path)) {
                require_once $file_path;
                return;
            }
        }

        // Log missing class for debugging
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log("ReactifyWP: Could not load class $class");
        }
    });
}

/**
 * Main ReactifyWP Plugin Class
 */
class ReactifyWP
{
    /**
     * Plugin instance
     *
     * @var ReactifyWP
     */
    private static $instance = null;

    /**
     * Admin instance
     *
     * @var ReactifyWP\Admin
     */
    public $admin;

    /**
     * Database manager instance
     *
     * @var ReactifyWP\Database
     */
    public $database;

    /**
     * Asset manager instance
     *
     * @var ReactifyWP\AssetManager
     */
    public $asset_manager;

    /**
     * Frontend optimizer instance
     *
     * @var ReactifyWP\FrontendOptimizer
     */
    public $frontend_optimizer;

    /**
     * File uploader instance
     *
     * @var ReactifyWP\FileUploader
     */
    public $file_uploader;

    /**
     * Security validator instance
     *
     * @var ReactifyWP\SecurityValidator
     */
    public $security_validator;

    /**
     * ZIP extractor instance
     *
     * @var ReactifyWP\ZipExtractor
     */
    public $zip_extractor;

    /**
     * File manager instance
     *
     * @var ReactifyWP\FileManager
     */
    public $file_manager;

    /**
     * Error handler instance
     *
     * @var ReactifyWP\ErrorHandler
     */
    public $error_handler;

    /**
     * React integration instance
     *
     * @var ReactifyWP\ReactIntegration
     */
    public $react_integration;

    /**
     * Page builder integration instance
     *
     * @var ReactifyWP\PageBuilderIntegration
     */
    public $page_builder_integration;

    /**
     * Performance optimizer instance
     *
     * @var ReactifyWP\PerformanceOptimizer
     */
    public $performance_optimizer;

    /**
     * CDN manager instance
     *
     * @var ReactifyWP\CDNManager
     */
    public $cdn_manager;

    /**
     * Debug manager instance
     *
     * @var ReactifyWP\DebugManager
     */
    public $debug_manager;

    /**
     * Settings manager instance
     *
     * @var ReactifyWP\Settings
     */
    public $settings;

    /**
     * Help system instance
     *
     * @var ReactifyWP\HelpSystem
     */
    public $help_system;

    /**
     * Project templates instance
     *
     * @var ReactifyWP\ProjectTemplates
     */
    public $project_templates;

    /**
     * Project manager instance
     *
     * @var ReactifyWP\Project
     */
    public $project;

    /**
     * Shortcode handler instance
     *
     * @var ReactifyWP\Shortcode
     */
    public $shortcode;

    /**
     * CLI handler instance
     *
     * @var ReactifyWP\CLI
     */
    public $cli;

    /**
     * Get plugin instance
     *
     * @return ReactifyWP
     */
    public static function instance()
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct()
    {
        $this->init_hooks();
    }

    /**
     * Initialize WordPress hooks
     */
    private function init_hooks()
    {
        add_action('init', [$this, 'init']);
        add_action('plugins_loaded', [$this, 'load_textdomain']);
        
        // Activation and deactivation hooks
        register_activation_hook(__FILE__, [$this, 'activate']);
        register_deactivation_hook(__FILE__, [$this, 'deactivate']);
        
        // Multisite hooks
        add_action('wpmu_new_blog', [$this, 'activate_new_site']);
        add_action('activate_blog', [$this, 'activate_new_site']);
    }

    /**
     * Initialize plugin components
     */
    public function init()
    {
        // Initialize core components (only if classes exist)
        $this->init_core_components();

        // Initialize optional components
        $this->init_optional_components();

        // Initialize integrations
        $this->init_integrations();
    }

    /**
     * Initialize core components that are required
     */
    private function init_core_components()
    {
        // Essential components
        if (class_exists('ReactifyWP\Database')) {
            $this->database = new ReactifyWP\Database();
        }

        if (class_exists('ReactifyWP\Settings')) {
            $this->settings = new ReactifyWP\Settings();
        }

        if (class_exists('ReactifyWP\Admin')) {
            $this->admin = new ReactifyWP\Admin();
        }

        if (class_exists('ReactifyWP\Project')) {
            $this->project = new ReactifyWP\Project();
        }

        if (class_exists('ReactifyWP\Shortcode')) {
            $this->shortcode = new ReactifyWP\Shortcode();
        }

        if (class_exists('ReactifyWP\ErrorHandler')) {
            $this->error_handler = new ReactifyWP\ErrorHandler();
        }
    }

    /**
     * Initialize optional components
     */
    private function init_optional_components()
    {
        // Upload and file management
        if (class_exists('ReactifyWP\FileUploader')) {
            $this->file_uploader = new ReactifyWP\FileUploader();
        }

        if (class_exists('ReactifyWP\SecurityValidator')) {
            $this->security_validator = new ReactifyWP\SecurityValidator();
        }

        if (class_exists('ReactifyWP\ZipExtractor')) {
            $this->zip_extractor = new ReactifyWP\ZipExtractor();
        }

        // Asset management
        if (class_exists('ReactifyWP\AssetManager')) {
            $this->asset_manager = new ReactifyWP\AssetManager();
        }

        // Performance and optimization
        if (class_exists('ReactifyWP\FrontendOptimizer')) {
            $this->frontend_optimizer = new ReactifyWP\FrontendOptimizer();
        }

        if (class_exists('ReactifyWP\PerformanceOptimizer')) {
            $this->performance_optimizer = new ReactifyWP\PerformanceOptimizer();
        }

        if (class_exists('ReactifyWP\CDNManager')) {
            $this->cdn_manager = new ReactifyWP\CDNManager();
        }

        // Other optional components
        if (class_exists('ReactifyWP\FileManager')) {
            $this->file_manager = new ReactifyWP\FileManager();
        }

        if (class_exists('ReactifyWP\ReactIntegration')) {
            $this->react_integration = new ReactifyWP\ReactIntegration();
        }

        if (class_exists('ReactifyWP\PageBuilderIntegration')) {
            $this->page_builder_integration = new ReactifyWP\PageBuilderIntegration();
        }

        if (class_exists('ReactifyWP\DebugManager')) {
            $this->debug_manager = new ReactifyWP\DebugManager();
        }

        if (class_exists('ReactifyWP\HelpSystem')) {
            $this->help_system = new ReactifyWP\HelpSystem();
        }

        if (class_exists('ReactifyWP\ProjectTemplates')) {
            $this->project_templates = new ReactifyWP\ProjectTemplates();
        }

        // Initialize CLI if WP-CLI is available
        if (defined('WP_CLI') && WP_CLI && class_exists('ReactifyWP\CLI')) {
            $this->cli = new ReactifyWP\CLI();
        }

        // Apply environment-specific settings if available
        if ($this->settings && method_exists($this->settings, 'apply_environment_settings')) {
            $this->settings->apply_environment_settings();
        }
    }

    /**
     * Initialize third-party integrations
     */
    private function init_integrations()
    {
        // Elementor integration
        if (did_action('elementor/loaded')) {
            new ReactifyWP\Integrations\Elementor();
        }

        // Beaver Builder integration
        if (class_exists('FLBuilder')) {
            new ReactifyWP\Integrations\BeaverBuilder();
        }
    }

    /**
     * Load plugin textdomain for internationalization
     */
    public function load_textdomain()
    {
        load_plugin_textdomain(
            'reactifywp',
            false,
            dirname(REACTIFYWP_PLUGIN_BASENAME) . '/languages'
        );
    }

    /**
     * Plugin activation
     */
    public static function activate()
    {
        // Create database tables
        self::create_tables_static();

        // Create upload directories
        self::create_upload_directories_static();

        // Set default options
        self::set_default_options_static();

        // Flush rewrite rules
        flush_rewrite_rules();
    }

    /**
     * Plugin deactivation
     */
    public static function deactivate()
    {
        // Flush rewrite rules
        flush_rewrite_rules();
    }

    /**
     * Activate plugin for new multisite blog
     *
     * @param int $blog_id Blog ID
     */
    public static function activate_new_site($blog_id)
    {
        if (is_plugin_active_for_network(REACTIFYWP_PLUGIN_BASENAME)) {
            switch_to_blog($blog_id);
            self::activate();
            restore_current_blog();
        }
    }

    /**
     * Create database tables
     */
    private function create_tables()
    {
        $database = new ReactifyWP\Database();
        $database->create_tables();
    }

    /**
     * Create database tables (static version for activation)
     */
    private static function create_tables_static()
    {
        $database = new ReactifyWP\Database();
        $database->create_tables();
    }

    /**
     * Create upload directories
     */
    private function create_upload_directories()
    {
        $upload_dir = wp_upload_dir();
        $blog_id = get_current_blog_id();
        $reactify_dir = $upload_dir['basedir'] . '/reactify-projects/' . $blog_id;

        if (!file_exists($reactify_dir)) {
            wp_mkdir_p($reactify_dir);

            // Create .htaccess for security
            $htaccess_content = "# Protect ReactifyWP uploads\n";
            $htaccess_content .= "<Files *.php>\n";
            $htaccess_content .= "deny from all\n";
            $htaccess_content .= "</Files>\n";

            file_put_contents($reactify_dir . '/.htaccess', $htaccess_content);
        }
    }

    /**
     * Create upload directories (static version for activation)
     */
    private static function create_upload_directories_static()
    {
        $upload_dir = wp_upload_dir();
        $blog_id = get_current_blog_id();
        $reactify_dir = $upload_dir['basedir'] . '/reactify-projects/' . $blog_id;

        if (!file_exists($reactify_dir)) {
            wp_mkdir_p($reactify_dir);

            // Create .htaccess for security
            $htaccess_content = "# Protect ReactifyWP uploads\n";
            $htaccess_content .= "<Files *.php>\n";
            $htaccess_content .= "deny from all\n";
            $htaccess_content .= "</Files>\n";

            file_put_contents($reactify_dir . '/.htaccess', $htaccess_content);
        }
    }

    /**
     * Set default plugin options
     */
    private function set_default_options()
    {
        $default_options = [
            'max_upload_size' => '50MB',
            'allowed_file_types' => ['zip'],
            'enable_scoped_styles' => true,
            'enable_cache_busting' => true,
            'defer_js_loading' => true,
        ];

        add_option('reactifywp_options', $default_options);
    }

    /**
     * Set default plugin options (static version for activation)
     */
    private static function set_default_options_static()
    {
        $default_options = [
            'max_upload_size' => '50MB',
            'allowed_file_types' => ['zip'],
            'enable_scoped_styles' => true,
            'enable_cache_busting' => true,
            'defer_js_loading' => true,
        ];

        add_option('reactifywp_options', $default_options);
    }
}

/**
 * Initialize the plugin
 */
function reactifywp()
{
    return ReactifyWP::instance();
}

// Plugin activation hook
register_activation_hook(__FILE__, ['ReactifyWP', 'activate']);

// Plugin deactivation hook
register_deactivation_hook(__FILE__, ['ReactifyWP', 'deactivate']);

// Multisite new blog activation
add_action('wpmu_new_blog', ['ReactifyWP', 'activate_new_site']);

// Start the plugin
reactifywp();
