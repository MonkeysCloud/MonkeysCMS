{{-- Sidebar Navigation Component --}}
{{-- themes/core/admin/views/components/sidebar-nav.ml.php --}}
{{-- Override this in a child theme to change the admin sidebar menu --}}

<a href="/admin" class="sidebar-link" data-tooltip="Dashboard">
  <span class="sidebar-link__icon"><i data-lucide="layout-dashboard" class="w-[18px] h-[18px]"></i></span>
  <span class="sidebar-link__text">Dashboard</span>
</a>

<div class="sidebar-group">
  <div class="sidebar-group__label">Content</div>

  {{-- Content — expandable --}}
  <div class="sidebar-item" data-expandable>
    <button class="sidebar-link" data-tooltip="Content">
      <span class="sidebar-link__icon"><i data-lucide="file-text" class="w-[18px] h-[18px]"></i></span>
      <span class="sidebar-link__text">Content</span>
      <span class="sidebar-link__arrow"><i data-lucide="chevron-right" class="w-3.5 h-3.5"></i></span>
    </button>
    <div class="sidebar-sub">
      <a href="/admin/content" class="sidebar-sub__link">All Content</a>
      @foreach($cms['content_types'] ?? [] as $ct)
      <a href="/admin/content/create/{{ $ct->type_id }}" class="sidebar-sub__link">New {{ $ct->label }}</a>
      @endforeach
    </div>
  </div>

  {{-- Media — expandable --}}
  <div class="sidebar-item" data-expandable>
    <button class="sidebar-link" data-tooltip="Media">
      <span class="sidebar-link__icon"><i data-lucide="image" class="w-[18px] h-[18px]"></i></span>
      <span class="sidebar-link__text">Media</span>
      <span class="sidebar-link__arrow"><i data-lucide="chevron-right" class="w-3.5 h-3.5"></i></span>
    </button>
    <div class="sidebar-sub">
      <a href="/admin/media" class="sidebar-sub__link">Library</a>
      <a href="/admin/media/upload" class="sidebar-sub__link">Upload</a>
    </div>
  </div>

  <a href="/admin/menus" class="sidebar-link" data-tooltip="Menus">
    <span class="sidebar-link__icon"><i data-lucide="menu" class="w-[18px] h-[18px]"></i></span>
    <span class="sidebar-link__text">Menus</span>
  </a>

  <a href="/admin/taxonomy" class="sidebar-link" data-tooltip="Taxonomy">
    <span class="sidebar-link__icon"><i data-lucide="tags" class="w-[18px] h-[18px]"></i></span>
    <span class="sidebar-link__text">Taxonomy</span>
  </a>

  <a href="/admin/blocks" class="sidebar-link" data-tooltip="Blocks">
    <span class="sidebar-link__icon"><i data-lucide="blocks" class="w-[18px] h-[18px]"></i></span>
    <span class="sidebar-link__text">Blocks</span>
  </a>
</div>

<div class="sidebar-divider"></div>

<div class="sidebar-group">
  <div class="sidebar-group__label">Structure</div>

  <a href="/admin/content-types" class="sidebar-link" data-tooltip="Content Types">
    <span class="sidebar-link__icon"><i data-lucide="database" class="w-[18px] h-[18px]"></i></span>
    <span class="sidebar-link__text">Content Types</span>
  </a>

  {{-- Users — expandable --}}
  <div class="sidebar-item" data-expandable>
    <button class="sidebar-link" data-tooltip="Users">
      <span class="sidebar-link__icon"><i data-lucide="users" class="w-[18px] h-[18px]"></i></span>
      <span class="sidebar-link__text">Users</span>
      <span class="sidebar-link__arrow"><i data-lucide="chevron-right" class="w-3.5 h-3.5"></i></span>
    </button>
    <div class="sidebar-sub">
      <a href="/admin/users" class="sidebar-sub__link">All Users</a>
      <a href="/admin/users/create" class="sidebar-sub__link">Add User</a>
      <a href="/admin/roles" class="sidebar-sub__link">Roles</a>
    </div>
  </div>
</div>

<div class="sidebar-divider"></div>

<div class="sidebar-group">
  <div class="sidebar-group__label">System</div>

  {{-- Appearance — expandable --}}
  <div class="sidebar-item" data-expandable>
    <button class="sidebar-link" data-tooltip="Appearance">
      <span class="sidebar-link__icon"><i data-lucide="paintbrush" class="w-[18px] h-[18px]"></i></span>
      <span class="sidebar-link__text">Appearance</span>
      <span class="sidebar-link__arrow"><i data-lucide="chevron-right" class="w-3.5 h-3.5"></i></span>
    </button>
    <div class="sidebar-sub">
      <a href="/admin/appearance" class="sidebar-sub__link">Themes</a>
      <a href="/admin/appearance/editor" class="sidebar-sub__link">Theme Editor</a>
    </div>
  </div>

  {{-- Cache — expandable --}}
  <div class="sidebar-item" data-expandable>
    <button class="sidebar-link" data-tooltip="Cache">
      <span class="sidebar-link__icon"><i data-lucide="hard-drive" class="w-[18px] h-[18px]"></i></span>
      <span class="sidebar-link__text">Cache</span>
      <span class="sidebar-link__arrow"><i data-lucide="chevron-right" class="w-3.5 h-3.5"></i></span>
    </button>
    <div class="sidebar-sub">
      <a href="/admin/cache" class="sidebar-sub__link">Settings</a>
      <form action="/admin/cache/clear" method="POST" style="margin:0">
        <input type="hidden" name="target" value="all">
        <button type="submit" class="sidebar-sub__link sidebar-sub__link--action" style="width:100%;text-align:left;border:none;background:none;font:inherit;cursor:pointer;color:inherit;">Clear All</button>
      </form>
    </div>
  </div>

  <a href="/admin/settings" class="sidebar-link" data-tooltip="Settings">
    <span class="sidebar-link__icon"><i data-lucide="settings" class="w-[18px] h-[18px]"></i></span>
    <span class="sidebar-link__text">Settings</span>
  </a>
</div>
