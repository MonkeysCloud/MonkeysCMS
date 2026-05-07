@extends('layouts.admin')

@section('title', 'Activity Log')
@section('page_title', 'Activity Log')

@section('breadcrumb')
<a href="/admin" class="breadcrumb__item">Dashboard</a>
<span class="breadcrumb__sep">›</span>
<span class="breadcrumb__item breadcrumb__item--active">Activity Log</span>
@endsection

@section('page_actions')
<form method="POST" action="/admin/activity/cleanup" data-confirm="Delete all log entries older than 90 days?" data-confirm-title="Cleanup Log">
  <button type="submit" name="days" value="90" class="btn btn--ghost btn--sm">
    <i data-lucide="trash-2" class="w-4 h-4"></i> Cleanup Old Entries
  </button>
</form>
@endsection

@section('content')
@php
  $qs = function(array $overrides = []) use ($filters) {
      $p = array_filter(array_merge($filters, $overrides), fn($v) => $v !== null && $v !== '');
      unset($p['page']);
      return $p ? '?' . http_build_query(array_merge($p, isset($overrides['page']) ? ['page' => $overrides['page']] : [])) : '';
  };

  $actionColors = [
      'created'   => ['bg' => 'rgba(52,211,153,.1)',  'color' => '#34d399', 'icon' => 'plus-circle'],
      'updated'   => ['bg' => 'rgba(96,165,250,.1)',  'color' => '#60a5fa', 'icon' => 'pencil'],
      'deleted'   => ['bg' => 'rgba(248,113,113,.1)', 'color' => '#f87171', 'icon' => 'trash-2'],
      'trashed'   => ['bg' => 'rgba(248,113,113,.08)','color' => '#fb923c', 'icon' => 'archive'],
      'restored'  => ['bg' => 'rgba(52,211,153,.08)', 'color' => '#34d399', 'icon' => 'rotate-ccw'],
      'published' => ['bg' => 'rgba(129,140,248,.1)', 'color' => '#818cf8', 'icon' => 'globe'],
      'login'     => ['bg' => 'rgba(52,211,153,.06)', 'color' => '#4ade80', 'icon' => 'log-in'],
      'logout'    => ['bg' => 'rgba(148,163,184,.08)','color' => '#94a3b8', 'icon' => 'log-out'],
      'uploaded'  => ['bg' => 'rgba(251,191,36,.08)', 'color' => '#fbbf24', 'icon' => 'upload'],
  ];

  $entityIcons = [
      'node'        => 'file-text',
      'user'        => 'user',
      'media'       => 'image',
      'menu'        => 'menu',
      'block_type'  => 'blocks',
      'block'       => 'blocks',
      'vocabulary'  => 'tags',
      'term'        => 'tag',
      'setting'     => 'settings',
      'role'        => 'shield',
      'content_type'=> 'database',
      'search'      => 'search',
  ];

  $entityLinks = [
      'node'        => '/admin/content/{id}/edit',
      'user'        => '/admin/users/{id}/edit',
      'media'       => '/admin/media/{id}/edit',
      'menu'        => '/admin/menus/{id}/edit',
      'vocabulary'  => '/admin/taxonomy/{id}/terms',
      'block_type'  => '/admin/blocks/{id}/edit',
      'role'        => '/admin/roles/{id}/edit',
  ];
@endphp

{{-- Flash messages --}}
@if(!empty($flash))
<div class="alert alert--success mb-4">{{ $flash }}</div>
@endif

{{-- Filter Bar --}}
<div class="card mb-4">
  <div class="card__body" style="padding:0.75rem 1rem;">
    <form action="/admin/activity" method="GET" class="al-filters">
      <div class="search-box" style="flex:1;min-width:180px">
        <i data-lucide="search" class="search-box__icon w-4 h-4"></i>
        <input type="text" name="search" class="search-box__input" placeholder="Search entity or details…"
               value="{{ $filters['search'] ?? '' }}" autocomplete="off">
      </div>

      <select name="action" class="form-select form-select--sm" onchange="this.form.submit()">
        <option value="">All Actions</option>
        @foreach($filterOptions['actions'] ?? [] as $a)
        <option value="{{ $a }}" {{ ($filters['action'] ?? '') === $a ? 'selected' : '' }}>{{ ucfirst($a) }}</option>
        @endforeach
      </select>

      <select name="entity_type" class="form-select form-select--sm" onchange="this.form.submit()">
        <option value="">All Types</option>
        @foreach($filterOptions['entity_types'] ?? [] as $et)
        <option value="{{ $et }}" {{ ($filters['entity_type'] ?? '') === $et ? 'selected' : '' }}>{{ ucfirst(str_replace('_', ' ', $et)) }}</option>
        @endforeach
      </select>

      <select name="user_id" class="form-select form-select--sm" onchange="this.form.submit()">
        <option value="">All Users</option>
        @foreach($users ?? [] as $u)
        <option value="{{ $u['id'] }}" {{ (string)($filters['user_id'] ?? '') === (string)$u['id'] ? 'selected' : '' }}>{{ $u['name'] }}</option>
        @endforeach
      </select>

      <input type="date" name="date_from" class="form-input form-input--sm"
             value="{{ $filters['date_from'] ?? '' }}" placeholder="From" title="From date"
             onchange="this.form.submit()">

      <input type="date" name="date_to" class="form-input form-input--sm"
             value="{{ $filters['date_to'] ?? '' }}" placeholder="To" title="To date"
             onchange="this.form.submit()">

      @if(!empty($filters))
      <a href="/admin/activity" class="btn btn--ghost btn--sm" title="Clear filters">
        <i data-lucide="x" class="w-4 h-4"></i>
      </a>
      @endif
    </form>
  </div>
</div>

{{-- Stats Row --}}
<div class="al-stats mb-4">
  <div class="al-stat">
    <span class="al-stat__value">{{ $total }}</span>
    <span class="al-stat__label">Total Entries</span>
  </div>
  <div class="al-stat">
    <span class="al-stat__value">{{ count($filterOptions['actions'] ?? []) }}</span>
    <span class="al-stat__label">Action Types</span>
  </div>
  <div class="al-stat">
    <span class="al-stat__value">{{ count($filterOptions['entity_types'] ?? []) }}</span>
    <span class="al-stat__label">Entity Types</span>
  </div>
</div>

{{-- Activity Table --}}
<div class="card">
  <div class="card__body p-0">
    <table class="table table--hover">
      <thead>
        <tr>
          <th style="width:170px">When</th>
          <th style="width:150px">User</th>
          <th style="width:120px">Action</th>
          <th>Entity</th>
          <th style="width:100px">Details</th>
        </tr>
      </thead>
      <tbody>
        @foreach($items as $entry)
        @php
          $ac = $actionColors[$entry['action']] ?? ['bg' => 'rgba(148,163,184,.08)', 'color' => '#94a3b8', 'icon' => 'activity'];
          $eIcon = $entityIcons[$entry['entity_type']] ?? 'box';
          $linkPattern = $entityLinks[$entry['entity_type']] ?? null;
          $entityLink = $linkPattern && $entry['entity_id']
              ? str_replace('{id}', $entry['entity_id'], $linkPattern)
              : null;
          $timeAgo = '';
          if ($entry['created_at']) {
              $diff = time() - strtotime($entry['created_at']);
              if ($diff < 60) $timeAgo = $diff . 's ago';
              elseif ($diff < 3600) $timeAgo = floor($diff/60) . 'm ago';
              elseif ($diff < 86400) $timeAgo = floor($diff/3600) . 'h ago';
              elseif ($diff < 604800) $timeAgo = floor($diff/86400) . 'd ago';
              else $timeAgo = date('M j', strtotime($entry['created_at']));
          }
        @endphp
        <tr>
          <td>
            <span class="text-sm" style="color:#94a3b8" title="{{ $entry['created_at'] }}">{{ $timeAgo }}</span>
            <div class="text-xs text-muted">{{ date('H:i:s', strtotime($entry['created_at'])) }}</div>
          </td>
          <td>
            <div class="al-user">
              <div class="al-user__avatar">{{ strtoupper(mb_substr($entry['user_name'] ?? '?', 0, 1)) }}</div>
              <div>
                <div class="text-sm" style="color:#e2e8f0">{{ $entry['user_name'] ?? 'System' }}</div>
                @if($entry['ip_address'])
                <div class="text-xs text-muted">{{ $entry['ip_address'] }}</div>
                @endif
              </div>
            </div>
          </td>
          <td>
            <span class="al-action-badge" style="background:{{ $ac['bg'] }};color:{{ $ac['color'] }}">
              <i data-lucide="{{ $ac['icon'] }}" class="w-3 h-3"></i>
              {{ ucfirst($entry['action']) }}
            </span>
          </td>
          <td>
            <div class="al-entity">
              <span class="al-entity__type">
                <i data-lucide="{{ $eIcon }}" class="w-3.5 h-3.5"></i>
                {{ ucfirst(str_replace('_', ' ', $entry['entity_type'])) }}
              </span>
              @if($entry['entity_label'])
              @if($entityLink)
              <a href="{{ $entityLink }}" class="al-entity__label">{{ $entry['entity_label'] }}</a>
              @else
              <span class="al-entity__label" style="color:#cbd5e1">{{ $entry['entity_label'] }}</span>
              @endif
              @endif
              @if($entry['entity_id'])
              <span class="text-xs text-muted">#{{ $entry['entity_id'] }}</span>
              @endif
            </div>
          </td>
          <td>
            @if(!empty($entry['details']))
            <button class="btn btn--ghost btn--xs al-details-toggle" onclick="this.closest('tr').querySelector('.al-details-row')?.classList.toggle('hidden');this.closest('tr').nextElementSibling?.classList.toggle('hidden')">
              <i data-lucide="code" class="w-3.5 h-3.5"></i>
            </button>
            @else
            <span class="text-muted text-xs">—</span>
            @endif
          </td>
        </tr>
        @if(!empty($entry['details']))
        <tr class="hidden al-details-expansion">
          <td colspan="5" style="padding:0 1rem .75rem">
            <div class="al-details-json">
              <pre><code>{{ json_encode($entry['details'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</code></pre>
            </div>
          </td>
        </tr>
        @endif
        @endforeach

        @if(empty($items))
        <tr>
          <td colspan="5">
            <div class="empty-state">
              <div class="empty-state__icon"><i data-lucide="scroll-text" class="w-12 h-12"></i></div>
              @if(!empty($filters))
              <div class="empty-state__title">No matching activity found</div>
              <p class="text-muted">Try adjusting your filters or <a href="/admin/activity">clear all</a>.</p>
              @else
              <div class="empty-state__title">No activity recorded yet</div>
              <p class="text-muted">Actions will be logged here as you use the CMS.</p>
              @endif
            </div>
          </td>
        </tr>
        @endif
      </tbody>
    </table>
  </div>
</div>

{{-- Pagination --}}
@if($pages > 1)
<div class="flex-between mt-4">
  <span class="text-sm text-muted">Page {{ $page }} of {{ $pages }} ({{ $total }} entries)</span>
  <div class="pagination">
    @if($page > 1)
    <a href="/admin/activity{{ $qs(['page' => $page - 1]) }}" class="pagination__item">&laquo;</a>
    @endif
    @for($i = max(1, $page - 3); $i <= min($pages, $page + 3); $i++)
    <a href="/admin/activity{{ $qs(['page' => $i]) }}"
       class="pagination__item {{ $i === $page ? 'active' : '' }}">{{ $i }}</a>
    @endfor
    @if($page < $pages)
    <a href="/admin/activity{{ $qs(['page' => $page + 1]) }}" class="pagination__item">&raquo;</a>
    @endif
  </div>
</div>
@endif
@endsection

@push('head')
<style>
/* ── Activity Log Styles ─────────────────────────────────────────── */
.al-filters {
  display: flex;
  align-items: center;
  gap: .6rem;
  flex-wrap: wrap;
}

.al-filters .form-select,
.al-filters .form-select--sm {
  width: auto;
  min-width: 130px;
  max-width: 180px;
  flex-shrink: 0;
}

.al-filters .form-input--sm {
  width: auto;
  min-width: 120px;
  max-width: 150px;
  flex-shrink: 0;
}

.al-stats {
  display: flex;
  gap: .75rem;
}

.al-stat {
  flex: 1;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: .25rem;
  padding: .75rem 1rem;
  background: rgba(20, 22, 38, .6);
  border: 1px solid rgba(255,255,255,.06);
  border-radius: 12px;
}

.al-stat__value {
  font-size: 1.4rem;
  font-weight: 700;
  color: #e2e8f0;
}

.al-stat__label {
  font-size: .72rem;
  color: #64748b;
  text-transform: uppercase;
  letter-spacing: .04em;
  font-weight: 600;
}

/* ── User Cell ───────────────────────────────────────────────────── */
.al-user {
  display: flex;
  align-items: center;
  gap: .5rem;
}

.al-user__avatar {
  width: 28px;
  height: 28px;
  border-radius: 8px;
  background: linear-gradient(135deg, rgba(129,140,248,.15), rgba(168,85,247,.15));
  border: 1px solid rgba(129,140,248,.2);
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: .7rem;
  font-weight: 700;
  color: #a5b4fc;
  flex-shrink: 0;
}

/* ── Action Badge ────────────────────────────────────────────────── */
.al-action-badge {
  display: inline-flex;
  align-items: center;
  gap: .3rem;
  padding: .2rem .55rem;
  border-radius: 6px;
  font-size: .72rem;
  font-weight: 600;
  letter-spacing: .01em;
}

/* ── Entity Cell ─────────────────────────────────────────────────── */
.al-entity {
  display: flex;
  align-items: center;
  gap: .5rem;
  flex-wrap: wrap;
}

.al-entity__type {
  display: inline-flex;
  align-items: center;
  gap: .25rem;
  font-size: .72rem;
  color: #64748b;
  text-transform: uppercase;
  letter-spacing: .03em;
  font-weight: 600;
}

.al-entity__label {
  font-size: .82rem;
  color: #818cf8;
  text-decoration: none;
  font-weight: 500;
}

.al-entity__label:hover {
  color: #a5b4fc;
  text-decoration: underline;
}

/* ── Details JSON ────────────────────────────────────────────────── */
.al-details-json {
  background: rgba(15,23,42,.7);
  border: 1px solid rgba(255,255,255,.06);
  border-radius: 8px;
  padding: .75rem 1rem;
  max-height: 200px;
  overflow: auto;
}

.al-details-json pre {
  margin: 0;
  white-space: pre-wrap;
  word-break: break-word;
}

.al-details-json code {
  font-size: .75rem;
  color: #94a3b8;
  font-family: 'JetBrains Mono', 'Fira Code', monospace;
}

.al-details-expansion td {
  border-bottom: 1px solid rgba(255,255,255,.03) !important;
}

/* ── Search Box (reuse from content) ─────────────────────────────── */
.search-box { position: relative; }
.search-box__icon {
  position: absolute; left: .75rem; top: 50%; transform: translateY(-50%);
  color: #64748b; pointer-events: none;
}
.search-box__input {
  width: 100%; padding: .45rem 1rem .45rem 2.25rem;
  background: rgba(255,255,255,.04); border: 1px solid rgba(255,255,255,.08);
  border-radius: 8px; color: #e2e8f0; font-size: .82rem;
  transition: border-color .2s;
}
.search-box__input:focus {
  outline: none; border-color: rgba(99,102,241,.5);
}
.search-box__input::placeholder { color: #64748b; }

.form-input--sm {
  font-size: .8rem;
  padding: .35rem .6rem;
  background: rgba(255,255,255,.04);
  border: 1px solid rgba(255,255,255,.08);
  border-radius: 8px;
  color: #e2e8f0;
  height: auto;
}

.form-input--sm:focus {
  outline: none;
  border-color: rgba(99,102,241,.5);
}

.hidden { display: none !important; }

.form-select--sm {
  font-size: .8rem;
  padding: .35rem 2rem .35rem .6rem;
  height: auto;
}
</style>
@endpush
