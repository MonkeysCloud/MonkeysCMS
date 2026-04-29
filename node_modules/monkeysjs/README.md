# MonkeysJS

<p align="center">
  <img src="https://monkeyslegion.com/images/icon-monkeyslegion.png" alt="MonkeysJS Logo" width="200">
</p>

<p align="center">
  <strong>The robust, reactive JavaScript library that replaces Alpine.js and Axios.</strong><br>
  Built-in HTTP client, advanced DOM binding, WebSockets, and powerful form handling in one lightweight package.
</p>

<p align="center">
  <a href="#installation">Installation</a> •
  <a href="#quick-start">Quick Start</a> •
  <a href="#features">Features</a> •
  <a href="#documentation">Documentation</a> •
  <a href="#examples">Examples</a>
</p>

---

## Why MonkeysJS?

MonkeysJS is designed to be a complete solution for modern web interfaces. Instead of stitching together Alpine.js for interactivity and Axios for requests, getting disparate libraries to talk to each other, MonkeysJS provides a unified, reactive system where your data, DOM, and network requests work in harmony.

## Features

✨ **Advanced Reactivity** - Fine-grained reactivity system (`ref`, `reactive`, `computed`, `watch`) that powers everything.

🌐 **Production-Ready HTTP** - A robust alternative to Axios with caching, retries, deduplication, and file upload progress (`useUpload`).

🎯 **Pro-Level DOM Binding** - More than just text updates. Includes `$dispatch`, `$watch`, formatted inputs with `m-mask`, and lifecycle hooks.

📡 **WebSocket Client** - integrated `useWebSocket` with auto-reconnect, heartbeats, and reactive state.

📝 **Form handling** - Native `useForm` with validation, dirty checking, and async submission.

🔄 **Smart Data Fetching** - Declarative `m-fetch` for zero-boilerplate data loading.

📦 **Zero Dependencies** - ~14KB gzipped. No bloat.

## Installation

### NPM

```bash
npm install monkeysjs
```

### CDN

```html
<script src="https://unpkg.com/monkeysjs"></script>
```

## Quick Start

### Browser (Alpine.js style)

```html
<div m-data="{ count: 0, open: false }">
  <!-- Dispatch custom events like a pro -->
  <button @click="$dispatch('toggle-menu', { state: !open })">Toggle</button>

  <!-- Watch for changes right in your HTML -->
  <div m-effect="$watch(() => count, val => console.log(val))"></div>

  <!-- Format inputs automatically -->
  <input m-mask="99-99-9999" placeholder="Phone Number" />

  <span m-text="count"></span>
  <button @click="count++">Increment</button>
</div>
```

### ES Modules

```javascript
import { reactive, http, useUpload } from "monkeysjs";

// Reactive state
const state = reactive({ count: 0 });

// File Upload with Progress
const { upload, progress } = useUpload("/api/files");

async function handleFile(file) {
  await upload(file);
  console.log("Upload complete!");
}
```

## Documentation

### DOM Binding

MonkeysJS offers a superset of directives you might expect, plus power-user features.

#### `$dispatch(event, detail)`

Emit custom events that bubble up the DOM.

```html
<button @click="$dispatch('notify', { message: 'Saved!' })">Save</button>
```

#### `$watch(source, callback)`

React to changes declaratively.

```html
<div
  m-effect="$watch(() => isOpen, val => document.body.classList.toggle('noscroll', val))"
></div>
```

#### `m-mask`

Built-in input masking for dates, phones, and more.

- `9`: Numeric
- `a`: Alpha
- `*`: Alphanumeric

```html
<input m-mask="999-99-9999" placeholder="SSN" />
```

### HTTP Client

Forget Axios. MonkeysJS has you covered.

#### `useUpload(url)`

Upload files with built-in progress tracking.

```javascript
import { useUpload } from "monkeysjs";

const { upload, progress, isUploading, error } = useUpload("/api/upload");

// In your view
// <div :style="{ width: progress + '%' }"></div>
```

#### `useFetch(url)`

Reactive data fetching.

```javascript
import { useFetch } from "monkeysjs";

const { data, error, isLoading, execute } = useFetch("/api/users");
```

### WebSockets

Built-in reactive WebSocket client.

```javascript
import { useWebSocket } from "monkeysjs";

const { send, data, isConnected } = useWebSocket("wss://api.example.com");
```

## License

MIT

## Contributors

<table>
  <tr>
    <td>
      <a href="https://github.com/yorchperaza">
        <img src="https://github.com/yorchperaza.png" width="100px;" alt="Jorge Peraza"/><br />
        <sub><b>Jorge Peraza</b></sub>
      </a>
    </td>
  </tr>
</table>
