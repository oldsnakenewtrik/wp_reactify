// ReactifyWP Auto-Mount System for Complex Apps
// This handles mounting React apps to WordPress containers

export function createReactifyMount(AppComponent) {
  function mountApp() {
    // Find ReactifyWP containers with multiple selectors
    const containers = [
      ...document.querySelectorAll('[data-reactify-slug]'),
      ...document.querySelectorAll('[id*="reactify"]'),
      ...document.querySelectorAll('.reactify-container'),
      ...document.querySelectorAll('[class*="reactify-"]')
    ];
    
    console.log('ReactifyWP: Found containers:', containers.length);
    
    containers.forEach((container, index) => {
      if (container && !container.hasAttribute('data-app-mounted')) {
        container.setAttribute('data-app-mounted', 'true');
        
        // Clear any existing content (loading indicators, etc.)
        container.innerHTML = '';
        
        // Add a wrapper div for better styling control
        const appWrapper = document.createElement('div');
        appWrapper.className = 'reactify-app-wrapper';
        appWrapper.style.cssText = `
          width: 100%;
          min-height: 400px;
          font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', 'Roboto', sans-serif;
          box-sizing: border-box;
        `;
        container.appendChild(appWrapper);
        
        try {
          // Mount React app using modern or legacy API
          if (window.ReactDOM.createRoot) {
            const root = window.ReactDOM.createRoot(appWrapper);
            root.render(window.React.createElement(AppComponent));
          } else {
            window.ReactDOM.render(window.React.createElement(AppComponent), appWrapper);
          }
          
          console.log(`ReactifyWP: App mounted to container ${index + 1}:`, container);
          
          // Dispatch custom event for tracking
          const event = new CustomEvent('reactifyAppMounted', {
            detail: { container, component: AppComponent.name }
          });
          window.dispatchEvent(event);
          
        } catch (error) {
          console.error('ReactifyWP: Failed to mount app:', error);
          appWrapper.innerHTML = `
            <div style="padding: 20px; background: #f8d7da; color: #721c24; border-radius: 4px;">
              <h3>App Loading Error</h3>
              <p>Failed to load the React application. Please check the console for details.</p>
              <details>
                <summary>Error Details</summary>
                <pre>${error.message}</pre>
              </details>
            </div>
          `;
        }
      }
    });
    
    // If no containers found, log helpful debug info
    if (containers.length === 0) {
      console.warn('ReactifyWP: No containers found. Looking for:', [
        '[data-reactify-slug]',
        '[id*="reactify"]', 
        '.reactify-container',
        '[class*="reactify-"]'
      ]);
      
      // Show all potential containers for debugging
      const allDivs = document.querySelectorAll('div');
      console.log('ReactifyWP: All divs on page:', allDivs.length);
      console.log('ReactifyWP: Page HTML:', document.body.innerHTML.substring(0, 500) + '...');
    }
  }
  
  // Multiple mounting strategies for reliability
  function initializeMount() {
    // Strategy 1: Immediate mount if DOM is ready
    if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', mountApp);
    } else {
      mountApp();
    }
    
    // Strategy 2: Delayed mounts for dynamic content
    setTimeout(mountApp, 100);   // Quick retry
    setTimeout(mountApp, 500);   // Medium retry  
    setTimeout(mountApp, 1000);  // Slow retry
    setTimeout(mountApp, 2000);  // Final retry
    
    // Strategy 3: Mutation observer for dynamically added containers
    if (window.MutationObserver) {
      const observer = new MutationObserver((mutations) => {
        let shouldMount = false;
        mutations.forEach((mutation) => {
          mutation.addedNodes.forEach((node) => {
            if (node.nodeType === 1) { // Element node
              const hasReactifyContainer = node.querySelector && (
                node.querySelector('[data-reactify-slug]') ||
                node.querySelector('[id*="reactify"]') ||
                node.querySelector('.reactify-container') ||
                node.matches && (
                  node.matches('[data-reactify-slug]') ||
                  node.matches('[id*="reactify"]') ||
                  node.matches('.reactify-container')
                )
              );
              if (hasReactifyContainer) {
                shouldMount = true;
              }
            }
          });
        });
        
        if (shouldMount) {
          setTimeout(mountApp, 50); // Small delay to ensure DOM is stable
        }
      });
      
      observer.observe(document.body, {
        childList: true,
        subtree: true
      });
      
      // Store observer for cleanup
      window.ReactifyMountObserver = observer;
    }
    
    // Strategy 4: Window load event (final fallback)
    window.addEventListener('load', () => {
      setTimeout(mountApp, 100);
    });
  }
  
  // Initialize mounting
  initializeMount();
  
  // Return API for manual control
  return {
    mount: mountApp,
    component: AppComponent,
    unmount: () => {
      // Clean up mounted apps
      const mountedContainers = document.querySelectorAll('[data-app-mounted="true"]');
      mountedContainers.forEach(container => {
        container.removeAttribute('data-app-mounted');
        container.innerHTML = '';
      });
      
      // Clean up observer
      if (window.ReactifyMountObserver) {
        window.ReactifyMountObserver.disconnect();
        delete window.ReactifyMountObserver;
      }
    }
  };
}

// Global error boundary for React apps
export function createErrorBoundary() {
  return class ReactifyErrorBoundary extends React.Component {
    constructor(props) {
      super(props);
      this.state = { hasError: false, error: null };
    }

    static getDerivedStateFromError(error) {
      return { hasError: true, error };
    }

    componentDidCatch(error, errorInfo) {
      console.error('ReactifyWP Error Boundary caught an error:', error, errorInfo);
    }

    render() {
      if (this.state.hasError) {
        return React.createElement('div', {
          style: {
            padding: '20px',
            background: '#f8d7da',
            color: '#721c24',
            borderRadius: '4px',
            margin: '10px 0'
          }
        }, [
          React.createElement('h3', { key: 'title' }, 'Something went wrong'),
          React.createElement('p', { key: 'message' }, 'The React application encountered an error and could not render properly.'),
          React.createElement('details', { key: 'details' }, [
            React.createElement('summary', { key: 'summary' }, 'Error Details'),
            React.createElement('pre', {
              key: 'error',
              style: { fontSize: '12px', overflow: 'auto' }
            }, this.state.error?.toString())
          ])
        ]);
      }

      return this.props.children;
    }
  };
}

// Utility to ensure React is available
export function ensureReact() {
  return new Promise((resolve, reject) => {
    if (window.React && window.ReactDOM) {
      resolve();
      return;
    }

    // Load React from CDN if not available
    const reactScript = document.createElement('script');
    reactScript.src = 'https://unpkg.com/react@18/umd/react.production.min.js';
    reactScript.crossOrigin = 'anonymous';

    reactScript.onload = () => {
      const reactDOMScript = document.createElement('script');
      reactDOMScript.src = 'https://unpkg.com/react-dom@18/umd/react-dom.production.min.js';
      reactDOMScript.crossOrigin = 'anonymous';

      reactDOMScript.onload = () => resolve();
      reactDOMScript.onerror = () => reject(new Error('Failed to load ReactDOM'));

      document.head.appendChild(reactDOMScript);
    };

    reactScript.onerror = () => reject(new Error('Failed to load React'));
    document.head.appendChild(reactScript);
  });
}
