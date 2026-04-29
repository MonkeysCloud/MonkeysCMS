/**
 * MonkeysCMS — Admin Entry Point
 * 
 * Powered by MonkeysJS — reactive DOM binding, HTTP client, forms, WebSockets
 * Replaces: Alpine.js, htmx, Axios
 */

import {
  createApp,
  reactive,
  ref,
  computed,
  watch,
  http,
  createClient,
  useForm,
  useWebSocket,
  debounce,
} from 'monkeysjs';

// ─── HTTP Client Configuration ──────────────────────────────────────────────
const api = createClient({
  baseURL: '/admin/api',
  headers: {
    'X-Requested-With': 'XMLHttpRequest',
    'Accept': 'application/json',
  },
});

// ─── Global Admin State ─────────────────────────────────────────────────────
const adminState = reactive({
  sidebar: {
    open: true,
    collapsed: false,
  },
  notifications: [],
  user: null,
  loading: false,
});

// ─── Notification System ────────────────────────────────────────────────────
function notify(message, type = 'info', duration = 5000) {
  const id = Date.now();
  adminState.notifications.push({ id, message, type, visible: true });

  if (duration > 0) {
    setTimeout(() => {
      const idx = adminState.notifications.findIndex(n => n.id === id);
      if (idx !== -1) adminState.notifications.splice(idx, 1);
    }, duration);
  }
}

// ─── Initialize Admin App ───────────────────────────────────────────────────
const app = createApp({
  // State
  ...adminState,

  // Sidebar
  toggleSidebar() {
    adminState.sidebar.open = !adminState.sidebar.open;
  },

  collapseSidebar() {
    adminState.sidebar.collapsed = !adminState.sidebar.collapsed;
  },

  // Notifications
  notify,

  dismissNotification(id) {
    const idx = adminState.notifications.findIndex(n => n.id === id);
    if (idx !== -1) adminState.notifications.splice(idx, 1);
  },

  // HTTP helpers
  api,
});

// Mount on admin wrapper
const adminEl = document.getElementById('admin-app');
if (adminEl) {
  app.mount('#admin-app');
}

// ─── Export for module use ───────────────────────────────────────────────────
export { app, api, adminState, notify };
