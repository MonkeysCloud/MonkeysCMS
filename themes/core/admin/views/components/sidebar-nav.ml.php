{{-- Sidebar Navigation Component --}}
{{-- themes/core/admin/views/components/sidebar-nav.ml.php --}}
{{-- Data-driven admin menu rendered from AdminMenuRegistry --}}
{{-- Override by extending AdminMenuRegistry in plugins or child themes --}}

@php
  $adminMenu = $cms['admin_menu'] ?? null;
  $currentPath = $_SERVER['REQUEST_URI'] ?? '/admin';
@endphp

@if($adminMenu !== null)

  {{-- ── Dashboard (always first, outside groups) ──────────────────── --}}
  @if(isset($adminMenu['dashboard']))
    @php $dash = $adminMenu['dashboard']; @endphp
    <a href="{{ $dash->url }}" class="sidebar-link{{ $dash->isActive($currentPath) ? ' active' : '' }}" data-tooltip="{{ $dash->label }}">
      <span class="sidebar-link__icon"><i data-lucide="{{ $dash->icon ?? 'layout-dashboard' }}" class="w-[18px] h-[18px]"></i></span>
      <span class="sidebar-link__text">{{ $dash->label }}</span>
    </a>
  @endif

  {{-- ── Groups ───────────────────────────────────────────────────── --}}
  @foreach($adminMenu['groups'] as $group)
  <div class="sidebar-divider"></div>
  <div class="sidebar-group">
    <div class="sidebar-group__label">{{ $group->label }}</div>

    @foreach($group->items as $item)
      @if($item->isExpandable)
        {{-- ── Expandable item with children ───────────────────────── --}}
        <div class="sidebar-item{{ $item->isActive($currentPath) ? ' expanded' : '' }}" data-expandable>
          <button class="sidebar-link{{ $item->isActive($currentPath) ? ' active' : '' }}" data-tooltip="{{ $item->label }}">
            <span class="sidebar-link__icon"><i data-lucide="{{ $item->icon ?? 'circle' }}" class="w-[18px] h-[18px]"></i></span>
            <span class="sidebar-link__text">{{ $item->label }}</span>
            @if($item->badge)
              <span class="badge badge--{{ $item->badgeVariant ?? 'info' }}" style="margin-left:auto;margin-right:.25rem">{{ $item->badge }}</span>
            @endif
            <span class="sidebar-link__arrow"><i data-lucide="chevron-right" class="w-3.5 h-3.5"></i></span>
          </button>
          <div class="sidebar-sub">
            @foreach($item->children as $child)
              @if(!empty($child->attributes['_form_action'] ?? ''))
                {{-- Special: form-based sub-item (e.g., Cache Clear) --}}
                <form action="{{ $child->attributes['_form_action'] }}" method="POST" style="margin:0">
                  @if(!empty($child->attributes['_form_fields']))
                    @foreach($child->attributes['_form_fields'] as $fn => $fv)
                      <input type="hidden" name="{{ $fn }}" value="{{ $fv }}">
                    @endforeach
                  @endif
                  <button type="submit" class="sidebar-sub__link sidebar-sub__link--action" style="width:100%;text-align:left;border:none;background:none;font:inherit;cursor:pointer;color:inherit;">{{ $child->label }}</button>
                </form>
              @else
                <a href="{{ $child->url }}" class="sidebar-sub__link{{ $child->isActive($currentPath) ? ' active' : '' }}">
                  @if($child->badge)
                    <span style="display:flex;align-items:center;justify-content:space-between;width:100%">
                      <span>{{ $child->label }}</span>
                      <span class="badge badge--{{ $child->badgeVariant ?? 'info' }}" style="font-size:0.65rem">{{ $child->badge }}</span>
                    </span>
                  @else
                    {{ $child->label }}
                  @endif
                </a>
              @endif
            @endforeach
          </div>
        </div>
      @else
        {{-- ── Simple link ──────────────────────────────────────────── --}}
        <a href="{{ $item->url }}" class="sidebar-link{{ $item->isActive($currentPath) ? ' active' : '' }}" data-tooltip="{{ $item->label }}" @if($item->target) target="{{ $item->target }}" @endif>
          <span class="sidebar-link__icon"><i data-lucide="{{ $item->icon ?? 'circle' }}" class="w-[18px] h-[18px]"></i></span>
          <span class="sidebar-link__text">{{ $item->label }}</span>
          @if($item->badge)
            <span class="badge badge--{{ $item->badgeVariant ?? 'info' }}" style="margin-left:auto">{{ $item->badge }}</span>
          @endif
        </a>
      @endif
    @endforeach
  </div>
  @endforeach

@else
  {{-- ── Fallback: Legacy hardcoded sidebar ────────────────────────── --}}
  {{-- This renders when AdminMenuRegistry is not available (e.g., install) --}}

  <a href="/admin" class="sidebar-link" data-tooltip="Dashboard">
    <span class="sidebar-link__icon"><i data-lucide="layout-dashboard" class="w-[18px] h-[18px]"></i></span>
    <span class="sidebar-link__text">Dashboard</span>
  </a>

  <div class="sidebar-divider"></div>
  <div class="sidebar-group">
    <div class="sidebar-group__label">Content</div>
    <a href="/admin/content" class="sidebar-link" data-tooltip="Content">
      <span class="sidebar-link__icon"><i data-lucide="file-text" class="w-[18px] h-[18px]"></i></span>
      <span class="sidebar-link__text">Content</span>
    </a>
    <a href="/admin/media" class="sidebar-link" data-tooltip="Media">
      <span class="sidebar-link__icon"><i data-lucide="image" class="w-[18px] h-[18px]"></i></span>
      <span class="sidebar-link__text">Media</span>
    </a>
  </div>

  <div class="sidebar-divider"></div>
  <div class="sidebar-group">
    <div class="sidebar-group__label">System</div>
    <a href="/admin/settings" class="sidebar-link" data-tooltip="Settings">
      <span class="sidebar-link__icon"><i data-lucide="settings" class="w-[18px] h-[18px]"></i></span>
      <span class="sidebar-link__text">Settings</span>
    </a>
  </div>
@endif
