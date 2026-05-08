@extends('layouts.admin')

@section('title', 'Editorial Workflow')
@section('page_title', 'Editorial Workflow')

@section('breadcrumb')
<a href="/admin" class="breadcrumb__item">Dashboard</a>
<span class="breadcrumb__sep">›</span>
<span class="breadcrumb__item breadcrumb__item--active">Workflow</span>
@endsection

@section('page_actions')
<a href="/admin/workflow/settings" class="btn btn--ghost btn--sm">
  <i data-lucide="settings" class="w-4 h-4"></i> Settings
</a>
@endsection

@section('content')

{{-- Status Map --}}
@php
  $statusMap = [];
  foreach ($statuses ?? [] as $s) {
    $statusMap[$s['machine_name']] = $s;
  }
@endphp

{{-- Pending Count Banner --}}
@if($pendingCount > 0)
<div class="wf-banner mb-4">
  <div class="wf-banner__icon"><i data-lucide="inbox" class="w-5 h-5"></i></div>
  <div>
    <strong>{{ $pendingCount }} item(s)</strong> awaiting editorial review
  </div>
</div>
@endif

{{-- Filters --}}
<div class="card mb-4">
  <div class="card__body" style="padding:.5rem .75rem;">
    <div class="wf-filters">
      <a href="/admin/workflow" class="btn btn--sm {{ !$activeType ? 'btn--primary' : 'btn--ghost' }}">All Types</a>
      @foreach($types as $ct)
      <a href="/admin/workflow?type={{ $ct->type_id }}" class="btn btn--sm {{ $activeType === $ct->type_id ? 'btn--primary' : 'btn--ghost' }}">
        {{ $ct->icon ?? '📄' }} {{ $ct->label }}
      </a>
      @endforeach
    </div>
  </div>
</div>

{{-- Queue Table --}}
<div class="card">
  <div class="card__header">
    <h3 class="card__title"><i data-lucide="git-pull-request" class="w-4 h-4"></i> Review Queue</h3>
    <span class="text-xs text-muted">{{ $queue['total'] ?? 0 }} item(s)</span>
  </div>
  <div class="card__body p-0">
    @if(!empty($queue['items']))
    <table class="table table--hover">
      <thead>
        <tr>
          <th>Title</th>
          <th>Type</th>
          <th>Status</th>
          <th>Author</th>
          <th>Updated</th>
          <th class="table__actions">Actions</th>
        </tr>
      </thead>
      <tbody>
        @foreach($queue['items'] as $item)
        @php
          $st = $statusMap[$item['status']] ?? null;
          $stColor = $st ? $st['color'] : '#94a3b8';
          $stLabel = $st ? $st['label'] : ucfirst($item['status']);
          $stIcon  = $st ? $st['icon'] : 'circle';
        @endphp
        <tr data-node-id="{{ $item['id'] }}">
          <td>
            <a href="/admin/content/{{ $item['id'] }}/edit" class="wf-node-title">{{ $item['title'] }}</a>
          </td>
          <td><span class="text-xs text-muted">{{ $item['content_type'] }}</span></td>
          <td>
            <span class="wf-status-badge" style="--wf-color:{{ $stColor }}">
              <i data-lucide="{{ $stIcon }}" class="w-3 h-3"></i> {{ $stLabel }}
            </span>
          </td>
          <td><span class="text-xs">{{ $item['author_name'] ?? '—' }}</span></td>
          <td><span class="text-xs text-muted">{{ date('M j, H:i', strtotime($item['updated_at'])) }}</span></td>
          <td class="table__actions">
            <div class="wf-actions">
              @if($item['status'] === 'needs_review')
              <button class="btn btn--xs btn--ghost" style="color:#3b82f6"
                      onclick="wfAction({{ $item['id'] }}, 'claim')">
                <i data-lucide="user-check" class="w-3 h-3"></i> Claim
              </button>
              @endif
              @if($item['status'] === 'in_review' || $item['status'] === 'needs_review')
              <button class="btn btn--xs btn--ghost" style="color:#22c55e"
                      onclick="wfAction({{ $item['id'] }}, 'approve')">
                <i data-lucide="check" class="w-3 h-3"></i> Approve
              </button>
              <button class="btn btn--xs btn--ghost" style="color:#f87171"
                      onclick="wfReject({{ $item['id'] }})">
                <i data-lucide="x" class="w-3 h-3"></i> Reject
              </button>
              @endif
              <button class="btn btn--xs btn--ghost"
                      onclick="wfHistory({{ $item['id'] }})">
                <i data-lucide="history" class="w-3 h-3"></i>
              </button>
            </div>
          </td>
        </tr>
        @endforeach
      </tbody>
    </table>

    {{-- Pagination --}}
    @if(($queue['pages'] ?? 0) > 1)
    <div class="card__body" style="display:flex;justify-content:center;padding:.5rem">
      <div class="pagination">
        @for($p = 1; $p <= $queue['pages']; $p++)
        <a href="/admin/workflow?page={{ $p }}{{ $activeType ? '&type=' . $activeType : '' }}"
           class="pagination__item {{ $page === $p ? 'active' : '' }}">{{ $p }}</a>
        @endfor
      </div>
    </div>
    @endif

    @else
    <div class="empty-state">
      <div class="empty-state__icon"><i data-lucide="check-circle" class="w-12 h-12" style="color:#22c55e"></i></div>
      <div class="empty-state__title">All clear!</div>
      <p class="text-muted">No content items are pending review.</p>
    </div>
    @endif
  </div>
</div>

{{-- History Modal --}}
<div class="wf-modal" id="wf-history-modal" hidden>
  <div class="wf-modal__backdrop" onclick="document.getElementById('wf-history-modal').hidden=true"></div>
  <div class="wf-modal__content">
    <div class="wf-modal__header">
      <h3>Transition History</h3>
      <button onclick="document.getElementById('wf-history-modal').hidden=true" class="btn btn--ghost btn--xs">&times;</button>
    </div>
    <div class="wf-modal__body" id="wf-history-body">
      <div class="text-center text-muted">Loading…</div>
    </div>
  </div>
</div>

@endsection

@push('scripts')
<script>
async function wfAction(nodeId, action) {
  try {
    const resp = await CMS.fetch(`/admin/workflow/${nodeId}/${action}`, { method: 'POST', body: '{}' });
    const data = await resp.json();
    if (data.success) {
      location.reload();
    } else {
      alert(data.error || 'Action failed');
    }
  } catch (err) {
    alert('Error: ' + (err.message || 'Network error'));
  }
}

function wfReject(nodeId) {
  const comment = prompt('Rejection reason (optional):');
  if (comment === null) return; // cancelled
  CMS.fetch(`/admin/workflow/${nodeId}/reject`, {
    method: 'POST',
    body: JSON.stringify({ comment }),
  }).then(r => r.json()).then(data => {
    if (data.success) location.reload();
    else alert(data.error || 'Reject failed');
  }).catch(err => alert('Error: ' + err.message));
}

async function wfHistory(nodeId) {
  const modal = document.getElementById('wf-history-modal');
  const body = document.getElementById('wf-history-body');
  modal.hidden = false;
  body.innerHTML = '<div class="text-center text-muted">Loading…</div>';

  try {
    const resp = await CMS.fetch(`/admin/workflow/${nodeId}/history`);
    const data = await resp.json();
    if (!data.history || data.history.length === 0) {
      body.innerHTML = '<div class="text-center text-muted">No transitions recorded yet.</div>';
      return;
    }

    let html = `<div class="wf-history-list">`;
    for (const t of data.history) {
      html += `
        <div class="wf-history-item">
          <div class="wf-history-item__transition">
            <span class="wf-status-badge wf-status-badge--sm">${t.from_status}</span>
            <i data-lucide="arrow-right" class="w-3 h-3"></i>
            <span class="wf-status-badge wf-status-badge--sm">${t.to_status}</span>
          </div>
          <div class="wf-history-item__meta">
            <span>${t.user_name || 'System'}</span>
            <span class="text-muted">· ${new Date(t.created_at).toLocaleString()}</span>
          </div>
          ${t.comment ? `<div class="wf-history-item__comment">${t.comment}</div>` : ''}
        </div>`;
    }
    html += '</div>';
    body.innerHTML = html;
    if (window.lucide) lucide.createIcons({ nodes: [body] });
  } catch (err) {
    body.innerHTML = '<div class="text-center text-danger">Failed to load history.</div>';
  }
}
</script>
@endpush

@push('head')
<style>
/* ── Workflow Styles ───────────────────────────────────────────────── */
.wf-banner {
  display: flex;
  align-items: center;
  gap: .75rem;
  padding: .75rem 1rem;
  background: linear-gradient(135deg, rgba(249,115,22,.08), rgba(251,191,36,.05));
  border: 1px solid rgba(249,115,22,.2);
  border-radius: 12px;
  color: #fbbf24;
  font-size: .85rem;
}

.wf-banner__icon { flex-shrink: 0; }

.wf-filters {
  display: flex;
  gap: .35rem;
  flex-wrap: wrap;
}

.wf-node-title {
  font-weight: 600;
  color: #e2e8f0;
  text-decoration: none;
}
.wf-node-title:hover { color: #818cf8; }

.wf-status-badge {
  display: inline-flex;
  align-items: center;
  gap: .25rem;
  padding: .15rem .5rem;
  border-radius: 6px;
  font-size: .7rem;
  font-weight: 600;
  background: color-mix(in srgb, var(--wf-color, #94a3b8) 15%, transparent);
  color: var(--wf-color, #94a3b8);
  white-space: nowrap;
}

.wf-status-badge--sm {
  padding: .1rem .35rem;
  font-size: .65rem;
}

.wf-actions {
  display: flex;
  gap: .25rem;
}

/* ── Modal ─────────────────────────────────────────────────────────── */
.wf-modal {
  position: fixed;
  inset: 0;
  z-index: 1000;
  display: flex;
  align-items: center;
  justify-content: center;
}

.wf-modal__backdrop {
  position: absolute;
  inset: 0;
  background: rgba(0,0,0,.6);
}

.wf-modal__content {
  position: relative;
  width: 560px;
  max-height: 80vh;
  background: #1e2035;
  border: 1px solid rgba(255,255,255,.06);
  border-radius: 16px;
  overflow: hidden;
  display: flex;
  flex-direction: column;
}

.wf-modal__header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: .75rem 1rem;
  border-bottom: 1px solid rgba(255,255,255,.06);
}

.wf-modal__header h3 {
  font-size: .9rem;
  font-weight: 600;
  color: #e2e8f0;
  margin: 0;
}

.wf-modal__body {
  padding: 1rem;
  overflow-y: auto;
  flex: 1;
}

/* ── History List ──────────────────────────────────────────────────── */
.wf-history-list {
  display: flex;
  flex-direction: column;
  gap: .75rem;
}

.wf-history-item {
  padding: .5rem .75rem;
  background: rgba(20,22,38,.5);
  border: 1px solid rgba(255,255,255,.04);
  border-radius: 8px;
}

.wf-history-item__transition {
  display: flex;
  align-items: center;
  gap: .4rem;
  margin-bottom: .25rem;
}

.wf-history-item__meta {
  font-size: .72rem;
  color: #94a3b8;
}

.wf-history-item__comment {
  margin-top: .25rem;
  font-size: .78rem;
  color: #cbd5e1;
  font-style: italic;
  padding-left: .5rem;
  border-left: 2px solid rgba(255,255,255,.06);
}
</style>
@endpush
