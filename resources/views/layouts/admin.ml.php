<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>@yield('title', 'MonkeysCMS') | Admin</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="/build/assets/admin-css.css">
  @stack('head')
</head>
<body id="admin-app">
  <div class="admin-wrapper">

    {{-- ═══ Sidebar ═══ --}}
    <aside class="admin-sidebar" :class="{ collapsed: sidebarCollapsed }">
      <div class="admin-sidebar__logo">
        <span style="font-size:1.5rem;">🐒</span>
        <span $m-show="!sidebarCollapsed">MonkeysCMS</span>
      </div>
      <nav class="admin-sidebar__nav">
        <a href="/admin" class="admin-sidebar__link">
          <span>📊</span>
          <span $m-show="!sidebarCollapsed">Dashboard</span>
        </a>
        <a href="/admin/content" class="admin-sidebar__link">
          <span>📝</span>
          <span $m-show="!sidebarCollapsed">Content</span>
        </a>
        <a href="/admin/media" class="admin-sidebar__link">
          <span>🖼️</span>
          <span $m-show="!sidebarCollapsed">Media</span>
        </a>
        <a href="/admin/menus" class="admin-sidebar__link">
          <span>☰</span>
          <span $m-show="!sidebarCollapsed">Menus</span>
        </a>
        <a href="/admin/taxonomy" class="admin-sidebar__link">
          <span>🏷️</span>
          <span $m-show="!sidebarCollapsed">Taxonomy</span>
        </a>
        <a href="/admin/blocks" class="admin-sidebar__link">
          <span>🧱</span>
          <span $m-show="!sidebarCollapsed">Block Types</span>
        </a>

        <div style="margin:0.75rem 0; border-top:1px solid var(--cms-border);"></div>

        <a href="/admin/content-types" class="admin-sidebar__link">
          <span>⚙️</span>
          <span $m-show="!sidebarCollapsed">Content Types</span>
        </a>
        <a href="/admin/users" class="admin-sidebar__link">
          <span>👥</span>
          <span $m-show="!sidebarCollapsed">Users</span>
        </a>
        <a href="/admin/settings" class="admin-sidebar__link">
          <span>🔧</span>
          <span $m-show="!sidebarCollapsed">Settings</span>
        </a>
      </nav>
    </aside>

    {{-- ═══ Main Content ═══ --}}
    <div class="admin-main" :class="{ 'sidebar-collapsed': sidebarCollapsed }">

      {{-- Toolbar --}}
      <header class="admin-toolbar">
        <div style="display:flex; align-items:center; gap:0.75rem;">
          <button class="btn btn-secondary btn-sm" $m-on:click="sidebarCollapsed = !sidebarCollapsed">
            ☰
          </button>
          <span class="admin-toolbar__title">@yield('toolbar_title', '')</span>
        </div>
        <div style="display:flex; align-items:center; gap:0.75rem;">
          @yield('toolbar_actions')
          <a href="/admin/profile" class="btn btn-secondary btn-sm">👤 Profile</a>
          <a href="/admin/logout" class="btn btn-secondary btn-sm">Logout</a>
        </div>
      </header>

      {{-- Content --}}
      <main class="admin-content">
        @yield('content')
      </main>
    </div>
  </div>

  {{-- ═══ Notifications ═══ --}}
  <div class="notifications">
    <template $m-for="n in notifications">
      <div class="notification" :class="'notification--' + n.type" :key="n.id">
        <span $m-text="n.message"></span>
        <button style="background:none; border:none; color:inherit; cursor:pointer; margin-left:auto;"
                $m-on:click="dismissNotification(n.id)">✕</button>
      </div>
    </template>
  </div>

  <script type="module" src="/build/assets/admin.js"></script>
  @stack('scripts')
</body>
</html>
