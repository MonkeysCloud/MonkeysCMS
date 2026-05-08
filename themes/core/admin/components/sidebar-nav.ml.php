{{-- Sidebar Navigation Component --}}
{{-- themes/core/admin/components/sidebar-nav.ml.php --}}
{{-- Override this in a child theme to change the admin sidebar menu --}}

<a href="/admin" class="admin-sidebar__link">
  <span class="admin-sidebar__icon">📊</span>
  <span class="admin-sidebar__label" $m-show="!sidebarCollapsed">Dashboard</span>
</a>

<div class="admin-sidebar__group">
  <div class="admin-sidebar__group-label" $m-show="!sidebarCollapsed">Content</div>
  <a href="/admin/content" class="admin-sidebar__link">
    <span class="admin-sidebar__icon">📝</span>
    <span class="admin-sidebar__label" $m-show="!sidebarCollapsed">Content</span>
  </a>
  <a href="/admin/media" class="admin-sidebar__link">
    <span class="admin-sidebar__icon">🖼️</span>
    <span class="admin-sidebar__label" $m-show="!sidebarCollapsed">Media</span>
  </a>
  <a href="/admin/menus" class="admin-sidebar__link">
    <span class="admin-sidebar__icon">☰</span>
    <span class="admin-sidebar__label" $m-show="!sidebarCollapsed">Menus</span>
  </a>
  <a href="/admin/taxonomy" class="admin-sidebar__link">
    <span class="admin-sidebar__icon">🏷️</span>
    <span class="admin-sidebar__label" $m-show="!sidebarCollapsed">Taxonomy</span>
  </a>
  <a href="/admin/blocks" class="admin-sidebar__link">
    <span class="admin-sidebar__icon">🧱</span>
    <span class="admin-sidebar__label" $m-show="!sidebarCollapsed">Blocks</span>
  </a>
</div>

<div class="admin-sidebar__divider"></div>

<div class="admin-sidebar__group">
  <div class="admin-sidebar__group-label" $m-show="!sidebarCollapsed">Structure</div>
  <a href="/admin/content-types" class="admin-sidebar__link">
    <span class="admin-sidebar__icon">⚙️</span>
    <span class="admin-sidebar__label" $m-show="!sidebarCollapsed">Content Types</span>
  </a>
  <a href="/admin/users" class="admin-sidebar__link">
    <span class="admin-sidebar__icon">👥</span>
    <span class="admin-sidebar__label" $m-show="!sidebarCollapsed">Users</span>
  </a>
</div>

<div class="admin-sidebar__divider"></div>

<div class="admin-sidebar__group">
  <div class="admin-sidebar__group-label" $m-show="!sidebarCollapsed">System</div>
  <a href="/admin/appearance" class="admin-sidebar__link">
    <span class="admin-sidebar__icon">🎨</span>
    <span class="admin-sidebar__label" $m-show="!sidebarCollapsed">Appearance</span>
  </a>
  <a href="/admin/settings" class="admin-sidebar__link">
    <span class="admin-sidebar__icon">🔧</span>
    <span class="admin-sidebar__label" $m-show="!sidebarCollapsed">Settings</span>
  </a>
</div>
