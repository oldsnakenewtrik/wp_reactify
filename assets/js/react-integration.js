/**
 * React Integration for ReactifyWP
 * Enhanced React app integration with WordPress
 */

(function() {
    'use strict';

    // Extend ReactifyWP with React-specific functionality
    window.ReactifyWP = window.ReactifyWP || {};
    
    window.ReactifyWP.ReactIntegration = {
        
        /**
         * Registered React apps
         */
        apps: new Map(),

        /**
         * App instances
         */
        instances: new Map(),

        /**
         * Configuration
         */
        config: {
            debug: false,
            errorBoundary: true,
            performance: true
        },

        /**
         * Initialize React integration
         */
        init(config = {}) {
            this.config = { ...this.config, ...config };
            
            // Set up React DevTools detection
            this.detectReactDevTools();
            
            // Set up performance monitoring
            if (this.config.performance) {
                this.setupPerformanceMonitoring();
            }

            // Set up error boundaries
            if (this.config.errorBoundary) {
                this.setupGlobalErrorBoundary();
            }

            if (this.config.debug) {
                console.log('ReactifyWP React Integration initialized', this.config);
            }
        },

        /**
         * Register React app
         */
        registerApp(slug, config) {
            const appConfig = {
                slug: slug,
                name: config.name || slug,
                version: config.version || '1.0.0',
                type: config.type || 'widget',
                container: config.container || `#reactify-${slug}`,
                component: config.component || null,
                props: config.props || {},
                state: config.state || {},
                hooks: config.hooks || {},
                errorBoundary: config.errorBoundary !== false,
                ssr: config.ssr || false,
                hydration: config.hydration || false,
                routing: config.routing || false,
                api: config.api || {},
                wordpress: config.wordpress || {}
            };

            this.apps.set(slug, appConfig);

            if (this.config.debug) {
                console.log(`Registered React app: ${slug}`, appConfig);
            }

            return appConfig;
        },

        /**
         * Initialize all registered apps
         */
        initializeApps() {
            this.apps.forEach((config, slug) => {
                this.initializeApp(slug);
            });
        },

        /**
         * Initialize specific app
         */
        async initializeApp(slug) {
            const config = this.apps.get(slug);
            if (!config) {
                console.warn(`ReactifyWP: App "${slug}" not found`);
                return;
            }

            try {
                // Find container
                const container = document.querySelector(config.container);
                if (!container) {
                    console.warn(`ReactifyWP: Container "${config.container}" not found for app "${slug}"`);
                    return;
                }

                // Set up app context
                const appContext = this.createAppContext(slug, config);

                // Initialize app based on type
                let instance;
                switch (config.type) {
                    case 'spa':
                        instance = await this.initializeSPA(slug, config, container, appContext);
                        break;
                    case 'mpa':
                        instance = await this.initializeMPA(slug, config, container, appContext);
                        break;
                    case 'widget':
                    default:
                        instance = await this.initializeWidget(slug, config, container, appContext);
                        break;
                }

                if (instance) {
                    this.instances.set(slug, instance);
                    this.triggerAppEvent(slug, 'initialized', { config, instance });
                }

            } catch (error) {
                console.error(`ReactifyWP: Failed to initialize app "${slug}"`, error);
                this.handleAppError(slug, error);
            }
        },

        /**
         * Create app context
         */
        createAppContext(slug, config) {
            const wp = window.ReactifyWP.WPBridge;
            
            return {
                slug: slug,
                config: config,
                wp: {
                    // WordPress data
                    site: wp.getSiteInfo(),
                    user: wp.getCurrentUser(),
                    page: wp.getPageInfo(),
                    post: wp.getCurrentPost(),
                    menus: wp.getMenus(),
                    customizer: wp.getCustomizerData(),
                    
                    // WordPress functions
                    getPosts: wp.getPosts.bind(wp),
                    getUsers: wp.getUsers.bind(wp),
                    getMedia: wp.getMedia.bind(wp),
                    getComments: wp.getComments.bind(wp),
                    createPost: wp.createPost.bind(wp),
                    updatePost: wp.updatePost.bind(wp),
                    deletePost: wp.deletePost.bind(wp),
                    createComment: wp.createComment.bind(wp),
                    updateComment: wp.updateComment.bind(wp),
                    approveComment: wp.approveComment.bind(wp),
                    updateUserMeta: wp.updateUserMeta.bind(wp),
                    userCan: wp.userCan.bind(wp),
                    isLoggedIn: wp.isUserLoggedIn.bind(wp),
                    
                    // Utility functions
                    formatDate: wp.formatDate.bind(wp),
                    getAssetUrl: wp.getAssetUrl.bind(wp),
                    getAdminUrl: wp.getAdminUrl.bind(wp)
                },
                
                // App-specific functions
                setState: (newState) => this.updateAppState(slug, newState),
                getState: () => this.getAppState(slug),
                emit: (event, data) => this.triggerAppEvent(slug, event, data),
                on: (event, callback) => this.subscribeToAppEvent(slug, event, callback),
                off: (event, callback) => this.unsubscribeFromAppEvent(slug, event, callback),
                
                // Performance tracking
                trackPerformance: (metrics) => wp.trackPerformance(metrics),
                trackInteraction: (action, element, value) => wp.trackInteraction(action, element, value),
                
                // Error handling
                reportError: (error) => this.handleAppError(slug, error)
            };
        },

        /**
         * Initialize SPA (Single Page Application)
         */
        async initializeSPA(slug, config, container, context) {
            // SPA initialization logic
            const instance = {
                type: 'spa',
                container: container,
                context: context,
                router: null,
                routes: config.routes || []
            };

            // Set up routing if enabled
            if (config.routing && config.routes) {
                instance.router = this.setupSPARouting(slug, config.routes, context);
            }

            // Mount main component
            if (config.component) {
                this.mountComponent(config.component, container, context);
            }

            return instance;
        },

        /**
         * Initialize MPA (Multi Page Application)
         */
        async initializeMPA(slug, config, container, context) {
            // MPA initialization logic
            const instance = {
                type: 'mpa',
                container: container,
                context: context,
                pages: config.pages || {}
            };

            // Mount page-specific component
            const currentPage = this.getCurrentPageType();
            if (config.pages && config.pages[currentPage]) {
                this.mountComponent(config.pages[currentPage], container, context);
            } else if (config.component) {
                this.mountComponent(config.component, container, context);
            }

            return instance;
        },

        /**
         * Initialize Widget
         */
        async initializeWidget(slug, config, container, context) {
            // Widget initialization logic
            const instance = {
                type: 'widget',
                container: container,
                context: context
            };

            // Mount widget component
            if (config.component) {
                this.mountComponent(config.component, container, context);
            }

            return instance;
        },

        /**
         * Mount React component
         */
        mountComponent(component, container, context) {
            // This is a placeholder for actual React mounting
            // In a real implementation, this would use ReactDOM.render or createRoot
            
            if (typeof component === 'function') {
                // Function component
                try {
                    const element = component(context);
                    if (element && typeof element === 'object' && element.type) {
                        // This looks like a React element
                        this.renderReactElement(element, container);
                    }
                } catch (error) {
                    console.error('Error mounting function component:', error);
                    this.handleAppError(context.slug, error);
                }
            } else if (typeof component === 'string') {
                // HTML string
                container.innerHTML = component;
            } else if (component && typeof component === 'object') {
                // React element or component class
                this.renderReactElement(component, container);
            }
        },

        /**
         * Render React element (placeholder)
         */
        renderReactElement(element, container) {
            // Placeholder for React rendering
            // In a real implementation, this would use ReactDOM
            console.log('Rendering React element:', element, 'in container:', container);
            
            // For now, just add a placeholder
            container.innerHTML = `
                <div class="reactify-app-placeholder">
                    <p>React App Placeholder</p>
                    <p>Component: ${element.type?.name || 'Unknown'}</p>
                </div>
            `;
        },

        /**
         * Set up SPA routing
         */
        setupSPARouting(slug, routes, context) {
            const router = {
                routes: routes,
                currentRoute: null,
                navigate: (path) => this.navigateToRoute(slug, path),
                back: () => window.history.back(),
                forward: () => window.history.forward()
            };

            // Listen for popstate events
            window.addEventListener('popstate', (event) => {
                this.handleRouteChange(slug, window.location.pathname);
            });

            // Handle initial route
            this.handleRouteChange(slug, window.location.pathname);

            return router;
        },

        /**
         * Navigate to route
         */
        navigateToRoute(slug, path) {
            window.history.pushState({}, '', path);
            this.handleRouteChange(slug, path);
        },

        /**
         * Handle route change
         */
        handleRouteChange(slug, path) {
            const config = this.apps.get(slug);
            const instance = this.instances.get(slug);
            
            if (!config || !instance || !instance.router) {
                return;
            }

            // Find matching route
            const route = instance.router.routes.find(r => {
                if (typeof r.path === 'string') {
                    return r.path === path;
                } else if (r.path instanceof RegExp) {
                    return r.path.test(path);
                }
                return false;
            });

            if (route) {
                instance.router.currentRoute = route;
                
                // Mount route component
                if (route.component) {
                    this.mountComponent(route.component, instance.container, instance.context);
                }
                
                this.triggerAppEvent(slug, 'route-changed', { path, route });
            }
        },

        /**
         * Get current page type
         */
        getCurrentPageType() {
            const wp = window.ReactifyWP.WPBridge;
            const page = wp.getPageInfo();
            
            if (page) {
                if (page.is_front_page) return 'front-page';
                if (page.is_home) return 'home';
                if (page.is_single) return 'single';
                if (page.is_page) return 'page';
                if (page.is_archive) return 'archive';
                if (page.is_search) return 'search';
                if (page.is_404) return '404';
            }
            
            return 'default';
        },

        /**
         * Update app state
         */
        updateAppState(slug, newState) {
            const config = this.apps.get(slug);
            if (config) {
                config.state = { ...config.state, ...newState };
                this.triggerAppEvent(slug, 'state-updated', { state: config.state });
            }
        },

        /**
         * Get app state
         */
        getAppState(slug) {
            const config = this.apps.get(slug);
            return config ? config.state : {};
        },

        /**
         * Trigger app event
         */
        triggerAppEvent(slug, event, data = {}) {
            const eventData = {
                app: slug,
                event: event,
                data: data,
                timestamp: Date.now()
            };

            // Trigger local event
            const customEvent = new CustomEvent(`reactify-app-${event}`, {
                detail: eventData
            });
            document.dispatchEvent(customEvent);

            // Send to WordPress
            if (window.ReactifyWP.WPBridge) {
                window.ReactifyWP.WPBridge.sendAppEvent(slug, event, data);
            }

            if (this.config.debug) {
                console.log(`App event [${slug}]:`, event, data);
            }
        },

        /**
         * Subscribe to app event
         */
        subscribeToAppEvent(slug, event, callback) {
            document.addEventListener(`reactify-app-${event}`, (e) => {
                if (e.detail.app === slug) {
                    callback(e.detail.data);
                }
            });
        },

        /**
         * Unsubscribe from app event
         */
        unsubscribeFromAppEvent(slug, event, callback) {
            document.removeEventListener(`reactify-app-${event}`, callback);
        },

        /**
         * Handle app error
         */
        handleAppError(slug, error) {
            console.error(`ReactifyWP App Error [${slug}]:`, error);

            const errorData = {
                message: error.message || 'Unknown error',
                stack: error.stack || '',
                url: window.location.href,
                timestamp: Date.now()
            };

            // Report to WordPress
            if (window.ReactifyWP.WPBridge) {
                window.ReactifyWP.WPBridge.reportError(errorData);
            }

            // Trigger error event
            this.triggerAppEvent(slug, 'error', errorData);

            // Show error boundary if enabled
            const config = this.apps.get(slug);
            if (config && config.errorBoundary) {
                this.showErrorBoundary(slug, error);
            }
        },

        /**
         * Show error boundary
         */
        showErrorBoundary(slug, error) {
            const instance = this.instances.get(slug);
            if (!instance || !instance.container) {
                return;
            }

            const isAdmin = window.ReactifyWP.WPBridge?.userCan('manage_options');
            
            const errorHTML = `
                <div class="reactify-error-boundary">
                    <div class="reactify-error-content">
                        <h3>Something went wrong</h3>
                        <p>The React application encountered an error and could not continue.</p>
                        ${isAdmin ? `
                            <details>
                                <summary>Error Details (Admin Only)</summary>
                                <pre>${error.message}\n\n${error.stack}</pre>
                            </details>
                        ` : ''}
                        <button onclick="location.reload()" class="reactify-retry-btn">
                            Reload Page
                        </button>
                    </div>
                </div>
            `;

            instance.container.innerHTML = errorHTML;
        },

        /**
         * Detect React DevTools
         */
        detectReactDevTools() {
            if (window.__REACT_DEVTOOLS_GLOBAL_HOOK__) {
                console.log('ReactifyWP: React DevTools detected');
                this.config.hasReactDevTools = true;
            }
        },

        /**
         * Set up performance monitoring
         */
        setupPerformanceMonitoring() {
            // Monitor React app performance
            if (window.performance && window.performance.mark) {
                // Set up performance observers
                if ('PerformanceObserver' in window) {
                    const observer = new PerformanceObserver((list) => {
                        list.getEntries().forEach(entry => {
                            if (entry.name.includes('reactify')) {
                                this.trackPerformanceEntry(entry);
                            }
                        });
                    });

                    observer.observe({ entryTypes: ['measure', 'mark'] });
                }
            }
        },

        /**
         * Track performance entry
         */
        trackPerformanceEntry(entry) {
            const metrics = {
                name: entry.name,
                duration: entry.duration,
                startTime: entry.startTime,
                entryType: entry.entryType
            };

            if (window.ReactifyWP.WPBridge) {
                window.ReactifyWP.WPBridge.trackPerformance(metrics);
            }
        },

        /**
         * Set up global error boundary
         */
        setupGlobalErrorBoundary() {
            // This would be implemented with actual React error boundaries
            // For now, we'll use the existing error handling
        },

        /**
         * Destroy app
         */
        destroyApp(slug) {
            const instance = this.instances.get(slug);
            if (instance) {
                // Clean up instance
                if (instance.container) {
                    instance.container.innerHTML = '';
                }

                this.instances.delete(slug);
                this.triggerAppEvent(slug, 'destroyed');
            }
        },

        /**
         * Reload app
         */
        reloadApp(slug) {
            this.destroyApp(slug);
            setTimeout(() => {
                this.initializeApp(slug);
            }, 100);
        }
    };

    // Auto-initialize if configuration is available
    if (window.ReactifyWPIntegration) {
        window.ReactifyWP.ReactIntegration.init(window.ReactifyWPIntegration);
    }

    // Expose initializeApps globally for backward compatibility
    window.ReactifyWP.initializeApps = function() {
        window.ReactifyWP.ReactIntegration.initializeApps();
    };

})();
