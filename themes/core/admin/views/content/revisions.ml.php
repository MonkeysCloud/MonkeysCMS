@extends('layouts.admin')

@section('title', 'Revisions: ' . $node->title)
@section('page_title', 'Revision History')

@section('breadcrumb')
<a href="/admin" class="breadcrumb__item">Dashboard</a>
<span class="breadcrumb__sep">›</span>
<a href="/admin/content" class="breadcrumb__item">Content</a>
<span class="breadcrumb__sep">›</span>
<a href="/admin/content/{{ $node->id }}/edit" class="breadcrumb__item">{{ $node->title }}</a>
<span class="breadcrumb__sep">›</span>
<span class="breadcrumb__item breadcrumb__item--active">Revisions</span>
@endsection

@section('page_actions')
<a href="/admin/content/{{ $node->id }}/edit" class="btn btn--ghost btn--sm">
  <i data-lucide="arrow-left" class="w-4 h-4"></i> Back to Editor
</a>
@endsection

@section('content')

{{-- Compare Form --}}
<div class="card mb-4">
  <div class="card__header">
    <h3 class="card__title"><i data-lucide="git-compare" class="w-4 h-4"></i> Compare Revisions</h3>
  </div>
  <div class="card__body" style="padding:.75rem 1rem;">
    <form action="/admin/content/{{ $node->id }}/diff" method="GET" class="rev-compare-form">
      <div class="rev-compare-form__selects">
        <div>
          <label class="text-xs text-muted">From (older)</label>
          <select name="from" class="form-select form-select--sm" id="rev-from">
            @foreach($revisions as $rev)
            @php $revTs = $rev['created_at'] instanceof DateTimeImmutable ? $rev['created_at']->getTimestamp() : strtotime($rev['created_at']); @endphp
            <option value="{{ $rev['revision'] }}">Rev {{ $rev['revision'] }} — {{ date('M j, H:i', $revTs) }} {{ $rev['author_name'] ? '(' . $rev['author_name'] . ')' : '' }}</option>
            @endforeach
            <option value="0">Current</option>
          </select>
        </div>
        <div class="rev-compare-form__arrow"><i data-lucide="arrow-right" class="w-5 h-5"></i></div>
        <div>
          <label class="text-xs text-muted">To (newer)</label>
          <select name="to" class="form-select form-select--sm" id="rev-to">
            <option value="0" selected>Current</option>
            @foreach($revisions as $rev)
            @php $revTs2 = $rev['created_at'] instanceof DateTimeImmutable ? $rev['created_at']->getTimestamp() : strtotime($rev['created_at']); @endphp
            <option value="{{ $rev['revision'] }}">Rev {{ $rev['revision'] }} — {{ date('M j, H:i', $revTs2) }} {{ $rev['author_name'] ? '(' . $rev['author_name'] . ')' : '' }}</option>
            @endforeach
          </select>
        </div>
      </div>
      <button type="submit" class="btn btn--primary btn--sm">
        <i data-lucide="diff" class="w-4 h-4"></i> Compare
      </button>
    </form>
  </div>
</div>

{{-- Revision Timeline --}}
<div class="card">
  <div class="card__header">
    <h3 class="card__title"><i data-lucide="history" class="w-4 h-4"></i> Timeline</h3>
    <span class="text-xs text-muted">{{ count($revisions) }} revision(s) · Current: Rev {{ $node->revision }}</span>
  </div>
  <div class="card__body p-0">
    @if(!empty($revisions))
    <div class="rev-timeline">
      {{-- Current state --}}
      <div class="rev-timeline__item rev-timeline__item--current">
        <div class="rev-timeline__dot rev-timeline__dot--current"></div>
        <div class="rev-timeline__content">
          <div class="rev-timeline__header">
            <span class="rev-timeline__rev">Rev {{ $node->revision }}</span>
            <span class="rev-timeline__badge rev-timeline__badge--current">Current</span>
          </div>
          <div class="rev-timeline__meta">
            @php $updTs = ($node->updated_at instanceof DateTimeImmutable) ? $node->updated_at->getTimestamp() : strtotime($node->updated_at ?? 'now'); @endphp
            <span>{{ date('M j, Y · H:i:s', $updTs) }}</span>
          </div>
        </div>
      </div>

      @foreach($revisions as $rev)
      <div class="rev-timeline__item">
        <div class="rev-timeline__dot"></div>
        <div class="rev-timeline__content">
          <div class="rev-timeline__header">
            <span class="rev-timeline__rev">Rev {{ $rev['revision'] }}</span>
            @if($rev['author_name'])
            <span class="rev-timeline__author">
              <i data-lucide="user" class="w-3 h-3"></i> {{ $rev['author_name'] }}
            </span>
            @endif
            @if($rev['message'])
            <span class="rev-timeline__message">{{ $rev['message'] }}</span>
            @endif
          </div>
          <div class="rev-timeline__meta">
            @php $tlTs = $rev['created_at'] instanceof DateTimeImmutable ? $rev['created_at']->getTimestamp() : strtotime($rev['created_at']); @endphp
            <span>{{ date('M j, Y · H:i:s', $tlTs) }}</span>
          </div>
          <div class="rev-timeline__actions">
            <a href="/admin/content/{{ $node->id }}/diff?from={{ $rev['revision'] }}&to=0"
               class="btn btn--ghost btn--xs">
              <i data-lucide="diff" class="w-3 h-3"></i> Compare to Current
            </a>
            <form action="/admin/content/{{ $node->id }}/revert/{{ $rev['revision'] }}" method="POST"
                  data-confirm="Revert to revision {{ $rev['revision'] }}? Current content will be saved as a new revision."
                  style="display:inline">
              <button type="submit" class="btn btn--ghost btn--xs" style="color:#fbbf24">
                <i data-lucide="rotate-ccw" class="w-3 h-3"></i> Revert
              </button>
            </form>
          </div>
        </div>
      </div>
      @endforeach
    </div>
    @else
    <div class="empty-state">
      <div class="empty-state__icon"><i data-lucide="history" class="w-12 h-12"></i></div>
      <div class="empty-state__title">No revisions yet</div>
      <p class="text-muted">Revisions are created automatically when you save content.</p>
    </div>
    @endif
  </div>
</div>
@endsection

@push('head')
<style>
/* ── Revisions Styles ──────────────────────────────────────────────── */
.rev-compare-form {
  display: flex;
  align-items: flex-end;
  gap: 1rem;
}

.rev-compare-form__selects {
  display: flex;
  align-items: flex-end;
  gap: .75rem;
  flex: 1;
}

.rev-compare-form__selects > div {
  flex: 1;
}

.rev-compare-form__arrow {
  color: #64748b;
  padding-bottom: .3rem;
}

/* ── Timeline ──────────────────────────────────────────────────────── */
.rev-timeline {
  padding: 1rem 1.25rem;
}

.rev-timeline__item {
  position: relative;
  padding-left: 2rem;
  padding-bottom: 1.5rem;
  border-left: 2px solid rgba(255,255,255,.06);
  margin-left: .5rem;
}

.rev-timeline__item:last-child {
  border-left-color: transparent;
  padding-bottom: 0;
}

.rev-timeline__dot {
  position: absolute;
  left: -.45rem;
  top: .2rem;
  width: .75rem;
  height: .75rem;
  border-radius: 50%;
  background: #334155;
  border: 2px solid #475569;
}

.rev-timeline__dot--current {
  background: #818cf8;
  border-color: #a5b4fc;
  box-shadow: 0 0 8px rgba(129,140,248,.4);
}

.rev-timeline__content {
  display: flex;
  flex-direction: column;
  gap: .25rem;
}

.rev-timeline__header {
  display: flex;
  align-items: center;
  gap: .5rem;
  flex-wrap: wrap;
}

.rev-timeline__rev {
  font-weight: 600;
  font-size: .85rem;
  color: #e2e8f0;
}

.rev-timeline__badge--current {
  font-size: .65rem;
  padding: .1rem .4rem;
  border-radius: 4px;
  background: rgba(129,140,248,.15);
  color: #818cf8;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: .03em;
}

.rev-timeline__author {
  display: inline-flex;
  align-items: center;
  gap: .2rem;
  font-size: .75rem;
  color: #94a3b8;
}

.rev-timeline__message {
  font-size: .78rem;
  color: #94a3b8;
  font-style: italic;
}

.rev-timeline__meta {
  font-size: .72rem;
  color: #64748b;
}

.rev-timeline__actions {
  display: flex;
  gap: .4rem;
  margin-top: .25rem;
}

.card__header {
  display: flex;
  align-items: center;
  justify-content: space-between;
}

.card__title {
  display: flex;
  align-items: center;
  gap: .4rem;
  font-size: .9rem;
  font-weight: 600;
  color: #e2e8f0;
}
</style>
@endpush
