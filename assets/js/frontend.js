/**
 * ReactifyWP Enhanced Frontend JavaScript
 */

(function () {
    'use strict';

    const ReactifyWP = {
        // Configuration
        config: {
            debug: false,
            performance: true,
            errorBoundary: true,
            retryAttempts: 3,
            retryDelay: 1000,
            lazyLoadThreshold: 100
        },

        // State management
        state: {
            apps: new Map(),
            loadingApps: new Set(),
            failedApps: new Set(),
            observers: new Map(),
            performance: new Map()
        },

        /**
         * Initialize frontend functionality
         */
        init() {
            this.setupGlobalVariables();
            this.setupErrorHandling();
            this.initializeApps();
            this.setupLazyLoading();
            this.bindEvents();
            this.setupPerformanceMonitoring();
        },

        /**
         * Set up global variables for React apps
         */
        setupGlobalVariables() {
            // Make ReactifyWP available globally
            window.ReactifyWP = this;

            // Initialize app configurations
            this.apps = window.ReactifyWP?.apps || {};
            this.queue = window.ReactifyWP?.queue || [];

            // Set up legacy compatibility
            if (window.reactifyWP?.projects) {
                Object.keys(window.reactifyWP.projects).forEach(slug => {
                    const project = window.reactifyWP.projects[slug];
                    window[`reactify_${slug}_data`] = project;
                });
            }
        },

        /**
         * Set up global error handling
         */
        setupErrorHandling() {
            // Handle script loading errors
            window.ReactifyWP.handleScriptError = (script) => {
                const src = script.src;
                const slug = this.extractSlugFromUrl(src);

                if (slug) {
                    this.handleAppError(slug, new Error(`Failed to load script: ${src}`));
                }
            };

            // Handle unhandled promise rejections
            window.addEventListener('unhandledrejection', (event) => {
                if (event.reason && event.reason.reactifyApp) {
                    this.handleAppError(event.reason.reactifyApp, event.reason);
                    event.preventDefault();
                }
            });

            // Handle general JavaScript errors
            window.addEventListener('error', (event) => {
                if (event.filename && event.filename.includes('reactify-projects')) {
                    const slug = this.extractSlugFromUrl(event.filename);
                    if (slug) {
                        this.handleAppError(slug, new Error(event.message));
                    }
                }
            });
        },

        /**
         * Initialize React apps
         */
        initializeApps() {
            // Process queued apps first
            if (this.queue && this.queue.length > 0) {
                this.queue.forEach(slug => {
                    this.initializeQueuedApp(slug);
                });
            }

            // Find all ReactifyWP containers
            const containers = document.querySelectorAll('[class*="reactify-container"]');

            containers.forEach(container => {
                const slug = container.dataset.reactifySlug;
                if (slug && !this.state.apps.has(slug)) {
                    this.initializeApp(slug, container);
                }
            });

            // Initialize legacy containers
            const legacyContainers = document.querySelectorAll('[id^="reactify-"]');
            legacyContainers.forEach(container => {
                const slug = container.id.replace(/^reactify-(.+?)-\d+$/, '$1') || container.id.replace('reactify-', '');
                if (slug && !this.state.apps.has(slug)) {
                    this.initializeApp(slug, container);
                }
            });
        },

        /**
         * Initialize queued app
         */
        initializeQueuedApp(slug) {
            const appConfig = this.apps[slug];
            if (!appConfig) {
                console.warn(`ReactifyWP: No configuration found for app "${slug}"`);
                return;
            }

            const container = document.getElementById(appConfig.containerId);
            if (!container) {
                console.warn(`ReactifyWP: Container "${appConfig.containerId}" not found for app "${slug}"`);
                return;
            }

            this.initializeApp(slug, container, appConfig);
        },

        /**
         * Initialize individual React app
         */
        initializeApp(slug, container, appConfig = null) {
            // Get app configuration
            const config = appConfig || this.apps[slug] || this.createLegacyConfig(slug, container);

            // Check if app is already loading or loaded
            if (this.state.loadingApps.has(slug) || this.state.apps.has(slug)) {
                return;
            }

            // Mark as loading
            this.state.loadingApps.add(slug);

            // Start performance monitoring
            if (this.config.performance) {
                this.startPerformanceMonitoring(slug);
            }

            // Set up global variables for this specific app
            this.setupAppGlobals(slug, container, config);

            // Check loading strategy
            if (config.loading === 'lazy') {
                this.setupLazyApp(slug, container, config);
                return;
            }

            // Initialize app immediately
            this.mountApp(slug, container, config);
        },

        /**
         * Create legacy configuration for backward compatibility
         */
        createLegacyConfig(slug, container) {
            return {
                slug: slug,
                name: slug,
                version: '1.0.0',
                containerId: container.id,
                props: {},
                config: {},
                theme: '',
                responsive: true,
                debug: false,
                errorBoundary: true,
                loading: 'auto',
                wordpress: {
                    ajaxUrl: window.ajaxurl || '/wp-admin/admin-ajax.php',
                    nonce: '',
                    userId: 0,
                    isAdmin: false,
                    locale: 'en_US',
                    homeUrl: window.location.origin,
                    apiUrl: window.location.origin + '/wp-json/reactifywp/v1/'
                }
            };
        },

        /**
         * Set up app-specific global variables
         */
        setupAppGlobals(slug, container, config) {
            // Legacy compatibility
            window.reactifySlug = slug;
            window.reactifyMountId = config.containerId;
            window.reactifyContainer = container;

            // Enhanced configuration
            window[`reactify_${slug}_config`] = config;
            window[`reactify_${slug}_container`] = container;
        },

        /**
         * Set up lazy loading
         */
        setupLazyLoading() {
            if (!('IntersectionObserver' in window)) {
                // Fallback for browsers without IntersectionObserver
                this.initializeLazyAppsFallback();
                return;
            }

            const lazyContainers = document.querySelectorAll('[data-reactify-lazy="true"]');

            if (lazyContainers.length === 0) {
                return;
            }

            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        const container = entry.target;
                        const slug = container.dataset.reactifySlug;

                        if (slug) {
                            observer.unobserve(container);
                            this.loadLazyApp(slug, container);
                        }
                    }
                });
            }, {
                rootMargin: `${this.config.lazyLoadThreshold}px`
            });

            lazyContainers.forEach(container => {
                observer.observe(container);
                this.state.observers.set(container.dataset.reactifySlug, observer);
            });
        },

        /**
         * Set up lazy app for intersection observer
         */
        setupLazyApp(slug, container, config) {
            container.dataset.reactifyLazy = 'true';
            container.dataset.reactifySlug = slug;

            // Add to lazy loading observer if not already set up
            if ('IntersectionObserver' in window && !this.state.observers.has(slug)) {
                const observer = new IntersectionObserver((entries) => {
                    entries.forEach(entry => {
                        if (entry.isIntersecting) {
                            observer.unobserve(container);
                            this.loadLazyApp(slug, container, config);
                        }
                    });
                }, {
                    rootMargin: `${this.config.lazyLoadThreshold}px`
                });

                observer.observe(container);
                this.state.observers.set(slug, observer);
            }
        },

        /**
         * Load lazy app when it comes into view
         */
        loadLazyApp(slug, container, config = null) {
            const appConfig = config || this.apps[slug];

            if (!appConfig) {
                console.warn(`ReactifyWP: No configuration found for lazy app "${slug}"`);
                return;
            }

            // Load app assets dynamically
            this.loadAppAssets(slug, appConfig).then(() => {
                this.mountApp(slug, container, appConfig);
            }).catch(error => {
                this.handleAppError(slug, error);
            });
        },

        /**
         * Mount React app
         */
        mountApp(slug, container, config) {
            try {
                // Show loading indicator
                this.showLoadingIndicator(container, config);

                // Set up error boundary if enabled
                if (config.errorBoundary) {
                    this.setupErrorBoundary(slug, container, config);
                }

                // Wait for React app to mount
                this.waitForAppMount(container, slug, config);

                // Dispatch initialization event
                this.dispatchAppEvent('reactifyWPInit', slug, container, config);

                // Store app state
                this.state.apps.set(slug, {
                    container: container,
                    config: config,
                    mounted: false,
                    error: null
                });

            } catch (error) {
                this.handleAppError(slug, error);
            }
        },

        /**
         * Load app assets dynamically
         */
        loadAppAssets(slug, config) {
            return new Promise((resolve, reject) => {
                const assetsToLoad = [];

                // This would be populated by the server-side asset manager
                // For now, we'll resolve immediately as assets are already enqueued
                resolve();
            });
        },

        /**
         * Show loading indicator
         */
        showLoadingIndicator(container, config = {}) {
            // Check if custom fallback is provided
            const existingFallback = container.querySelector('.reactify-loading-fallback');
            if (existingFallback) {
                existingFallback.style.display = 'block';
                return;
            }

            // Check if loading indicator already exists
            const existingLoader = container.querySelector('.reactify-loading-indicator');
            if (existingLoader) {
                existingLoader.style.display = 'block';
                return;
            }

            // Create new loading indicator
            const loader = document.createElement('div');
            loader.className = 'reactify-loading-indicator';
            loader.innerHTML = `
                <div class="reactify-spinner">
                    <div class="reactify-spinner-circle"></div>
                </div>
                <p class="reactify-loading-text">Loading React App...</p>
            `;

            container.appendChild(loader);
        },

        /**
         * Hide loading indicator
         */
        hideLoadingIndicator(container) {
            const loader = container.querySelector('.reactifywp-loader');
            if (loader) {
                loader.remove();
            }
        },

        /**
         * Set up error boundary for React app
         */
        setupErrorBoundary(slug, container, config) {
            const errorBoundary = container.querySelector('.reactify-error-boundary');
            if (!errorBoundary) {
                return;
            }

            // Add error event listener
            errorBoundary.addEventListener('error', (event) => {
                this.handleAppError(slug, event.error || new Error('React Error Boundary triggered'));
            });
        },

        /**
         * Wait for React app to mount
         */
        waitForAppMount(container, slug, config, attempts = 0) {
            const maxAttempts = 100; // 10 seconds with 100ms intervals
            const mountPoint = container.querySelector('.reactify-mount-point') || container.querySelector(`#${config.containerId}`);

            if (!mountPoint) {
                this.handleAppError(slug, new Error('Mount point not found'));
                return;
            }

            // Check if React app has mounted
            const hasContent = Array.from(mountPoint.children).some(child =>
                !child.classList.contains('reactify-loading-indicator') &&
                !child.classList.contains('reactify-loading-fallback')
            );

            if (hasContent) {
                this.hideLoadingIndicator(container);
                this.onAppMounted(container, slug, config);
                return;
            }

            if (attempts < maxAttempts) {
                setTimeout(() => {
                    this.waitForAppMount(container, slug, config, attempts + 1);
                }, 100);
            } else {
                this.onAppMountTimeout(container, slug, config);
            }
        },

        /**
         * Handle successful app mount
         */
        onAppMounted(container, slug, config) {
            container.classList.add('reactify-mounted');
            container.classList.remove('reactify-loading');

            // Update app state
            const appState = this.state.apps.get(slug);
            if (appState) {
                appState.mounted = true;
                appState.error = null;
            }

            // Remove from loading set
            this.state.loadingApps.delete(slug);

            // End performance monitoring
            if (this.config.performance) {
                this.endPerformanceMonitoring(slug);
            }

            // Dispatch mounted event
            this.dispatchAppEvent('reactifyWPMounted', slug, container, config);

            // Log success if debug mode
            if (config.debug) {
                console.log(`ReactifyWP: App "${slug}" mounted successfully`);
            }
        },

        /**
         * Handle app mount timeout
         */
        onAppMountTimeout(container, slug, config) {
            const error = new Error(`React app "${slug}" failed to mount within timeout period`);
            this.handleAppError(slug, error, container);
        },

        /**
         * Handle app errors
         */
        handleAppError(slug, error, container = null) {
            console.error(`ReactifyWP Error [${slug}]:`, error);

            // Update app state
            const appState = this.state.apps.get(slug);
            if (appState) {
                appState.error = error;
                appState.mounted = false;
            }

            // Add to failed apps
            this.state.failedApps.add(slug);
            this.state.loadingApps.delete(slug);

            // Find container if not provided
            if (!container) {
                const config = this.apps[slug];
                container = config ? document.getElementById(config.containerId) : null;
            }

            if (container) {
                this.hideLoadingIndicator(container);
                this.showErrorMessage(container, slug, error);
            }

            // Dispatch error event
            this.dispatchAppEvent('reactifyWPError', slug, container, { error });

            // Attempt retry if configured
            if (this.config.retryAttempts > 0) {
                this.retryAppLoad(slug, error);
            }
        },

        /**
         * Show error message
         */
        showErrorMessage(container, slug, error) {
            const errorDiv = document.createElement('div');
            errorDiv.className = 'reactify-error';

            const isAdmin = this.apps[slug]?.wordpress?.isAdmin || false;

            if (isAdmin) {
                errorDiv.innerHTML = `
                    <div class="reactify-error-admin">
                        <h4>ReactifyWP Error: ${slug}</h4>
                        <p><strong>Error:</strong> ${error.message}</p>
                        <p><strong>Stack:</strong> <code>${error.stack || 'No stack trace available'}</code></p>
                        <button type="button" class="reactify-retry-btn" data-slug="${slug}">Retry</button>
                    </div>
                `;
            } else {
                errorDiv.innerHTML = `
                    <div class="reactify-error-user">
                        <p>Unable to load content. Please try refreshing the page.</p>
                    </div>
                `;
            }

            container.appendChild(errorDiv);

            // Add retry functionality
            const retryBtn = errorDiv.querySelector('.reactify-retry-btn');
            if (retryBtn) {
                retryBtn.addEventListener('click', () => {
                    this.retryAppLoad(slug);
                    errorDiv.remove();
                });
            }
        },

        /**
         * Retry app loading
         */
        retryAppLoad(slug, previousError = null) {
            const appState = this.state.apps.get(slug);
            if (!appState) {
                return;
            }

            // Check retry attempts
            appState.retryCount = (appState.retryCount || 0) + 1;

            if (appState.retryCount > this.config.retryAttempts) {
                console.error(`ReactifyWP: Max retry attempts reached for app "${slug}"`);
                return;
            }

            // Clear error state
            this.state.failedApps.delete(slug);
            appState.error = null;

            // Retry after delay
            setTimeout(() => {
                console.log(`ReactifyWP: Retrying app "${slug}" (attempt ${appState.retryCount})`);
                this.initializeApp(slug, appState.container, appState.config);
            }, this.config.retryDelay * appState.retryCount);
        },

        /**
         * Start performance monitoring
         */
        startPerformanceMonitoring(slug) {
            if (!window.performance || !window.performance.mark) {
                return;
            }

            const startMark = `reactify-${slug}-start`;
            window.performance.mark(startMark);

            this.state.performance.set(slug, {
                startMark: startMark,
                startTime: Date.now()
            });
        },

        /**
         * End performance monitoring
         */
        endPerformanceMonitoring(slug) {
            const perfData = this.state.performance.get(slug);
            if (!perfData || !window.performance) {
                return;
            }

            const endMark = `reactify-${slug}-end`;
            const measureName = `reactify-${slug}-load`;

            window.performance.mark(endMark);
            window.performance.measure(measureName, perfData.startMark, endMark);

            const measure = window.performance.getEntriesByName(measureName)[0];
            const loadTime = Date.now() - perfData.startTime;

            // Log performance data
            console.log(`ReactifyWP Performance [${slug}]:`, {
                loadTime: `${loadTime}ms`,
                performanceEntry: measure
            });

            // Clean up marks
            window.performance.clearMarks(perfData.startMark);
            window.performance.clearMarks(endMark);
            window.performance.clearMeasures(measureName);

            this.state.performance.delete(slug);
        },

        /**
         * Dispatch app event
         */
        dispatchAppEvent(eventName, slug, container, data = {}) {
            const event = new CustomEvent(eventName, {
                detail: {
                    slug: slug,
                    container: container,
                    ...data
                }
            });

            document.dispatchEvent(event);
            if (container) {
                container.dispatchEvent(event);
            }
        },

        /**
         * Extract slug from URL
         */
        extractSlugFromUrl(url) {
            const match = url.match(/reactify-projects\/\d+\/([^\/]+)\//);
            return match ? match[1] : null;
        },

        /**
         * Bind event handlers
         */
        bindEvents() {
            // Handle window resize for responsive apps
            let resizeTimeout;
            window.addEventListener('resize', () => {
                clearTimeout(resizeTimeout);
                resizeTimeout = setTimeout(() => {
                    this.handleResize();
                }, 250);
            });

            // Handle visibility change for performance optimization
            document.addEventListener('visibilitychange', () => {
                this.handleVisibilityChange();
            });
        },

        /**
         * Handle window resize
         */
        handleResize() {
            const event = new CustomEvent('reactifyWPResize', {
                detail: {
                    width: window.innerWidth,
                    height: window.innerHeight
                }
            });
            
            document.dispatchEvent(event);
        },

        /**
         * Handle visibility change
         */
        handleVisibilityChange() {
            const event = new CustomEvent('reactifyWPVisibilityChange', {
                detail: {
                    hidden: document.hidden
                }
            });
            
            document.dispatchEvent(event);
        },

        /**
         * Initialize lazy apps fallback for older browsers
         */
        initializeLazyAppsFallback() {
            const lazyContainers = document.querySelectorAll('[data-reactify-lazy="true"]');

            // Load all lazy apps immediately as fallback
            lazyContainers.forEach(container => {
                const slug = container.dataset.reactifySlug;
                if (slug) {
                    this.loadLazyApp(slug, container);
                }
            });
        },

        /**
         * Set up performance monitoring
         */
        setupPerformanceMonitoring() {
            if (!this.config.performance || !window.performance) {
                return;
            }

            // Monitor page load performance
            window.addEventListener('load', () => {
                setTimeout(() => {
                    this.reportPagePerformance();
                }, 1000);
            });
        },

        /**
         * Report page performance
         */
        reportPagePerformance() {
            if (!window.performance || !window.performance.getEntriesByType) {
                return;
            }

            const navigation = window.performance.getEntriesByType('navigation')[0];
            const paintEntries = window.performance.getEntriesByType('paint');

            const performanceData = {
                pageLoad: navigation ? `${Math.round(navigation.loadEventEnd - navigation.fetchStart)}ms` : 'N/A',
                domContentLoaded: navigation ? `${Math.round(navigation.domContentLoadedEventEnd - navigation.fetchStart)}ms` : 'N/A',
                firstPaint: paintEntries.find(entry => entry.name === 'first-paint')?.startTime || 'N/A',
                firstContentfulPaint: paintEntries.find(entry => entry.name === 'first-contentful-paint')?.startTime || 'N/A',
                reactifyApps: this.state.apps.size,
                failedApps: this.state.failedApps.size
            };

            console.log('ReactifyWP Page Performance:', performanceData);
        },

        /**
         * Utility function to get project data
         */
        getProjectData(slug) {
            return this.apps[slug] || window.reactifyWP?.projects?.[slug] || null;
        },

        /**
         * Utility function to get all containers
         */
        getContainers() {
            return document.querySelectorAll('[class*="reactify-container"], [id^="reactify-"]');
        },

        /**
         * Utility function to get container by slug
         */
        getContainer(slug) {
            const config = this.apps[slug];
            if (config && config.containerId) {
                return document.getElementById(config.containerId);
            }
            return document.getElementById(`reactify-${slug}`);
        },

        /**
         * Get app state
         */
        getAppState(slug) {
            return this.state.apps.get(slug) || null;
        },

        /**
         * Check if app is loaded
         */
        isAppLoaded(slug) {
            const appState = this.state.apps.get(slug);
            return appState ? appState.mounted : false;
        },

        /**
         * Check if app failed to load
         */
        isAppFailed(slug) {
            return this.state.failedApps.has(slug);
        },

        /**
         * Get all loaded apps
         */
        getLoadedApps() {
            const loadedApps = [];
            this.state.apps.forEach((appState, slug) => {
                if (appState.mounted) {
                    loadedApps.push(slug);
                }
            });
            return loadedApps;
        },

        /**
         * Reload app
         */
        reloadApp(slug) {
            const appState = this.state.apps.get(slug);
            if (!appState) {
                console.warn(`ReactifyWP: App "${slug}" not found`);
                return;
            }

            // Clear current state
            this.state.apps.delete(slug);
            this.state.loadingApps.delete(slug);
            this.state.failedApps.delete(slug);

            // Clear container
            const container = appState.container;
            const mountPoint = container.querySelector('.reactify-mount-point');
            if (mountPoint) {
                mountPoint.innerHTML = '';
            }

            // Reinitialize
            this.initializeApp(slug, container, appState.config);
        },

        /**
         * Destroy app
         */
        destroyApp(slug) {
            const appState = this.state.apps.get(slug);
            if (!appState) {
                return;
            }

            // Clean up observer
            const observer = this.state.observers.get(slug);
            if (observer) {
                observer.disconnect();
                this.state.observers.delete(slug);
            }

            // Clean up container
            const container = appState.container;
            if (container) {
                container.innerHTML = '';
                container.classList.remove('reactify-mounted', 'reactify-loading');
            }

            // Clean up state
            this.state.apps.delete(slug);
            this.state.loadingApps.delete(slug);
            this.state.failedApps.delete(slug);
            this.state.performance.delete(slug);

            // Dispatch destroy event
            this.dispatchAppEvent('reactifyWPDestroy', slug, container);
        },

        /**
         * Load CSS dynamically
         */
        loadCSS(url, projectSlug, assetData) {
            return new Promise((resolve, reject) => {
                // Check if already loaded
                if (document.querySelector(`link[href="${url}"]`)) {
                    resolve();
                    return;
                }

                const link = document.createElement('link');
                link.rel = 'stylesheet';
                link.href = url;
                link.crossOrigin = 'anonymous';

                link.onload = () => {
                    console.log(`ReactifyWP: Loaded CSS for ${projectSlug}:`, url);
                    resolve();
                };

                link.onerror = () => {
                    console.error(`ReactifyWP: Failed to load CSS for ${projectSlug}:`, url);
                    reject(new Error(`Failed to load CSS: ${url}`));
                };

                document.head.appendChild(link);
            });
        },

        /**
         * Load JavaScript dynamically
         */
        loadJS(url, projectSlug, assetData) {
            return new Promise((resolve, reject) => {
                // Check if already loaded
                if (document.querySelector(`script[src="${url}"]`)) {
                    resolve();
                    return;
                }

                const script = document.createElement('script');
                script.src = url;
                script.crossOrigin = 'anonymous';
                script.defer = true;

                script.onload = () => {
                    console.log(`ReactifyWP: Loaded JS for ${projectSlug}:`, url);
                    resolve();
                };

                script.onerror = () => {
                    console.error(`ReactifyWP: Failed to load JS for ${projectSlug}:`, url);
                    reject(new Error(`Failed to load JS: ${url}`));
                };

                document.head.appendChild(script);
            });
        },

        /**
         * Preload assets
         */
        preloadAssets(assets) {
            assets.forEach(asset => {
                const link = document.createElement('link');
                link.rel = 'preload';
                link.href = asset.url;
                link.as = asset.type === 'js' ? 'script' : 'style';
                link.crossOrigin = 'anonymous';

                document.head.appendChild(link);
            });
        },

        /**
         * Set up lazy loading for projects
         */
        setupLazyLoading(projects) {
            if (!('IntersectionObserver' in window)) {
                // Fallback: load all assets immediately
                projects.forEach(slug => {
                    this.preloadProjectAssets(slug);
                });
                return;
            }

            // Set up intersection observer for lazy containers
            const lazyContainers = document.querySelectorAll('[data-reactify-lazy="true"]');

            if (lazyContainers.length === 0) {
                return;
            }

            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        const container = entry.target;
                        const slug = container.dataset.reactifySlug;

                        if (slug) {
                            observer.unobserve(container);
                            this.preloadProjectAssets(slug);
                        }
                    }
                });
            }, {
                rootMargin: '100px'
            });

            lazyContainers.forEach(container => {
                observer.observe(container);
            });
        },

        /**
         * Preload project assets
         */
        preloadProjectAssets(slug) {
            const appConfig = this.apps[slug];
            if (!appConfig || !appConfig.wordpress) {
                console.warn('ReactifyWP: No configuration available for asset preloading');
                return;
            }

            const data = {
                action: 'reactifywp_preload_assets',
                project: slug,
                nonce: appConfig.wordpress.nonce || ''
            };

            fetch(appConfig.wordpress.ajaxUrl || '/wp-admin/admin-ajax.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams(data)
            })
            .then(response => response.json())
            .then(result => {
                if (result.success) {
                    this.preloadAssets(result.data.assets);

                    // Execute preload script if provided
                    if (result.data.preload_script) {
                        eval(result.data.preload_script);
                    }
                }
            })
            .catch(error => {
                console.error('ReactifyWP: Asset preloading failed:', error);
            });
        },

        /**
         * Optimize images with lazy loading
         */
        optimizeImages() {
            if (!('IntersectionObserver' in window)) {
                return;
            }

            const images = document.querySelectorAll('.reactify-container img[data-src]');

            if (images.length === 0) {
                return;
            }

            const imageObserver = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        const img = entry.target;
                        img.src = img.dataset.src;
                        img.removeAttribute('data-src');
                        imageObserver.unobserve(img);
                    }
                });
            });

            images.forEach(img => {
                imageObserver.observe(img);
            });
        }
    };

    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => {
            ReactifyWP.init();
        });
    } else {
        ReactifyWP.init();
    }

    // Make ReactifyWP available globally for React apps
    window.ReactifyWP = ReactifyWP;

})();

/* CSS for loading and error states */
const style = document.createElement('style');
style.textContent = `
    .reactifywp-loader {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        padding: 40px 20px;
        text-align: center;
    }

    .reactifywp-spinner {
        margin-bottom: 16px;
    }

    .reactifywp-spinner-circle {
        width: 40px;
        height: 40px;
        border: 4px solid #f3f3f3;
        border-top: 4px solid #0073aa;
        border-radius: 50%;
        animation: reactifywp-spin 1s linear infinite;
    }

    @keyframes reactifywp-spin {
        0% { transform: rotate(0deg); }
        100% { transform: rotate(360deg); }
    }

    .reactifywp-loader p {
        margin: 0;
        color: #666;
        font-size: 14px;
    }

    .reactifywp-error {
        background: #f8d7da;
        color: #721c24;
        padding: 16px;
        border: 1px solid #f5c6cb;
        border-radius: 4px;
        margin: 16px 0;
    }

    .reactifywp-error p {
        margin: 0 0 8px 0;
    }

    .reactifywp-error p:last-child {
        margin-bottom: 0;
    }

    .reactifywp-scoped {
        all: revert;
    }

    .reactifywp-container {
        position: relative;
    }

    .reactifywp-mounted {
        /* Styles for successfully mounted apps */
    }
`;

document.head.appendChild(style);
