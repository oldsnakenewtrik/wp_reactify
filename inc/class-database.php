<?php
/**
 * Database management for ReactifyWP
 *
 * @package ReactifyWP
 */

namespace ReactifyWP;

/**
 * Database class
 */
class Database
{
    /**
     * Current database version
     */
    const DB_VERSION = '1.1.0';

    /**
     * Database version option key
     */
    const DB_VERSION_KEY = 'reactifywp_db_version';

    /**
     * Constructor
     */
    public function __construct()
    {
        add_action('init', [$this, 'maybe_upgrade_database']);
    }

    /**
     * Create or upgrade database tables
     */
    public function create_tables()
    {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        // Projects table
        $projects_table = $wpdb->prefix . 'reactify_projects';
        $projects_sql = "CREATE TABLE $projects_table (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            blog_id bigint(20) unsigned NOT NULL DEFAULT 1,
            slug varchar(100) NOT NULL,
            shortcode varchar(100) NOT NULL,
            project_name varchar(255) NOT NULL,
            description text,
            file_path text NOT NULL,
            file_size bigint(20) unsigned DEFAULT 0,
            version varchar(50) NOT NULL,
            status enum('active', 'inactive', 'error') DEFAULT 'active',
            settings longtext,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY unique_blog_slug (blog_id, slug),
            KEY blog_id (blog_id),
            KEY status (status),
            KEY created_at (created_at),
            KEY updated_at (updated_at),
            KEY slug_status (slug, status)
        ) $charset_collate;";

        // Assets table for tracking individual assets
        $assets_table = $wpdb->prefix . 'reactify_assets';
        $assets_sql = "CREATE TABLE $assets_table (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            project_id bigint(20) unsigned NOT NULL,
            file_path varchar(500) NOT NULL,
            file_type enum('js', 'css', 'html', 'other') NOT NULL,
            file_size bigint(20) unsigned DEFAULT 0,
            file_hash varchar(64),
            dependencies text,
            load_order int(11) DEFAULT 0,
            is_critical tinyint(1) DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY project_id (project_id),
            KEY file_type (file_type),
            KEY load_order (load_order),
            KEY is_critical (is_critical)
        ) $charset_collate;";

        // Usage statistics table
        $stats_table = $wpdb->prefix . 'reactify_stats';
        $stats_sql = "CREATE TABLE $stats_table (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            project_id bigint(20) unsigned NOT NULL,
            page_id bigint(20) unsigned,
            page_url varchar(500),
            views bigint(20) unsigned DEFAULT 1,
            last_viewed datetime DEFAULT CURRENT_TIMESTAMP,
            user_agent text,
            ip_address varchar(45),
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY project_id (project_id),
            KEY page_id (page_id),
            KEY last_viewed (last_viewed),
            KEY created_at (created_at)
        ) $charset_collate;";

        // Error logs table
        $errors_table = $wpdb->prefix . 'reactify_errors';
        $errors_sql = "CREATE TABLE $errors_table (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            project_id bigint(20) unsigned,
            error_type varchar(50) NOT NULL,
            error_message text NOT NULL,
            error_data longtext,
            stack_trace text,
            user_id bigint(20) unsigned,
            ip_address varchar(45),
            user_agent text,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY project_id (project_id),
            KEY error_type (error_type),
            KEY user_id (user_id),
            KEY created_at (created_at)
        ) $charset_collate;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        // Create tables one by one with error handling
        try {
            dbDelta($projects_sql);
        } catch (Exception $e) {
            error_log('ReactifyWP: Error creating projects table: ' . $e->getMessage());
        }

        // Skip complex tables for now - just create the main projects table
        // The other tables can be created later if needed

        // Simple assets table without foreign keys
        $simple_assets_sql = "CREATE TABLE {$wpdb->prefix}reactify_assets (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            project_id bigint(20) unsigned NOT NULL,
            file_path varchar(500) NOT NULL,
            file_type varchar(50) NOT NULL,
            file_size bigint(20) unsigned DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY project_id (project_id)
        ) $charset_collate;";

        try {
            dbDelta($simple_assets_sql);
        } catch (Exception $e) {
            error_log('ReactifyWP: Error creating assets table: ' . $e->getMessage());
        }

        // Update database version
        update_option(self::DB_VERSION_KEY, self::DB_VERSION);
    }

    /**
     * Check if database needs upgrade
     */
    public function maybe_upgrade_database()
    {
        $current_version = get_option(self::DB_VERSION_KEY, '0.0.0');
        
        if (version_compare($current_version, self::DB_VERSION, '<')) {
            $this->upgrade_database($current_version);
        }
    }

    /**
     * Upgrade database from old version
     *
     * @param string $from_version Current database version
     */
    private function upgrade_database($from_version)
    {
        global $wpdb;

        // Backup existing data before upgrade
        $this->backup_data();

        // Version-specific upgrades
        if (version_compare($from_version, '1.0.0', '<')) {
            $this->upgrade_to_1_0_0();
        }

        if (version_compare($from_version, '1.1.0', '<')) {
            $this->upgrade_to_1_1_0();
        }

        // Recreate tables with new schema
        $this->create_tables();

        // Restore data if needed
        $this->restore_data($from_version);
    }

    /**
     * Upgrade to version 1.0.0
     */
    private function upgrade_to_1_0_0()
    {
        // Initial version - no upgrade needed
    }

    /**
     * Upgrade to version 1.1.0
     */
    private function upgrade_to_1_1_0()
    {
        global $wpdb;

        $projects_table = $wpdb->prefix . 'reactify_projects';

        // Add new columns if they don't exist
        $columns_to_add = [
            'description' => 'ADD COLUMN description text AFTER project_name',
            'file_size' => 'ADD COLUMN file_size bigint(20) unsigned DEFAULT 0 AFTER file_path',
            'status' => "ADD COLUMN status enum('active', 'inactive', 'error') DEFAULT 'active' AFTER version",
            'settings' => 'ADD COLUMN settings longtext AFTER status'
        ];

        foreach ($columns_to_add as $column => $sql) {
            $column_exists = $wpdb->get_results($wpdb->prepare(
                "SHOW COLUMNS FROM {$projects_table} LIKE %s",
                $column
            ));

            if (empty($column_exists)) {
                $wpdb->query("ALTER TABLE {$projects_table} {$sql}");
            }
        }

        // Add new indexes
        $indexes_to_add = [
            'status' => 'ADD INDEX status (status)',
            'slug_status' => 'ADD INDEX slug_status (slug, status)'
        ];

        foreach ($indexes_to_add as $index => $sql) {
            $index_exists = $wpdb->get_results($wpdb->prepare(
                "SHOW INDEX FROM {$projects_table} WHERE Key_name = %s",
                $index
            ));

            if (empty($index_exists)) {
                $wpdb->query("ALTER TABLE {$projects_table} {$sql}");
            }
        }
    }

    /**
     * Backup existing data
     */
    private function backup_data()
    {
        global $wpdb;

        $projects_table = $wpdb->prefix . 'reactify_projects';
        $backup_table = $projects_table . '_backup_' . date('Y_m_d_H_i_s');

        // Check if original table exists
        $table_exists = $wpdb->get_var($wpdb->prepare(
            "SHOW TABLES LIKE %s",
            $projects_table
        ));

        if ($table_exists) {
            $wpdb->query("CREATE TABLE {$backup_table} AS SELECT * FROM {$projects_table}");
        }
    }

    /**
     * Restore data after upgrade
     *
     * @param string $from_version Previous version
     */
    private function restore_data($from_version)
    {
        global $wpdb;

        $projects_table = $wpdb->prefix . 'reactify_projects';
        $backup_tables = $wpdb->get_results(
            "SHOW TABLES LIKE '{$projects_table}_backup_%'",
            ARRAY_N
        );

        if (!empty($backup_tables)) {
            $latest_backup = end($backup_tables)[0];
            
            // Migrate data from backup to new table
            $backup_data = $wpdb->get_results("SELECT * FROM {$latest_backup}", ARRAY_A);
            
            foreach ($backup_data as $row) {
                $this->migrate_project_data($row, $from_version);
            }

            // Clean up backup table after successful migration
            $wpdb->query("DROP TABLE {$latest_backup}");
        }
    }

    /**
     * Migrate individual project data
     *
     * @param array  $row          Project data
     * @param string $from_version Previous version
     */
    private function migrate_project_data($row, $from_version)
    {
        global $wpdb;

        $projects_table = $wpdb->prefix . 'reactify_projects';

        // Prepare data for new schema
        $data = [
            'blog_id' => $row['blog_id'],
            'slug' => $row['slug'],
            'shortcode' => $row['shortcode'] ?? $row['slug'],
            'project_name' => $row['project_name'],
            'description' => $row['description'] ?? '',
            'file_path' => $row['file_path'],
            'file_size' => $row['file_size'] ?? $this->calculate_directory_size($row['file_path']),
            'version' => $row['version'],
            'status' => $row['status'] ?? 'active',
            'settings' => $row['settings'] ?? json_encode([]),
            'created_at' => $row['created_at'],
            'updated_at' => $row['updated_at'] ?? current_time('mysql')
        ];

        $wpdb->insert($projects_table, $data);
    }

    /**
     * Calculate directory size
     *
     * @param string $directory Directory path
     * @return int Size in bytes
     */
    private function calculate_directory_size($directory)
    {
        if (!is_dir($directory)) {
            return 0;
        }

        $size = 0;
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($directory));
        
        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $size += $file->getSize();
            }
        }
        
        return $size;
    }

    /**
     * Get database statistics
     *
     * @return array Database statistics
     */
    public function get_statistics()
    {
        global $wpdb;

        $projects_table = $wpdb->prefix . 'reactify_projects';
        $assets_table = $wpdb->prefix . 'reactify_assets';
        $stats_table = $wpdb->prefix . 'reactify_stats';
        $errors_table = $wpdb->prefix . 'reactify_errors';

        $blog_id = get_current_blog_id();

        return [
            'total_projects' => $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$projects_table} WHERE blog_id = %d",
                $blog_id
            )),
            'active_projects' => $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$projects_table} WHERE blog_id = %d AND status = 'active'",
                $blog_id
            )),
            'total_assets' => $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$assets_table} a 
                 JOIN {$projects_table} p ON a.project_id = p.id 
                 WHERE p.blog_id = %d",
                $blog_id
            )),
            'total_views' => $wpdb->get_var($wpdb->prepare(
                "SELECT SUM(views) FROM {$stats_table} s 
                 JOIN {$projects_table} p ON s.project_id = p.id 
                 WHERE p.blog_id = %d",
                $blog_id
            )),
            'total_errors' => $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$errors_table} e 
                 JOIN {$projects_table} p ON e.project_id = p.id 
                 WHERE p.blog_id = %d AND e.created_at > DATE_SUB(NOW(), INTERVAL 30 DAY)",
                $blog_id
            )),
            'database_size' => $this->get_database_size()
        ];
    }

    /**
     * Get database size
     *
     * @return string Formatted database size
     */
    private function get_database_size()
    {
        global $wpdb;

        $tables = [
            $wpdb->prefix . 'reactify_projects',
            $wpdb->prefix . 'reactify_assets',
            $wpdb->prefix . 'reactify_stats',
            $wpdb->prefix . 'reactify_errors'
        ];

        $total_size = 0;

        foreach ($tables as $table) {
            $size = $wpdb->get_var($wpdb->prepare(
                "SELECT ROUND(((data_length + index_length) / 1024 / 1024), 2) 
                 FROM information_schema.TABLES 
                 WHERE table_schema = %s AND table_name = %s",
                DB_NAME,
                $table
            ));

            $total_size += (float) $size;
        }

        return size_format($total_size * 1024 * 1024);
    }

    /**
     * Clean up old data
     *
     * @param int $days Days to keep data
     */
    public function cleanup_old_data($days = 90)
    {
        global $wpdb;

        $stats_table = $wpdb->prefix . 'reactify_stats';
        $errors_table = $wpdb->prefix . 'reactify_errors';

        // Clean up old statistics
        $wpdb->query($wpdb->prepare(
            "DELETE FROM {$stats_table} WHERE created_at < DATE_SUB(NOW(), INTERVAL %d DAY)",
            $days
        ));

        // Clean up old errors
        $wpdb->query($wpdb->prepare(
            "DELETE FROM {$errors_table} WHERE created_at < DATE_SUB(NOW(), INTERVAL %d DAY)",
            $days
        ));
    }

    /**
     * Optimize database tables
     */
    public function optimize_tables()
    {
        global $wpdb;

        $tables = [
            $wpdb->prefix . 'reactify_projects',
            $wpdb->prefix . 'reactify_assets',
            $wpdb->prefix . 'reactify_stats',
            $wpdb->prefix . 'reactify_errors'
        ];

        foreach ($tables as $table) {
            $wpdb->query("OPTIMIZE TABLE {$table}");
        }
    }
}
