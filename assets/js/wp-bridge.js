/**
 * WordPress Bridge for ReactifyWP
 * Provides seamless communication between React apps and WordPress
 */

(function() {
    'use strict';

    // WordPress Bridge API
    window.ReactifyWP = window.ReactifyWP || {};
    window.ReactifyWP.WPBridge = {
        
        /**
         * Get WordPress data
         */
        wp: window.ReactifyWP?.wpBridge || {},

        /**
         * Configuration
         */
        config: {
            ajaxUrl: '',
            apiUrl: '',
            nonce: '',
            debug: false
        },

        /**
         * Initialize bridge
         */
        init(config = {}) {
            this.config = { ...this.config, ...config };
            this.setupEventListeners();
            this.setupErrorHandling();
            
            if (this.config.debug) {
                console.log('ReactifyWP Bridge initialized', this.config);
            }
        },

        /**
         * Set up event listeners
         */
        setupEventListeners() {
            // Listen for WordPress events
            document.addEventListener('wp-data-updated', (event) => {
                this.handleWPDataUpdate(event.detail);
            });

            // Listen for React app events
            document.addEventListener('reactify-app-event', (event) => {
                this.handleAppEvent(event.detail);
            });
        },

        /**
         * Set up error handling
         */
        setupErrorHandling() {
            window.addEventListener('error', (event) => {
                if (event.filename && event.filename.includes('reactify')) {
                    this.reportError({
                        message: event.message,
                        filename: event.filename,
                        lineno: event.lineno,
                        colno: event.colno,
                        stack: event.error?.stack
                    });
                }
            });

            window.addEventListener('unhandledrejection', (event) => {
                if (event.reason && event.reason.reactifyApp) {
                    this.reportError({
                        message: event.reason.message || 'Unhandled Promise Rejection',
                        stack: event.reason.stack,
                        type: 'promise_rejection'
                    });
                }
            });
        },

        /**
         * Get WordPress data
         */
        async getWPData(type, params = {}) {
            try {
                const response = await this.makeRequest('reactifywp_get_wp_data', {
                    type: type,
                    params: params
                });

                if (response.success) {
                    return response.data;
                } else {
                    throw new Error(response.data || 'Failed to get WordPress data');
                }
            } catch (error) {
                console.error('Error getting WordPress data:', error);
                throw error;
            }
        },

        /**
         * Update WordPress data
         */
        async updateWPData(type, data, action = 'update') {
            try {
                const response = await this.makeRequest('reactifywp_update_wp_data', {
                    type: type,
                    data: data,
                    action: action
                });

                if (response.success) {
                    // Trigger data update event
                    this.triggerEvent('wp-data-updated', {
                        type: type,
                        data: response.data,
                        action: action
                    });

                    return response.data;
                } else {
                    throw new Error(response.data || 'Failed to update WordPress data');
                }
            } catch (error) {
                console.error('Error updating WordPress data:', error);
                throw error;
            }
        },

        /**
         * Send app event to WordPress
         */
        async sendAppEvent(appSlug, eventType, eventData = {}) {
            try {
                const response = await this.makeRequest('reactifywp_app_event', {
                    app: appSlug,
                    event: eventType,
                    data: eventData
                });

                return response.success ? response.data : null;
            } catch (error) {
                console.error('Error sending app event:', error);
                return null;
            }
        },

        /**
         * Make AJAX request to WordPress
         */
        async makeRequest(action, data = {}) {
            const requestData = {
                action: action,
                nonce: this.config.nonce,
                ...data
            };

            const response = await fetch(this.config.ajaxUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams(requestData)
            });

            if (!response.ok) {
                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            }

            return await response.json();
        },

        /**
         * Make REST API request
         */
        async makeRestRequest(endpoint, options = {}) {
            const url = this.config.apiUrl + endpoint.replace(/^\//, '');
            
            const defaultOptions = {
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': this.config.nonce
                }
            };

            const requestOptions = { ...defaultOptions, ...options };

            const response = await fetch(url, requestOptions);

            if (!response.ok) {
                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            }

            return await response.json();
        },

        /**
         * Get current user
         */
        getCurrentUser() {
            return this.wp.user || null;
        },

        /**
         * Get site information
         */
        getSiteInfo() {
            return this.wp.site || null;
        },

        /**
         * Get current page information
         */
        getPageInfo() {
            return this.wp.page || null;
        },

        /**
         * Get current post
         */
        getCurrentPost() {
            return this.wp.post || null;
        },

        /**
         * Get navigation menus
         */
        getMenus() {
            return this.wp.menus || {};
        },

        /**
         * Get customizer data
         */
        getCustomizerData() {
            return this.wp.customizer || {};
        },

        /**
         * Check user capability
         */
        userCan(capability) {
            const user = this.getCurrentUser();
            return user && user.capabilities && user.capabilities[capability] === true;
        },

        /**
         * Check if user is logged in
         */
        isUserLoggedIn() {
            const user = this.getCurrentUser();
            return user && user.logged_in === true;
        },

        /**
         * Get posts
         */
        async getPosts(params = {}) {
            return await this.getWPData('posts', params);
        },

        /**
         * Get users
         */
        async getUsers(params = {}) {
            return await this.getWPData('users', params);
        },

        /**
         * Get media
         */
        async getMedia(params = {}) {
            return await this.getWPData('media', params);
        },

        /**
         * Get comments
         */
        async getComments(params = {}) {
            return await this.getWPData('comments', params);
        },

        /**
         * Create post
         */
        async createPost(postData) {
            return await this.updateWPData('post', postData, 'create');
        },

        /**
         * Update post
         */
        async updatePost(postData) {
            return await this.updateWPData('post', postData, 'update');
        },

        /**
         * Delete post
         */
        async deletePost(postId) {
            return await this.updateWPData('post', { ID: postId }, 'delete');
        },

        /**
         * Create comment
         */
        async createComment(commentData) {
            return await this.updateWPData('comment', commentData, 'create');
        },

        /**
         * Update comment
         */
        async updateComment(commentData) {
            return await this.updateWPData('comment', commentData, 'update');
        },

        /**
         * Approve comment
         */
        async approveComment(commentId) {
            return await this.updateWPData('comment', { comment_ID: commentId }, 'approve');
        },

        /**
         * Update user meta
         */
        async updateUserMeta(userId, metaKey, metaValue) {
            return await this.updateWPData('user_meta', {
                user_id: userId,
                meta_key: metaKey,
                meta_value: metaValue
            }, 'update');
        },

        /**
         * Handle WordPress data update
         */
        handleWPDataUpdate(detail) {
            if (this.config.debug) {
                console.log('WordPress data updated:', detail);
            }

            // Update local cache if needed
            if (detail.type === 'post' && this.wp.post && this.wp.post.id === detail.data.id) {
                // Refresh current post data
                this.refreshCurrentPost();
            }
        },

        /**
         * Handle app event
         */
        handleAppEvent(detail) {
            if (this.config.debug) {
                console.log('App event:', detail);
            }

            // Send to WordPress
            this.sendAppEvent(detail.app, detail.event, detail.data);
        },

        /**
         * Refresh current post data
         */
        async refreshCurrentPost() {
            try {
                const postId = this.wp.page?.id;
                if (postId) {
                    const posts = await this.getPosts({ include: [postId] });
                    if (posts.posts && posts.posts.length > 0) {
                        this.wp.post = posts.posts[0];
                        this.triggerEvent('wp-post-refreshed', this.wp.post);
                    }
                }
            } catch (error) {
                console.error('Error refreshing post data:', error);
            }
        },

        /**
         * Report error to WordPress
         */
        reportError(errorData) {
            this.sendAppEvent('system', 'error', errorData);
        },

        /**
         * Track page view
         */
        trackPageView(url, title) {
            this.sendAppEvent('analytics', 'page_view', {
                url: url || window.location.href,
                title: title || document.title,
                referrer: document.referrer
            });
        },

        /**
         * Track user interaction
         */
        trackInteraction(action, element, value) {
            this.sendAppEvent('analytics', 'user_interaction', {
                action: action,
                element: element,
                value: value
            });
        },

        /**
         * Track performance metrics
         */
        trackPerformance(metrics) {
            this.sendAppEvent('analytics', 'performance', {
                metrics: metrics
            });
        },

        /**
         * Trigger custom event
         */
        triggerEvent(eventName, detail = {}) {
            const event = new CustomEvent(eventName, { detail: detail });
            document.dispatchEvent(event);
        },

        /**
         * Subscribe to events
         */
        on(eventName, callback) {
            document.addEventListener(eventName, callback);
        },

        /**
         * Unsubscribe from events
         */
        off(eventName, callback) {
            document.removeEventListener(eventName, callback);
        },

        /**
         * Utility: Format date
         */
        formatDate(date, format = 'Y-m-d H:i:s') {
            const d = new Date(date);
            const site = this.getSiteInfo();
            
            // Use WordPress date format if available
            if (site && site.date_format) {
                // This is a simplified implementation
                // In a real implementation, you'd want to use a proper date formatting library
                return d.toLocaleDateString();
            }
            
            return d.toISOString().slice(0, 19).replace('T', ' ');
        },

        /**
         * Utility: Get asset URL
         */
        getAssetUrl(path) {
            const site = this.getSiteInfo();
            if (site && site.url) {
                return site.url.replace(/\/$/, '') + '/' + path.replace(/^\//, '');
            }
            return path;
        },

        /**
         * Utility: Get admin URL
         */
        getAdminUrl(path = '') {
            const site = this.getSiteInfo();
            if (site && site.admin_url) {
                return site.admin_url.replace(/\/$/, '') + '/' + path.replace(/^\//, '');
            }
            return '/wp-admin/' + path.replace(/^\//, '');
        }
    };

    // Auto-initialize if configuration is available
    if (window.ReactifyWPIntegration) {
        window.ReactifyWP.WPBridge.init(window.ReactifyWPIntegration);
    }

})();
