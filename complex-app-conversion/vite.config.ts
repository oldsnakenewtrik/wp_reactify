import { defineConfig } from 'vite'
import react from '@vitejs/plugin-react'

// https://vitejs.dev/config/
export default defineConfig({
  plugins: [react()],
  base: './', // Use relative paths for assets (important for WordPress)
  
  build: {
    // Optimize for ReactifyWP
    rollupOptions: {
      output: {
        // Consistent file naming for ReactifyWP
        entryFileNames: 'assets/[name]-[hash].js',
        chunkFileNames: 'assets/[name]-[hash].js',
        assetFileNames: 'assets/[name]-[hash].[ext]',
        
        // Optimize chunk splitting
        manualChunks: {
          // Keep React separate for potential CDN usage
          react: ['react', 'react-dom'],
          // Vendor libraries
          vendor: ['lucide-react']
        }
      }
    },
    
    // Optimize for WordPress environment
    target: 'es2015', // Broader browser support
    minify: 'terser',
    sourcemap: false, // Disable for production
    
    // Ensure assets are properly handled
    assetsDir: 'assets',
    
    // Optimize bundle size
    chunkSizeWarningLimit: 1000,
    
    // CSS handling
    cssCodeSplit: true,
    
    // Rollup options for better WordPress compatibility
    rollupOptions: {
      output: {
        // Consistent naming
        entryFileNames: 'assets/[name]-[hash].js',
        chunkFileNames: 'assets/[name]-[hash].js',
        assetFileNames: 'assets/[name]-[hash].[ext]',
        
        // Optimize chunks for ReactifyWP
        manualChunks(id) {
          // React core
          if (id.includes('react') || id.includes('react-dom')) {
            return 'react';
          }
          
          // Vendor libraries
          if (id.includes('node_modules')) {
            return 'vendor';
          }
          
          // App components
          if (id.includes('src/components')) {
            return 'components';
          }
        }
      },
      
      // External dependencies (if using CDN)
      external: [
        // Uncomment if you want to use React from CDN
        // 'react',
        // 'react-dom'
      ],
      
      // Global variables for externals
      globals: {
        // react: 'React',
        // 'react-dom': 'ReactDOM'
      }
    }
  },
  
  // Development server configuration
  server: {
    port: 3000,
    open: true,
    cors: true,
    
    // Proxy for WordPress development (if needed)
    proxy: {
      // Uncomment and configure if developing against a WordPress site
      // '/wp-json': {
      //   target: 'http://localhost:8080',
      //   changeOrigin: true
      // }
    }
  },
  
  // Preview server (for testing builds)
  preview: {
    port: 3001,
    open: true
  },
  
  // Resolve configuration
  resolve: {
    alias: {
      // Add aliases if needed
      '@': '/src'
    }
  },
  
  // CSS configuration
  css: {
    // PostCSS configuration
    postcss: {
      plugins: [
        // Add PostCSS plugins if needed
      ]
    },
    
    // CSS modules configuration
    modules: {
      // Configure CSS modules if used
      localsConvention: 'camelCase'
    }
  },
  
  // Define global constants
  define: {
    // Environment variables
    __APP_VERSION__: JSON.stringify(process.env.npm_package_version || '1.0.0'),
    __BUILD_TIME__: JSON.stringify(new Date().toISOString()),
    
    // WordPress integration flags
    __WORDPRESS_MODE__: JSON.stringify(process.env.WORDPRESS_MODE === 'true'),
    __REACTIFY_WP__: JSON.stringify(true)
  },
  
  // Optimization
  optimizeDeps: {
    include: [
      'react',
      'react-dom',
      'lucide-react'
    ],
    exclude: [
      // Exclude any problematic dependencies
    ]
  }
})
