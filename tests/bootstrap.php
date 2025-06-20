<?php
/**
 * PHPUnit bootstrap file for ReactifyWP plugin tests
 *
 * @package ReactifyWP
 */

// Define testing environment
define('REACTIFYWP_TESTING', true);

// WordPress test environment
$_tests_dir = getenv('WP_TESTS_DIR');

if (!$_tests_dir) {
    $_tests_dir = rtrim(sys_get_temp_dir(), '/\\') . '/wordpress-tests-lib';
}

if (!file_exists($_tests_dir . '/includes/functions.php')) {
    echo "Could not find $_tests_dir/includes/functions.php, have you run tests/bin/install-wp-tests.sh ?" . PHP_EOL; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    exit(1);
}

// Give access to tests_add_filter() function
require_once $_tests_dir . '/includes/functions.php';

/**
 * Manually load the plugin being tested
 */
function _manually_load_plugin()
{
    require dirname(dirname(__FILE__)) . '/reactifywp.php';
}

tests_add_filter('muplugins_loaded', '_manually_load_plugin');

// Start up the WP testing environment
require $_tests_dir . '/includes/bootstrap.php';

// Load Brain Monkey for mocking WordPress functions
require_once dirname(__FILE__) . '/../vendor/autoload.php';

// Initialize Brain Monkey
\Brain\Monkey\setUp();

// Clean up after each test
register_shutdown_function(function () {
    \Brain\Monkey\tearDown();
});

/**
 * Base test case class for ReactifyWP tests
 */
abstract class ReactifyWP_Test_Case extends WP_UnitTestCase
{
    /**
     * Set up test environment
     */
    public function setUp(): void
    {
        parent::setUp();
        \Brain\Monkey\setUp();
        
        // Create test upload directory
        $this->create_test_upload_dir();
        
        // Set up test data
        $this->set_up_test_data();
    }

    /**
     * Tear down test environment
     */
    public function tearDown(): void
    {
        \Brain\Monkey\tearDown();
        
        // Clean up test data
        $this->clean_up_test_data();
        
        parent::tearDown();
    }

    /**
     * Create test upload directory
     */
    protected function create_test_upload_dir()
    {
        $upload_dir = wp_upload_dir();
        $test_dir = $upload_dir['basedir'] . '/reactify-projects-test';
        
        if (!file_exists($test_dir)) {
            wp_mkdir_p($test_dir);
        }
    }

    /**
     * Set up test data
     */
    protected function set_up_test_data()
    {
        // Override upload directory for testing
        add_filter('upload_dir', [$this, 'filter_upload_dir']);
    }

    /**
     * Clean up test data
     */
    protected function clean_up_test_data()
    {
        // Remove test upload directory
        $upload_dir = wp_upload_dir();
        $test_dir = $upload_dir['basedir'] . '/reactify-projects-test';
        
        if (file_exists($test_dir)) {
            $this->remove_directory($test_dir);
        }
        
        // Remove filter
        remove_filter('upload_dir', [$this, 'filter_upload_dir']);
    }

    /**
     * Filter upload directory for testing
     *
     * @param array $upload_dir Upload directory info
     * @return array Modified upload directory info
     */
    public function filter_upload_dir($upload_dir)
    {
        $upload_dir['basedir'] = $upload_dir['basedir'] . '/reactify-projects-test';
        $upload_dir['baseurl'] = $upload_dir['baseurl'] . '/reactify-projects-test';
        
        return $upload_dir;
    }

    /**
     * Recursively remove directory
     *
     * @param string $dir Directory path
     */
    protected function remove_directory($dir)
    {
        if (!is_dir($dir)) {
            return;
        }

        $files = array_diff(scandir($dir), ['.', '..']);
        
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            
            if (is_dir($path)) {
                $this->remove_directory($path);
            } else {
                unlink($path);
            }
        }
        
        rmdir($dir);
    }

    /**
     * Create a test ZIP file
     *
     * @param string $name ZIP file name
     * @param array  $files Files to include in ZIP
     * @return string Path to created ZIP file
     */
    protected function create_test_zip($name, $files = [])
    {
        $upload_dir = wp_upload_dir();
        $zip_path = $upload_dir['basedir'] . '/' . $name;
        
        $zip = new ZipArchive();
        
        if ($zip->open($zip_path, ZipArchive::CREATE) !== true) {
            throw new Exception("Cannot create ZIP file: $zip_path");
        }
        
        // Default files if none provided
        if (empty($files)) {
            $files = [
                'index.html' => '<div id="root"></div>',
                'static/js/main.js' => 'console.log("Hello from React");',
                'static/css/main.css' => 'body { margin: 0; }',
                'asset-manifest.json' => json_encode([
                    'files' => [
                        'main.js' => '/static/js/main.js',
                        'main.css' => '/static/css/main.css'
                    ]
                ])
            ];
        }
        
        foreach ($files as $filename => $content) {
            $zip->addFromString($filename, $content);
        }
        
        $zip->close();
        
        return $zip_path;
    }

    /**
     * Assert that a directory exists
     *
     * @param string $directory Directory path
     * @param string $message   Error message
     */
    protected function assertDirectoryExists($directory, $message = '')
    {
        $this->assertTrue(is_dir($directory), $message ?: "Directory does not exist: $directory");
    }

    /**
     * Assert that a file exists
     *
     * @param string $file    File path
     * @param string $message Error message
     */
    protected function assertFileExists($file, $message = '')
    {
        $this->assertTrue(file_exists($file), $message ?: "File does not exist: $file");
    }
}
