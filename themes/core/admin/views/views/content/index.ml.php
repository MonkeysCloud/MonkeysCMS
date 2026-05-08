@extends('layouts.admin')

@section('title', 'Content')
@section('page_title', 'Content')

@section('breadcrumb')
<a href="/admin" class="breadcrumb__item">Dashboard</a>
<span class="breadcrumb__sep">›</span>
<span class="breadcrumb__item breadcrumb__item--active">Content</span>
@endsection

@section('page_actions')
<div class="dropdown" id="create-dropdown">
  <button class="btn btn--primary btn--sm" id="create-btn">
    <i data-lucide="plus" class="w-4 h-4"></i>
    New Content
    <i data-lucide="chevron-down" class="w-3.5 h-3.5"></i>
  </button>
  <div class="dropdown__menu" id="create-menu" style="display:none;">
    @foreach($contentTypes ?? [] as $ct)
    <a href="/admin/content/create/{{ $ct->type_id }}" class="dropdown__item">
      <i data-lucide="{{ $ct->icon }}" class="w-4 h-4"></i>
      {{ $ct->label }}
    </a>
    @endforeach
  </div>
</div>
@endsection

@section('content')
<div id="content-listing">

  {{-- Type Tabs + Filters --}}
  <div class="card mb-4">
    <div class="card__body" style="padding:0.75rem 1rem;">
      <div class="content-filters">
        {{-- Type Tabs --}}
        <div class="content-tabs">
          <a href="/admin/content" class="content-tab {{ empty($activeType) ? 'content-tab--active' : '' }}">
            <i data-lucide="layers" class="w-4 h-4"></i> All
          </a>
          @foreach($contentTypes ?? [] as $ct)
          <a href="/admin/content?type={{ $ct->type_id }}" class="content-tab {{ ($activeType ?? '') === $ct->type_id ? 'content-tab--active' : '' }}">
            <i data-lucide="{{ $ct->icon }}" class="w-4 h-4"></i> {{ $ct->label_plural ?: $ct->label }}
          </a>
          @endforeach
        </div>

        {{-- Status Filter --}}
        <div class="content-filters__right">
          <select class="form-select form-select--sm" id="status-filter"
                  onchange="window.location.href=this.value">
            <option value="/admin/content{{ !empty($activeType) ? '?type=' . $activeType : '' }}" {{ ($activeStatus ?? 'all') === 'all' ? 'selected' : '' }}>All Status</option>
            @foreach($statuses ?? [] as $status)
            <option value="/admin/content?{{ !empty($activeType) ? 'type=' . $activeType . '&' : '' }}status={{ $status->value }}" {{ ($activeStatus ?? '') === $status->value ? 'selected' : '' }}>
              {{ $status->label() }}
            </option>
            @endforeach
          </select>
        </div>
      </div>
    </div>
  </div>

  {{-- Bulk Actions Bar --}}
  <div class="bulk-bar" id="bulk-bar" style="display:none;">
    <form action="/admin/content/bulk" method="POST" id="bulk-form">
      <span class="bulk-bar__count"><span id="selected-count">0</span> selected</span>
      <button type="submit" name="action" value="publish" class="btn btn--xs btn--ghost">
        <i data-lucide="check-circle" class="w-3.5 h-3.5"></i> Publish
      </button>
      <button type="submit" name="action" value="draft" class="btn btn--xs btn--ghost">
        <i data-lucide="pencil-line" class="w-3.5 h-3.5"></i> Draft
      </button>
      <button type="submit" name="action" value="archive" class="btn btn--xs btn--ghost">
        <i data-lucide="archive" class="w-3.5 h-3.5"></i> Archive
      </button>
      <button type="submit" name="action" value="delete" class="btn btn--xs btn--ghost text-danger"
              onclick="return confirm('Delete selected content?')">
        <i data-lucide="trash-2" class="w-3.5 h-3.5"></i> Delete
      </button>
      <div id="bulk-ids"></div>
    </form>
  </div>

  {{-- Content Table --}}
  <div class="card">
    <div class="card__body p-0">
      <table class="table table--hover">
        <thead>
          <tr>
            <th class="table__check"><input type="checkbox" id="check-all"></th>
            <th>Title</th>
            <th>Type</th>
            <th>Author</th>
            <th>Status</th>
            <th>Updated</th>
            <th class="table__actions">Actions</th>
          </tr>
        </thead>
        <tbody>
          @foreach($nodes ?? [] as $node)
          <tr>
            <td class="table__check">
              <input type="checkbox" value="{{ $node->id }}" class="node-check">
            </td>
            <td>
              <a href="{{ $node->editUrl }}" class="font-medium content-title">{{ $node->title }}</a>
              <div class="text-xs text-muted">/{{ $node->slug }}</div>
            </td>
            <td class="text-sm">
              @php
                $typeEntity = ($contentTypes ?? [])[$node->content_type] ?? null;
              @endphp
              @if($typeEntity)
              <span class="ct-badge">
                <i data-lucide="{{ $typeEntity->icon }}" class="w-3.5 h-3.5"></i>
                {{ $typeEntity->label }}
              </span>
              @else
              <span class="text-muted">{{ $node->content_type }}</span>
              @endif
            </td>
            <td class="text-sm text-muted">{{ $node->author_name ?? '—' }}</td>
            <td>
              <span class="badge {{ $node->statusBadge }}">
                <i data-lucide="{{ $node->statusEnum->icon() }}" class="w-3 h-3"></i>
                {{ $node->statusLabel }}
              </span>
            </td>
            <td class="text-sm text-muted" title="{{ $node->updated_at?->format('Y-m-d H:i:s') ?? '' }}">
              {{ $node->updatedAgo }}
            </td>
            <td class="table__actions">
              <a href="{{ $node->editUrl }}" class="btn btn--xs btn--ghost" title="Edit">
                <i data-lucide="pencil" class="w-3.5 h-3.5"></i>
              </a>
              @php $typeObj = ($contentTypes ?? [])[$node->content_type] ?? null; @endphp
              @if($typeObj && $typeObj->hasMosaic)
              <a href="/admin/mosaic/{{ $node->id }}" class="btn btn--xs btn--ghost" title="Mosaic Editor">
                <i data-lucide="layout-grid" class="w-3.5 h-3.5"></i>
              </a>
              @endif
              <form action="/admin/content/{{ $node->id }}/delete" method="POST" style="display:inline"
                    onsubmit="return confirm('Delete this content?')">
                <button type="submit" class="btn btn--xs btn--ghost text-danger" title="Delete">
                  <i data-lucide="trash-2" class="w-3.5 h-3.5"></i>
                </button>
              </form>
            </td>
          </tr>
          @endforeach
          @empty($nodes)
          <tr>
            <td colspan="7">
              <div class="empty-state">
                <div class="empty-state__icon"><i data-lucide="file-text" class="w-12 h-12"></i></div>
                <div class="empty-state__title">No content found</div>
                <p class="text-muted">Create your first piece of content.</p>
              </div>
            </td>
          </tr>
          @endempty
        </tbody>
      </table>
    </div>
  </div>

  {{-- Pagination --}}
  @if(($pagination['pages'] ?? 1) > 1)
  <div class="flex-between mt-4">
    <span class="text-sm text-muted">Showing {{ $pagination['from'] ?? 0 }}–{{ $pagination['to'] ?? 0 }} of {{ $pagination['total'] ?? 0 }}</span>
    <div class="pagination">
      @if($pagination['has_prev'] ?? false)
      <a href="?page={{ ($pagination['page'] ?? 1) - 1 }}{{ !empty($activeType) ? '&type=' . $activeType : '' }}{{ ($activeStatus ?? 'all') !== 'all' ? '&status=' . $activeStatus : '' }}"
         class="pagination__item">&laquo;</a>
      @endif
      @for($i = 1; $i <= ($pagination['pages'] ?? 1); $i++)
      <a href="?page={{ $i }}{{ !empty($activeType) ? '&type=' . $activeType : '' }}{{ ($activeStatus ?? 'all') !== 'all' ? '&status=' . $activeStatus : '' }}"
         class="pagination__item {{ ($pagination['page'] ?? 1) == $i ? 'active' : '' }}">{{ $i }}</a>
      @endfor
      @if($pagination['has_next'] ?? false)
      <a href="?page={{ ($pagination['page'] ?? 1) + 1 }}{{ !empty($activeType) ? '&type=' . $activeType : '' }}{{ ($activeStatus ?? 'all') !== 'all' ? '&status=' . $activeStatus : '' }}"
         class="pagination__item">&raquo;</a>
      @endif
    </div>
  </div>
  @endif
</div>

@push('head')
<style>
.content-filters {
  display: flex; align-items: center; justify-content: space-between; gap: 1rem; flex-wrap: wrap;
}
.content-tabs {
  display: flex; gap: 0.25rem; flex-wrap: wrap;
}
.content-tab {
  display: inline-flex; align-items: center; gap: 0.35rem;
  padding: 0.4rem 0.75rem; font-size: 0.8rem; color: #94a3b8;
  border-radius: 8px; text-decoration: none; transition: all 0.15s;
  border: 1px solid transparent;
}
.content-tab:hover { color: #e2e8f0; background: rgba(255,255,255,0.04); }
.content-tab--active {
  color: #818cf8; background: rgba(99,102,241,0.08);
  border-color: rgba(99,102,241,0.15);
}
.content-filters__right {
  display: flex; gap: 0.5rem; align-items: center;
}
.form-select--sm {
  font-size: 0.8rem; padding: 0.35rem 2rem 0.35rem 0.6rem; height: auto;
}
.ct-badge {
  display: inline-flex; align-items: center; gap: 0.3rem;
  font-size: 0.75rem; color: #94a3b8;
}
.content-title { color: #e2e8f0; text-decoration: none; }
.content-title:hover { color: #818cf8; }
.bulk-bar {
  background: rgba(99,102,241,0.08); border: 1px solid rgba(99,102,241,0.15);
  border-radius: 10px; padding: 0.5rem 1rem; margin-bottom: 0.75rem;
  display: flex; align-items: center; gap: 0.75rem;
}
.bulk-bar__count {
  font-size: 0.8rem; font-weight: 600; color: #818cf8; margin-right: 0.5rem;
}
.dropdown { position: relative; }
.dropdown__menu {
  position: absolute; top: 100%; right: 0; min-width: 180px;
  background: rgba(20,22,38,0.98); border: 1px solid rgba(255,255,255,0.08);
  border-radius: 10px; padding: 0.4rem; z-index: 50; margin-top: 0.4rem;
  box-shadow: 0 8px 30px rgba(0,0,0,0.3);
}
.dropdown__item {
  display: flex; align-items: center; gap: 0.5rem;
  padding: 0.5rem 0.75rem; font-size: 0.8rem; color: #cbd5e1;
  text-decoration: none; border-radius: 6px; transition: background 0.15s;
}
.dropdown__item:hover { background: rgba(255,255,255,0.06); color: #e2e8f0; }
</style>
@endpush

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
  // Dropdown toggle
  const createBtn = document.getElementById('create-btn');
  const createMenu = document.getElementById('create-menu');
  if (createBtn && createMenu) {
    createBtn.addEventListener('click', () => {
      createMenu.style.display = createMenu.style.display === 'none' ? 'block' : 'none';
    });
    document.addEventListener('click', (e) => {
      if (!createBtn.contains(e.target) && !createMenu.contains(e.target)) {
        createMenu.style.display = 'none';
      }
    });
  }

  // Bulk selection
  const checkAll = document.getElementById('check-all');
  const bulkBar = document.getElementById('bulk-bar');
  const bulkIds = document.getElementById('bulk-ids');
  const selectedCount = document.getElementById('selected-count');

  function updateBulkBar() {
    const checked = document.querySelectorAll('.node-check:checked');
    if (bulkBar) bulkBar.style.display = checked.length > 0 ? 'flex' : 'none';
    if (selectedCount) selectedCount.textContent = checked.length;
    if (bulkIds) {
      bulkIds.innerHTML = '';
      checked.forEach(cb => {
        const input = document.createElement('input');
        input.type = 'hidden'; input.name = 'ids[]'; input.value = cb.value;
        bulkIds.appendChild(input);
      });
    }
  }

  if (checkAll) {
    checkAll.addEventListener('change', () => {
      document.querySelectorAll('.node-check').forEach(cb => { cb.checked = checkAll.checked; });
      updateBulkBar();
    });
  }
  document.querySelectorAll('.node-check').forEach(cb => {
    cb.addEventListener('change', updateBulkBar);
  });
});
</script>
@endpush
@endsection
