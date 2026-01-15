/**
 * Asset Copy Script
 * 
 * Copies JS libraries from node_modules to public/js
 * Run with: npm run build:js
 */

const fs = require('fs');
const path = require('path');

const assets = [
  {
    src: 'node_modules/htmx.org/dist/htmx.min.js',
    dest: 'public/js/htmx.min.js'
  },
  {
    src: 'node_modules/alpinejs/dist/cdn.min.js',
    dest: 'public/js/alpine.min.js'
  },
  {
    src: 'node_modules/sortablejs/Sortable.min.js',
    dest: 'public/js/sortable.min.js'
  },
  {
    src: 'node_modules/codemirror/lib/codemirror.js',
    dest: 'public/vendor/codemirror/codemirror.min.js'
  },
  {
    src: 'node_modules/codemirror/lib/codemirror.css',
    dest: 'public/vendor/codemirror/codemirror.min.css'
  },
  {
    src: 'node_modules/codemirror/theme/dracula.css',
    dest: 'public/vendor/codemirror/theme/dracula.css'
  }
];

const baseDir = path.resolve(__dirname, '..');

console.log('Copying JS assets...\n');

assets.forEach(asset => {
  const srcPath = path.join(baseDir, asset.src);
  const destPath = path.join(baseDir, asset.dest);
  
  try {
    // Ensure destination directory exists
    const destDir = path.dirname(destPath);
    if (!fs.existsSync(destDir)) {
      fs.mkdirSync(destDir, { recursive: true });
    }
    
    // Copy file
    fs.copyFileSync(srcPath, destPath);
    
    const size = (fs.statSync(destPath).size / 1024).toFixed(1);
    console.log(`✓ ${path.basename(asset.dest)} (${size} KB)`);
  } catch (err) {
    console.error(`✗ Failed to copy ${asset.src}: ${err.message}`);
  }
});

console.log('\nDone!');
