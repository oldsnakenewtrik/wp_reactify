# Manual Conversion: Complex React App to ReactifyWP
## Step-by-Step Guide for Your SonderCare Bed Selector

### 🎯 Overview
Let's convert your existing complex TypeScript React app to work with ReactifyWP manually, then we can automate it later.

---

## 📋 Step 1: Prepare Your Project

### 1.1 Copy Your Project
```bash
# Navigate to your project directory
cd "C:\Users\ss\Downloads\project-bolt-sb1-98rexr (2)\project"

# Copy to a new ReactifyWP-compatible directory
cp -r . "C:\Users\ss\Desktop\Projects\wp-reactify\sondercare-reactifywp"
cd "C:\Users\ss\Desktop\Projects\wp-reactify\sondercare-reactifywp"
```

### 1.2 Update package.json
Add ReactifyWP-specific scripts:
```json
{
  "scripts": {
    "build:reactifywp": "vite build --mode production",
    "preview:reactifywp": "vite preview"
  }
}
```

---

## 🔧 Step 2: Create ReactifyWP Mount System

### 2.1 Create `src/reactify-mount.tsx`
```typescript
import React from 'react';
import ReactDOM from 'react-dom/client';

export function createReactifyMount(AppComponent: React.ComponentType) {
  function mountApp() {
    // Find ReactifyWP containers
    const containers = [
      ...document.querySelectorAll('[data-reactify-slug]'),
      ...document.querySelectorAll('[id*="reactify"]'),
      ...document.querySelectorAll('.reactify-container')
    ] as HTMLElement[];
    
    console.log('ReactifyWP: Found containers:', containers.length);
    
    containers.forEach((container, index) => {
      if (container && !container.hasAttribute('data-app-mounted')) {
        container.setAttribute('data-app-mounted', 'true');
        container.innerHTML = '';
        
        // Create wrapper for styling isolation
        const wrapper = document.createElement('div');
        wrapper.className = 'sondercare-app-wrapper';
        wrapper.style.cssText = `
          width: 100%;
          min-height: 400px;
          font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
          box-sizing: border-box;
        `;
        container.appendChild(wrapper);
        
        try {
          // Mount React app
          const root = ReactDOM.createRoot(wrapper);
          root.render(React.createElement(AppComponent));
          
          console.log(`SonderCare app mounted to container ${index + 1}`);
        } catch (error) {
          console.error('Failed to mount SonderCare app:', error);
          wrapper.innerHTML = `
            <div style="padding: 20px; background: #f8d7da; color: #721c24; border-radius: 4px;">
              <h3>App Loading Error</h3>
              <p>Failed to load the SonderCare Bed Selector.</p>
            </div>
          `;
        }
      }
    });
  }
  
  // Auto-mount strategies
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', mountApp);
  } else {
    mountApp();
  }
  
  // Retry mounting for dynamic content
  setTimeout(mountApp, 100);
  setTimeout(mountApp, 500);
  setTimeout(mountApp, 1000);
  
  return { mount: mountApp, component: AppComponent };
}
```

### 2.2 Update `src/main.tsx`
```typescript
import { StrictMode } from 'react'
import { createRoot } from 'react-dom/client'
import App from './App.tsx'
import './index.css'
import { createReactifyMount } from './reactify-mount.tsx'

// ReactifyWP Integration
const reactifyMount = createReactifyMount(() => (
  <StrictMode>
    <App />
  </StrictMode>
));

// Expose globally for ReactifyWP
(window as any).SonderCareApp = {
  mount: reactifyMount.mount,
  component: App,
  version: '1.0.0'
};

// Standard React development mode
const rootElement = document.getElementById('root');
if (rootElement) {
  createRoot(rootElement).render(
    <StrictMode>
      <App />
    </StrictMode>
  );
}
```

---

## ⚙️ Step 3: Update Vite Configuration

### 3.1 Update `vite.config.ts`
```typescript
import { defineConfig } from 'vite'
import react from '@vitejs/plugin-react'

export default defineConfig({
  plugins: [react()],
  base: './', // Important for WordPress
  
  build: {
    rollupOptions: {
      output: {
        entryFileNames: 'assets/[name]-[hash].js',
        chunkFileNames: 'assets/[name]-[hash].js',
        assetFileNames: 'assets/[name]-[hash].[ext]'
      }
    },
    target: 'es2015', // Better browser support
    minify: 'terser'
  }
})
```

---

## 🎨 Step 4: Ensure CSS Compatibility

### 4.1 Update `src/index.css`
Add WordPress compatibility:
```css
/* WordPress/ReactifyWP Compatibility */
.sondercare-app-wrapper {
  all: revert;
  font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', 'Roboto', sans-serif;
}

.sondercare-app-wrapper * {
  box-sizing: border-box;
}

/* Your existing styles... */
/* Keep all your Tailwind and custom styles as they are */
```

---

## 🏗️ Step 5: Build and Package

### 5.1 Build the App
```bash
npm run build
```

### 5.2 Verify Build Output
Check that `dist/` contains:
- ✅ `index.html`
- ✅ `assets/` folder with JS and CSS files

### 5.3 Create ZIP Package
```bash
cd dist
# Create ZIP with all contents
powershell -Command "Compress-Archive -Path '*' -DestinationPath '../sondercare-bed-selector.zip' -Force"
cd ..
```

---

## 🚀 Step 6: Deploy to ReactifyWP

### 6.1 Upload to WordPress
1. Go to **WordPress Admin → Settings → ReactifyWP**
2. Upload `sondercare-bed-selector.zip`
3. Set slug: `sondercare-bed-selector`
4. Click "Upload Project"

### 6.2 Test the Shortcode
Add to any post/page:
```
[reactify slug="sondercare-bed-selector"]
```

---

## 🔍 Step 7: Troubleshooting

### If the app doesn't load:
1. **Check browser console** for errors
2. **Verify container detection**:
   ```javascript
   // Run in browser console
   console.log('Containers found:', document.querySelectorAll('[data-reactify-slug]'));
   ```
3. **Check if React is available**:
   ```javascript
   console.log('React available:', !!window.React);
   console.log('ReactDOM available:', !!window.ReactDOM);
   ```

### Common Issues:
- **CSS conflicts**: Add more specific selectors
- **JavaScript errors**: Check TypeScript compilation
- **Asset loading**: Verify relative paths in build

---

## ✅ Success Checklist

- [ ] Project copied and dependencies installed
- [ ] ReactifyWP mount system added
- [ ] main.tsx updated for dual compatibility
- [ ] Vite config optimized for WordPress
- [ ] CSS compatibility ensured
- [ ] App builds without errors
- [ ] ZIP package created from dist folder
- [ ] Uploaded to ReactifyWP successfully
- [ ] Shortcode displays the app correctly
- [ ] All quiz functionality works in WordPress

---

## 🎯 Next Steps

Once this manual process works:
1. **Automate with build scripts**
2. **Create reusable templates**
3. **Set up CI/CD pipeline**
4. **Build more complex apps**

**Your complex SonderCare app will work perfectly in ReactifyWP!** 🎉
