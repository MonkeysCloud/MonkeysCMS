@extends('layouts.admin')

@section('title', 'Trash')
@section('page_title', 'Trash')

@section('breadcrumb')
<a href="/admin" class="breadcrumb__item">Dashboard</a>
<span class="breadcrumb__sep">›</span>
<a href="/admin/content" class="breadcrumb__item">Content</a>
<span class="breadcrumb__sep">›</span>
<span class="breadcrumb__item breadcrumb__item--active">Trash</span>
@endsection

@section('toolbar_actions')
@if(!empty($nodes))
<form method="POST" action="/admin/content/empty-trash" data-confirm="Permanently delete ALL trashed content? This cannot be undone.">
  <button type="submit" class="btn btn--danger btn--sm">
    <i data-lucide="trash-2" class="w-4 h-4"></i> Empty Trash
  </button>
</form>
@endif
@endsection

@section('content')
<div class="mb-4" style="display:flex;gap:.5rem;align-items:center">
  <a href="/admin/content" class="btn btn--ghost btn--sm">← Back to Content</a>
  <span style="color:#64748b;font-size:.8rem">{{ $total }} item(s) in trash</span>
</div>

<div class="trash-table-wrap">
  <table class="admin-table">
    <thead>
      <tr>
        <th>Title</th>
        <th>Type</th>
        <th>Author</th>
        <th>Deleted</th>
        <th style="width:160px">Actions</th>
      </tr>
    </thead>
    <tbody>
      @if(empty($nodes))
      <tr>
        <td colspan="5" style="text-align:center;padding:2rem;color:#64748b">
          <i data-lucide="inbox" class="w-5 h-5" style="margin:0 auto .5rem;display:block;opacity:.5"></i>
          Trash is empty.
        </td>
      </tr>
      @endif
      @foreach($nodes as $node)
      <tr>
        <td>
          <span style="color:#e2e8f0;font-weight:500">{{ $node['title'] }}</span>
          <div style="font-size:.72rem;color:#64748b;margin-top:.15rem">{{ $node['slug'] }}</div>
        </td>
        <td><span class="badge badge--ghost" style="font-size:.7rem">{{ $node['content_type'] }}</span></td>
        <td style="color:#94a3b8;font-size:.8rem">{{ $node['author_name'] ?? '—' }}</td>
        <td style="color:#64748b;font-size:.8rem">{{ $node['deleted_at'] }}</td>
        <td>
          <div style="display:flex;gap:.35rem">
            <form method="POST" action="/admin/content/{{ $node['id'] }}/restore">
              <button type="submit" class="btn btn--ghost btn--sm" style="color:#34d399" title="Restore">
                <i data-lucide="rotate-ccw" class="w-3.5 h-3.5"></i> Restore
              </button>
            </form>
            <form method="POST" action="/admin/content/{{ $node['id'] }}/destroy" data-confirm="Permanently delete this content? This cannot be undone.">
              <button type="submit" class="btn btn--ghost btn--sm" style="color:#f87171" title="Delete permanently">
                <i data-lucide="x-circle" class="w-3.5 h-3.5"></i>
              </button>
            </form>
          </div>
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
  <a href="/admin/content/trash?page={{ $p }}"
     class="pagination__item {{ $p === $page ? 'pagination__item--active' : '' }}">{{ $p }}</a>
  @endfor
</div>
@endif
@endsection

@push('styles')
<style>
.trash-table-wrap {
  background: rgba(20, 22, 38, 0.6);
  border: 1px solid rgba(255,255,255,0.06);
  border-radius: 16px;
  overflow: hidden;
}
.trash-table-wrap .admin-table { width: 100%; border-collapse: collapse; font-size: .8125rem; }
.trash-table-wrap .admin-table th {
  text-align: left; padding: .75rem 1rem; font-weight: 600; font-size: .72rem;
  text-transform: uppercase; letter-spacing: .05em; color: #64748b;
  border-bottom: 1px solid rgba(255,255,255,0.06); background: rgba(255,255,255,0.02);
}
.trash-table-wrap .admin-table td {
  padding: .75rem 1rem; border-bottom: 1px solid rgba(255,255,255,0.03);
  color: #cbd5e1; vertical-align: middle;
}
.trash-table-wrap .admin-table tr:last-child td { border-bottom: none; }
.trash-table-wrap .admin-table tr:hover td { background: rgba(255,255,255,0.015); }
</style>
@endpush
