/**
 * MonkeysCMS — Frontend Entry Point
 * 
 * Lightweight frontend JS for the public-facing site.
 * Uses MonkeysJS for reactive components and HTTP client.
 */

import {
  createApp,
  reactive,
  http,
  autoInit,
} from 'monkeysjs';

// ─── Frontend App ───────────────────────────────────────────────────────────
const app = createApp({
  menuOpen: false,

  toggleMenu() {
    this.menuOpen = !this.menuOpen;
  },
});

// Auto-initialize any $m-* directives in the DOM
autoInit();

export { app };
