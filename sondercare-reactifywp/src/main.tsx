import React, { StrictMode } from 'react'
import { createRoot } from 'react-dom/client'
import App from './App.tsx'
import './index.css'
import { createReactifyMount } from './reactify-mount.tsx'

// Error Boundary Component
class ErrorBoundary extends React.Component {
  constructor(props) {
    super(props);
    this.state = { hasError: false, error: null };
  }
  
  static getDerivedStateFromError(error) {
    return { hasError: true, error };
  }
  
  componentDidCatch(error, errorInfo) {
    console.error('SonderCare App Error:', error, errorInfo);
  }
  
  render() {
    if (this.state.hasError) {
      return (
        <div style={{
          padding: '20px',
          background: '#f8d7da',
          color: '#721c24',
          borderRadius: '4px',
          margin: '10px 0'
        }}>
          <h3>SonderCare App Error</h3>
          <p>The bed selector encountered an error. Please refresh the page.</p>
          <details>
            <summary>Error Details</summary>
            <pre style={{ fontSize: '12px', overflow: 'auto' }}>
              {this.state.error?.toString()}
            </pre>
          </details>
        </div>
      );
    }
    
    return this.props.children;
  }
}

// Wrapped App component with error boundary
function WrappedApp() {
  return (
    <ErrorBoundary>
      <StrictMode>
        <App />
      </StrictMode>
    </ErrorBoundary>
  );
}

// ReactifyWP Integration
function initializeReactifyWP() {
  try {
    // Create ReactifyWP mount system
    const reactifyMount = createReactifyMount(WrappedApp);
    
    // Expose globally for debugging and manual control
    window.SonderCareApp = {
      mount: reactifyMount.mount,
      unmount: reactifyMount.unmount,
      component: WrappedApp,
      version: '1.0.0'
    };
    
    console.log('SonderCare Bed Selector initialized for ReactifyWP');
    
  } catch (error) {
    console.error('Failed to initialize SonderCare ReactifyWP integration:', error);
    
    // Fallback error display
    const fallbackContainers = document.querySelectorAll('div[id], div[class]');
    fallbackContainers.forEach(container => {
      if (container.innerHTML.includes('Loading') || container.innerHTML.includes('reactify')) {
        container.innerHTML = `
          <div style="padding: 20px; background: #f8d7da; color: #721c24; border-radius: 4px;">
            <h3>SonderCare App Initialization Failed</h3>
            <p>Could not load the SonderCare Bed Selector. Please refresh the page or contact support.</p>
            <details>
              <summary>Technical Details</summary>
              <pre>${error.message}</pre>
            </details>
          </div>
        `;
      }
    });
  }
}

// Standard React development mode (for local development)
function initializeStandardReact() {
  const rootElement = document.getElementById('root');
  if (rootElement) {
    createRoot(rootElement).render(
      <StrictMode>
        <App />
      </StrictMode>
    );
    console.log('SonderCare Bed Selector initialized in development mode');
  }
}

// Determine initialization mode
function initialize() {
  // Check if we're in a ReactifyWP environment
  const isReactifyWP = 
    // Check for ReactifyWP containers
    document.querySelector('[data-reactify-slug]') ||
    document.querySelector('[id*="reactify"]') ||
    document.querySelector('.reactify-container') ||
    // Check for WordPress indicators
    document.body.classList.contains('wordpress') ||
    document.querySelector('meta[name="generator"][content*="WordPress"]') ||
    window.wp !== undefined ||
    // Check URL patterns
    window.location.href.includes('/wp-content/') ||
    window.location.href.includes('/wp-admin/');
    
  if (isReactifyWP) {
    console.log('Detected ReactifyWP/WordPress environment');
    initializeReactifyWP();
  } else {
    console.log('Detected standard React environment');
    initializeStandardReact();
  }
}

// Initialize based on DOM state
if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', initialize);
} else {
  initialize();
}

// Also try initialization after a short delay (for dynamic content)
setTimeout(initialize, 100);

// Export for external access
export { WrappedApp as default, initializeReactifyWP, initializeStandardReact };
