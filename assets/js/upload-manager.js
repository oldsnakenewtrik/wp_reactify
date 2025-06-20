/**
 * ReactifyWP Upload Manager
 * Handles file uploads with progress tracking, chunking, and error recovery
 */

(function ($) {
    'use strict';

    const ReactifyUploadManager = {
        // Configuration
        config: {
            chunkSize: 1024 * 1024, // 1MB chunks
            maxRetries: 3,
            retryDelay: 1000,
            maxFileSize: 50 * 1024 * 1024, // 50MB default
            allowedTypes: ['application/zip'],
            uploadEndpoint: reactifyWPUpload.ajaxUrl,
            nonce: reactifyWPUpload.nonce
        },

        // State management
        state: {
            uploads: new Map(),
            activeUploads: 0,
            totalUploads: 0,
            globalProgress: 0
        },

        /**
         * Initialize upload manager
         */
        init() {
            this.bindEvents();
            this.setupDropzone();
            this.loadConfig();
        },

        /**
         * Load configuration from server
         */
        loadConfig() {
            if (window.reactifyWPUpload) {
                this.config = { ...this.config, ...window.reactifyWPUpload.config };
            }
        },

        /**
         * Bind event handlers
         */
        bindEvents() {
            // File input change
            $(document).on('change', '#reactifywp-file-input', this.handleFileSelect.bind(this));
            
            // Upload button click
            $(document).on('click', '#reactifywp-upload-btn', this.startUpload.bind(this));
            
            // Cancel upload
            $(document).on('click', '.reactifywp-cancel-upload', this.cancelUpload.bind(this));
            
            // Retry upload
            $(document).on('click', '.reactifywp-retry-upload', this.retryUpload.bind(this));
            
            // Clear completed uploads
            $(document).on('click', '#reactifywp-clear-completed', this.clearCompleted.bind(this));

            // Prevent default drag behaviors
            $(document).on('dragenter dragover drop', function(e) {
                e.preventDefault();
                e.stopPropagation();
            });
        },

        /**
         * Set up drag and drop zone
         */
        setupDropzone() {
            const dropzone = $('#reactifywp-dropzone');
            
            if (dropzone.length === 0) {
                return;
            }

            dropzone.on('dragenter dragover', (e) => {
                e.preventDefault();
                dropzone.addClass('dragover');
            });

            dropzone.on('dragleave', (e) => {
                e.preventDefault();
                if (!dropzone.is(e.target) && dropzone.has(e.target).length === 0) {
                    dropzone.removeClass('dragover');
                }
            });

            dropzone.on('drop', (e) => {
                e.preventDefault();
                dropzone.removeClass('dragover');
                
                const files = e.originalEvent.dataTransfer.files;
                this.handleFiles(files);
            });

            // Click to browse
            dropzone.on('click', () => {
                $('#reactifywp-file-input').click();
            });
        },

        /**
         * Handle file selection
         */
        handleFileSelect(e) {
            const files = e.target.files;
            this.handleFiles(files);
        },

        /**
         * Handle dropped or selected files
         */
        handleFiles(files) {
            if (!files || files.length === 0) {
                return;
            }

            // Validate files
            for (let i = 0; i < files.length; i++) {
                const file = files[i];
                const validation = this.validateFile(file);
                
                if (validation.valid) {
                    this.addFileToQueue(file);
                } else {
                    this.showError(validation.error);
                }
            }

            this.updateUI();
        },

        /**
         * Validate file before upload
         */
        validateFile(file) {
            // Check file size
            if (file.size > this.config.maxFileSize) {
                return {
                    valid: false,
                    error: `File "${file.name}" is too large. Maximum size: ${this.formatFileSize(this.config.maxFileSize)}`
                };
            }

            // Check file type
            if (!this.config.allowedTypes.includes(file.type)) {
                return {
                    valid: false,
                    error: `File "${file.name}" has invalid type. Only ZIP files are allowed.`
                };
            }

            // Check file extension
            const extension = file.name.split('.').pop().toLowerCase();
            if (extension !== 'zip') {
                return {
                    valid: false,
                    error: `File "${file.name}" must have .zip extension.`
                };
            }

            return { valid: true };
        },

        /**
         * Add file to upload queue
         */
        addFileToQueue(file) {
            const uploadId = this.generateUploadId();
            const upload = {
                id: uploadId,
                file: file,
                status: 'queued',
                progress: 0,
                chunks: Math.ceil(file.size / this.config.chunkSize),
                uploadedChunks: 0,
                retries: 0,
                error: null,
                startTime: null,
                endTime: null,
                speed: 0,
                eta: 0
            };

            this.state.uploads.set(uploadId, upload);
            this.state.totalUploads++;
            
            this.renderUploadItem(upload);
        },

        /**
         * Start upload process
         */
        startUpload() {
            const queuedUploads = Array.from(this.state.uploads.values())
                .filter(upload => upload.status === 'queued');

            if (queuedUploads.length === 0) {
                this.showMessage('No files to upload.', 'warning');
                return;
            }

            // Start uploads (limit concurrent uploads)
            const maxConcurrent = 2;
            let started = 0;

            for (const upload of queuedUploads) {
                if (started >= maxConcurrent) {
                    break;
                }
                
                this.uploadFile(upload);
                started++;
            }
        },

        /**
         * Upload individual file
         */
        async uploadFile(upload) {
            try {
                upload.status = 'uploading';
                upload.startTime = Date.now();
                this.state.activeUploads++;
                
                this.updateUploadItem(upload);

                if (upload.chunks > 1) {
                    await this.uploadFileChunked(upload);
                } else {
                    await this.uploadFileSingle(upload);
                }

                upload.status = 'completed';
                upload.endTime = Date.now();
                upload.progress = 100;
                
                this.state.activeUploads--;
                this.updateUploadItem(upload);
                this.showSuccess(`File "${upload.file.name}" uploaded successfully!`);

                // Start next queued upload
                this.startNextUpload();

            } catch (error) {
                this.handleUploadError(upload, error);
            }
        },

        /**
         * Upload file in single request
         */
        uploadFileSingle(upload) {
            return new Promise((resolve, reject) => {
                const formData = new FormData();
                formData.append('action', 'reactifywp_upload_file');
                formData.append('nonce', this.config.nonce);
                formData.append('file', upload.file);
                formData.append('upload_id', upload.id);

                const xhr = new XMLHttpRequest();

                xhr.upload.addEventListener('progress', (e) => {
                    if (e.lengthComputable) {
                        upload.progress = (e.loaded / e.total) * 100;
                        this.updateUploadProgress(upload);
                    }
                });

                xhr.addEventListener('load', () => {
                    if (xhr.status === 200) {
                        try {
                            const response = JSON.parse(xhr.responseText);
                            if (response.success) {
                                resolve(response.data);
                            } else {
                                reject(new Error(response.data || 'Upload failed'));
                            }
                        } catch (e) {
                            reject(new Error('Invalid server response'));
                        }
                    } else {
                        reject(new Error(`HTTP ${xhr.status}: ${xhr.statusText}`));
                    }
                });

                xhr.addEventListener('error', () => {
                    reject(new Error('Network error'));
                });

                xhr.addEventListener('timeout', () => {
                    reject(new Error('Upload timeout'));
                });

                xhr.timeout = 300000; // 5 minutes
                xhr.open('POST', this.config.uploadEndpoint);
                xhr.send(formData);
            });
        },

        /**
         * Upload file in chunks
         */
        async uploadFileChunked(upload) {
            const file = upload.file;
            const chunkSize = this.config.chunkSize;
            
            // Upload chunks
            for (let chunk = 0; chunk < upload.chunks; chunk++) {
                const start = chunk * chunkSize;
                const end = Math.min(start + chunkSize, file.size);
                const chunkBlob = file.slice(start, end);

                await this.uploadChunk(upload, chunk, chunkBlob);
                
                upload.uploadedChunks++;
                upload.progress = (upload.uploadedChunks / upload.chunks) * 90; // Reserve 10% for finalization
                this.updateUploadProgress(upload);
            }

            // Finalize upload
            await this.finalizeUpload(upload);
            upload.progress = 100;
        },

        /**
         * Upload single chunk
         */
        uploadChunk(upload, chunkIndex, chunkBlob) {
            return new Promise((resolve, reject) => {
                const formData = new FormData();
                formData.append('action', 'reactifywp_upload_chunk');
                formData.append('nonce', this.config.nonce);
                formData.append('file', chunkBlob);
                formData.append('upload_id', upload.id);
                formData.append('filename', upload.file.name);
                formData.append('chunk', chunkIndex);
                formData.append('chunks', upload.chunks);

                $.ajax({
                    url: this.config.uploadEndpoint,
                    type: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    timeout: 60000,
                    success: (response) => {
                        if (response.success) {
                            resolve(response.data);
                        } else {
                            reject(new Error(response.data || 'Chunk upload failed'));
                        }
                    },
                    error: (xhr, status, error) => {
                        reject(new Error(`Chunk ${chunkIndex} failed: ${error}`));
                    }
                });
            });
        },

        /**
         * Finalize chunked upload
         */
        finalizeUpload(upload) {
            return new Promise((resolve, reject) => {
                $.ajax({
                    url: this.config.uploadEndpoint,
                    type: 'POST',
                    data: {
                        action: 'reactifywp_finalize_upload',
                        nonce: this.config.nonce,
                        upload_id: upload.id,
                        filename: upload.file.name,
                        total_chunks: upload.chunks
                    },
                    timeout: 60000,
                    success: (response) => {
                        if (response.success) {
                            resolve(response.data);
                        } else {
                            reject(new Error(response.data || 'Upload finalization failed'));
                        }
                    },
                    error: (xhr, status, error) => {
                        reject(new Error(`Finalization failed: ${error}`));
                    }
                });
            });
        },

        /**
         * Handle upload error
         */
        handleUploadError(upload, error) {
            upload.status = 'error';
            upload.error = error.message;
            upload.retries++;
            
            this.state.activeUploads--;
            this.updateUploadItem(upload);
            
            console.error('Upload error:', error);
            
            // Auto-retry if under limit
            if (upload.retries < this.config.maxRetries) {
                setTimeout(() => {
                    this.retryUpload(null, upload.id);
                }, this.config.retryDelay * upload.retries);
            } else {
                this.showError(`Upload failed: ${error.message}`);
            }
        },

        /**
         * Cancel upload
         */
        cancelUpload(e, uploadId) {
            if (e) {
                e.preventDefault();
                uploadId = $(e.target).data('upload-id');
            }

            const upload = this.state.uploads.get(uploadId);
            if (!upload) {
                return;
            }

            if (upload.status === 'uploading') {
                // Cancel server-side upload
                $.ajax({
                    url: this.config.uploadEndpoint,
                    type: 'POST',
                    data: {
                        action: 'reactifywp_cancel_upload',
                        nonce: this.config.nonce,
                        upload_id: uploadId
                    }
                });
                
                this.state.activeUploads--;
            }

            upload.status = 'cancelled';
            this.updateUploadItem(upload);
            this.startNextUpload();
        },

        /**
         * Retry upload
         */
        retryUpload(e, uploadId) {
            if (e) {
                e.preventDefault();
                uploadId = $(e.target).data('upload-id');
            }

            const upload = this.state.uploads.get(uploadId);
            if (!upload) {
                return;
            }

            upload.status = 'queued';
            upload.progress = 0;
            upload.uploadedChunks = 0;
            upload.error = null;
            
            this.updateUploadItem(upload);
            this.uploadFile(upload);
        },

        /**
         * Start next queued upload
         */
        startNextUpload() {
            if (this.state.activeUploads >= 2) {
                return;
            }

            const nextUpload = Array.from(this.state.uploads.values())
                .find(upload => upload.status === 'queued');

            if (nextUpload) {
                this.uploadFile(nextUpload);
            }
        },

        /**
         * Clear completed uploads
         */
        clearCompleted() {
            const completedIds = [];

            this.state.uploads.forEach((upload, id) => {
                if (upload.status === 'completed' || upload.status === 'cancelled') {
                    completedIds.push(id);
                }
            });

            completedIds.forEach(id => {
                this.state.uploads.delete(id);
                $(`#upload-item-${id}`).remove();
            });

            this.updateGlobalProgress();
        },

        /**
         * Render upload item in UI
         */
        renderUploadItem(upload) {
            const container = $('#reactifywp-upload-queue');

            if (container.length === 0) {
                return;
            }

            const html = `
                <div class="upload-item" id="upload-item-${upload.id}" data-upload-id="${upload.id}">
                    <div class="upload-info">
                        <div class="upload-filename">${this.escapeHtml(upload.file.name)}</div>
                        <div class="upload-filesize">${this.formatFileSize(upload.file.size)}</div>
                    </div>
                    <div class="upload-progress">
                        <div class="progress-bar">
                            <div class="progress-fill" style="width: 0%"></div>
                        </div>
                        <div class="progress-text">0%</div>
                    </div>
                    <div class="upload-status">
                        <span class="status-text">Queued</span>
                        <div class="upload-actions">
                            <button type="button" class="reactifywp-cancel-upload" data-upload-id="${upload.id}" title="Cancel">
                                <span class="dashicons dashicons-no"></span>
                            </button>
                        </div>
                    </div>
                </div>
            `;

            container.append(html);
            this.updateGlobalProgress();
        },

        /**
         * Update upload item in UI
         */
        updateUploadItem(upload) {
            const item = $(`#upload-item-${upload.id}`);

            if (item.length === 0) {
                return;
            }

            // Update status
            const statusText = item.find('.status-text');
            const actions = item.find('.upload-actions');

            switch (upload.status) {
                case 'uploading':
                    statusText.text('Uploading...');
                    item.removeClass('queued error completed cancelled').addClass('uploading');
                    break;

                case 'completed':
                    statusText.text('Completed');
                    item.removeClass('queued uploading error cancelled').addClass('completed');
                    actions.html('<span class="dashicons dashicons-yes-alt"></span>');
                    break;

                case 'error':
                    statusText.text(`Error: ${upload.error}`);
                    item.removeClass('queued uploading completed cancelled').addClass('error');
                    actions.html(`
                        <button type="button" class="reactifywp-retry-upload" data-upload-id="${upload.id}" title="Retry">
                            <span class="dashicons dashicons-update"></span>
                        </button>
                        <button type="button" class="reactifywp-cancel-upload" data-upload-id="${upload.id}" title="Remove">
                            <span class="dashicons dashicons-no"></span>
                        </button>
                    `);
                    break;

                case 'cancelled':
                    statusText.text('Cancelled');
                    item.removeClass('queued uploading error completed').addClass('cancelled');
                    actions.html('<span class="dashicons dashicons-dismiss"></span>');
                    break;
            }

            this.updateUploadProgress(upload);
        },

        /**
         * Update upload progress
         */
        updateUploadProgress(upload) {
            const item = $(`#upload-item-${upload.id}`);

            if (item.length === 0) {
                return;
            }

            const progressFill = item.find('.progress-fill');
            const progressText = item.find('.progress-text');

            progressFill.css('width', `${upload.progress}%`);
            progressText.text(`${Math.round(upload.progress)}%`);

            // Calculate and display speed/ETA for uploading files
            if (upload.status === 'uploading' && upload.startTime) {
                const elapsed = (Date.now() - upload.startTime) / 1000;
                const uploaded = (upload.progress / 100) * upload.file.size;
                const speed = uploaded / elapsed;
                const remaining = upload.file.size - uploaded;
                const eta = remaining / speed;

                upload.speed = speed;
                upload.eta = eta;

                if (elapsed > 2) { // Only show after 2 seconds
                    const speedText = this.formatSpeed(speed);
                    const etaText = this.formatTime(eta);
                    progressText.text(`${Math.round(upload.progress)}% - ${speedText} - ${etaText} remaining`);
                }
            }

            this.updateGlobalProgress();
        },

        /**
         * Update global progress
         */
        updateGlobalProgress() {
            const uploads = Array.from(this.state.uploads.values());

            if (uploads.length === 0) {
                this.state.globalProgress = 0;
                return;
            }

            const totalProgress = uploads.reduce((sum, upload) => sum + upload.progress, 0);
            this.state.globalProgress = totalProgress / uploads.length;

            // Update global progress bar if it exists
            const globalProgress = $('#reactifywp-global-progress');
            if (globalProgress.length > 0) {
                globalProgress.find('.progress-fill').css('width', `${this.state.globalProgress}%`);
                globalProgress.find('.progress-text').text(`${Math.round(this.state.globalProgress)}%`);
            }

            // Update upload button state
            const uploadBtn = $('#reactifywp-upload-btn');
            const hasQueued = uploads.some(upload => upload.status === 'queued');
            const hasUploading = uploads.some(upload => upload.status === 'uploading');

            uploadBtn.prop('disabled', !hasQueued || hasUploading);

            if (hasUploading) {
                uploadBtn.text('Uploading...');
            } else if (hasQueued) {
                uploadBtn.text('Start Upload');
            } else {
                uploadBtn.text('Upload Files');
            }
        },

        /**
         * Update UI state
         */
        updateUI() {
            const uploads = Array.from(this.state.uploads.values());
            const queueContainer = $('#reactifywp-upload-queue');
            const emptyState = $('#reactifywp-empty-state');

            if (uploads.length === 0) {
                queueContainer.hide();
                emptyState.show();
            } else {
                queueContainer.show();
                emptyState.hide();
            }

            // Update counters
            const totalCount = uploads.length;
            const completedCount = uploads.filter(u => u.status === 'completed').length;
            const errorCount = uploads.filter(u => u.status === 'error').length;

            $('#reactifywp-upload-stats').html(`
                Total: ${totalCount} |
                Completed: ${completedCount} |
                Errors: ${errorCount}
            `);

            this.updateGlobalProgress();
        },

        /**
         * Show success message
         */
        showSuccess(message) {
            this.showMessage(message, 'success');
        },

        /**
         * Show error message
         */
        showError(message) {
            this.showMessage(message, 'error');
        },

        /**
         * Show message
         */
        showMessage(message, type = 'info') {
            const container = $('#reactifywp-messages');

            if (container.length === 0) {
                return;
            }

            const messageId = 'msg-' + Date.now();
            const html = `
                <div class="notice notice-${type} is-dismissible" id="${messageId}">
                    <p>${this.escapeHtml(message)}</p>
                    <button type="button" class="notice-dismiss" onclick="$('#${messageId}').remove()">
                        <span class="screen-reader-text">Dismiss this notice.</span>
                    </button>
                </div>
            `;

            container.append(html);

            // Auto-remove after 5 seconds for success messages
            if (type === 'success') {
                setTimeout(() => {
                    $(`#${messageId}`).fadeOut(() => {
                        $(`#${messageId}`).remove();
                    });
                }, 5000);
            }
        },

        /**
         * Generate unique upload ID
         */
        generateUploadId() {
            return 'upload-' + Date.now() + '-' + Math.random().toString(36).substr(2, 9);
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
         * Format upload speed
         */
        formatSpeed(bytesPerSecond) {
            return this.formatFileSize(bytesPerSecond) + '/s';
        },

        /**
         * Format time duration
         */
        formatTime(seconds) {
            if (seconds < 60) {
                return Math.round(seconds) + 's';
            } else if (seconds < 3600) {
                return Math.round(seconds / 60) + 'm';
            } else {
                return Math.round(seconds / 3600) + 'h';
            }
        },

        /**
         * Escape HTML
         */
        escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
    };

    // Initialize when document is ready
    $(document).ready(() => {
        ReactifyUploadManager.init();
    });

    // Expose globally
    window.ReactifyUploadManager = ReactifyUploadManager;

})(jQuery);
