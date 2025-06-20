/**
 * ReactifyWP Admin JavaScript
 */

(function ($) {
    'use strict';

    const ReactifyWPAdmin = {
        /**
         * Initialize admin functionality
         */
        init() {
            this.bindEvents();
            this.initUploadForm();
            this.initDragAndDrop();
            this.initProjectsTable();
            this.initSearch();
        },

        /**
         * Bind event handlers
         */
        bindEvents() {
            $(document).on('submit', '#reactifywp-upload-form', this.handleUpload.bind(this));
            $(document).on('click', '.reactifywp-delete-project', this.handleDelete.bind(this));
            $(document).on('click', '.reactifywp-copy-shortcode', this.handleCopyShortcode.bind(this));
            $(document).on('input', '#reactifywp-slug', this.handleSlugInput.bind(this));
            $(document).on('click', '.reactifywp-toggle-status', this.handleToggleStatus.bind(this));
            $(document).on('click', '.reactifywp-edit-project', this.handleEditProject.bind(this));
            $(document).on('click', '.reactifywp-duplicate-project', this.handleDuplicateProject.bind(this));
            $(document).on('click', '#reactifywp-apply-bulk', this.handleBulkAction.bind(this));
            $(document).on('click', '#cb-select-all', this.handleSelectAll.bind(this));
            $(document).on('input', '#reactifywp-search', this.handleSearch.bind(this));
            $(document).on('change', '#reactifywp-status-filter', this.handleFilter.bind(this));
            $(document).on('click', '[data-sort]', this.handleSort.bind(this));
        },

        /**
         * Initialize drag and drop functionality
         */
        initDragAndDrop() {
            const $dropzone = $('#reactifywp-dropzone');
            const $fileInput = $('#reactifywp-file-input');
            const $form = $('#reactifywp-upload-form');

            // Click to browse
            $dropzone.on('click', () => {
                $fileInput.click();
            });

            // File input change
            $fileInput.on('change', (e) => {
                const file = e.target.files[0];
                if (file) {
                    this.handleFileSelection(file);
                }
            });

            // Drag and drop events
            $dropzone.on('dragover dragenter', (e) => {
                e.preventDefault();
                e.stopPropagation();
                $dropzone.addClass('dragover');
            });

            $dropzone.on('dragleave dragend', (e) => {
                e.preventDefault();
                e.stopPropagation();
                $dropzone.removeClass('dragover');
            });

            $dropzone.on('drop', (e) => {
                e.preventDefault();
                e.stopPropagation();
                $dropzone.removeClass('dragover');

                const files = e.originalEvent.dataTransfer.files;
                if (files.length > 0) {
                    this.handleFileSelection(files[0]);
                }
            });
        },

        /**
         * Handle file selection
         */
        handleFileSelection(file) {
            // Validate file type
            if (!file.name.toLowerCase().endsWith('.zip')) {
                this.showNotice(reactifyWPAdmin.strings.invalidFile, 'error');
                return;
            }

            // Validate file size
            const maxSize = this.parseSize(reactifyWPAdmin.maxUploadSize);
            if (file.size > maxSize) {
                this.showNotice(reactifyWPAdmin.strings.fileTooLarge || 'File size exceeds maximum allowed size.', 'error');
                return;
            }

            // Show upload form
            this.showUploadForm(file);
        },

        /**
         * Show upload form with file details
         */
        showUploadForm(file) {
            const $dropzone = $('#reactifywp-dropzone');
            const $form = $('#reactifywp-upload-form');
            const $fileInput = $('#reactifywp-file');
            const $slugInput = $('#reactifywp-slug');
            const $nameInput = $('#reactifywp-name');
            const $shortcodeInput = $('#reactifywp-shortcode');

            // Hide dropzone and show form
            $dropzone.hide();
            $form.show();

            // Set file
            const dt = new DataTransfer();
            dt.items.add(file);
            $fileInput[0].files = dt.files;

            // Auto-populate fields
            const slug = file.name
                .replace(/\.zip$/i, '')
                .toLowerCase()
                .replace(/[^a-z0-9-]/g, '-')
                .replace(/-+/g, '-')
                .replace(/^-|-$/g, '');

            $slugInput.val(slug);
            $nameInput.val(slug.replace(/-/g, ' ').replace(/\b\w/g, l => l.toUpperCase()));
            $shortcodeInput.val(slug);

            // Update file description
            $form.find('.description').first().text(`Selected: ${file.name} (${this.formatFileSize(file.size)})`);
        },

        /**
         * Initialize upload form
         */
        initUploadForm() {
            const $form = $('#reactifywp-upload-form');
            const $fileInput = $('#reactifywp-file');
            const $slugInput = $('#reactifywp-slug');
            const $nameInput = $('#reactifywp-name');
            const $shortcodeInput = $('#reactifywp-shortcode');

            // Auto-populate fields based on file name
            $fileInput.on('change', function () {
                const fileName = this.files[0]?.name;
                if (fileName && !$slugInput.val()) {
                    const slug = fileName
                        .replace(/\.zip$/i, '')
                        .toLowerCase()
                        .replace(/[^a-z0-9-]/g, '-')
                        .replace(/-+/g, '-')
                        .replace(/^-|-$/g, '');

                    $slugInput.val(slug);

                    if (!$nameInput.val()) {
                        $nameInput.val(slug.replace(/-/g, ' ').replace(/\b\w/g, l => l.toUpperCase()));
                    }

                    if (!$shortcodeInput.val()) {
                        $shortcodeInput.val(slug);
                    }
                }
            });

            // Reset form button
            $form.append('<button type="button" class="button" id="reactifywp-reset-form">Cancel</button>');

            $('#reactifywp-reset-form').on('click', () => {
                this.resetUploadForm();
            });
        },

        /**
         * Reset upload form
         */
        resetUploadForm() {
            const $dropzone = $('#reactifywp-dropzone');
            const $form = $('#reactifywp-upload-form');

            $form.hide();
            $form[0].reset();
            $dropzone.show();
        },

        /**
         * Handle upload form submission
         */
        handleUpload(e) {
            e.preventDefault();

            const $form = $(e.target);
            const $submitButton = $form.find('button[type="submit"]');
            const $progress = $('#reactifywp-progress');
            const $progressBar = $('#reactifywp-progress-bar');
            const $progressText = $('#reactifywp-progress-text');

            // Validate form
            if (!this.validateUploadForm($form)) {
                return;
            }

            // Prepare form data
            const formData = new FormData($form[0]);
            formData.append('action', 'reactifywp_upload');
            formData.append('nonce', reactifyWPAdmin.nonce);

            // Show progress
            $form.hide();
            $progress.show();
            $submitButton.prop('disabled', true);

            // Submit upload with progress
            $.ajax({
                url: reactifyWPAdmin.ajaxUrl,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                xhr: () => {
                    const xhr = new window.XMLHttpRequest();
                    xhr.upload.addEventListener('progress', (e) => {
                        if (e.lengthComputable) {
                            const percentComplete = (e.loaded / e.total) * 100;
                            $progressBar.css('width', percentComplete + '%');
                            $progressText.text(`Uploading... ${Math.round(percentComplete)}%`);
                        }
                    });
                    return xhr;
                },
                success: (response) => {
                    $progressText.text('Processing...');
                    $progressBar.css('width', '100%');

                    setTimeout(() => {
                        if (response.success) {
                            this.showNotice(response.data.message || reactifyWPAdmin.strings.uploadSuccess, 'success');
                            this.resetUploadForm();
                            this.refreshProjectsList();
                        } else {
                            this.showNotice(response.data || reactifyWPAdmin.strings.uploadError, 'error');
                            $form.show();
                        }
                        $progress.hide();
                        $progressBar.css('width', '0%');
                        $submitButton.prop('disabled', false);
                    }, 1000);
                },
                error: () => {
                    this.showNotice(reactifyWPAdmin.strings.uploadError, 'error');
                    $form.show();
                    $progress.hide();
                    $progressBar.css('width', '0%');
                    $submitButton.prop('disabled', false);
                }
            });
        },

        /**
         * Handle project deletion
         */
        handleDelete(e) {
            e.preventDefault();

            const $button = $(e.target);
            const slug = $button.data('slug');

            if (!confirm(reactifyWPAdmin.strings.deleteConfirm)) {
                return;
            }

            $button.prop('disabled', true);

            $.ajax({
                url: reactifyWPAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'reactifywp_delete',
                    nonce: reactifyWPAdmin.nonce,
                    slug: slug
                },
                success: (response) => {
                    if (response.success) {
                        this.showNotice(response.data.message || 'Project deleted successfully!', 'success');
                        $button.closest('tr').fadeOut(() => {
                            $button.closest('tr').remove();
                        });
                    } else {
                        this.showNotice(response.data || 'Failed to delete project.', 'error');
                        $button.prop('disabled', false);
                    }
                },
                error: () => {
                    this.showNotice('Failed to delete project.', 'error');
                    $button.prop('disabled', false);
                }
            });
        },

        /**
         * Handle shortcode copy
         */
        handleCopyShortcode(e) {
            e.preventDefault();

            const $button = $(e.target);
            const shortcode = $button.data('shortcode');

            if (navigator.clipboard) {
                navigator.clipboard.writeText(shortcode).then(() => {
                    this.showNotice('Shortcode copied to clipboard!', 'success');
                });
            } else {
                // Fallback for older browsers
                const $temp = $('<textarea>').val(shortcode).appendTo('body').select();
                document.execCommand('copy');
                $temp.remove();
                this.showNotice('Shortcode copied to clipboard!', 'success');
            }
        },

        /**
         * Handle slug input validation
         */
        handleSlugInput(e) {
            const $input = $(e.target);
            let value = $input.val();

            // Convert to lowercase and replace invalid characters
            value = value.toLowerCase().replace(/[^a-z0-9-]/g, '-').replace(/-+/g, '-');
            
            if (value !== $input.val()) {
                $input.val(value);
            }

            // Update shortcode field if it's empty or matches the old slug
            const $shortcodeInput = $('#reactifywp-shortcode');
            if (!$shortcodeInput.val() || $shortcodeInput.val() === $input.data('old-value')) {
                $shortcodeInput.val(value);
            }

            $input.data('old-value', value);
        },

        /**
         * Validate upload form
         */
        validateUploadForm($form) {
            const $fileInput = $form.find('#reactifywp-file');
            const $slugInput = $form.find('#reactifywp-slug');

            // Check file
            if (!$fileInput[0].files.length) {
                this.showNotice(reactifyWPAdmin.strings.invalidFile, 'error');
                return false;
            }

            // Check slug
            if (!$slugInput.val()) {
                this.showNotice(reactifyWPAdmin.strings.slugRequired, 'error');
                return false;
            }

            if (!/^[a-z0-9-]+$/.test($slugInput.val())) {
                this.showNotice(reactifyWPAdmin.strings.slugInvalid, 'error');
                return false;
            }

            return true;
        },

        /**
         * Show admin notice
         */
        showNotice(message, type = 'info') {
            const $notice = $(`
                <div class="notice notice-${type} is-dismissible">
                    <p>${message}</p>
                    <button type="button" class="notice-dismiss">
                        <span class="screen-reader-text">Dismiss this notice.</span>
                    </button>
                </div>
            `);

            $('.wrap h1').after($notice);

            // Auto-dismiss success notices
            if (type === 'success') {
                setTimeout(() => {
                    $notice.fadeOut();
                }, 3000);
            }

            // Handle dismiss button
            $notice.find('.notice-dismiss').on('click', () => {
                $notice.fadeOut();
            });
        },

        /**
         * Refresh projects list
         */
        refreshProjectsList() {
            location.reload();
        },

        /**
         * Initialize projects table functionality
         */
        initProjectsTable() {
            this.sortOrder = {};
            this.currentSort = null;
        },

        /**
         * Initialize search functionality
         */
        initSearch() {
            let searchTimeout;
            $('#reactifywp-search').on('input', () => {
                clearTimeout(searchTimeout);
                searchTimeout = setTimeout(() => {
                    this.filterProjects();
                }, 300);
            });
        },

        /**
         * Handle project status toggle
         */
        handleToggleStatus(e) {
            e.preventDefault();
            const $button = $(e.target).closest('button');
            const slug = $button.data('slug');
            const currentStatus = $button.data('status');
            const newStatus = currentStatus === 'active' ? 'inactive' : 'active';

            this.updateProjectStatus(slug, newStatus);
        },

        /**
         * Handle project editing
         */
        handleEditProject(e) {
            e.preventDefault();
            const slug = $(e.target).data('slug');
            this.openEditModal(slug);
        },

        /**
         * Handle project duplication
         */
        handleDuplicateProject(e) {
            e.preventDefault();
            const slug = $(e.target).data('slug');

            if (confirm('Are you sure you want to duplicate this project?')) {
                this.duplicateProject(slug);
            }
        },

        /**
         * Handle bulk actions
         */
        handleBulkAction(e) {
            e.preventDefault();
            const action = $('#reactifywp-bulk-action').val();
            const selected = $('input[name="project[]"]:checked').map(function() {
                return this.value;
            }).get();

            if (!action || selected.length === 0) {
                this.showNotice('Please select an action and at least one project.', 'warning');
                return;
            }

            if (action === 'delete' && !confirm(`Are you sure you want to delete ${selected.length} project(s)?`)) {
                return;
            }

            this.performBulkAction(action, selected);
        },

        /**
         * Handle select all checkbox
         */
        handleSelectAll(e) {
            const checked = e.target.checked;
            $('input[name="project[]"]').prop('checked', checked);
        },

        /**
         * Handle search input
         */
        handleSearch(e) {
            this.filterProjects();
        },

        /**
         * Handle status filter
         */
        handleFilter(e) {
            this.filterProjects();
        },

        /**
         * Handle table sorting
         */
        handleSort(e) {
            e.preventDefault();
            const $link = $(e.target).closest('a');
            const sortBy = $link.data('sort');

            if (this.currentSort === sortBy) {
                this.sortOrder[sortBy] = this.sortOrder[sortBy] === 'asc' ? 'desc' : 'asc';
            } else {
                this.sortOrder[sortBy] = 'asc';
                this.currentSort = sortBy;
            }

            this.sortProjects(sortBy, this.sortOrder[sortBy]);
        },

        /**
         * Filter projects based on search and status
         */
        filterProjects() {
            const searchTerm = $('#reactifywp-search').val().toLowerCase();
            const statusFilter = $('#reactifywp-status-filter').val();

            $('#reactifywp-projects-tbody tr').each(function() {
                const $row = $(this);
                const projectName = $row.find('.column-name strong a').text().toLowerCase();
                const slug = $row.find('.column-slug code').text().toLowerCase();
                const status = $row.data('status');

                const matchesSearch = !searchTerm ||
                    projectName.includes(searchTerm) ||
                    slug.includes(searchTerm);

                const matchesStatus = !statusFilter || status === statusFilter;

                $row.toggle(matchesSearch && matchesStatus);
            });
        },

        /**
         * Sort projects table
         */
        sortProjects(sortBy, order) {
            const $tbody = $('#reactifywp-projects-tbody');
            const $rows = $tbody.find('tr').get();

            $rows.sort((a, b) => {
                let aVal, bVal;

                switch (sortBy) {
                    case 'project_name':
                        aVal = $(a).find('.column-name strong a').text();
                        bVal = $(b).find('.column-name strong a').text();
                        break;
                    case 'slug':
                        aVal = $(a).find('.column-slug code').text();
                        bVal = $(b).find('.column-slug code').text();
                        break;
                    case 'created_at':
                        aVal = $(a).find('.column-date abbr').attr('title');
                        bVal = $(b).find('.column-date abbr').attr('title');
                        break;
                    default:
                        return 0;
                }

                if (order === 'asc') {
                    return aVal.localeCompare(bVal);
                } else {
                    return bVal.localeCompare(aVal);
                }
            });

            $tbody.empty().append($rows);

            // Update sort indicators
            $('.sorting-indicator').removeClass('asc desc');
            $(`[data-sort="${sortBy}"] .sorting-indicator`).addClass(order);
        },

        /**
         * Format file size
         */
        formatFileSize(bytes) {
            if (bytes === 0) return '0 Bytes';
            const k = 1024;
            const sizes = ['Bytes', 'KB', 'MB', 'GB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
        },

        /**
         * Parse size string to bytes
         */
        parseSize(sizeStr) {
            const units = {
                'B': 1,
                'KB': 1024,
                'MB': 1024 * 1024,
                'GB': 1024 * 1024 * 1024
            };

            const match = sizeStr.match(/^(\d+)\s*([A-Z]{1,2})$/i);
            if (match) {
                const value = parseInt(match[1], 10);
                const unit = match[2].toUpperCase();
                return value * (units[unit] || 1);
            }

            return parseInt(sizeStr, 10) || 0;
        }
    };

    // Initialize when document is ready
    $(document).ready(() => {
        ReactifyWPAdmin.init();
    });

})(jQuery);
