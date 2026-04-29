<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="robots" content="noindex, nofollow">
  <title>@yield('title', 'Dashboard') | MonkeysCMS Admin</title>

  {{-- Google Fonts --}}
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">

  {{-- Global Libraries (CSS) — injected by ThemeManager --}}
  @foreach($__cms_assets['css'] ?? [] as $cssFile)
  <link rel="stylesheet" href="{{ $cssFile }}">
  @endforeach

  {{-- Page-specific head --}}
  @stack('head')
</head>
<body id="admin-app" class="admin-body">

  <div class="admin-wrapper">

    {{-- ═══════════════════════════════════════════════════════════════════
         REGION: sidebar
         ═══════════════════════════════════════════════════════════════════ --}}
    <aside class="admin-sidebar" :class="{ collapsed: sidebarCollapsed }">

      {{-- Logo --}}
      <div class="admin-sidebar__logo">
        <div class="admin-sidebar__logo-icon">🐒</div>
        <span class="admin-sidebar__logo-text" $m-show="!sidebarCollapsed">MonkeysCMS</span>
      </div>

      {{-- Navigation --}}
      <nav class="admin-sidebar__nav">
        @include('admin.components.sidebar-nav')
      </nav>

      {{-- Sidebar footer --}}
      <div class="admin-sidebar__footer" $m-show="!sidebarCollapsed">
        <span class="text-xs text-muted">v1.0.0</span>
      </div>
    </aside>

    {{-- ═══════════════════════════════════════════════════════════════════
         Main Area
         ═══════════════════════════════════════════════════════════════════ --}}
    <div class="admin-main" :class="{ 'sidebar-collapsed': sidebarCollapsed }">

      {{-- ── REGION: header ──────────────────────────────────────────── --}}
      <header class="admin-header">
        <div class="admin-header__left">
          <button class="admin-header__toggle" $m-on:click="sidebarCollapsed = !sidebarCollapsed" aria-label="Toggle sidebar">
            <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 6h14M3 10h14M3 14h14"/></svg>
          </button>
          {{-- REGION: breadcrumb --}}
          <div class="admin-breadcrumb">
            @yield('breadcrumb')
          </div>
        </div>
        <div class="admin-header__right">
          {{-- REGION: actions (toolbar buttons) --}}
          @yield('toolbar_actions')
          <div class="admin-header__user">
            <a href="/admin/profile" class="admin-header__user-btn">
              <span class="admin-header__avatar">👤</span>
              <span $m-show="!sidebarCollapsed" class="admin-header__username">@yield('username', 'Admin')</span>
            </a>
            <a href="/admin/logout" class="btn btn--sm btn--ghost">Logout</a>
          </div>
        </div>
      </header>

      {{-- ── REGION: messages ────────────────────────────────────────── --}}
      <div class="admin-messages">
        @yield('messages')
        <template $m-for="n in notifications">
          <div class="alert" :class="'alert--' + n.type" :key="n.id">
            <span $m-text="n.message"></span>
            <button class="alert__dismiss" $m-on:click="dismissNotification(n.id)">✕</button>
          </div>
        </template>
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
  <script>
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

    // Active sidebar link
    const current = window.location.pathname;
    document.querySelectorAll('.admin-sidebar__link').forEach(link => {
      const href = link.getAttribute('href');
      if (href === current || (href !== '/admin' && current.startsWith(href))) {
        link.classList.add('active');
      }
    });

    // Keyboard shortcuts
    document.addEventListener('keydown', (e) => {
      if ((e.ctrlKey || e.metaKey) && e.key === 's') {
        e.preventDefault();
        document.querySelector('[data-save-btn]')?.click();
      }
    });
  });
  </script>

  {{-- Page-specific scripts --}}
  @stack('scripts')
</body>
</html>
