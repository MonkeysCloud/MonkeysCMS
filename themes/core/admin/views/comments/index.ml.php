@extends('layouts.admin')

@section('title', 'Comments')
@section('page_title', 'Comments')

@section('breadcrumb')
<a href="/admin" class="breadcrumb__item">Dashboard</a>
<span class="breadcrumb__sep">›</span>
<span class="breadcrumb__item breadcrumb__item--active">Comments</span>
@endsection

@section('content')
@php
  $qs = function(array $overrides = []) use ($activeStatus, $search, $nodeId) {
      $p = array_filter(array_merge([
          'status' => $activeStatus !== 'all' ? $activeStatus : null,
          'search' => $search !== '' ? $search : null,
          'node_id' => $nodeId,
      ], $overrides), fn($v) => $v !== null && $v !== '');
      return $p ? '?' . http_build_query($p) : '';
  };
@endphp

{{-- Status Tabs --}}
<div class="cm-tabs mb-3">
  @php
    $tabs = [
        'all'      => ['label' => 'All',      'icon' => 'message-circle'],
        'pending'  => ['label' => 'Pending',   'icon' => 'clock'],
        'approved' => ['label' => 'Approved',  'icon' => 'check-circle'],
        'spam'     => ['label' => 'Spam',      'icon' => 'shield-alert'],
        'trashed'  => ['label' => 'Trashed',   'icon' => 'trash-2'],
    ];
  @endphp
  @foreach($tabs as $key => $tab)
  <a href="/admin/comments{{ $qs(['status' => $key !== 'all' ? $key : null, 'page' => null]) }}"
     class="cm-tab {{ $activeStatus === $key ? 'cm-tab--active' : '' }}">
    <i data-lucide="{{ $tab['icon'] }}" class="w-3.5 h-3.5"></i>
    {{ $tab['label'] }}
    @if(($statusCounts[$key] ?? 0) > 0)
    <span class="cm-tab__count">{{ $statusCounts[$key] }}</span>
    @endif
  </a>
  @endforeach

  @if(($statusCounts['spam'] ?? 0) > 0)
  <span style="flex:1"></span>
  <form method="POST" action="/admin/comments/empty-spam" data-confirm="Delete all spam comments permanently?">
    <button type="submit" class="btn btn--ghost btn--sm" style="color:#f87171">
      <i data-lucide="trash-2" class="w-3.5 h-3.5"></i> Empty Spam
    </button>
  </form>
  @endif
</div>

{{-- Search Bar --}}
<div class="card mb-4">
  <div class="card__body" style="padding:0.6rem 1rem;">
    <form action="/admin/comments" method="GET" class="cm-search-row">
      @if($activeStatus !== 'all')
      <input type="hidden" name="status" value="{{ $activeStatus }}">
      @endif
      <div class="search-box" style="flex:1;min-width:200px">
        <i data-lucide="search" class="search-box__icon w-4 h-4"></i>
        <input type="text" name="search" class="search-box__input" placeholder="Search by author, email or content…"
               value="{{ $search }}" autocomplete="off">
      </div>
      @if($search !== '' || $nodeId)
      <a href="/admin/comments{{ $qs(['search' => null, 'node_id' => null, 'page' => null]) }}" class="btn btn--ghost btn--sm">
        <i data-lucide="x" class="w-4 h-4"></i> Clear
      </a>
      @endif
    </form>
  </div>
</div>

{{-- Bulk Actions Bar --}}
<div class="bulk-bar mb-3" id="cm-bulk-bar" style="display:none">
  <form action="/admin/comments/bulk" method="POST" id="cm-bulk-form">
    <span class="bulk-bar__count"><span id="cm-selected-count">0</span> selected</span>
    <button type="submit" name="action" value="approve" class="btn btn--xs btn--ghost" style="color:#34d399">
      <i data-lucide="check-circle" class="w-3.5 h-3.5"></i> Approve
    </button>
    <button type="submit" name="action" value="spam" class="btn btn--xs btn--ghost" style="color:#fbbf24">
      <i data-lucide="shield-alert" class="w-3.5 h-3.5"></i> Spam
    </button>
    <button type="submit" name="action" value="trash" class="btn btn--xs btn--ghost" style="color:#f87171">
      <i data-lucide="trash-2" class="w-3.5 h-3.5"></i> Trash
    </button>
    <div id="cm-bulk-ids"></div>
  </form>
</div>

{{-- Comments Table --}}
<div class="card">
  <div class="card__body p-0">
    <table class="table table--hover">
      <thead>
        <tr>
          <th class="table__check"><input type="checkbox" id="cm-check-all"></th>
          <th>Author</th>
          <th>Comment</th>
          <th style="width:180px">In Response To</th>
          <th style="width:100px">Status</th>
          <th style="width:100px">Date</th>
          <th style="width:150px">Actions</th>
        </tr>
      </thead>
      <tbody>
        @foreach($items as $comment)
        <tr>
          <td class="table__check">
            <input type="checkbox" value="{{ $comment->id }}" class="cm-check">
          </td>
          <td>
            <div class="cm-author">
              <img src="{{ $comment->gravatar }}" alt="" class="cm-author__avatar" width="32" height="32">
              <div>
                <div class="cm-author__name">
                  {{ $comment->author_name }}
                  @if($comment->author_id)
                  <span class="cm-author__badge">Member</span>
                  @endif
                </div>
                <div class="text-xs text-muted">{{ $comment->author_email }}</div>
              </div>
            </div>
          </td>
          <td>
            <div class="cm-body">{{ mb_substr(strip_tags($comment->body), 0, 120) }}{{ mb_strlen($comment->body) > 120 ? '…' : '' }}</div>
            @if($comment->parent_id)
            <span class="text-xs text-muted"><i data-lucide="corner-down-right" class="w-3 h-3"></i> Reply</span>
            @endif
          </td>
          <td>
            @if($comment->nodeTitle)
            <a href="/admin/content/{{ $comment->node_id }}/edit" class="cm-node-link">
              <i data-lucide="file-text" class="w-3 h-3"></i>
              {{ mb_substr($comment->nodeTitle, 0, 30) }}{{ mb_strlen($comment->nodeTitle) > 30 ? '…' : '' }}
            </a>
            @else
            <span class="text-muted">#{{ $comment->node_id }}</span>
            @endif
          </td>
          <td>
            <span class="badge {{ $comment->statusBadge }}">{{ ucfirst($comment->status) }}</span>
          </td>
          <td class="text-sm text-muted" title="{{ $comment->created_at?->format('Y-m-d H:i:s') }}">
            {{ $comment->timeAgo }}
          </td>
          <td>
            <div class="cm-actions">
              @if($comment->status !== 'approved')
              <form method="POST" action="/admin/comments/{{ $comment->id }}/approve" class="inline-form">
                <button type="submit" class="btn btn--xs btn--ghost" style="color:#34d399" title="Approve">
                  <i data-lucide="check" class="w-3.5 h-3.5"></i>
                </button>
              </form>
              @endif
              @if($comment->status !== 'spam')
              <form method="POST" action="/admin/comments/{{ $comment->id }}/spam" class="inline-form">
                <button type="submit" class="btn btn--xs btn--ghost" style="color:#fbbf24" title="Mark as Spam">
                  <i data-lucide="shield-alert" class="w-3.5 h-3.5"></i>
                </button>
              </form>
              @endif
              @if($comment->status !== 'trashed')
              <form method="POST" action="/admin/comments/{{ $comment->id }}/trash" class="inline-form">
                <button type="submit" class="btn btn--xs btn--ghost" style="color:#fb923c" title="Trash">
                  <i data-lucide="archive" class="w-3.5 h-3.5"></i>
                </button>
              </form>
              @endif
              <form method="POST" action="/admin/comments/{{ $comment->id }}/delete" class="inline-form"
                    data-confirm="Permanently delete this comment?">
                <button type="submit" class="btn btn--xs btn--ghost" style="color:#f87171" title="Delete permanently">
                  <i data-lucide="x-circle" class="w-3.5 h-3.5"></i>
                </button>
              </form>
            </div>
          </td>
        </tr>
        @endforeach

        @if(empty($items))
        <tr>
          <td colspan="7">
            <div class="empty-state">
              <div class="empty-state__icon"><i data-lucide="message-circle" class="w-12 h-12"></i></div>
              @if($search !== '')
              <div class="empty-state__title">No comments matching "{{ $search }}"</div>
              <p class="text-muted">Try a different search or <a href="/admin/comments">clear filters</a>.</p>
              @elseif($activeStatus !== 'all')
              <div class="empty-state__title">No {{ $activeStatus }} comments</div>
              <p class="text-muted"><a href="/admin/comments">View all comments</a></p>
              @else
              <div class="empty-state__title">No comments yet</div>
              <p class="text-muted">Comments will appear here when visitors respond to your content.</p>
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
  <span class="text-sm text-muted">Page {{ $page }} of {{ $pages }} ({{ $total }} comments)</span>
  <div class="pagination">
    @if($page > 1)
    <a href="/admin/comments{{ $qs(['page' => $page - 1]) }}" class="pagination__item">&laquo;</a>
    @endif
    @for($i = max(1, $page - 3); $i <= min($pages, $page + 3); $i++)
    <a href="/admin/comments{{ $qs(['page' => $i]) }}"
       class="pagination__item {{ $i === $page ? 'active' : '' }}">{{ $i }}</a>
    @endfor
    @if($page < $pages)
    <a href="/admin/comments{{ $qs(['page' => $page + 1]) }}" class="pagination__item">&raquo;</a>
    @endif
  </div>
</div>
@endif
@endsection

@push('head')
<style>
/* ── Comment Moderation Styles ───────────────────────────────────── */
.cm-tabs {
  display: flex;
  gap: .25rem;
  flex-wrap: wrap;
  align-items: center;
}

.cm-tab {
  display: inline-flex;
  align-items: center;
  gap: .35rem;
  padding: .4rem .75rem;
  font-size: .8rem;
  color: #94a3b8;
  border-radius: 8px;
  text-decoration: none;
  transition: all .15s;
  border: 1px solid transparent;
}

.cm-tab:hover { color: #e2e8f0; background: rgba(255,255,255,.04); }

.cm-tab--active {
  color: #818cf8;
  background: rgba(99,102,241,.08);
  border-color: rgba(99,102,241,.15);
}

.cm-tab__count {
  background: rgba(255,255,255,.08);
  padding: .1rem .4rem;
  border-radius: 4px;
  font-size: .7rem;
  font-weight: 600;
}

.cm-tab--active .cm-tab__count {
  background: rgba(99,102,241,.15);
  color: #a5b4fc;
}

.cm-search-row {
  display: flex;
  align-items: center;
  gap: .6rem;
}

/* ── Author Cell ─────────────────────────────────────────────────── */
.cm-author {
  display: flex;
  align-items: center;
  gap: .5rem;
}

.cm-author__avatar {
  border-radius: 8px;
  flex-shrink: 0;
  background: rgba(255,255,255,.05);
}

.cm-author__name {
  font-size: .82rem;
  color: #e2e8f0;
  font-weight: 500;
}

.cm-author__badge {
  display: inline-flex;
  padding: .05rem .35rem;
  font-size: .6rem;
  background: rgba(99,102,241,.1);
  color: #a5b4fc;
  border-radius: 4px;
  font-weight: 600;
  margin-left: .3rem;
  vertical-align: middle;
}

/* ── Comment Body ────────────────────────────────────────────────── */
.cm-body {
  font-size: .82rem;
  color: #cbd5e1;
  line-height: 1.45;
  max-width: 350px;
}

/* ── Node Link ───────────────────────────────────────────────────── */
.cm-node-link {
  display: inline-flex;
  align-items: center;
  gap: .25rem;
  font-size: .78rem;
  color: #818cf8;
  text-decoration: none;
}

.cm-node-link:hover {
  color: #a5b4fc;
  text-decoration: underline;
}

/* ── Actions ─────────────────────────────────────────────────────── */
.cm-actions {
  display: flex;
  gap: .2rem;
}

/* ── Reusable from content ───────────────────────────────────────── */
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
.search-box__input:focus { outline: none; border-color: rgba(99,102,241,.5); }
.search-box__input::placeholder { color: #64748b; }

.bulk-bar {
  background: rgba(99,102,241,.08); border: 1px solid rgba(99,102,241,.15);
  border-radius: 10px; padding: .5rem 1rem;
  display: flex; align-items: center; gap: .75rem;
}
.bulk-bar__count {
  font-size: .8rem; font-weight: 600; color: #818cf8; margin-right: .5rem;
}
</style>
@endpush

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
  const checkAll = document.getElementById('cm-check-all');
  const bulkBar = document.getElementById('cm-bulk-bar');
  const bulkIds = document.getElementById('cm-bulk-ids');
  const selectedCount = document.getElementById('cm-selected-count');

  function updateBulkBar() {
    const checked = document.querySelectorAll('.cm-check:checked');
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
      document.querySelectorAll('.cm-check').forEach(cb => { cb.checked = checkAll.checked; });
      updateBulkBar();
    });
  }

  document.querySelectorAll('.cm-check').forEach(cb => {
    cb.addEventListener('change', updateBulkBar);
  });
});
</script>
@endpush
