@extends('layouts.admin')

@section('title', 'Blocks')
@section('page_title', 'Blocks')

@section('breadcrumb')
<a href="/admin" class="breadcrumb__item">Dashboard</a>
<span class="breadcrumb__sep">›</span>
<span class="breadcrumb__item breadcrumb__item--active">Blocks</span>
@endsection

@section('page_actions')
<a href="/admin/blocks/create" class="btn btn--primary btn--sm">
  <i data-lucide="plus" class="w-4 h-4"></i>
  New Block Type
</a>
<a href="/admin/blocks/instances/create" class="btn btn--ghost btn--sm">
  <i data-lucide="copy-plus" class="w-4 h-4"></i>
  New Instance
</a>
@endsection

@section('content')

{{-- Tab Navigation --}}
<div class="blocks-tabs">
  <a href="/admin/blocks?tab=types"
     class="blocks-tab {{ ($tab ?? 'types') === 'types' ? 'blocks-tab--active' : '' }}">
    <i data-lucide="puzzle" class="w-4 h-4"></i>
    Block Types
    <span class="blocks-tab__badge">{{ $totalTypes ?? 0 }}</span>
  </a>
  <a href="/admin/blocks?tab=instances"
     class="blocks-tab {{ ($tab ?? 'types') === 'instances' ? 'blocks-tab--active' : '' }}">
    <i data-lucide="layers" class="w-4 h-4"></i>
    Instances
    <span class="blocks-tab__badge">{{ $totalInstances ?? 0 }}</span>
  </a>
</div>

{{-- Flash Messages --}}
@if(!empty($flashSuccess))
<div class="alert alert--success mb-4">
  <i data-lucide="check-circle" class="w-4 h-4"></i>
  {{ $flashSuccess }}
</div>
@endif
@if(!empty($flashError))
<div class="alert alert--error mb-4">
  <i data-lucide="alert-circle" class="w-4 h-4"></i>
  {{ $flashError }}
</div>
@endif

@if(($tab ?? 'types') === 'types')
{{-- ═══ Block Types Tab ═══ --}}

{{-- Grouped Block Types --}}
@foreach($grouped ?? [] as $category => $types)
<div class="blocks-category" id="category-{{ strtolower(preg_replace('/[^a-z0-9]/i', '-', $category)) }}">
  <div class="blocks-category__header">
    <i data-lucide="folder" class="w-4 h-4"></i>
    <span class="blocks-category__title">{{ $category }}</span>
    <span class="badge badge--default badge--sm">{{ count($types) }}</span>
  </div>

  <div class="blocks-grid">
    @foreach($types as $bt)
    <div class="bt-card {{ $bt['source'] === 'code' ? 'bt-card--code' : 'bt-card--custom' }}" id="bt-{{ $bt['id'] }}">
      <div class="bt-card__header">
        <div class="bt-card__icon">
          <i data-lucide="{{ $bt['icon'] ?? 'puzzle' }}" class="w-5 h-5"></i>
        </div>
        <div class="bt-card__meta">
          <h3 class="bt-card__title">{{ $bt['label'] }}</h3>
          <span class="bt-card__machine">{{ $bt['id'] }}</span>
        </div>
        @if($bt['source'] === 'code')
        <span class="badge badge--info badge--sm">
          <i data-lucide="code-2" class="w-3 h-3"></i> Code
        </span>
        @else
        <span class="badge badge--success badge--sm">
          <i data-lucide="database" class="w-3 h-3"></i> Custom
        </span>
        @endif
      </div>

      @if($bt['description'])
      <p class="bt-card__desc">{{ $bt['description'] }}</p>
      @endif

      <div class="bt-card__stats">
        @php
          $fieldCount = is_array($bt['fields']) ? count($bt['fields']) : 0;
        @endphp
        <span class="bt-stat">
          <i data-lucide="list" class="w-3.5 h-3.5"></i>
          {{ $fieldCount }} {{ $fieldCount === 1 ? 'field' : 'fields' }}
        </span>
        @if(!$bt['enabled'])
        <span class="badge badge--warning badge--sm">Disabled</span>
        @endif
      </div>

      <div class="bt-card__actions">
        @if($bt['source'] !== 'code')
        <a href="/admin/blocks/{{ $bt['id'] }}/edit" class="btn btn--sm btn--ghost" title="Edit">
          <i data-lucide="pencil" class="w-4 h-4"></i> Edit
        </a>
        @endif
        <a href="/admin/blocks/{{ $bt['id'] }}/fields" class="btn btn--sm btn--ghost" title="Fields">
          <i data-lucide="list" class="w-4 h-4"></i> Fields
        </a>
        <a href="/admin/blocks/{{ $bt['id'] }}/revisions" class="btn btn--sm btn--ghost" title="Revisions">
          <i data-lucide="history" class="w-4 h-4"></i>
        </a>
        <form action="/admin/blocks/{{ $bt['id'] }}/duplicate" method="POST" style="display:inline">
          <input type="hidden" name="new_type_id" value="{{ $bt['id'] }}_copy">
          <button type="submit" class="btn btn--sm btn--ghost" title="Duplicate">
            <i data-lucide="copy" class="w-4 h-4"></i>
          </button>
        </form>
        <a href="/admin/blocks/{{ $bt['id'] }}/export" class="btn btn--sm btn--ghost" title="Export JSON">
          <i data-lucide="download" class="w-4 h-4"></i>
        </a>
        @if($bt['source'] !== 'code' && !$bt['is_system'])
        <form action="/admin/blocks/{{ $bt['id'] }}/delete" method="POST" style="display:inline"
              data-confirm="Delete block type '{{ $bt['label'] }}'? This will also remove all instances." data-confirm-title="Delete Block Type">
          <button type="submit" class="btn btn--sm btn--ghost text-danger" title="Delete">
            <i data-lucide="trash-2" class="w-4 h-4"></i>
          </button>
        </form>
        @endif
      </div>
    </div>
    @endforeach
  </div>
</div>
@endforeach

@if(empty($grouped))
<div class="empty-state">
  <div class="empty-state__icon"><i data-lucide="puzzle" class="w-12 h-12"></i></div>
  <div class="empty-state__title">No block types yet</div>
  <p class="text-muted">Create your first custom block type to use in the Mosaic page builder.</p>
  <a href="/admin/blocks/create" class="btn btn--primary mt-3">
    <i data-lucide="plus" class="w-4 h-4"></i> Create Block Type
  </a>
</div>
@endif

@else
{{-- ═══ Block Instances Tab ═══ --}}

@if(!empty($instances))
<div class="blocks-table-wrap">
  <table class="admin-table">
    <thead>
      <tr>
        <th>Label</th>
        <th>Block Type</th>
        <th>Status</th>
        <th class="text-center">Usage</th>
        <th>Updated</th>
        <th class="text-right">Actions</th>
      </tr>
    </thead>
    <tbody>
      @foreach($instances as $inst)
      <tr id="bi-{{ $inst['id'] }}">
        <td>
          <a href="/admin/blocks/instances/{{ $inst['id'] }}/edit" class="text-primary font-medium hover:underline">{{ $inst['label'] }}</a>
          @if($inst['description'])
          <div class="text-xs text-muted mt-0.5">{{ $inst['description'] }}</div>
          @endif
        </td>
        <td>
          <span class="badge badge--default badge--sm">{{ $inst['block_type'] }}</span>
        </td>
        <td>
          <span class="badge {{ $inst['status'] === 'published' ? 'badge--success' : 'badge--warning' }} badge--sm">
            {{ ucfirst($inst['status']) }}
          </span>
        </td>
        <td class="text-center">
          <span class="text-sm text-muted">{{ $inst['usage_count'] }}×</span>
        </td>
        <td class="text-xs text-muted">{{ $inst['updated_at'] }}</td>
        <td class="text-right">
          <a href="/admin/blocks/instances/{{ $inst['id'] }}/edit" class="btn btn--sm btn--ghost">
            <i data-lucide="pencil" class="w-4 h-4"></i>
          </a>
          <form action="/admin/blocks/instances/{{ $inst['id'] }}/delete" method="POST" style="display:inline"
                data-confirm="Delete this block instance?" data-confirm-title="Delete Instance">
            <button type="submit" class="btn btn--sm btn--ghost text-danger">
              <i data-lucide="trash-2" class="w-4 h-4"></i>
            </button>
          </form>
        </td>
      </tr>
      @endforeach
    </tbody>
  </table>
</div>
@else
<div class="empty-state">
  <div class="empty-state__icon"><i data-lucide="layers" class="w-12 h-12"></i></div>
  <div class="empty-state__title">No block instances yet</div>
  <p class="text-muted">Create reusable block instances to place across your layouts.</p>
  <a href="/admin/blocks/instances/create" class="btn btn--primary mt-3">
    <i data-lucide="copy-plus" class="w-4 h-4"></i> Create Instance
  </a>
</div>
@endif

@endif

@push('head')
<style>
/* ── Blocks Tabs ─────────────────────────────────────────────── */
.blocks-tabs {
  display: flex;
  gap: 0;
  margin-bottom: 1.5rem;
  border-bottom: 1px solid rgba(255,255,255,0.06);
}
.blocks-tab {
  display: inline-flex;
  align-items: center;
  gap: 0.5rem;
  padding: 0.75rem 1.25rem;
  font-size: 0.8125rem;
  font-weight: 500;
  color: #94a3b8;
  text-decoration: none;
  border-bottom: 2px solid transparent;
  margin-bottom: -1px;
  transition: all 0.2s ease;
}
.blocks-tab:hover { color: #e2e8f0; }
.blocks-tab--active {
  color: #818cf8;
  border-bottom-color: #6366f1;
}
.blocks-tab__badge {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-width: 1.25rem;
  height: 1.25rem;
  padding: 0 0.4rem;
  font-size: 0.65rem;
  font-weight: 600;
  border-radius: 9999px;
  background: rgba(255,255,255,0.06);
  color: #94a3b8;
}
.blocks-tab--active .blocks-tab__badge {
  background: rgba(99,102,241,0.15);
  color: #818cf8;
}

/* ── Category Groups ─────────────────────────────────────────── */
.blocks-category {
  margin-bottom: 2rem;
}
.blocks-category__header {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  margin-bottom: 1rem;
  color: #e2e8f0;
  font-size: 0.875rem;
  font-weight: 600;
}

/* ── Block Type Grid ─────────────────────────────────────────── */
.blocks-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
  gap: 1rem;
}

/* ── Block Type Cards ────────────────────────────────────────── */
.bt-card {
  background: rgba(20, 22, 38, 0.6);
  border: 1px solid rgba(255,255,255,0.06);
  border-radius: 16px;
  padding: 1.25rem 1.5rem;
  transition: border-color 0.2s, box-shadow 0.2s, transform 0.15s;
}
.bt-card:hover {
  border-color: rgba(99,102,241,0.2);
  box-shadow: 0 4px 20px rgba(0,0,0,0.15);
  transform: translateY(-1px);
}
.bt-card--code { border-left: 3px solid rgba(59,130,246,0.3); }
.bt-card--custom { border-left: 3px solid rgba(16,185,129,0.3); }

.bt-card__header {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  margin-bottom: 0.625rem;
}
.bt-card__icon {
  width: 42px; height: 42px;
  display: flex; align-items: center; justify-content: center;
  background: linear-gradient(135deg, rgba(99,102,241,0.15), rgba(139,92,246,0.1));
  border-radius: 12px;
  color: #818cf8;
  flex-shrink: 0;
}
.bt-card--custom .bt-card__icon {
  background: linear-gradient(135deg, rgba(16,185,129,0.15), rgba(52,211,153,0.1));
  color: #34d399;
}
.bt-card__meta { flex: 1; min-width: 0; }
.bt-card__title {
  font-size: 0.9375rem; font-weight: 600; color: #e2e8f0;
  margin: 0; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
  line-height: 1.3;
}
.bt-card__machine {
  font-size: 0.6875rem; color: #64748b;
  font-family: var(--font-mono, 'JetBrains Mono', monospace);
}
.bt-card__desc {
  font-size: 0.8rem; color: #64748b; line-height: 1.5;
  margin: 0 0 0.5rem;
  display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical;
  overflow: hidden;
}

/* Stats row */
.bt-card__stats {
  display: flex; align-items: center; gap: 0.75rem;
  margin-bottom: 0.75rem;
}
.bt-stat {
  display: inline-flex; align-items: center; gap: 0.25rem;
  font-size: 0.7rem; color: #94a3b8;
  background: rgba(255,255,255,0.04);
  padding: 0.2rem 0.5rem; border-radius: 6px;
  border: 1px solid rgba(255,255,255,0.06);
}

/* Actions */
.bt-card__actions {
  display: flex; gap: 0.375rem; padding-top: 0.75rem;
  border-top: 1px solid rgba(255,255,255,0.04);
  flex-wrap: wrap;
}
.bt-card__actions .btn--ghost {
  font-size: 0.75rem;
  padding: 0.25rem 0.5rem;
}

/* ── Instances Table Wrap ────────────────────────────────────── */
.blocks-table-wrap {
  background: rgba(20, 22, 38, 0.6);
  border: 1px solid rgba(255,255,255,0.06);
  border-radius: 16px;
  overflow: hidden;
}
.blocks-table-wrap .admin-table {
  width: 100%;
  border-collapse: collapse;
  font-size: 0.8125rem;
}
.blocks-table-wrap .admin-table th {
  text-align: left;
  padding: 0.75rem 1rem;
  font-weight: 600;
  font-size: 0.72rem;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  color: #64748b;
  border-bottom: 1px solid rgba(255,255,255,0.06);
  background: rgba(255,255,255,0.02);
}
.blocks-table-wrap .admin-table td {
  padding: 0.75rem 1rem;
  border-bottom: 1px solid rgba(255,255,255,0.03);
  color: #cbd5e1;
  vertical-align: middle;
}
.blocks-table-wrap .admin-table tr:last-child td {
  border-bottom: none;
}
.blocks-table-wrap .admin-table tr:hover td {
  background: rgba(255,255,255,0.015);
}
</style>
@endpush

@endsection
