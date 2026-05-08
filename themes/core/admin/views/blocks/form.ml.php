@extends('layouts.admin')

@section('title', $title ?? 'Block Type')
@section('page_title', $title ?? 'Block Type')

@section('breadcrumb')
<a href="/admin" class="breadcrumb__item">Dashboard</a>
<span class="breadcrumb__sep">›</span>
<a href="/admin/blocks" class="breadcrumb__item">Blocks</a>
<span class="breadcrumb__sep">›</span>
<span class="breadcrumb__item breadcrumb__item--active">{{ $isNew ? 'Create' : 'Edit' }}</span>
@endsection

@section('page_actions')
<a href="/admin/blocks" class="btn btn--ghost btn--sm">
  <i data-lucide="arrow-left" class="w-4 h-4"></i> Back to Blocks
</a>
@if(!$isNew)
<a href="/admin/blocks/{{ $blockType['id'] }}/fields" class="btn btn--ghost btn--sm">
  <i data-lucide="list" class="w-4 h-4"></i> Fields
</a>
<a href="/admin/blocks/{{ $blockType['id'] }}/revisions" class="btn btn--ghost btn--sm">
  <i data-lucide="history" class="w-4 h-4"></i> Revisions
</a>
@endif
@endsection

@section('content')

{{-- Flash Messages --}}
@if(!empty($flashSuccess))
<div class="alert alert--success mb-4">
  <i data-lucide="check-circle" class="w-4 h-4"></i> {{ $flashSuccess }}
</div>
@endif
@if(!empty($flashError))
<div class="alert alert--error mb-4">
  <i data-lucide="alert-circle" class="w-4 h-4"></i> {{ $flashError }}
</div>
@endif

{{-- FormBuilder rendered form --}}
{!! $formHtml !!}

{{-- Auto-generate machine name from label --}}
@if($isNew)
<script>
document.addEventListener('DOMContentLoaded', () => {
  const label = document.querySelector('[name="label"]');
  const typeId = document.querySelector('[name="type_id"]');
  if (!label || !typeId) return;

  let userEdited = false;
  typeId.addEventListener('input', () => { userEdited = true; });

  label.addEventListener('input', () => {
    if (userEdited) return;
    typeId.value = label.value
      .toLowerCase()
      .replace(/[^a-z0-9]+/g, '_')
      .replace(/^_|_$/g, '');
  });
});
</script>
@endif

@push('head')
<style>
/* ── Base admin-card styles (for FormBuilder groups) ─────────── */
.admin-card {
  background: linear-gradient(145deg, rgba(30,30,46,0.95), rgba(24,24,37,0.98));
  border: 1px solid rgba(255,255,255,0.06);
  border-radius: 12px;
  overflow: hidden;
  margin-bottom: 1.25rem;
}
.admin-card__header {
  padding: 0.85rem 1.1rem;
  background: linear-gradient(135deg, rgba(99,102,241,0.06), rgba(139,92,246,0.03));
  border-bottom: 1px solid rgba(255,255,255,0.05);
}
.admin-card__title {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  font-size: 0.88rem;
  font-weight: 600;
  color: #cdd6f4;
  margin: 0;
}
.admin-card__title svg,
.admin-card__title i { color: #a5b4fc; opacity: 0.7; }
.admin-card__desc {
  font-size: 0.78rem;
  color: #64748b;
  margin: 0.25rem 0 0;
}
.admin-card__body {
  padding: 1.25rem 1.1rem;
}

/* ── Form layout overrides ───────────────────────────────────── */
.admin-grid { display: grid; gap: 1.25rem; }
.admin-grid--2 { grid-template-columns: 1fr 1fr; }
.admin-grid--3 { grid-template-columns: 1fr 1fr 1fr; }
@media (max-width: 900px) {
  .admin-grid--2, .admin-grid--3 { grid-template-columns: 1fr; }
}

/* Code textarea (template field) */
.form-input[name="template"],
textarea.form-input[rows] {
  font-family: 'JetBrains Mono', 'Fira Code', monospace;
  font-size: 0.8125rem;
  line-height: 1.6;
  tab-size: 2;
}
</style>
@endpush

@endsection
