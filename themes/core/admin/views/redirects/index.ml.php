@extends('layouts.admin')

@section('title', 'Redirects')
@section('page_title', 'URL Redirects')

@section('breadcrumb')
<a href="/admin" class="breadcrumb__item">Dashboard</a>
<span class="breadcrumb__sep">›</span>
<span class="breadcrumb__item breadcrumb__item--active">Redirects</span>
@endsection

@section('toolbar_actions')
<button type="button" class="btn btn--primary btn--sm" onclick="document.getElementById('add-modal').showModal()">
  <i data-lucide="plus" class="w-4 h-4"></i> Add Redirect
</button>
@endsection

@section('content')

@if(!empty($flashSuccess))
<div class="alert alert--success mb-4">
  <i data-lucide="check-circle" class="w-4 h-4"></i>
  {{ $flashSuccess }}
</div>
@endif

@if(!empty($flashError))
<div class="alert alert--danger mb-4">
  <i data-lucide="alert-circle" class="w-4 h-4"></i>
  {{ $flashError }}
</div>
@endif

{{-- Search --}}
<div class="mb-4">
  <form method="GET" action="/admin/redirects" class="flex gap-2">
    <input type="text" name="search" class="form-input" placeholder="Search redirects..." value="{{ $search ?? '' }}" style="max-width:320px">
    <button type="submit" class="btn btn--ghost btn--sm">Search</button>
    @if(!empty($search))
    <a href="/admin/redirects" class="btn btn--ghost btn--sm">Clear</a>
    @endif
  </form>
</div>

{{-- Redirects Table --}}
<div class="redirects-table-wrap">
  <table class="admin-table">
    <thead>
      <tr>
        <th>Source Path</th>
        <th>Target Path</th>
        <th>Code</th>
        <th>Hits</th>
        <th>Last Hit</th>
        <th>Created</th>
        <th style="width:80px">Actions</th>
      </tr>
    </thead>
    <tbody>
      @if(empty($items))
      <tr>
        <td colspan="7" style="text-align:center;padding:2rem;color:#64748b">
          <i data-lucide="corner-up-right" class="w-5 h-5" style="margin:0 auto .5rem;display:block;opacity:.5"></i>
          No redirects configured yet.
        </td>
      </tr>
      @endif
      @foreach($items as $item)
      <tr>
        <td><code style="font-size:.8rem;color:#818cf8">{{ $item['source_path'] }}</code></td>
        <td><code style="font-size:.8rem;color:#34d399">{{ $item['target_path'] }}</code></td>
        <td>
          <span class="badge {{ $item['status_code'] == 301 ? 'badge--info' : 'badge--warning' }}">
            {{ $item['status_code'] }}
          </span>
        </td>
        <td style="color:#94a3b8">{{ number_format((int)$item['hits']) }}</td>
        <td style="color:#64748b;font-size:.8rem">{{ $item['last_hit_at'] ?? '—' }}</td>
        <td style="color:#64748b;font-size:.8rem">{{ $item['created_at'] ?? '' }}</td>
        <td>
          <form method="POST" action="/admin/redirects/{{ $item['id'] }}/delete" onsubmit="return confirm('Delete this redirect?')">
            <button type="submit" class="btn btn--ghost btn--sm" style="color:#f87171">
              <i data-lucide="trash-2" class="w-3.5 h-3.5"></i>
            </button>
          </form>
        </td>
      </tr>
      @endforeach
    </tbody>
  </table>
</div>

{{-- Pagination --}}
@if($totalPages > 1)
<div class="pagination mt-4">
  @for($p = 1; $p <= $totalPages; $p++)
  <a href="/admin/redirects?page={{ $p }}&search={{ urlencode($search ?? '') }}"
     class="pagination__item {{ $p === $page ? 'pagination__item--active' : '' }}">{{ $p }}</a>
  @endfor
</div>
@endif

{{-- Add Modal --}}
<dialog id="add-modal" class="modal">
  <div class="modal__content" style="min-width:480px">
    <div class="modal__header">
      <h3 class="modal__title">Add Redirect</h3>
      <button type="button" class="modal__close" onclick="this.closest('dialog').close()">×</button>
    </div>
    <form method="POST" action="/admin/redirects">
      <div class="modal__body">
        <div class="form-group">
          <label class="form-label">Source Path</label>
          <input type="text" name="source_path" class="form-input" placeholder="/old-page" required>
          <small class="text-muted">The old URL path that should redirect</small>
        </div>
        <div class="form-group">
          <label class="form-label">Target Path</label>
          <input type="text" name="target_path" class="form-input" placeholder="/new-page" required>
          <small class="text-muted">The destination URL path</small>
        </div>
        <div class="form-group">
          <label class="form-label">Status Code</label>
          <select name="status_code" class="form-select">
            <option value="301">301 — Permanent Redirect</option>
            <option value="302">302 — Temporary Redirect</option>
          </select>
        </div>
      </div>
      <div class="modal__footer">
        <button type="button" class="btn btn--ghost" onclick="this.closest('dialog').close()">Cancel</button>
        <button type="submit" class="btn btn--primary">Create Redirect</button>
      </div>
    </form>
  </div>
</dialog>
@endsection

@push('styles')
<style>
.redirects-table-wrap {
  background: rgba(20, 22, 38, 0.6);
  border: 1px solid rgba(255,255,255,0.06);
  border-radius: 16px;
  overflow: hidden;
}
.redirects-table-wrap .admin-table { width: 100%; border-collapse: collapse; font-size: .8125rem; }
.redirects-table-wrap .admin-table th {
  text-align: left; padding: .75rem 1rem; font-weight: 600; font-size: .72rem;
  text-transform: uppercase; letter-spacing: .05em; color: #64748b;
  border-bottom: 1px solid rgba(255,255,255,0.06); background: rgba(255,255,255,0.02);
}
.redirects-table-wrap .admin-table td {
  padding: .75rem 1rem; border-bottom: 1px solid rgba(255,255,255,0.03);
  color: #cbd5e1; vertical-align: middle;
}
.redirects-table-wrap .admin-table tr:last-child td { border-bottom: none; }
.redirects-table-wrap .admin-table tr:hover td { background: rgba(255,255,255,0.015); }

/* Modal */
.modal { border: none; border-radius: 16px; background: #141626; color: #e2e8f0; padding: 0; box-shadow: 0 25px 50px rgba(0,0,0,.5); }
.modal::backdrop { background: rgba(0,0,0,.6); }
.modal__content { display: flex; flex-direction: column; }
.modal__header { display: flex; align-items: center; justify-content: space-between; padding: 1.25rem 1.5rem; border-bottom: 1px solid rgba(255,255,255,0.06); }
.modal__title { margin: 0; font-size: 1rem; font-weight: 600; }
.modal__close { background: none; border: none; color: #64748b; font-size: 1.5rem; cursor: pointer; line-height: 1; }
.modal__close:hover { color: #e2e8f0; }
.modal__body { padding: 1.5rem; }
.modal__footer { padding: 1rem 1.5rem; border-top: 1px solid rgba(255,255,255,0.06); display: flex; justify-content: flex-end; gap: .5rem; }
</style>
@endpush
