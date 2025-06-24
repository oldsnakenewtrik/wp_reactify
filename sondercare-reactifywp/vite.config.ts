import { defineConfig } from 'vite'
import react from '@vitejs/plugin-react'

// https://vitejs.dev/config/
export default defineConfig({
  plugins: [react()],
  base: './', // Use relative paths for WordPress compatibility
  
  build: {
    rollupOptions: {
      output: {
        // Consistent file naming for ReactifyWP
        entryFileNames: 'assets/[name]-[hash].js',
        chunkFileNames: 'assets/[name]-[hash].js',
        assetFileNames: 'assets/[name]-[hash].[ext]'
      }
    },
    target: 'es2015', // Better browser support for WordPress
    minify: true,
    sourcemap: false
  }
})
