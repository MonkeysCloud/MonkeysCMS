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
@php
  // Build query string helper (preserves all active filters)
  $qs = function(array $overrides = []) use ($activeType, $activeStatus, $search, $authorId, $sortBy, $sortDir) {
      $p = array_filter(array_merge([
          'type'   => $activeType,
          'status' => $activeStatus !== 'all' ? $activeStatus : null,
          'search' => $search !== '' ? $search : null,
          'author' => $authorId,
          'sort'   => $sortBy !== 'updated_at' ? $sortBy : null,
          'dir'    => $sortDir !== 'DESC' ? $sortDir : null,
      ], $overrides), fn($v) => $v !== null && $v !== '');
      return $p ? '?' . http_build_query($p) : '';
  };

  // Sort link helper
  $sortLink = function(string $col) use ($qs, $sortBy, $sortDir) {
      $newDir = ($sortBy === $col && $sortDir === 'DESC') ? 'ASC' : 'DESC';
      return '/admin/content' . $qs(['sort' => $col, 'dir' => $newDir, 'page' => null]);
  };
  $sortIcon = function(string $col) use ($sortBy, $sortDir) {
      if ($sortBy !== $col) return 'arrow-up-down';
      return $sortDir === 'ASC' ? 'arrow-up' : 'arrow-down';
  };
@endphp

<div id="content-listing">

  {{-- Search + Filters Bar --}}
  <div class="card mb-4">
    <div class="card__body" style="padding:0.75rem 1rem;">
      <form action="/admin/content" method="GET" id="content-filter-form" class="content-filters-grid">
        {{-- Search --}}
        <div class="search-box">
          <i data-lucide="search" class="search-box__icon w-4 h-4"></i>
          <input type="text" name="search" class="search-box__input" placeholder="Search title or content…"
                 value="{{ $search }}" autocomplete="off" id="content-search">
          @if($search !== '')
          <a href="/admin/content{{ $qs(['search' => null, 'page' => null]) }}" class="search-box__clear" title="Clear">
            <i data-lucide="x" class="w-3.5 h-3.5"></i>
          </a>
          @endif
        </div>

        {{-- Preserve hidden state --}}
        @if($activeType)
        <input type="hidden" name="type" value="{{ $activeType }}">
        @endif
        @if($activeStatus !== 'all')
        <input type="hidden" name="status" value="{{ $activeStatus }}">
        @endif
        @if($authorId)
        <input type="hidden" name="author" value="{{ $authorId }}">
        @endif
        @if($sortBy !== 'updated_at')
        <input type="hidden" name="sort" value="{{ $sortBy }}">
        @endif
        @if($sortDir !== 'DESC')
        <input type="hidden" name="dir" value="{{ $sortDir }}">
        @endif

        {{-- Author filter --}}
        <select class="form-select form-select--sm" id="author-filter"
                onchange="applyFilter('author', this.value)">
          <option value="">All Authors</option>
          @foreach($authors ?? [] as $author)
          <option value="{{ $author['id'] }}" {{ $authorId == $author['id'] ? 'selected' : '' }}>
            {{ $author['name'] }}
          </option>
          @endforeach
        </select>

        {{-- Status filter --}}
        <select class="form-select form-select--sm" id="status-filter"
                onchange="applyFilter('status', this.value)">
          <option value="" {{ ($activeStatus ?? 'all') === 'all' ? 'selected' : '' }}>All Status</option>
          @foreach($statuses ?? [] as $status)
          <option value="{{ $status->value }}" {{ ($activeStatus ?? '') === $status->value ? 'selected' : '' }}>
            {{ $status->label() }}
          </option>
          @endforeach
        </select>

        {{-- Language filter (only when multilingual is enabled) --}}
        @if(!empty($enabledLanguages) && count($enabledLanguages) > 1)
        <select class="form-select form-select--sm" id="lang-filter"
                onchange="applyFilter('lang', this.value)">
          <option value="">All Languages</option>
          @foreach($enabledLanguages as $lang)
          <option value="{{ $lang->code }}" {{ ($activeLang ?? '') === $lang->code ? 'selected' : '' }}>
            {{ $lang->flagEmoji }} {{ $lang->native }}
          </option>
          @endforeach
        </select>
        @endif
      </form>
    </div>
  </div>

  {{-- Type Tabs --}}
  <div class="content-tabs mb-3">
    <a href="/admin/content{{ $qs(['type' => null, 'page' => null]) }}" class="content-tab {{ empty($activeType) ? 'content-tab--active' : '' }}">
      <i data-lucide="layers" class="w-4 h-4"></i> All
    </a>
    @foreach($contentTypes ?? [] as $ct)
    <a href="/admin/content{{ $qs(['type' => $ct->type_id, 'page' => null]) }}" class="content-tab {{ ($activeType ?? '') === $ct->type_id ? 'content-tab--active' : '' }}">
      <i data-lucide="{{ $ct->icon }}" class="w-4 h-4"></i> {{ $ct->label_plural ?: $ct->label }}
    </a>
    @endforeach
    <span style="flex:1"></span>
    <a href="/admin/content/trash" class="content-tab" style="color:#f87171">
      <i data-lucide="trash-2" class="w-4 h-4"></i> Trash
    </a>
  </div>

  {{-- Active filters summary --}}
  @if($search !== '' || $authorId)
  <div class="active-filters mb-3">
    <span class="active-filters__label">Filters:</span>
    @if($search !== '')
    <a href="/admin/content{{ $qs(['search' => null, 'page' => null]) }}" class="filter-chip">
      <i data-lucide="search" class="w-3 h-3"></i> "{{ $search }}"
      <i data-lucide="x" class="w-3 h-3"></i>
    </a>
    @endif
    @if($authorId)
    @php
      $authorName = 'Unknown';
      foreach ($authors ?? [] as $a) { if ((int)$a['id'] === $authorId) { $authorName = $a['name']; break; } }
    @endphp
    <a href="/admin/content{{ $qs(['author' => null, 'page' => null]) }}" class="filter-chip">
      <i data-lucide="user" class="w-3 h-3"></i> {{ $authorName }}
      <i data-lucide="x" class="w-3 h-3"></i>
    </a>
    @endif
    <a href="/admin/content" class="filter-chip filter-chip--clear">Clear all</a>
  </div>
  @endif

  {{-- Bulk Actions Bar (auto-managed by BulkActions.js) --}}
  <div class="bulk-bar" data-bulk-toolbar style="display:none;">
    <span class="bulk-bar__count"><span data-bulk-count>0</span> selected</span>
    <button class="btn btn--xs btn--ghost" data-bulk-action="publish">
      <i data-lucide="check-circle" class="w-3.5 h-3.5"></i> Publish
    </button>
    <button class="btn btn--xs btn--ghost" data-bulk-action="draft">
      <i data-lucide="pencil-line" class="w-3.5 h-3.5"></i> Draft
    </button>
    <button class="btn btn--xs btn--ghost" data-bulk-action="archive">
      <i data-lucide="archive" class="w-3.5 h-3.5"></i> Archive
    </button>
    <button class="btn btn--xs btn--ghost text-danger" data-bulk-action="delete"
            data-bulk-confirm="Are you sure you want to delete {count} item{s}? This action cannot be undone."
            data-bulk-severity="danger">
      <i data-lucide="trash-2" class="w-3.5 h-3.5"></i> Delete
    </button>
  </div>

  {{-- Content Table --}}
  <div class="card">
    <div class="card__body p-0">
      <table class="table table--hover" id="content-table" data-bulk-actions data-bulk-url="/admin/content/bulk">
        <thead>
          <tr>
            <th class="table__check"><input type="checkbox" data-bulk-select-all></th>
            <th>
              <a href="{{ $sortLink('title') }}" class="sort-link">
                Title <i data-lucide="{{ $sortIcon('title') }}" class="w-3 h-3"></i>
              </a>
            </th>
            <th>Type</th>
            <th>Author</th>
            @if(!empty($enabledLanguages) && count($enabledLanguages) > 1)
            <th style="width:55px;text-align:center">Lang</th>
            @endif
            <th>Status</th>
            <th>
              <a href="{{ $sortLink('updated_at') }}" class="sort-link">
                Updated <i data-lucide="{{ $sortIcon('updated_at') }}" class="w-3 h-3"></i>
              </a>
            </th>
            <th>
              <a href="{{ $sortLink('created_at') }}" class="sort-link">
                Created <i data-lucide="{{ $sortIcon('created_at') }}" class="w-3 h-3"></i>
              </a>
            </th>
            <th class="table__actions">Actions</th>
          </tr>
        </thead>
        <tbody>
          @foreach($nodes ?? [] as $node)
          <tr>
            <td class="table__check">
              <input type="checkbox" value="{{ $node->id }}" data-bulk-item>
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
            @if(!empty($enabledLanguages) && count($enabledLanguages) > 1)
            <td style="text-align:center" title="{{ $node->language }}">{{ strtoupper($node->language ?? 'en') }}</td>
            @endif
            <td>
              <span class="badge {{ $node->statusBadge }}">
                <i data-lucide="{{ $node->statusEnum->icon() }}" class="w-3 h-3"></i>
                {{ $node->statusLabel }}
              </span>
            </td>
            <td class="text-sm text-muted" title="{{ $node->updated_at?->format('Y-m-d H:i:s') ?? '' }}">
              {{ $node->updatedAgo }}
            </td>
            <td class="text-sm text-muted" title="{{ $node->created_at?->format('Y-m-d H:i:s') ?? '' }}">
              {{ $node->created_at?->format('M j, Y') ?? '—' }}
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
              <form action="/admin/content/{{ $node->id }}/delete" method="POST" class="inline-form"
                    data-confirm="Delete this content?" data-confirm-title="Delete Content">
                <button type="submit" class="btn btn--xs btn--ghost text-danger" title="Delete">
                  <i data-lucide="trash-2" class="w-3.5 h-3.5"></i>
                </button>
              </form>
            </td>
          </tr>
          @endforeach
          @empty($nodes)
          <tr>
            <td colspan="8">
              <div class="empty-state">
                <div class="empty-state__icon"><i data-lucide="file-text" class="w-12 h-12"></i></div>
                @if($search !== '')
                <div class="empty-state__title">No results for "{{ $search }}"</div>
                <p class="text-muted">Try a different search term or <a href="/admin/content">clear filters</a>.</p>
                @else
                <div class="empty-state__title">No content found</div>
                <p class="text-muted">Create your first piece of content.</p>
                @endif
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
      <a href="/admin/content{{ $qs(['page' => ($pagination['page'] ?? 1) - 1]) }}"
         class="pagination__item">&laquo;</a>
      @endif
      @for($i = 1; $i <= ($pagination['pages'] ?? 1); $i++)
      <a href="/admin/content{{ $qs(['page' => $i]) }}"
         class="pagination__item {{ ($pagination['page'] ?? 1) == $i ? 'active' : '' }}">{{ $i }}</a>
      @endfor
      @if($pagination['has_next'] ?? false)
      <a href="/admin/content{{ $qs(['page' => ($pagination['page'] ?? 1) + 1]) }}"
         class="pagination__item">&raquo;</a>
      @endif
    </div>
  </div>
  @endif
</div>

@push('head')
<link rel="stylesheet" href="/themes/core/admin/css/bulk.css?v={{ time() }}">
<style>
/* ── Search + Filters Grid ────────────────────────────────────────── */
.content-filters-grid {
  display: flex; align-items: center; gap: 0.75rem; flex-wrap: wrap;
}
.search-box {
  position: relative; flex: 1; min-width: 200px;
}
.search-box__icon {
  position: absolute; left: 0.75rem; top: 50%; transform: translateY(-50%);
  color: #64748b; pointer-events: none;
}
.search-box__input {
  width: 100%; padding: 0.5rem 2rem 0.5rem 2.25rem;
  background: rgba(255,255,255,0.04); border: 1px solid rgba(255,255,255,0.08);
  border-radius: 8px; color: #e2e8f0; font-size: 0.85rem;
  transition: border-color 0.2s, background 0.2s;
}
.search-box__input:focus {
  outline: none; border-color: rgba(99,102,241,0.5);
  background: rgba(255,255,255,0.06);
}
.search-box__input::placeholder { color: #64748b; }
.search-box__clear {
  position: absolute; right: 0.5rem; top: 50%; transform: translateY(-50%);
  color: #94a3b8; display: flex; padding: 0.25rem; border-radius: 4px;
  transition: color 0.15s, background 0.15s;
}
.search-box__clear:hover { color: #e2e8f0; background: rgba(255,255,255,0.08); }

/* ── Content tabs ─────────────────────────────────────────────────── */
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

/* ── Filter chips ─────────────────────────────────────────────────── */
.active-filters {
  display: flex; align-items: center; gap: 0.5rem; flex-wrap: wrap;
}
.active-filters__label { font-size: 0.75rem; color: #64748b; }
.filter-chip {
  display: inline-flex; align-items: center; gap: 0.25rem;
  padding: 0.2rem 0.5rem; font-size: 0.7rem; color: #a5b4fc;
  background: rgba(99,102,241,0.1); border: 1px solid rgba(99,102,241,0.2);
  border-radius: 999px; text-decoration: none; transition: all 0.15s;
}
.filter-chip:hover { background: rgba(99,102,241,0.2); border-color: rgba(99,102,241,0.4); }
.filter-chip--clear { color: #94a3b8; background: transparent; border-color: rgba(255,255,255,0.08); }
.filter-chip--clear:hover { color: #e2e8f0; background: rgba(255,255,255,0.05); }

/* ── Sort links ───────────────────────────────────────────────────── */
.sort-link {
  display: inline-flex; align-items: center; gap: 0.25rem;
  color: inherit; text-decoration: none; white-space: nowrap;
}
.sort-link:hover { color: #a5b4fc; }

/* ── Misc ─────────────────────────────────────────────────────────── */
.form-select--sm {
  font-size: 0.8rem; padding: 0.35rem 2rem 0.35rem 0.6rem; height: auto;
}
.ct-badge {
  display: inline-flex; align-items: center; gap: 0.3rem;
  font-size: 0.75rem; color: #94a3b8;
}
.content-title { color: #e2e8f0; text-decoration: none; }
.content-title:hover { color: #818cf8; }
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
  // ── Dropdown toggle ────────────────────────────────────────────
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

  // ── Debounced search ───────────────────────────────────────────
  const searchInput = document.getElementById('content-search');
  let searchTimer;
  if (searchInput) {
    searchInput.addEventListener('keyup', (e) => {
      if (e.key === 'Enter') {
        document.getElementById('content-filter-form').submit();
        return;
      }
      clearTimeout(searchTimer);
      searchTimer = setTimeout(() => {
        if (searchInput.value.length >= 3 || searchInput.value.length === 0) {
          document.getElementById('content-filter-form').submit();
        }
      }, 600);
    });
    // Focus search on page load if there's a search term
    if (searchInput.value) {
      searchInput.focus();
      searchInput.setSelectionRange(searchInput.value.length, searchInput.value.length);
    }
  }

  // Bulk selection is auto-managed by BulkActions.js via data-bulk-* attributes
});

// ── Filter helper (used by select dropdowns) ───────────────────────
function applyFilter(name, value) {
  const url = new URL(window.location.href);
  if (value) {
    url.searchParams.set(name, value);
  } else {
    url.searchParams.delete(name);
  }
  url.searchParams.delete('page');
  window.location.href = url.toString();
}
</script>
<script src="/themes/core/admin/js/bulk-actions.js?v={{ time() }}"></script>
@endpush
@endsection
