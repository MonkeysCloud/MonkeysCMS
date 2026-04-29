import { defineConfig } from 'vite';
import { resolve } from 'path';

export default defineConfig({
  root: resolve(__dirname),
  publicDir: false,

  resolve: {
    alias: {
      monkeysjs: resolve(__dirname, 'node_modules/monkeysjs/dist/monkeysjs.esm.js'),
    },
  },

  build: {
    outDir: 'public/build',
    emptyOutDir: true,
    manifest: true,
    rollupOptions: {
      input: {
        admin: resolve(__dirname, 'resources/js/admin.js'),
        frontend: resolve(__dirname, 'resources/js/frontend.js'),
        'mosaic-editor': resolve(__dirname, 'resources/js/mosaic-editor.js'),
        'admin-css': resolve(__dirname, 'resources/css/admin.css'),
        'frontend-css': resolve(__dirname, 'resources/css/frontend.css'),
      },
    },
  },

  server: {
    origin: 'http://localhost:5173',
    cors: true,
  },
});
