@extends('layouts.admin')

@section('title', 'Compare: ' . $node->title)
@section('page_title', 'Compare Revisions')

@section('breadcrumb')
<a href="/admin" class="breadcrumb__item">Dashboard</a>
<span class="breadcrumb__sep">›</span>
<a href="/admin/content" class="breadcrumb__item">Content</a>
<span class="breadcrumb__sep">›</span>
<a href="/admin/content/{{ $node->id }}/edit" class="breadcrumb__item">{{ $node->title }}</a>
<span class="breadcrumb__sep">›</span>
<a href="/admin/content/{{ $node->id }}/revisions" class="breadcrumb__item">Revisions</a>
<span class="breadcrumb__sep">›</span>
<span class="breadcrumb__item breadcrumb__item--active">Diff</span>
@endsection

@section('page_actions')
<a href="/admin/content/{{ $node->id }}/revisions" class="btn btn--ghost btn--sm">
  <i data-lucide="arrow-left" class="w-4 h-4"></i> Back to Revisions
</a>
@if($from > 0)
<form action="/admin/content/{{ $node->id }}/revert/{{ $from }}" method="POST"
      data-confirm="Revert to revision {{ $from }}? Current content will be saved as a new revision."
      style="display:inline">
  <button type="submit" class="btn btn--ghost btn--sm" style="color:#fbbf24">
    <i data-lucide="rotate-ccw" class="w-4 h-4"></i> Revert to Rev {{ $from }}
  </button>
</form>
@endif
@endsection

@section('content')
@php
  $fromLabel = $from > 0 ? ('Rev ' . $from . ($revFrom ? ' — ' . date('M j, H:i', strtotime($revFrom['created_at'])) : '')) : 'Current';
  $toLabel   = $to > 0   ? ('Rev ' . $to . ($revTo ? ' — ' . date('M j, H:i', strtotime($revTo['created_at'])) : '')) : 'Current';
@endphp

{{-- Diff Header --}}
<div class="diff-header mb-4">
  <div class="diff-header__side diff-header__side--from">
    <i data-lucide="minus-circle" class="w-4 h-4"></i>
    <span>{{ $fromLabel }}</span>
    @if($revFrom && $revFrom['author_name'])
    <span class="text-xs text-muted">by {{ $revFrom['author_name'] }}</span>
    @endif
  </div>
  <div class="diff-header__arrow"><i data-lucide="arrow-right" class="w-5 h-5"></i></div>
  <div class="diff-header__side diff-header__side--to">
    <i data-lucide="plus-circle" class="w-4 h-4"></i>
    <span>{{ $toLabel }}</span>
    @if($revTo && $revTo['author_name'])
    <span class="text-xs text-muted">by {{ $revTo['author_name'] }}</span>
    @endif
  </div>
</div>

@if(empty($diffs))
<div class="card">
  <div class="card__body">
    <div class="empty-state">
      <div class="empty-state__icon"><i data-lucide="check-circle" class="w-12 h-12" style="color:#34d399"></i></div>
      <div class="empty-state__title">No differences</div>
      <p class="text-muted">These two revisions are identical.</p>
    </div>
  </div>
</div>
@else

{{-- Summary --}}
<div class="diff-summary mb-4">
  <span class="diff-summary__count">{{ count($diffs) }} field(s) changed</span>
  <div class="diff-summary__fields">
    @foreach($diffs as $d)
    <a href="#diff-{{ $d['field'] }}" class="diff-summary__chip">{{ $d['label'] }}</a>
    @endforeach
  </div>
</div>

{{-- Field Diffs --}}
@foreach($diffs as $d)
<div class="card mb-3" id="diff-{{ $d['field'] }}">
  <div class="card__header">
    <h3 class="card__title">
      <i data-lucide="text" class="w-4 h-4"></i> {{ $d['label'] }}
      <span class="text-xs text-muted">({{ $d['field'] }})</span>
    </h3>
  </div>
  <div class="card__body p-0">
    <div class="diff-block">
      @foreach($d['diff'] as $line)
      @if($line['type'] === 'same')
      <div class="diff-line diff-line--same"><span class="diff-line__prefix">&nbsp;</span><span class="diff-line__content">{{ $line['content'] }}</span></div>
      @elseif($line['type'] === 'remove')
      <div class="diff-line diff-line--remove"><span class="diff-line__prefix">−</span><span class="diff-line__content">{{ $line['content'] }}</span></div>
      @elseif($line['type'] === 'add')
      <div class="diff-line diff-line--add"><span class="diff-line__prefix">+</span><span class="diff-line__content">{{ $line['content'] }}</span></div>
      @endif
      @endforeach
    </div>
  </div>
</div>
@endforeach

@endif
@endsection

@push('head')
<style>
/* ── Diff Header ───────────────────────────────────────────────────── */
.diff-header {
  display: flex;
  align-items: center;
  gap: .75rem;
  padding: .75rem 1rem;
  background: rgba(20, 22, 38, .6);
  border: 1px solid rgba(255,255,255,.06);
  border-radius: 12px;
}

.diff-header__side {
  display: flex;
  align-items: center;
  gap: .4rem;
  flex: 1;
  font-size: .85rem;
  font-weight: 600;
}

.diff-header__side--from { color: #f87171; }
.diff-header__side--to   { color: #34d399; }

.diff-header__arrow { color: #64748b; }

/* ── Summary ───────────────────────────────────────────────────────── */
.diff-summary {
  display: flex;
  align-items: center;
  gap: .75rem;
  flex-wrap: wrap;
}

.diff-summary__count {
  font-size: .82rem;
  font-weight: 600;
  color: #e2e8f0;
}

.diff-summary__fields {
  display: flex;
  gap: .3rem;
  flex-wrap: wrap;
}

.diff-summary__chip {
  font-size: .68rem;
  padding: .15rem .45rem;
  border-radius: 4px;
  background: rgba(129,140,248,.1);
  color: #818cf8;
  font-weight: 600;
  text-decoration: none;
  transition: background .2s;
}

.diff-summary__chip:hover {
  background: rgba(129,140,248,.2);
}

/* ── Diff Block ────────────────────────────────────────────────────── */
.diff-block {
  font-family: 'JetBrains Mono', 'Fira Code', monospace;
  font-size: .75rem;
  overflow-x: auto;
}

.diff-line {
  display: flex;
  padding: .15rem .75rem;
  border-bottom: 1px solid rgba(255,255,255,.02);
  min-height: 1.4em;
}

.diff-line--same {
  color: #94a3b8;
}

.diff-line--remove {
  background: rgba(248,113,113,.08);
  color: #fca5a5;
}

.diff-line--add {
  background: rgba(52,211,153,.08);
  color: #6ee7b7;
}

.diff-line__prefix {
  width: 1.5rem;
  flex-shrink: 0;
  color: inherit;
  opacity: .5;
  user-select: none;
}

.diff-line__content {
  white-space: pre-wrap;
  word-break: break-word;
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
