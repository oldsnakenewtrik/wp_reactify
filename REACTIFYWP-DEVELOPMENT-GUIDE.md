# ReactifyWP Development Guide
## Complete Workflow for Building Complex React Apps

### 🎯 Overview
This guide provides a **repeatable process** for creating complex React applications that work seamlessly with the ReactifyWP WordPress plugin.

### 📋 Prerequisites
- Node.js 16+ installed
- Basic React knowledge
- WordPress site with ReactifyWP plugin installed
- Code editor (VS Code recommended)

---

## 🚀 Quick Start Workflow

### Step 1: Project Setup
```bash
# Create new React project
npm create vite@latest my-reactify-app -- --template react
cd my-reactify-app
npm install

# Install additional dependencies for complex apps
npm install react-router-dom axios styled-components
```

### Step 2: Create ReactifyWP Mounting System
Create `src/reactify-mount.js`:
```javascript
// ReactifyWP Auto-Mount System
export function createReactifyMount(AppComponent) {
  function mountApp() {
    // Find ReactifyWP containers
    const containers = [
      ...document.querySelectorAll('[data-reactify-slug]'),
      ...document.querySelectorAll('[id*="reactify"]'),
      ...document.querySelectorAll('.reactify-container')
    ];
    
    containers.forEach(container => {
      if (container && !container.hasAttribute('data-app-mounted')) {
        container.setAttribute('data-app-mounted', 'true');
        container.innerHTML = '';
        
        // Mount React app
        const root = ReactDOM.createRoot ? 
          ReactDOM.createRoot(container) : 
          null;
          
        if (root) {
          root.render(React.createElement(AppComponent));
        } else {
          ReactDOM.render(React.createElement(AppComponent), container);
        }
        
        console.log('App mounted to:', container);
      }
    });
  }
  
  // Auto-mount when DOM is ready
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', mountApp);
  } else {
    mountApp();
  }
  
  // Retry mounting (for dynamic content)
  setTimeout(mountApp, 100);
  setTimeout(mountApp, 500);
  setTimeout(mountApp, 1000);
  
  return { mount: mountApp, component: AppComponent };
}
```

### Step 3: Modify Your Main App
Update `src/main.jsx`:
```javascript
import React from 'react'
import ReactDOM from 'react-dom/client'
import App from './App.jsx'
import './index.css'
import { createReactifyMount } from './reactify-mount.js'

// For ReactifyWP compatibility
window.MyReactifyApp = createReactifyMount(App);

// For standalone development
if (document.getElementById('root')) {
  ReactDOM.createRoot(document.getElementById('root')).render(
    <React.StrictMode>
      <App />
    </React.StrictMode>,
  )
}
```

### Step 4: Configure Build for ReactifyWP
Update `vite.config.js`:
```javascript
import { defineConfig } from 'vite'
import react from '@vitejs/plugin-react'

export default defineConfig({
  plugins: [react()],
  build: {
    rollupOptions: {
      output: {
        // Ensure consistent file naming
        entryFileNames: 'assets/[name]-[hash].js',
        chunkFileNames: 'assets/[name]-[hash].js',
        assetFileNames: 'assets/[name]-[hash].[ext]'
      }
    }
  }
})
```

### Step 5: Build and Package
```bash
# Build for production
npm run build

# Create ReactifyWP-compatible ZIP
cd dist
zip -r ../my-reactify-app.zip .
cd ..
```

### Step 6: Deploy to WordPress
1. Go to WordPress Admin → Settings → ReactifyWP
2. Upload `my-reactify-app.zip`
3. Set slug: `my-app`
4. Use shortcode: `[reactify slug="my-app"]`

---

## 🔧 Advanced Features

### Multi-Page Apps with Routing
```javascript
// src/App.jsx
import { BrowserRouter, Routes, Route, HashRouter } from 'react-router-dom'

function App() {
  // Use HashRouter for WordPress compatibility
  return (
    <HashRouter>
      <Routes>
        <Route path="/" element={<Home />} />
        <Route path="/quiz" element={<Quiz />} />
        <Route path="/results" element={<Results />} />
      </Routes>
    </HashRouter>
  )
}
```

### State Management
```javascript
// src/context/AppContext.jsx
import { createContext, useContext, useReducer } from 'react'

const AppContext = createContext()

export function AppProvider({ children }) {
  const [state, dispatch] = useReducer(appReducer, initialState)
  
  return (
    <AppContext.Provider value={{ state, dispatch }}>
      {children}
    </AppContext.Provider>
  )
}

export const useApp = () => useContext(AppContext)
```

### API Integration
```javascript
// src/services/api.js
const API_BASE = window.location.origin + '/wp-json/wp/v2'

export async function fetchData(endpoint) {
  const response = await fetch(`${API_BASE}/${endpoint}`)
  return response.json()
}
```

---

## 🎨 Styling Best Practices

### CSS Modules or Styled Components
```javascript
// Option 1: CSS Modules
import styles from './Component.module.css'

// Option 2: Styled Components
import styled from 'styled-components'
const Container = styled.div`
  /* styles */
`
```

### WordPress Theme Compatibility
```css
/* Ensure styles don't conflict with WordPress */
.reactify-app {
  all: revert;
  font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
}

.reactify-app * {
  box-sizing: border-box;
}
```

---

## 🧪 Testing

### Development Testing
```bash
# Test locally
npm run dev
# Visit http://localhost:5173
```

### WordPress Testing
1. Build and upload to ReactifyWP
2. Test shortcode in different contexts:
   - Posts/Pages
   - Widgets
   - Theme templates

---

## 🔍 Troubleshooting

### Common Issues:
1. **App doesn't mount**: Check container selectors in `reactify-mount.js`
2. **Styles conflict**: Use CSS scoping or styled-components
3. **Routing issues**: Use HashRouter instead of BrowserRouter
4. **Build errors**: Check Vite configuration

### Debug Mode:
```javascript
// Add to reactify-mount.js for debugging
console.log('Available containers:', containers);
console.log('React available:', !!window.React);
console.log('ReactDOM available:', !!window.ReactDOM);
```

---

## 📦 File Structure
```
my-reactify-app/
├── src/
│   ├── components/
│   ├── pages/
│   ├── services/
│   ├── context/
│   ├── reactify-mount.js
│   ├── App.jsx
│   └── main.jsx
├── dist/ (after build)
├── vite.config.js
└── package.json
```

---

## ✅ Checklist
- [ ] Project created with Vite
- [ ] ReactifyWP mount system added
- [ ] Main.jsx updated for compatibility
- [ ] Vite config optimized
- [ ] App built successfully
- [ ] ZIP created from dist folder
- [ ] Uploaded to ReactifyWP
- [ ] Shortcode tested

---

*Next: See automation tools and templates for even faster development!*
