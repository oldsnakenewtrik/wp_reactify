import React from 'react';
import ReactDOM from 'react-dom/client';

export function createReactifyMount(AppComponent: React.ComponentType) {
  function mountApp() {
    // Find ReactifyWP containers with multiple selectors
    const containers = [
      ...document.querySelectorAll('[data-reactify-slug]'),
      ...document.querySelectorAll('[id*="reactify"]'),
      ...document.querySelectorAll('.reactify-container'),
      ...document.querySelectorAll('[class*="reactify-"]')
    ] as HTMLElement[];
    
    console.log('SonderCare ReactifyWP: Found containers:', containers.length);
    
    containers.forEach((container, index) => {
      if (container && !container.hasAttribute('data-sondercare-mounted')) {
        container.setAttribute('data-sondercare-mounted', 'true');
        
        // Clear any existing content (loading indicators, etc.)
        container.innerHTML = '';
        
        // Create wrapper div for styling isolation
        const appWrapper = document.createElement('div');
        appWrapper.className = 'sondercare-app-wrapper';
        appWrapper.style.cssText = `
          width: 100%;
          min-height: 600px;
          font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', 'Roboto', sans-serif;
          box-sizing: border-box;
          all: revert;
        `;
        container.appendChild(appWrapper);
        
        try {
          // Mount React app using modern or legacy API
          if (ReactDOM.createRoot) {
            const root = ReactDOM.createRoot(appWrapper);
            root.render(React.createElement(AppComponent));
          } else {
            // Fallback for older React versions
            (ReactDOM as any).render(React.createElement(AppComponent), appWrapper);
          }
          
          console.log(`SonderCare app mounted to container ${index + 1}:`, container);
          
          // Dispatch custom event for tracking
          const event = new CustomEvent('sondercareAppMounted', {
            detail: { container, component: AppComponent.name }
          });
          window.dispatchEvent(event);
          
        } catch (error) {
          console.error('SonderCare: Failed to mount app:', error);
          appWrapper.innerHTML = `
            <div style="padding: 20px; background: #f8d7da; color: #721c24; border-radius: 4px;">
              <h3>SonderCare Bed Selector - Loading Error</h3>
              <p>Failed to load the bed selector application. Please refresh the page or contact support.</p>
              <details>
                <summary>Error Details</summary>
                <pre style="font-size: 12px; overflow: auto;">${error.message}</pre>
              </details>
            </div>
          `;
        }
      }
    });
    
    // If no containers found, log helpful debug info
    if (containers.length === 0) {
      console.warn('SonderCare ReactifyWP: No containers found. Looking for:', [
        '[data-reactify-slug]',
        '[id*="reactify"]', 
        '.reactify-container',
        '[class*="reactify-"]'
      ]);
      
      // Show all potential containers for debugging
      const allDivs = document.querySelectorAll('div');
      console.log('SonderCare ReactifyWP: All divs on page:', allDivs.length);
      
      // Look for any div that might be a ReactifyWP container
      const potentialContainers = Array.from(allDivs).filter(div => 
        div.innerHTML.includes('Loading') || 
        div.innerHTML.includes('reactify') ||
        div.className.includes('reactify') ||
        div.id.includes('reactify')
      );
      
      if (potentialContainers.length > 0) {
        console.log('SonderCare ReactifyWP: Potential containers found:', potentialContainers);
      }
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
              const element = node as Element;
              const hasReactifyContainer = element.querySelector && (
                element.querySelector('[data-reactify-slug]') ||
                element.querySelector('[id*="reactify"]') ||
                element.querySelector('.reactify-container') ||
                (element.matches && (
                  element.matches('[data-reactify-slug]') ||
                  element.matches('[id*="reactify"]') ||
                  element.matches('.reactify-container')
                ))
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
      (window as any).SonderCareMountObserver = observer;
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
      const mountedContainers = document.querySelectorAll('[data-sondercare-mounted="true"]');
      mountedContainers.forEach(container => {
        container.removeAttribute('data-sondercare-mounted');
        container.innerHTML = '';
      });
      
      // Clean up observer
      if ((window as any).SonderCareMountObserver) {
        (window as any).SonderCareMountObserver.disconnect();
        delete (window as any).SonderCareMountObserver;
      }
    }
  };
}
