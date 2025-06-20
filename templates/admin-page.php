<?php
/**
 * Admin page template for ReactifyWP
 *
 * @package ReactifyWP
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
    
    <div class="reactifywp-admin-container">
        <!-- Upload Section -->
        <div class="reactifywp-upload-section">
            <h2><?php esc_html_e('Upload New Project', 'reactifywp'); ?></h2>

            <!-- Drag and Drop Upload Area -->
            <div class="reactifywp-upload-dropzone" id="reactifywp-dropzone">
                <div class="reactifywp-dropzone-content">
                    <div class="dashicons dashicons-cloud-upload"></div>
                    <p class="upload-instructions">
                        <?php esc_html_e('Drag and drop your ZIP file here, or click to browse', 'reactifywp'); ?>
                    </p>
                    <p class="upload-note">
                        <?php printf(esc_html__('Maximum size: %s', 'reactifywp'), esc_html($this->get_max_upload_size())); ?>
                    </p>
                    <input type="file" id="reactifywp-file-input" accept=".zip" style="display: none;">
                </div>
            </div>

            <!-- Upload Progress -->
            <div class="reactifywp-upload-progress" id="reactifywp-progress" style="display: none;">
                <div class="reactifywp-progress">
                    <div class="reactifywp-progress-bar" id="reactifywp-progress-bar"></div>
                </div>
                <div class="reactifywp-progress-text" id="reactifywp-progress-text">
                    <?php esc_html_e('Uploading...', 'reactifywp'); ?>
                </div>
            </div>

            <form id="reactifywp-upload-form" enctype="multipart/form-data" style="display: none;">
                <?php wp_nonce_field('reactifywp_admin', 'reactifywp_nonce'); ?>

                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="reactifywp-file"><?php esc_html_e('ZIP File', 'reactifywp'); ?></label>
                        </th>
                        <td>
                            <input type="file" id="reactifywp-file" name="file" accept=".zip" required>
                            <p class="description">
                                <?php esc_html_e('Selected file will appear here', 'reactifywp'); ?>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="reactifywp-slug"><?php esc_html_e('Project Slug', 'reactifywp'); ?></label>
                        </th>
                        <td>
                            <input type="text" id="reactifywp-slug" name="slug" class="regular-text" required pattern="[a-z0-9-]+">
                            <p class="description">
                                <?php esc_html_e('Unique identifier for your project. Use lowercase letters, numbers, and hyphens only.', 'reactifywp'); ?>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="reactifywp-name"><?php esc_html_e('Project Name', 'reactifywp'); ?></label>
                        </th>
                        <td>
                            <input type="text" id="reactifywp-name" name="project_name" class="regular-text">
                            <p class="description">
                                <?php esc_html_e('Display name for your project (optional, defaults to slug).', 'reactifywp'); ?>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="reactifywp-shortcode"><?php esc_html_e('Shortcode Name', 'reactifywp'); ?></label>
                        </th>
                        <td>
                            <input type="text" id="reactifywp-shortcode" name="shortcode" class="regular-text">
                            <p class="description">
                                <?php esc_html_e('Custom shortcode name (optional, defaults to slug).', 'reactifywp'); ?>
                            </p>
                        </td>
                    </tr>
                </table>
                
                <p class="submit">
                    <button type="submit" class="button button-primary">
                        <?php esc_html_e('Upload Project', 'reactifywp'); ?>
                    </button>
                    <span class="spinner"></span>
                </p>
            </form>
        </div>

        <!-- Projects List -->
        <div class="reactifywp-projects-section">
            <div class="reactifywp-projects-header">
                <h2><?php esc_html_e('Existing Projects', 'reactifywp'); ?></h2>

                <div class="reactifywp-projects-controls">
                    <!-- Search and Filter -->
                    <div class="reactifywp-search-filter">
                        <input type="search" id="reactifywp-search" placeholder="<?php esc_attr_e('Search projects...', 'reactifywp'); ?>" class="regular-text">
                        <select id="reactifywp-status-filter">
                            <option value=""><?php esc_html_e('All Statuses', 'reactifywp'); ?></option>
                            <option value="active"><?php esc_html_e('Active', 'reactifywp'); ?></option>
                            <option value="inactive"><?php esc_html_e('Inactive', 'reactifywp'); ?></option>
                            <option value="error"><?php esc_html_e('Error', 'reactifywp'); ?></option>
                        </select>
                        <button type="button" class="button" id="reactifywp-refresh-projects">
                            <span class="dashicons dashicons-update"></span>
                            <?php esc_html_e('Refresh', 'reactifywp'); ?>
                        </button>
                    </div>

                    <!-- Bulk Actions -->
                    <div class="reactifywp-bulk-actions">
                        <select id="reactifywp-bulk-action">
                            <option value=""><?php esc_html_e('Bulk Actions', 'reactifywp'); ?></option>
                            <option value="activate"><?php esc_html_e('Activate', 'reactifywp'); ?></option>
                            <option value="deactivate"><?php esc_html_e('Deactivate', 'reactifywp'); ?></option>
                            <option value="delete"><?php esc_html_e('Delete', 'reactifywp'); ?></option>
                            <option value="export"><?php esc_html_e('Export', 'reactifywp'); ?></option>
                        </select>
                        <button type="button" class="button" id="reactifywp-apply-bulk">
                            <?php esc_html_e('Apply', 'reactifywp'); ?>
                        </button>
                    </div>
                </div>
            </div>

            <?php if (empty($projects)): ?>
                <div class="reactifywp-empty-state">
                    <div class="dashicons dashicons-admin-plugins"></div>
                    <h3><?php esc_html_e('No Projects Yet', 'reactifywp'); ?></h3>
                    <p><?php esc_html_e('Upload your first React application using the drag-and-drop area above.', 'reactifywp'); ?></p>
                </div>
            <?php else: ?>
                <div class="reactifywp-projects-table-container">
                    <table class="wp-list-table widefat fixed striped" id="reactifywp-projects-table">
                        <thead>
                            <tr>
                                <td class="manage-column column-cb check-column">
                                    <input type="checkbox" id="cb-select-all">
                                </td>
                                <th scope="col" class="manage-column column-name sortable">
                                    <a href="#" data-sort="project_name">
                                        <span><?php esc_html_e('Project Name', 'reactifywp'); ?></span>
                                        <span class="sorting-indicator"></span>
                                    </a>
                                </th>
                                <th scope="col" class="manage-column column-slug sortable">
                                    <a href="#" data-sort="slug">
                                        <span><?php esc_html_e('Slug', 'reactifywp'); ?></span>
                                        <span class="sorting-indicator"></span>
                                    </a>
                                </th>
                                <th scope="col" class="manage-column column-shortcode">
                                    <?php esc_html_e('Shortcode', 'reactifywp'); ?>
                                </th>
                                <th scope="col" class="manage-column column-status">
                                    <?php esc_html_e('Status', 'reactifywp'); ?>
                                </th>
                                <th scope="col" class="manage-column column-size">
                                    <?php esc_html_e('Size', 'reactifywp'); ?>
                                </th>
                                <th scope="col" class="manage-column column-version">
                                    <?php esc_html_e('Version', 'reactifywp'); ?>
                                </th>
                                <th scope="col" class="manage-column column-date sortable">
                                    <a href="#" data-sort="created_at">
                                        <span><?php esc_html_e('Created', 'reactifywp'); ?></span>
                                        <span class="sorting-indicator"></span>
                                    </a>
                                </th>
                                <th scope="col" class="manage-column column-actions">
                                    <?php esc_html_e('Actions', 'reactifywp'); ?>
                                </th>
                            </tr>
                        </thead>
                        <tbody id="reactifywp-projects-tbody">
                            <?php foreach ($projects as $project): ?>
                                <tr data-slug="<?php echo esc_attr($project['slug']); ?>" data-status="<?php echo esc_attr($project['status'] ?? 'active'); ?>">
                                    <th scope="row" class="check-column">
                                        <input type="checkbox" name="project[]" value="<?php echo esc_attr($project['slug']); ?>">
                                    </th>
                                    <td class="column-name">
                                        <strong>
                                            <a href="#" class="reactifywp-edit-project" data-slug="<?php echo esc_attr($project['slug']); ?>">
                                                <?php echo esc_html($project['project_name']); ?>
                                            </a>
                                        </strong>
                                        <?php if (!empty($project['description'])): ?>
                                            <p class="description"><?php echo esc_html($project['description']); ?></p>
                                        <?php endif; ?>
                                        <div class="row-actions">
                                            <span class="edit">
                                                <a href="#" class="reactifywp-edit-project" data-slug="<?php echo esc_attr($project['slug']); ?>">
                                                    <?php esc_html_e('Edit', 'reactifywp'); ?>
                                                </a> |
                                            </span>
                                            <span class="view">
                                                <a href="#" class="reactifywp-preview-project" data-slug="<?php echo esc_attr($project['slug']); ?>">
                                                    <?php esc_html_e('Preview', 'reactifywp'); ?>
                                                </a> |
                                            </span>
                                            <span class="duplicate">
                                                <a href="#" class="reactifywp-duplicate-project" data-slug="<?php echo esc_attr($project['slug']); ?>">
                                                    <?php esc_html_e('Duplicate', 'reactifywp'); ?>
                                                </a> |
                                            </span>
                                            <span class="trash">
                                                <a href="#" class="reactifywp-delete-project" data-slug="<?php echo esc_attr($project['slug']); ?>">
                                                    <?php esc_html_e('Delete', 'reactifywp'); ?>
                                                </a>
                                            </span>
                                        </div>
                                    </td>
                                    <td class="column-slug">
                                        <code><?php echo esc_html($project['slug']); ?></code>
                                    </td>
                                    <td class="column-shortcode">
                                        <div class="reactifywp-shortcode-container">
                                            <code class="reactifywp-shortcode">[reactify slug="<?php echo esc_attr($project['slug']); ?>"]</code>
                                            <button type="button" class="button button-small reactifywp-copy-shortcode" data-shortcode='[reactify slug="<?php echo esc_attr($project['slug']); ?>"]' title="<?php esc_attr_e('Copy shortcode', 'reactifywp'); ?>">
                                                <span class="dashicons dashicons-admin-page"></span>
                                            </button>
                                        </div>
                                    </td>
                                    <td class="column-status">
                                        <span class="reactifywp-status reactifywp-status-<?php echo esc_attr($project['status'] ?? 'active'); ?>">
                                            <?php echo esc_html(ucfirst($project['status'] ?? 'active')); ?>
                                        </span>
                                    </td>
                                    <td class="column-size">
                                        <?php echo esc_html(size_format($project['file_size'] ?? 0)); ?>
                                    </td>
                                    <td class="column-version">
                                        <span class="reactifywp-version" title="<?php echo esc_attr($project['version']); ?>">
                                            <?php echo esc_html(substr($project['version'], 0, 8)); ?>
                                        </span>
                                    </td>
                                    <td class="column-date">
                                        <abbr title="<?php echo esc_attr($project['created_at']); ?>">
                                            <?php echo esc_html(mysql2date(get_option('date_format'), $project['created_at'])); ?>
                                        </abbr>
                                    </td>
                                    <td class="column-actions">
                                        <div class="reactifywp-actions">
                                            <button type="button" class="button button-small reactifywp-toggle-status"
                                                    data-slug="<?php echo esc_attr($project['slug']); ?>"
                                                    data-status="<?php echo esc_attr($project['status'] ?? 'active'); ?>"
                                                    title="<?php echo esc_attr(($project['status'] ?? 'active') === 'active' ? __('Deactivate', 'reactifywp') : __('Activate', 'reactifywp')); ?>">
                                                <span class="dashicons dashicons-<?php echo ($project['status'] ?? 'active') === 'active' ? 'pause' : 'controls-play'; ?>"></span>
                                            </button>
                                            <button type="button" class="button button-small reactifywp-re-upload"
                                                    data-slug="<?php echo esc_attr($project['slug']); ?>"
                                                    title="<?php esc_attr_e('Re-upload', 'reactifywp'); ?>">
                                                <span class="dashicons dashicons-upload"></span>
                                            </button>
                                            <button type="button" class="button button-small reactifywp-download"
                                                    data-slug="<?php echo esc_attr($project['slug']); ?>"
                                                    title="<?php esc_attr_e('Download', 'reactifywp'); ?>">
                                                <span class="dashicons dashicons-download"></span>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <!-- Settings Section -->
        <div class="reactifywp-settings-section">
            <h2><?php esc_html_e('Settings', 'reactifywp'); ?></h2>
            
            <form method="post" action="options.php">
                <?php
                settings_fields('reactifywp_options');
                do_settings_sections('reactifywp');
                submit_button();
                ?>
            </form>
        </div>

        <!-- Usage Instructions -->
        <div class="reactifywp-help-section">
            <h2><?php esc_html_e('Usage Instructions', 'reactifywp'); ?></h2>
            
            <div class="reactifywp-help-content">
                <h3><?php esc_html_e('How to Use ReactifyWP', 'reactifywp'); ?></h3>
                
                <ol>
                    <li>
                        <strong><?php esc_html_e('Prepare Your React App:', 'reactifywp'); ?></strong>
                        <p><?php esc_html_e('Build your React application for production and ensure it uses relative paths.', 'reactifywp'); ?></p>
                    </li>
                    <li>
                        <strong><?php esc_html_e('Create ZIP File:', 'reactifywp'); ?></strong>
                        <p><?php esc_html_e('Zip the contents of your build folder (not the folder itself).', 'reactifywp'); ?></p>
                    </li>
                    <li>
                        <strong><?php esc_html_e('Upload Project:', 'reactifywp'); ?></strong>
                        <p><?php esc_html_e('Use the upload form above to add your React app to WordPress.', 'reactifywp'); ?></p>
                    </li>
                    <li>
                        <strong><?php esc_html_e('Embed in Content:', 'reactifywp'); ?></strong>
                        <p><?php esc_html_e('Use the generated shortcode in posts, pages, or widgets.', 'reactifywp'); ?></p>
                    </li>
                </ol>

                <h3><?php esc_html_e('React App Requirements', 'reactifywp'); ?></h3>
                
                <ul>
                    <li><?php esc_html_e('Must contain an index.html file', 'reactifywp'); ?></li>
                    <li><?php esc_html_e('Use relative paths for assets', 'reactifywp'); ?></li>
                    <li><?php esc_html_e('Mount to dynamic container ID: reactify-{slug}', 'reactifywp'); ?></li>
                </ul>

                <h3><?php esc_html_e('Example React Mount Code', 'reactifywp'); ?></h3>
                
                <pre><code>// Mount to ReactifyWP container
const containerId = 'reactify-' + window.reactifySlug;
ReactDOM.render(&lt;App /&gt;, document.getElementById(containerId));</code></pre>
            </div>
        </div>
    </div>
</div>

<style>
.reactifywp-admin-container {
    max-width: 1200px;
}

.reactifywp-upload-section,
.reactifywp-projects-section,
.reactifywp-settings-section,
.reactifywp-help-section {
    background: #fff;
    border: 1px solid #ccd0d4;
    box-shadow: 0 1px 1px rgba(0,0,0,.04);
    margin: 20px 0;
    padding: 20px;
}

.reactifywp-projects-table-container {
    overflow-x: auto;
}

.reactifywp-copy-shortcode {
    margin-left: 5px;
}

.reactifywp-version {
    font-family: monospace;
    background: #f1f1f1;
    padding: 2px 6px;
    border-radius: 3px;
}

.reactifywp-help-content pre {
    background: #f8f9fa;
    border: 1px solid #e9ecef;
    border-radius: 4px;
    padding: 15px;
    overflow-x: auto;
}

.reactifywp-help-content code {
    background: #f8f9fa;
    padding: 2px 4px;
    border-radius: 3px;
    font-size: 90%;
}
</style>
