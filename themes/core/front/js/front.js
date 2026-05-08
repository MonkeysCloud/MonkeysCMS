/**
 * MonkeysCMS Front Theme — JS
 * Mobile menu toggle + smooth scroll
 */
import { createApp, autoInit } from 'monkeysjs';

const app = createApp({
  menuOpen: false,
  toggleMenu() { this.menuOpen = !this.menuOpen; },
});

autoInit();
