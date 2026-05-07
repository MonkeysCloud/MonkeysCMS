@extends('layouts.admin')

@section('title', $title ?? 'Block Revisions')
@section('page_title', $title ?? 'Block Revisions')

@section('breadcrumb')
<a href="/admin" class="breadcrumb__item">Dashboard</a>
<span class="breadcrumb__sep">›</span>
<a href="/admin/blocks" class="breadcrumb__item">Blocks</a>
<span class="breadcrumb__sep">›</span>
<a href="/admin/blocks/{{ $blockType['id'] }}/edit" class="breadcrumb__item">{{ $blockType['label'] }}</a>
<span class="breadcrumb__sep">›</span>
<span class="breadcrumb__item breadcrumb__item--active">Revisions</span>
@endsection

@section('page_actions')
<a href="/admin/blocks/{{ $blockType['id'] }}/edit" class="btn btn--ghost btn--sm">
  <i data-lucide="arrow-left" class="w-4 h-4"></i> Back to Edit
</a>
@endsection

@section('content')

{{-- Block Type Info Card --}}
<div class="rev-info">
  <div class="rev-info__icon">
    <i data-lucide="{{ $blockType['icon'] ?? 'puzzle' }}" class="w-6 h-6"></i>
  </div>
  <div class="rev-info__meta">
    <h3 class="rev-info__title">{{ $blockType['label'] }}</h3>
    <span class="rev-info__machine">{{ $blockType['id'] }}</span>
  </div>
  <span class="badge badge--default badge--sm">{{ count($revisions ?? []) }} revisions</span>
</div>

@if(!empty($revisions))
<div class="rev-timeline-wrap">
  <div class="rev-timeline">
    @php $isFirst = true; @endphp
    @foreach($revisions as $rev)
    <div class="rev-item {{ $isFirst ? 'rev-item--current' : '' }}">
      <div class="rev-item__line">
        <div class="rev-item__dot {{ $isFirst ? 'rev-item__dot--active' : '' }}"></div>
      </div>
      <div class="rev-item__content">
        <div class="rev-item__header">
          <span class="rev-item__number">Revision #{{ $rev['revision'] }}</span>
          @if($isFirst)
          <span class="badge badge--success badge--sm">Current</span>
          @endif
        </div>
        <div class="rev-item__summary">{{ $rev['change_summary'] ?? 'No description' }}</div>
        <div class="rev-item__footer">
          <span class="rev-item__date">
            <i data-lucide="clock" class="w-3 h-3"></i>
            {{ $rev['created_at'] }}
          </span>
          @if($rev['changed_by'])
          <span class="rev-item__author">
            <i data-lucide="user" class="w-3 h-3"></i>
            User #{{ $rev['changed_by'] }}
          </span>
          @endif
        </div>
      </div>
    </div>
    @php $isFirst = false; @endphp
    @endforeach
  </div>
</div>
@else
<div class="empty-state" style="margin: 3rem 0">
  <div class="empty-state__icon"><i data-lucide="history" class="w-12 h-12"></i></div>
  <div class="empty-state__title">No revisions yet</div>
  <p class="text-muted">Revisions are created automatically when block type fields or templates are modified.</p>
</div>
@endif

@push('head')
<style>
/* ── Revision Info Card ──────────────────────────────────────── */
.rev-info {
  display: flex; align-items: center; gap: 0.75rem;
  padding: 1rem 1.5rem;
  background: rgba(20,22,38,0.6);
  border: 1px solid rgba(255,255,255,0.06);
  border-radius: 16px;
  margin-bottom: 1.5rem;
}
.rev-info__icon {
  width: 44px; height: 44px;
  display: flex; align-items: center; justify-content: center;
  background: linear-gradient(135deg, rgba(99,102,241,0.15), rgba(139,92,246,0.1));
  border-radius: 12px; color: #818cf8; flex-shrink: 0;
}
.rev-info__meta { flex: 1; min-width: 0; }
.rev-info__title { font-size: 1rem; font-weight: 600; color: #e2e8f0; margin: 0; line-height: 1.3; }
.rev-info__machine { font-size: 0.7rem; color: #64748b; font-family: var(--font-mono, monospace); }

/* ── Timeline ────────────────────────────────────────────────── */
.rev-timeline-wrap {
  background: rgba(20,22,38,0.6);
  border: 1px solid rgba(255,255,255,0.06);
  border-radius: 16px;
  padding: 1.5rem 1.75rem;
}
.rev-timeline {
  position: relative;
}
.rev-item {
  display: flex;
  gap: 1rem;
  padding-bottom: 1.75rem;
  position: relative;
}
.rev-item:last-child { padding-bottom: 0; }

/* Timeline Line + Dot */
.rev-item__line {
  display: flex;
  flex-direction: column;
  align-items: center;
  width: 20px;
  flex-shrink: 0;
  position: relative;
}
.rev-item:not(:last-child) .rev-item__line::after {
  content: '';
  position: absolute;
  top: 18px;
  bottom: -1.75rem;
  width: 2px;
  background: rgba(255,255,255,0.06);
}
.rev-item__dot {
  width: 12px; height: 12px;
  border-radius: 50%;
  background: rgba(255,255,255,0.08);
  border: 2px solid rgba(255,255,255,0.15);
  z-index: 1;
  margin-top: 4px;
}
.rev-item__dot--active {
  background: #6366f1;
  border-color: rgba(99,102,241,0.4);
  box-shadow: 0 0 8px rgba(99,102,241,0.3);
}

/* Content */
.rev-item__content { flex: 1; min-width: 0; }
.rev-item__header {
  display: flex; align-items: center; gap: 0.5rem;
  margin-bottom: 0.25rem;
}
.rev-item__number {
  font-size: 0.875rem; font-weight: 600; color: #e2e8f0;
}
.rev-item__summary {
  font-size: 0.8125rem; color: #94a3b8; line-height: 1.5;
  margin-bottom: 0.375rem;
}
.rev-item__footer {
  display: flex; align-items: center; gap: 1rem;
}
.rev-item__date, .rev-item__author {
  display: inline-flex; align-items: center; gap: 0.25rem;
  font-size: 0.7rem; color: #64748b;
}
</style>
@endpush

@endsection
