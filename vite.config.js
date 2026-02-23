import { defineConfig } from 'vite';
import vue from '@vitejs/plugin-vue';

export default defineConfig({
  plugins: [vue()],
  resolve: {
    alias: {
      vue: 'vue/dist/vue.esm-bundler.js',
    },
  },
  root: '.',
  build: {
    outDir: 'public',
    emptyOutDir: false,
    rollupOptions: {
      input: './index.html',
    },
    assetsDir: 'assets',
  },
  server: {
    proxy: {
      '/login': {
        target: 'http://localhost:8080',
        bypass: (req) => (req.method === 'GET' ? '/index.html' : undefined),
      },
      '/spots': 'http://localhost:8080',
      '/reservations': 'http://localhost:8080',
    },
  },
});
