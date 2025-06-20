<?php
/**
 * Asset management for ReactifyWP
 *
 * @package ReactifyWP
 */

namespace ReactifyWP;

/**
 * Asset Manager class
 */
class AssetManager
{
    /**
     * Supported asset types
     */
    const SUPPORTED_TYPES = ['js', 'css', 'html', 'json', 'map'];

    /**
     * Critical asset patterns
     */
    const CRITICAL_PATTERNS = [
        '/runtime[.-]/',
        '/vendor[.-]/',
        '/main[.-]/',
        '/app[.-]/',
        '/chunk[.-]/'
    ];

    /**
     * Constructor
     */
    public function __construct()
    {
        add_action('wp_enqueue_scripts', [$this, 'maybe_enqueue_assets']);
        add_filter('script_loader_tag', [$this, 'add_script_attributes'], 10, 3);
        add_filter('style_loader_tag', [$this, 'add_style_attributes'], 10, 4);
    }

    /**
     * Scan and catalog project assets
     *
     * @param string $project_path Project directory path
     * @param int    $project_id   Project ID
     * @return array|\WP_Error Asset catalog or error
     */
    public function catalog_assets($project_path, $project_id)
    {
        if (!is_dir($project_path)) {
            return new \WP_Error('invalid_path', __('Project path does not exist.', 'reactifywp'));
        }

        $assets = [];
        $manifest_data = $this->parse_manifest($project_path);

        if ($manifest_data && !is_wp_error($manifest_data)) {
            $assets = $this->process_manifest_assets($manifest_data, $project_path, $project_id);
        } else {
            $assets = $this->scan_directory_assets($project_path, $project_id);
        }

        // Save assets to database
        $this->save_assets_to_database($assets, $project_id);

        return $assets;
    }

    /**
     * Parse asset manifest file
     *
     * @param string $project_path Project directory path
     * @return array|\WP_Error Manifest data or error
     */
    private function parse_manifest($project_path)
    {
        $manifest_files = [
            'asset-manifest.json',
            'manifest.json',
            '.vite/manifest.json',
            'build/asset-manifest.json'
        ];

        foreach ($manifest_files as $manifest_file) {
            $manifest_path = $project_path . '/' . $manifest_file;
            
            if (file_exists($manifest_path)) {
                $content = file_get_contents($manifest_path);
                $data = json_decode($content, true);
                
                if (json_last_error() === JSON_ERROR_NONE) {
                    return [
                        'type' => $this->detect_manifest_type($data),
                        'data' => $data,
                        'path' => $manifest_path
                    ];
                }
            }
        }

        return new \WP_Error('no_manifest', __('No valid manifest file found.', 'reactifywp'));
    }

    /**
     * Detect manifest type (Create React App, Vite, Webpack, etc.)
     *
     * @param array $data Manifest data
     * @return string Manifest type
     */
    private function detect_manifest_type($data)
    {
        if (isset($data['files']) && isset($data['entrypoints'])) {
            return 'create-react-app';
        }

        if (isset($data['index.html']) || isset($data['main.js'])) {
            return 'vite';
        }

        if (isset($data['assetsByChunkName'])) {
            return 'webpack';
        }

        if (isset($data['main']) || isset($data['vendor'])) {
            return 'parcel';
        }

        return 'unknown';
    }

    /**
     * Process assets from manifest
     *
     * @param array  $manifest_data Manifest data
     * @param string $project_path  Project path
     * @param int    $project_id    Project ID
     * @return array Processed assets
     */
    private function process_manifest_assets($manifest_data, $project_path, $project_id)
    {
        $assets = [];
        $type = $manifest_data['type'];
        $data = $manifest_data['data'];

        switch ($type) {
            case 'create-react-app':
                $assets = $this->process_cra_manifest($data, $project_path, $project_id);
                break;
            case 'vite':
                $assets = $this->process_vite_manifest($data, $project_path, $project_id);
                break;
            case 'webpack':
                $assets = $this->process_webpack_manifest($data, $project_path, $project_id);
                break;
            default:
                $assets = $this->process_generic_manifest($data, $project_path, $project_id);
                break;
        }

        return $assets;
    }

    /**
     * Process Create React App manifest
     *
     * @param array  $data         Manifest data
     * @param string $project_path Project path
     * @param int    $project_id   Project ID
     * @return array Assets
     */
    private function process_cra_manifest($data, $project_path, $project_id)
    {
        $assets = [];
        $load_order = 0;

        // Process entrypoints first
        if (isset($data['entrypoints'])) {
            foreach ($data['entrypoints'] as $file) {
                $asset = $this->create_asset_entry($file, $project_path, $project_id, $load_order++);
                if ($asset) {
                    $asset['is_critical'] = true;
                    $assets[] = $asset;
                }
            }
        }

        // Process other files
        if (isset($data['files'])) {
            foreach ($data['files'] as $key => $file) {
                // Skip entrypoints (already processed)
                if (isset($data['entrypoints']) && in_array($file, $data['entrypoints'], true)) {
                    continue;
                }

                $asset = $this->create_asset_entry($file, $project_path, $project_id, $load_order++);
                if ($asset) {
                    $asset['is_critical'] = $this->is_critical_asset($file);
                    $assets[] = $asset;
                }
            }
        }

        return $assets;
    }

    /**
     * Process Vite manifest
     *
     * @param array  $data         Manifest data
     * @param string $project_path Project path
     * @param int    $project_id   Project ID
     * @return array Assets
     */
    private function process_vite_manifest($data, $project_path, $project_id)
    {
        $assets = [];
        $load_order = 0;

        foreach ($data as $key => $entry) {
            if (isset($entry['file'])) {
                $asset = $this->create_asset_entry($entry['file'], $project_path, $project_id, $load_order++);
                if ($asset) {
                    $asset['is_critical'] = isset($entry['isEntry']) && $entry['isEntry'];
                    
                    // Add dependencies
                    if (isset($entry['imports'])) {
                        $asset['dependencies'] = json_encode($entry['imports']);
                    }
                    
                    $assets[] = $asset;
                }
            }

            // Process CSS files
            if (isset($entry['css'])) {
                foreach ($entry['css'] as $css_file) {
                    $asset = $this->create_asset_entry($css_file, $project_path, $project_id, $load_order++);
                    if ($asset) {
                        $asset['is_critical'] = true;
                        $assets[] = $asset;
                    }
                }
            }
        }

        return $assets;
    }

    /**
     * Process Webpack manifest
     *
     * @param array  $data         Manifest data
     * @param string $project_path Project path
     * @param int    $project_id   Project ID
     * @return array Assets
     */
    private function process_webpack_manifest($data, $project_path, $project_id)
    {
        $assets = [];
        $load_order = 0;

        if (isset($data['assetsByChunkName'])) {
            foreach ($data['assetsByChunkName'] as $chunk => $files) {
                $files = is_array($files) ? $files : [$files];
                
                foreach ($files as $file) {
                    $asset = $this->create_asset_entry($file, $project_path, $project_id, $load_order++);
                    if ($asset) {
                        $asset['is_critical'] = in_array($chunk, ['main', 'runtime', 'vendor'], true);
                        $assets[] = $asset;
                    }
                }
            }
        }

        return $assets;
    }

    /**
     * Process generic manifest
     *
     * @param array  $data         Manifest data
     * @param string $project_path Project path
     * @param int    $project_id   Project ID
     * @return array Assets
     */
    private function process_generic_manifest($data, $project_path, $project_id)
    {
        $assets = [];
        $load_order = 0;

        foreach ($data as $key => $value) {
            if (is_string($value) && $this->is_asset_file($value)) {
                $asset = $this->create_asset_entry($value, $project_path, $project_id, $load_order++);
                if ($asset) {
                    $asset['is_critical'] = $this->is_critical_asset($value);
                    $assets[] = $asset;
                }
            }
        }

        return $assets;
    }

    /**
     * Scan directory for assets
     *
     * @param string $project_path Project path
     * @param int    $project_id   Project ID
     * @return array Assets
     */
    private function scan_directory_assets($project_path, $project_id)
    {
        $assets = [];
        $load_order = 0;

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($project_path, \RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if ($file->isFile() && $this->is_asset_file($file->getFilename())) {
                $relative_path = str_replace($project_path . '/', '', $file->getPathname());
                $asset = $this->create_asset_entry($relative_path, $project_path, $project_id, $load_order++);
                
                if ($asset) {
                    $asset['is_critical'] = $this->is_critical_asset($relative_path);
                    $assets[] = $asset;
                }
            }
        }

        // Sort assets by type and criticality
        usort($assets, function ($a, $b) {
            if ($a['is_critical'] !== $b['is_critical']) {
                return $b['is_critical'] - $a['is_critical'];
            }
            
            if ($a['file_type'] !== $b['file_type']) {
                $order = ['css' => 0, 'js' => 1, 'html' => 2, 'other' => 3];
                return ($order[$a['file_type']] ?? 3) - ($order[$b['file_type']] ?? 3);
            }
            
            return $a['load_order'] - $b['load_order'];
        });

        // Update load order after sorting
        foreach ($assets as $index => &$asset) {
            $asset['load_order'] = $index;
        }

        return $assets;
    }

    /**
     * Create asset entry
     *
     * @param string $file_path    File path
     * @param string $project_path Project path
     * @param int    $project_id   Project ID
     * @param int    $load_order   Load order
     * @return array|null Asset entry or null
     */
    private function create_asset_entry($file_path, $project_path, $project_id, $load_order)
    {
        $full_path = $project_path . '/' . ltrim($file_path, '/');

        if (!file_exists($full_path)) {
            return null;
        }

        $file_info = pathinfo($file_path);
        $extension = strtolower($file_info['extension'] ?? '');

        if (!in_array($extension, self::SUPPORTED_TYPES, true)) {
            return null;
        }

        return [
            'project_id' => $project_id,
            'file_path' => $file_path,
            'file_type' => $this->get_asset_type($extension),
            'file_size' => filesize($full_path),
            'file_hash' => md5_file($full_path),
            'dependencies' => '',
            'load_order' => $load_order,
            'is_critical' => false
        ];
    }

    /**
     * Get asset type from extension
     *
     * @param string $extension File extension
     * @return string Asset type
     */
    private function get_asset_type($extension)
    {
        $type_map = [
            'js' => 'js',
            'css' => 'css',
            'html' => 'html',
            'htm' => 'html',
            'json' => 'other',
            'map' => 'other'
        ];

        return $type_map[$extension] ?? 'other';
    }

    /**
     * Check if file is an asset file
     *
     * @param string $filename Filename
     * @return bool True if asset file
     */
    private function is_asset_file($filename)
    {
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        return in_array($extension, self::SUPPORTED_TYPES, true);
    }

    /**
     * Check if asset is critical
     *
     * @param string $file_path File path
     * @return bool True if critical
     */
    private function is_critical_asset($file_path)
    {
        foreach (self::CRITICAL_PATTERNS as $pattern) {
            if (preg_match($pattern, $file_path)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Save assets to database
     *
     * @param array $assets     Assets array
     * @param int   $project_id Project ID
     */
    private function save_assets_to_database($assets, $project_id)
    {
        global $wpdb;

        $assets_table = $wpdb->prefix . 'reactify_assets';

        // Clear existing assets for this project
        $wpdb->delete($assets_table, ['project_id' => $project_id], ['%d']);

        // Insert new assets
        foreach ($assets as $asset) {
            $wpdb->insert($assets_table, $asset);
        }
    }

    /**
     * Get project assets from database
     *
     * @param int $project_id Project ID
     * @return array Assets
     */
    public function get_project_assets($project_id)
    {
        global $wpdb;

        $assets_table = $wpdb->prefix . 'reactify_assets';

        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$assets_table} WHERE project_id = %d ORDER BY is_critical DESC, load_order ASC",
            $project_id
        ), ARRAY_A);
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
}
