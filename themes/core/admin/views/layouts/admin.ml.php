<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="robots" content="noindex, nofollow">
  <meta name="csrf-token" content="{{ $cms['csrf_token'] ?? '' }}">
  <title>@yield('title', 'Dashboard') | {{ $cms['site']['name'] ?? 'MonkeysCMS' }} Admin</title>

  {{-- Admin Theme CSS (Vite-built or raw fallback) --}}
  <link rel="stylesheet" href="{{ vite_asset('themes/core/admin/css/admin.css') }}">
  <link rel="stylesheet" href="/assets/css/modal.css">
  <link rel="stylesheet" href="/assets/css/search.css">

  {{-- Global CSS + preconnect + fonts (injected by ThemeResolverMiddleware) --}}
  {!! $cms_head ?? '' !!}

  {{-- Import Map for ES module scripts (apex-assistant, etc.) --}}
  <script type="importmap">
  {
    "imports": {
      "monkeysjs": "/assets/js/monkeysjs.esm.js"
    }
  }
  </script>

  {{-- Page-specific head --}}
  @stack('head')
</head>
<body id="admin-app" class="admin-body">

  {{-- ═══ Global Admin Toolbar (rendered by middleware) ═══ --}}
  {!! $cms_admin_bar ?? '' !!}

  <div class="admin-wrapper">

    {{-- ═══════════════════════════════════════════════════════════════════
         REGION: sidebar
         ═══════════════════════════════════════════════════════════════════ --}}
    <aside class="admin-sidebar" id="admin-sidebar">

      {{-- Logo --}}
      <div class="admin-sidebar__logo">
        <img src="/assets/images/monkeyscmslogo.svg" alt="MonkeysCMS" class="admin-sidebar__logo-img" style="height:32px;width:auto;">
        <span class="admin-sidebar__logo-text">MonkeysCMS</span>
      </div>

      {{-- Navigation --}}
      <nav class="admin-sidebar__nav">
        @include('components.sidebar-nav')
      </nav>

      {{-- Sidebar footer --}}
      <div class="admin-sidebar__footer">
        <span class="text-xs text-muted">v1.0.0</span>
      </div>
    </aside>

    {{-- ═══════════════════════════════════════════════════════════════════
         Main Area
         ═══════════════════════════════════════════════════════════════════ --}}
    <div class="admin-main" id="admin-main">

      {{-- ── REGION: header ──────────────────────────────────────────── --}}
      <header class="admin-header">
        <div class="admin-header__left">
          <button class="admin-header__toggle" onclick="document.getElementById('admin-sidebar').classList.toggle('collapsed');document.getElementById('admin-main').classList.toggle('sidebar-collapsed');localStorage.setItem('monkeyscms_sidebar_collapsed',document.getElementById('admin-sidebar').classList.contains('collapsed'))" aria-label="Toggle sidebar">
            <i data-lucide="panel-left" class="w-5 h-5"></i>
          </button>
          {{-- REGION: breadcrumb --}}
          <div class="admin-breadcrumb">
            @yield('breadcrumb')
          </div>
        </div>
        <div class="admin-header__right">
          {{-- REGION: actions (toolbar buttons) --}}
          @yield('toolbar_actions')
          <button class="site-search-btn" data-mks-search aria-label="Search" style="margin-right:.5rem">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
              <circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/>
            </svg>
            <kbd>⌘K</kbd>
          </button>
          <div class="admin-header__user">
            <a href="/admin/profile" class="admin-header__user-btn">
              <span class="admin-header__avatar"><i data-lucide="circle-user" class="w-5 h-5"></i></span>
              <span class="admin-header__username">{{ $cms['user']['name'] ?? 'Admin' }}</span>
            </a>
            <a href="/admin/logout" class="btn btn--sm btn--ghost">Logout</a>
          </div>
        </div>
      </header>

      {{-- ── REGION: messages ────────────────────────────────────────── --}}
      <div class="admin-messages">
        @yield('messages')
        <div id="admin-notifications"></div>
      </div>

      {{-- ── Page Title ──────────────────────────────────────────────── --}}
      <div class="admin-page-header">
        <h1 class="admin-page-title">@yield('page_title', '')</h1>
        <div class="admin-page-actions">
          @yield('page_actions')
        </div>
      </div>

      {{-- ── REGION: content ─────────────────────────────────────────── --}}
      <main class="admin-content">
        @yield('content')
      </main>

      {{-- ── REGION: footer ──────────────────────────────────────────── --}}
      <footer class="admin-footer">
        @yield('footer')
        <span class="text-xs text-muted">MonkeysCMS &copy; {{ date('Y') }}</span>
      </footer>
    </div>
  </div>

  {{-- Global Libraries (JS) --}}
  @foreach($__cms_assets['js'] ?? [] as $jsFile)
  <script src="{{ $jsFile }}"></script>
  @endforeach

  {{-- Global Libraries (ES Modules) --}}
  @foreach($__cms_assets['modules'] ?? [] as $moduleFile)
  <script type="module" src="{{ $moduleFile }}"></script>
  @endforeach

  {{-- Admin App Init --}}
  <script src="/assets/js/modal.js"></script>
  <script>
  // ── Global CMS namespace ──────────────────────────────────────────────
  window.CMS = window.CMS || {};
  CMS.csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';

  /**
   * CMS.fetch — drop-in replacement for fetch() that auto-injects CSRF token.
   * Usage: CMS.fetch('/admin/some/endpoint', { method: 'POST', body: JSON.stringify(data) })
   */
  CMS.fetch = function(url, options = {}) {
    options.headers = Object.assign({
      'X-CSRF-TOKEN': CMS.csrfToken,
    }, options.headers || {});
    // Auto-set JSON content-type for object bodies
    if (options.body && typeof options.body === 'string' && !options.headers['Content-Type']) {
      options.headers['Content-Type'] = 'application/json';
    }
    return fetch(url, options);
  };

  document.addEventListener('DOMContentLoaded', () => {
    // Sidebar state persistence
    const sidebarKey = 'monkeyscms_sidebar_collapsed';
    window.adminState = {
      sidebarCollapsed: localStorage.getItem(sidebarKey) === 'true',
      notifications: [],

      toggleSidebar() {
        this.sidebarCollapsed = !this.sidebarCollapsed;
        localStorage.setItem(sidebarKey, this.sidebarCollapsed);
      },

      notify(message, type = 'info', duration = 5000) {
        const id = Date.now();
        this.notifications.push({ id, message, type });
        if (duration > 0) setTimeout(() => this.dismissNotification(id), duration);
      },

      dismissNotification(id) {
        this.notifications = this.notifications.filter(n => n.id !== id);
      },
    };

    // ── Sidebar: active state ──────────────────────────────────────────
    const current = window.location.pathname;

    // Mark active sidebar-link
    document.querySelectorAll('.sidebar-link[href]').forEach(link => {
      const href = link.getAttribute('href');
      if (href === current || (href !== '/admin' && current.startsWith(href))) {
        link.classList.add('active');
      }
    });

    // Mark active sub-link and auto-expand its parent
    document.querySelectorAll('.sidebar-sub__link').forEach(link => {
      const href = link.getAttribute('href');
      if (href === current || current.startsWith(href)) {
        link.classList.add('active');
        const parentItem = link.closest('.sidebar-item');
        if (parentItem) parentItem.classList.add('open');
      }
    });

    // (Sub-items are pure CSS hover flyouts — no JS needed)

    // ── Sidebar: restore collapsed state ───────────────────────────────
    if (localStorage.getItem('monkeyscms_sidebar_collapsed') === 'true') {
      document.getElementById('admin-sidebar')?.classList.add('collapsed');
      document.getElementById('admin-main')?.classList.add('sidebar-collapsed');
    }

    // Keyboard shortcuts
    document.addEventListener('keydown', (e) => {
      if ((e.ctrlKey || e.metaKey) && e.key === 's') {
        e.preventDefault();
        document.querySelector('[data-save-btn]')?.click();
      }
    });
  });
  </script>

  {{-- Global JS libraries (injected by ThemeResolverMiddleware) --}}
  {!! $cms_scripts ?? '' !!}

  {{-- Global Search Widget --}}
  <script src="/assets/js/search.js" defer></script>

  {{-- Page-specific scripts --}}
  @stack('scripts')
</body>
</html>
