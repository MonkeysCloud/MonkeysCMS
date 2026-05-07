@extends('layouts.admin')

@section('title', $contentType->label . ' — AI Configuration')
@section('page_title', 'AI Configuration')

@section('breadcrumb')
<a href="/admin" class="breadcrumb__item">Dashboard</a>
<span class="breadcrumb__sep">›</span>
<a href="/admin/content-types" class="breadcrumb__item">Content Types</a>
<span class="breadcrumb__sep">›</span>
<a href="/admin/content-types/{{ $contentType->type_id }}/edit" class="breadcrumb__item">{{ $contentType->label }}</a>
<span class="breadcrumb__sep">›</span>
<span class="breadcrumb__item breadcrumb__item--active">AI Configuration</span>
@endsection

@section('content')
<div class="content-type-ai">

  {{-- Sub-nav tabs --}}
  <div class="ct-subnav mb-4">
    <a href="/admin/content-types/{{ $contentType->type_id }}/edit" class="ct-subnav__link">
      <i data-lucide="settings" class="w-4 h-4"></i> Settings
    </a>
    <a href="/admin/content-types/{{ $contentType->type_id }}/fields" class="ct-subnav__link">
      <i data-lucide="list" class="w-4 h-4"></i> Fields
    </a>
    <a href="/admin/content-types/{{ $contentType->type_id }}/ai" class="ct-subnav__link ct-subnav__link--active">
      <i data-lucide="brain" class="w-4 h-4"></i> AI Assistant
    </a>
  </div>

  @if(!$apexEnabled)
  <div class="alert alert--warning mb-4">
    <i data-lucide="alert-triangle" class="w-4 h-4"></i>
    AI Assistant is globally disabled.
    <a href="/admin/ai" class="text-white" style="text-decoration:underline;">Enable it in AI Settings</a> first.
  </div>
  @endif

  @if(isset($_GET['saved']))
  <div class="alert alert--success mb-4">
    <i data-lucide="check-circle" class="w-4 h-4"></i>
    AI configuration for {{ $contentType->label }} saved successfully.
  </div>
  @endif

  {!! $formHtml !!}
</div>

@push('head')
<style>
/* Sub-navigation */
.ct-subnav {
  display: flex; gap: 0.25rem; background: rgba(255,255,255,0.03);
  border-radius: 0.5rem; padding: 0.25rem;
}
.ct-subnav__link {
  display: flex; align-items: center; gap: 0.4rem;
  padding: 0.5rem 1rem; border-radius: 0.375rem;
  font-size: 0.85rem; color: #94a3b8; text-decoration: none;
  transition: all 0.2s;
}
.ct-subnav__link:hover { color: #e2e8f0; background: rgba(255,255,255,0.05); }
.ct-subnav__link--active {
  color: #a5b4fc; background: rgba(99,102,241,0.15); font-weight: 600;
}

/* ── Toggle switch ────────────────────────────────────────────────── */
.ct-toggle {
  cursor: pointer;
  display: inline-flex;
  align-items: center;
  position: relative;
}
.ct-toggle input[type="checkbox"],
.ct-toggle input[type="hidden"] { display: none; }

.ct-toggle .toggle-switch {
  position: relative;
  width: 44px; height: 24px;
  background: rgba(255,255,255,0.12);
  border-radius: 999px;
  transition: background 0.25s ease;
  flex-shrink: 0;
}
.ct-toggle .toggle-switch::after {
  content: '';
  position: absolute;
  top: 3px; left: 3px;
  width: 18px; height: 18px;
  background: #94a3b8;
  border-radius: 50%;
  transition: transform 0.25s ease, background 0.25s ease;
  box-shadow: 0 1px 3px rgba(0,0,0,0.3);
}
.ct-toggle input[type="checkbox"]:checked + .toggle-switch {
  background: rgba(99,102,241,0.5);
}
.ct-toggle input[type="checkbox"]:checked + .toggle-switch::after {
  transform: translateX(20px);
  background: #a5b4fc;
}

/* Small toggle variant (field rows) */
.ct-toggle--sm .toggle-switch {
  width: 36px; height: 20px;
}
.ct-toggle--sm .toggle-switch::after {
  width: 14px; height: 14px;
}
.ct-toggle--sm input[type="checkbox"]:checked + .toggle-switch::after {
  transform: translateX(16px);
}

/* ── Field rows ───────────────────────────────────────────────────── */
.ai-field-row { transition: opacity 0.2s; }
.ai-field-row--off { opacity: 0.45; }

/* Table divider */
.table__divider td {
  padding: 0.5rem 1rem; background: rgba(99,102,241,0.06);
  border-bottom: 1px solid rgba(255,255,255,0.05);
}

/* ── Action chips ─────────────────────────────────────────────────── */
.ai-action-chip {
  display: inline-flex; align-items: center; gap: 0.3rem;
  padding: 0.2rem 0.6rem; margin: 0.15rem;
  border-radius: 1rem; font-size: 0.7rem;
  background: rgba(255,255,255,0.05);
  color: #94a3b8; cursor: pointer;
  border: 1px solid rgba(255,255,255,0.08);
  transition: all 0.2s; user-select: none;
}
.ai-action-chip input { display: none; }
.ai-action-chip:hover {
  background: rgba(99,102,241,0.1); color: #a5b4fc;
  border-color: rgba(99,102,241,0.3);
}
.ai-action-chip--on {
  background: rgba(99,102,241,0.2); color: #a5b4fc;
  border-color: rgba(99,102,241,0.5);
}
</style>
@endpush

@push('scripts')
<script>
function toggleAiSection(enabled) {
  const card = document.getElementById('ai-fields-card');
  card.style.opacity = enabled ? '1' : '0.35';
  card.style.pointerEvents = enabled ? 'auto' : 'none';
}

function toggleFieldRow(fieldName, enabled) {
  const row = document.querySelector(`[data-field="${fieldName}"]`);
  if (row) row.classList.toggle('ai-field-row--off', !enabled);
}

// Chip toggle styling
document.addEventListener('change', (e) => {
  const chip = e.target.closest('.ai-action-chip');
  if (chip) chip.classList.toggle('ai-action-chip--on', e.target.checked);
});

// Init
document.addEventListener('DOMContentLoaded', () => {
  const enabled = document.querySelector('input[name="ai_enabled"][type="checkbox"]');
  if (enabled) {
    toggleAiSection(enabled.checked);
    enabled.addEventListener('change', () => toggleAiSection(enabled.checked));
  }
});
</script>
@endpush
@endsection
