@extends('layouts.admin')

@section('title', $title ?? 'Block Instance')
@section('page_title', $title ?? 'Block Instance')

@section('breadcrumb')
<a href="/admin" class="breadcrumb__item">Dashboard</a>
<span class="breadcrumb__sep">›</span>
<a href="/admin/blocks?tab=instances" class="breadcrumb__item">Blocks</a>
<span class="breadcrumb__sep">›</span>
<span class="breadcrumb__item breadcrumb__item--active">{{ $isNew ? 'New Instance' : 'Edit Instance' }}</span>
@endsection

@section('page_actions')
<a href="/admin/blocks?tab=instances" class="btn btn--ghost btn--sm">
  <i data-lucide="arrow-left" class="w-4 h-4"></i> Back
</a>
@endsection

@section('content')

<form action="{{ $isNew ? '/admin/blocks/instances' : '/admin/blocks/instances/' . $instance['id'] }}" method="POST" id="instance-form">

  <div class="bi-layout">
    {{-- Main Column --}}
    <div class="bi-layout__main">

      {{-- General --}}
      <div class="admin-card">
        <div class="admin-card__header">
          <h3 class="admin-card__title">
            <i data-lucide="layers" class="w-5 h-5"></i> General
          </h3>
        </div>
        <div class="admin-card__body">
          <div class="form-group">
            <label class="form-label" for="inst-label">Label <span class="form-required">*</span></label>
            <input type="text" name="label" id="inst-label" class="form-input" required
                   value="{{ $instance['label'] ?? '' }}" placeholder="e.g. Footer Call to Action">
          </div>

          <div class="form-group">
            <label class="form-label" for="inst-desc">Description</label>
            <textarea name="description" id="inst-desc" class="form-input" rows="2"
                      placeholder="What this instance is used for...">{{ $instance['description'] ?? '' }}</textarea>
          </div>

          <div class="bi-form-row">
            <div class="form-group">
              <label class="form-label" for="inst-type">Block Type <span class="form-required">*</span></label>
              <select name="block_type" id="inst-type" class="form-input" required {{ !$isNew ? 'disabled' : '' }}>
                <option value="">— Select block type —</option>
                @foreach($allTypes as $bt)
                <option value="{{ $bt['id'] }}"
                  {{ (($instance['block_type'] ?? '') === $bt['id']) ? 'selected' : '' }}>
                  {{ $bt['label'] }} ({{ $bt['id'] }})
                </option>
                @endforeach
              </select>
              @if(!$isNew)
              <input type="hidden" name="block_type" value="{{ $instance['block_type'] }}">
              @endif
            </div>

            <div class="form-group">
              <label class="form-label" for="inst-status">Status</label>
              <select name="status" id="inst-status" class="form-input">
                <option value="published" {{ ($instance['status'] ?? 'published') === 'published' ? 'selected' : '' }}>Published</option>
                <option value="draft" {{ ($instance['status'] ?? '') === 'draft' ? 'selected' : '' }}>Draft</option>
                <option value="archived" {{ ($instance['status'] ?? '') === 'archived' ? 'selected' : '' }}>Archived</option>
              </select>
            </div>
          </div>
        </div>
      </div>

      {{-- Dynamic Fields --}}
      <div class="admin-card" id="dynamic-fields-card">
        <div class="admin-card__header">
          <h3 class="admin-card__title">
            <i data-lucide="edit-3" class="w-5 h-5"></i> Block Data
          </h3>
          <p class="admin-card__desc">Fill in the block's content fields</p>
        </div>
        <div class="admin-card__body" id="dynamic-fields">
          @if(!$isNew && !empty($blockType['fields']))
            @foreach($blockType['fields'] as $fname => $fdef)
            <div class="form-group">
              <label class="form-label">{{ $fdef['label'] ?? ucfirst(str_replace('_', ' ', $fname)) }}
                @if(!empty($fdef['required']))
                <span class="form-required">*</span>
                @endif
              </label>
              @php
                $fieldValue = $instance['data'][$fname] ?? $fdef['default'] ?? '';
                $fieldType = $fdef['type'] ?? 'string';
              @endphp
              @if(in_array($fieldType, ['text', 'html', 'markdown']))
              <div data-wysiwyg>
                <textarea name="field_{{ $fname }}" class="form-input" rows="4">{{ $fieldValue }}</textarea>
              </div>
              @elseif($fieldType === 'boolean')
              <label class="form-toggle">
                <input type="hidden" name="field_{{ $fname }}" value="0">
                <input type="checkbox" name="field_{{ $fname }}" value="1" {{ $fieldValue ? 'checked' : '' }}>
                <span class="form-toggle__label">Enabled</span>
              </label>
              @elseif($fieldType === 'select' && !empty($fdef['options']))
              <select name="field_{{ $fname }}" class="form-input">
                @foreach($fdef['options'] as $optVal => $optLabel)
                <option value="{{ $optVal }}" {{ $fieldValue === $optVal ? 'selected' : '' }}>{{ $optLabel }}</option>
                @endforeach
              </select>
              @elseif($fieldType === 'color')
              <input type="color" name="field_{{ $fname }}" class="form-input" value="{{ $fieldValue }}" style="height:40px; max-width:100px">
              @elseif($fieldType === 'url')
              <input type="url" name="field_{{ $fname }}" class="form-input" value="{{ $fieldValue }}" placeholder="https://...">
              @elseif($fieldType === 'email')
              <input type="email" name="field_{{ $fname }}" class="form-input" value="{{ $fieldValue }}">
              @elseif(in_array($fieldType, ['integer', 'float', 'decimal']))
              <input type="number" name="field_{{ $fname }}" class="form-input" value="{{ $fieldValue }}" step="{{ $fieldType === 'integer' ? '1' : '0.01' }}">
              @else
              <input type="text" name="field_{{ $fname }}" class="form-input" value="{{ $fieldValue }}">
              @endif
              @if(!empty($fdef['help_text']))
              <span class="form-hint">{{ $fdef['help_text'] }}</span>
              @endif
            </div>
            @endforeach
          @elseif($isNew)
          <p class="text-muted text-sm" id="field-placeholder">Select a block type above to see its fields.</p>
          @else
          <p class="text-muted text-sm">This block type has no fields.</p>
          @endif
        </div>
      </div>
    </div>

    {{-- Sidebar --}}
    <div class="bi-layout__sidebar">
      <div class="admin-card">
        <div class="admin-card__body" style="display:flex; flex-direction:column; gap:0.5rem;">
          <button type="submit" class="btn btn--primary btn--block">
            <i data-lucide="save" class="w-4 h-4"></i>
            {{ $isNew ? 'Create Instance' : 'Save Changes' }}
          </button>
          <a href="/admin/blocks?tab=instances" class="btn btn--ghost btn--block">Cancel</a>

          @if(!$isNew)
          <div style="margin-top:0.75rem; padding-top:0.75rem; border-top:1px solid rgba(255,255,255,0.06)">
            <div class="text-xs text-muted" style="margin-bottom:0.25rem">
              <i data-lucide="bar-chart-2" class="w-3 h-3" style="display:inline"></i>
              Usage: <strong>{{ $instance['usage_count'] ?? 0 }}×</strong>
            </div>
            <div class="text-xs text-muted" style="margin-bottom:0.25rem">Created: {{ $instance['created_at'] ?? '' }}</div>
            <div class="text-xs text-muted">Updated: {{ $instance['updated_at'] ?? '' }}</div>
          </div>
          @endif
        </div>
      </div>

      @if(!$isNew)
      <div class="admin-card" style="margin-top:1rem">
        <div class="admin-card__header" style="background:rgba(239,68,68,0.06)">
          <h3 class="admin-card__title" style="color:#fca5a5">
            <i data-lucide="alert-triangle" class="w-4 h-4"></i> Danger Zone
          </h3>
        </div>
        <div class="admin-card__body">
          <form action="/admin/blocks/instances/{{ $instance['id'] }}/delete" method="POST"
                data-confirm="Delete this block instance? It may still be referenced in layouts." data-confirm-title="Delete Instance">
            <button type="submit" class="btn btn--danger btn--sm btn--block">
              <i data-lucide="trash-2" class="w-4 h-4"></i> Delete Instance
            </button>
          </form>
        </div>
      </div>
      @endif
    </div>
  </div>
</form>

{{-- Dynamic field loading for new instances --}}
@if($isNew)
<script>
document.addEventListener('DOMContentLoaded', () => {
  const typeSelect = document.getElementById('inst-type');
  const fieldsContainer = document.getElementById('dynamic-fields');
  const allTypes = {!! json_encode(array_column($allTypes, null, 'id')) !!};

  typeSelect?.addEventListener('change', () => {
    const selectedType = allTypes[typeSelect.value];
    if (!selectedType || !selectedType.fields || Object.keys(selectedType.fields).length === 0) {
      fieldsContainer.innerHTML = '<p class="text-muted text-sm">This block type has no fields.</p>';
      return;
    }

    let html = '';
    for (const [name, def] of Object.entries(selectedType.fields)) {
      const label = def.label || name.replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase());
      const type = def.type || 'string';
      const req = def.required ? ' required' : '';

      html += '<div class="form-group">';
      html += '<label class="form-label">' + label + (def.required ? ' <span class="form-required">*</span>' : '') + '</label>';

      if (['text', 'html', 'markdown'].includes(type)) {
        html += '<div data-wysiwyg>';
        html += '<textarea name="field_' + name + '" class="form-input" rows="4"' + req + '>' + (def.default || '') + '</textarea>';
        html += '</div>';
      } else if (type === 'boolean') {
        html += '<label class="form-toggle"><input type="hidden" name="field_' + name + '" value="0"><input type="checkbox" name="field_' + name + '" value="1"><span class="form-toggle__label">Enabled</span></label>';
      } else if (type === 'select' && def.options) {
        html += '<select name="field_' + name + '" class="form-input"' + req + '>';
        for (const [v, l] of Object.entries(def.options)) {
          html += '<option value="' + v + '">' + l + '</option>';
        }
        html += '</select>';
      } else if (type === 'color') {
        html += '<input type="color" name="field_' + name + '" class="form-input" value="' + (def.default || '#000000') + '" style="height:40px;max-width:100px">';
      } else if (type === 'url') {
        html += '<input type="url" name="field_' + name + '" class="form-input" placeholder="https://..."' + req + '>';
      } else if (['integer', 'float', 'decimal'].includes(type)) {
        html += '<input type="number" name="field_' + name + '" class="form-input" value="' + (def.default || '') + '" step="' + (type === 'integer' ? '1' : '0.01') + '"' + req + '>';
      } else {
        html += '<input type="text" name="field_' + name + '" class="form-input" value="' + (def.default || '') + '"' + req + '>';
      }

      if (def.help_text) {
        html += '<span class="form-hint">' + def.help_text + '</span>';
      }
      html += '</div>';
    }

    fieldsContainer.innerHTML = html;
    // Re-init Lucide icons and WYSIWYG editors for dynamically added fields
    if (window.lucide) lucide.createIcons();
    if (window.CMS?.wysiwyg) CMS.wysiwyg.init();
  });
});
</script>
@endif

@push('head')
<style>
/* ── Instance Form Layout ────────────────────────────────────── */
.bi-layout {
  display: grid;
  grid-template-columns: 1fr 280px;
  gap: 1.5rem;
  align-items: start;
}
.bi-layout__main { min-width: 0; }
.bi-layout__sidebar { position: sticky; top: 1rem; }

@media (max-width: 768px) {
  .bi-layout { grid-template-columns: 1fr; }
  .bi-layout__sidebar { position: static; }
}

.bi-form-row {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 1rem;
}
@media (max-width: 640px) { .bi-form-row { grid-template-columns: 1fr; } }

/* ── Base admin-card styles ──────────────────────────────────── */
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
  display: flex; align-items: center; gap: 0.5rem;
  font-size: 0.88rem; font-weight: 600; color: #cdd6f4; margin: 0;
}
.admin-card__title svg,
.admin-card__title i { color: #a5b4fc; opacity: 0.7; }
.admin-card__desc {
  font-size: 0.78rem; color: #64748b; margin: 0.25rem 0 0;
}
.admin-card__body { padding: 1.25rem 1.1rem; }
</style>
@endpush

@push('scripts')
<script src="/themes/core/admin/js/wysiwyg.js"></script>
@endpush

@endsection

