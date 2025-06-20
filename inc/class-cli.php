<?php
/**
 * WP-CLI commands for ReactifyWP
 *
 * @package ReactifyWP
 */

namespace ReactifyWP;

/**
 * CLI class
 */
class CLI
{
    /**
     * Constructor
     */
    public function __construct()
    {
        if (defined('WP_CLI') && WP_CLI) {
            \WP_CLI::add_command('reactifywp', $this);
        }
    }

    /**
     * List all ReactifyWP projects
     *
     * ## OPTIONS
     *
     * [--format=<format>]
     * : Render output in a particular format.
     * ---
     * default: table
     * options:
     *   - table
     *   - csv
     *   - json
     *   - yaml
     * ---
     *
     * ## EXAMPLES
     *
     *     wp reactifywp list
     *     wp reactifywp list --format=json
     *
     * @param array $args       Command arguments
     * @param array $assoc_args Associative arguments
     */
    public function list($args, $assoc_args)
    {
        global $wpdb;

        $table_name = $wpdb->prefix . 'reactify_projects';
        $blog_id = get_current_blog_id();

        $projects = $wpdb->get_results($wpdb->prepare(
            "SELECT slug, project_name, shortcode, version, created_at, updated_at FROM {$table_name} WHERE blog_id = %d ORDER BY created_at DESC",
            $blog_id
        ), ARRAY_A);

        if (empty($projects)) {
            \WP_CLI::log('No projects found.');
            return;
        }

        $format = $assoc_args['format'] ?? 'table';
        \WP_CLI\Utils\format_items($format, $projects, ['slug', 'project_name', 'shortcode', 'version', 'created_at']);
    }

    /**
     * Upload a ReactifyWP project
     *
     * ## OPTIONS
     *
     * <file>
     * : Path to the ZIP file to upload
     *
     * --slug=<slug>
     * : Project slug (unique identifier)
     *
     * [--shortcode=<shortcode>]
     * : Shortcode name (defaults to slug)
     *
     * [--name=<name>]
     * : Project display name (defaults to slug)
     *
     * [--force]
     * : Overwrite existing project with same slug
     *
     * ## EXAMPLES
     *
     *     wp reactifywp upload my-app.zip --slug=my-app
     *     wp reactifywp upload calculator.zip --slug=calc --shortcode=calculator --name="Loan Calculator"
     *     wp reactifywp upload app.zip --slug=existing-app --force
     *
     * @param array $args       Command arguments
     * @param array $assoc_args Associative arguments
     */
    public function upload($args, $assoc_args)
    {
        if (empty($args[0])) {
            \WP_CLI::error('ZIP file path is required.');
        }

        $file_path = $args[0];
        $slug = $assoc_args['slug'] ?? '';
        $shortcode = $assoc_args['shortcode'] ?? $slug;
        $name = $assoc_args['name'] ?? $slug;
        $force = isset($assoc_args['force']);

        // Validate inputs
        if (empty($slug)) {
            \WP_CLI::error('Project slug is required. Use --slug=<slug>');
        }

        if (!preg_match('/^[a-z0-9-]+$/', $slug)) {
            \WP_CLI::error('Project slug can only contain lowercase letters, numbers, and hyphens.');
        }

        if (!file_exists($file_path)) {
            \WP_CLI::error("File not found: {$file_path}");
        }

        if (!is_readable($file_path)) {
            \WP_CLI::error("File is not readable: {$file_path}");
        }

        // Check file type
        $file_info = pathinfo($file_path);
        if (strtolower($file_info['extension']) !== 'zip') {
            \WP_CLI::error('Only ZIP files are supported.');
        }

        // Check if project exists
        $project = new Project();
        if ($project->get_by_slug($slug) && !$force) {
            \WP_CLI::error("Project '{$slug}' already exists. Use --force to overwrite.");
        }

        // Delete existing project if force is enabled
        if ($force && $project->get_by_slug($slug)) {
            \WP_CLI::log("Deleting existing project '{$slug}'...");
            $delete_result = $project->delete($slug);
            if (is_wp_error($delete_result)) {
                \WP_CLI::error("Failed to delete existing project: " . $delete_result->get_error_message());
            }
        }

        // Simulate file upload
        $this->simulate_file_upload($file_path, $slug, $shortcode, $name);

        \WP_CLI::success("Project '{$slug}' uploaded successfully!");
    }

    /**
     * Delete a ReactifyWP project
     *
     * ## OPTIONS
     *
     * <slug>
     * : Project slug to delete
     *
     * [--yes]
     * : Skip confirmation prompt
     *
     * ## EXAMPLES
     *
     *     wp reactifywp delete my-app
     *     wp reactifywp delete my-app --yes
     *
     * @param array $args       Command arguments
     * @param array $assoc_args Associative arguments
     */
    public function delete($args, $assoc_args)
    {
        if (empty($args[0])) {
            \WP_CLI::error('Project slug is required.');
        }

        $slug = $args[0];
        $skip_confirmation = isset($assoc_args['yes']);

        $project = new Project();
        $project_data = $project->get_by_slug($slug);

        if (!$project_data) {
            \WP_CLI::error("Project '{$slug}' not found.");
        }

        // Confirmation prompt
        if (!$skip_confirmation) {
            \WP_CLI::confirm("Are you sure you want to delete project '{$slug}'?");
        }

        $result = $project->delete($slug);

        if (is_wp_error($result)) {
            \WP_CLI::error("Failed to delete project: " . $result->get_error_message());
        }

        \WP_CLI::success("Project '{$slug}' deleted successfully!");
    }

    /**
     * Get information about a ReactifyWP project
     *
     * ## OPTIONS
     *
     * <slug>
     * : Project slug
     *
     * [--format=<format>]
     * : Render output in a particular format.
     * ---
     * default: table
     * options:
     *   - table
     *   - json
     *   - yaml
     * ---
     *
     * ## EXAMPLES
     *
     *     wp reactifywp info my-app
     *     wp reactifywp info my-app --format=json
     *
     * @param array $args       Command arguments
     * @param array $assoc_args Associative arguments
     */
    public function info($args, $assoc_args)
    {
        if (empty($args[0])) {
            \WP_CLI::error('Project slug is required.');
        }

        $slug = $args[0];
        $project = new Project();
        $project_data = $project->get_by_slug($slug);

        if (!$project_data) {
            \WP_CLI::error("Project '{$slug}' not found.");
        }

        // Get assets information
        $assets = $project->get_assets($slug);
        $asset_count = 0;
        if (!is_wp_error($assets)) {
            $asset_count = count($assets['js']) + count($assets['css']);
        }

        // Get directory size
        $size = $this->get_directory_size($project_data->file_path);

        $info = [
            'slug' => $project_data->slug,
            'project_name' => $project_data->project_name,
            'shortcode' => $project_data->shortcode,
            'version' => $project_data->version,
            'file_path' => $project_data->file_path,
            'size' => size_format($size),
            'asset_count' => $asset_count,
            'created_at' => $project_data->created_at,
            'updated_at' => $project_data->updated_at
        ];

        $format = $assoc_args['format'] ?? 'table';
        
        if ($format === 'table') {
            $items = [];
            foreach ($info as $key => $value) {
                $items[] = [
                    'field' => $key,
                    'value' => $value
                ];
            }
            \WP_CLI\Utils\format_items('table', $items, ['field', 'value']);
        } else {
            \WP_CLI\Utils\format_items($format, [$info], array_keys($info));
        }
    }

    /**
     * Simulate file upload for CLI
     *
     * @param string $file_path  File path
     * @param string $slug       Project slug
     * @param string $shortcode  Shortcode name
     * @param string $name       Project name
     */
    private function simulate_file_upload($file_path, $slug, $shortcode, $name)
    {
        // Simulate $_FILES and $_POST for the upload process
        $_FILES['file'] = [
            'name' => basename($file_path),
            'type' => 'application/zip',
            'tmp_name' => $file_path,
            'error' => UPLOAD_ERR_OK,
            'size' => filesize($file_path)
        ];

        $_POST['slug'] = $slug;
        $_POST['shortcode'] = $shortcode;
        $_POST['project_name'] = $name;

        // Override move_uploaded_file for CLI
        if (!function_exists('move_uploaded_file')) {
            function move_uploaded_file($from, $to) {
                return copy($from, $to);
            }
        }

        $project = new Project();
        $result = $project->upload_from_request();

        // Clean up globals
        unset($_FILES['file'], $_POST['slug'], $_POST['shortcode'], $_POST['project_name']);

        if (is_wp_error($result)) {
            \WP_CLI::error($result->get_error_message());
        }
    }

    /**
     * Get directory size recursively
     *
     * @param string $directory Directory path
     * @return int Size in bytes
     */
    private function get_directory_size($directory)
    {
        $size = 0;
        
        if (!is_dir($directory)) {
            return 0;
        }

        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($directory));
        
        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $size += $file->getSize();
            }
        }
        
        return $size;
    }
}
