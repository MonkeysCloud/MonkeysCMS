@extends('layouts.admin')

@section('title', $contentType->label . ' — Fields')
@section('page_title', 'Manage Fields')

@section('breadcrumb')
<a href="/admin" class="breadcrumb__item">Dashboard</a>
<span class="breadcrumb__sep">›</span>
<a href="/admin/content-types" class="breadcrumb__item">Content Types</a>
<span class="breadcrumb__sep">›</span>
<a href="/admin/content-types/{{ $contentType->type_id }}/edit" class="breadcrumb__item">{{ $contentType->label }}</a>
<span class="breadcrumb__sep">›</span>
<span class="breadcrumb__item breadcrumb__item--active">Fields</span>
@endsection

@section('content')
<div class="grid" style="grid-template-columns: 1fr 380px; gap: 1.5rem;">

  {{-- Field List --}}
  <div>
    <div class="card">
      <div class="card__header flex-between">
        <h3 class="card__title">
          <i data-lucide="{{ $contentType->icon }}" class="w-5 h-5" style="display:inline-block;vertical-align:-3px;margin-right:0.4rem;color:#818cf8;"></i>
          {{ $contentType->label }} Fields
        </h3>
        <span class="badge badge--muted">{{ count($fields ?? []) }} fields</span>
      </div>
      <div class="card__body p-0">
        @if(!empty($fields))
        <table class="table table--hover" id="fields-table">
          <thead>
            <tr>
              <th style="width:32px"></th>
              <th>Name</th>
              <th>Machine Name</th>
              <th>Type</th>
              <th>Widget</th>
              <th style="width:60px">Req</th>
              <th class="table__actions">Actions</th>
            </tr>
          </thead>
          <tbody id="sortable-fields">
            @foreach($fields as $field)
            <tr data-field-id="{{ $field->id }}" class="field-row">
              <td class="drag-handle" title="Drag to reorder">
                <i data-lucide="grip-vertical" class="w-4 h-4 text-muted"></i>
              </td>
              <td>
                <span class="font-medium">{{ $field->name }}</span>
                @if($field->help_text)
                <div class="text-xs text-muted">{{ $field->help_text }}</div>
                @endif
              </td>
              <td><code class="text-xs">{{ $field->machine_name }}</code></td>
              <td>
                <span class="badge badge--outline badge--sm">{{ $field->getFieldTypeEnum()->label() }}</span>
              </td>
              <td class="text-sm text-muted">{{ $field->getWidget() }}</td>
              <td class="text-center">
                @if($field->required)
                <span class="text-danger" title="Required">●</span>
                @else
                <span class="text-muted">○</span>
                @endif
              </td>
              <td class="table__actions">
                <form action="/admin/content-types/{{ $contentType->type_id }}/fields/{{ $field->id }}/delete"
                      method="POST" style="display:inline"
                      onsubmit="return confirm('Remove field {{ $field->name }}?')">
                  <button type="submit" class="btn btn--xs btn--ghost text-danger">
                    <i data-lucide="trash-2" class="w-3.5 h-3.5"></i>
                  </button>
                </form>
              </td>
            </tr>
            @endforeach
          </tbody>
        </table>
        @else
        <div class="empty-state py-8">
          <div class="empty-state__icon"><i data-lucide="list-plus" class="w-10 h-10"></i></div>
          <div class="empty-state__title">No fields defined</div>
          <p class="text-muted text-sm">Add fields using the form on the right →</p>
        </div>
        @endif
      </div>
    </div>
  </div>

  {{-- Add Field Form --}}
  <div>
    <div class="card" style="position:sticky;top:1rem;">
      <div class="card__header">
        <h3 class="card__title">
          <i data-lucide="plus-circle" class="w-5 h-5" style="display:inline-block;vertical-align:-3px;margin-right:0.3rem;color:#818cf8;"></i>
          Add Field
        </h3>
      </div>
      <div class="card__body">
        <form action="/admin/content-types/{{ $contentType->type_id }}/fields" method="POST" id="add-field-form">
          <div class="form-group">
            <label class="form-label">Label <span class="text-danger">*</span></label>
            <input type="text" name="name" class="form-input" placeholder="e.g. Summary"
                   required id="field-label-input">
          </div>

          <div class="form-group">
            <label class="form-label">Machine Name <span class="text-danger">*</span></label>
            <input type="text" name="machine_name" class="form-input" placeholder="e.g. summary"
                   pattern="[a-z][a-z0-9_]*" maxlength="100" required id="field-machine-input">
            <p class="form-help">Auto-generated from label. Lowercase, underscores.</p>
          </div>

          <div class="form-group">
            <label class="form-label">Field Type <span class="text-danger">*</span></label>
            <select name="field_type" class="form-select" required id="field-type-select">
              @foreach($fieldTypes ?? [] as $ft)
              <option value="{{ $ft->value }}">{{ $ft->label() }}</option>
              @endforeach
            </select>
          </div>

          <div class="form-group">
            <label class="form-label">Widget</label>
            <input type="text" name="widget" class="form-input" placeholder="Auto-detected"
                   id="field-widget-input">
            <p class="form-help">Leave empty to use the default widget for this field type.</p>
          </div>

          <div class="form-group">
            <label class="form-label">Help Text</label>
            <input type="text" name="help_text" class="form-input" placeholder="Displayed below the field">
          </div>

          <div class="form-group">
            <label class="form-label">Weight</label>
            <input type="number" name="weight" class="form-input" value="{{ count($fields ?? []) }}" min="0">
          </div>

          <div class="form-group" style="display:flex;gap:1rem;">
            <label class="form-toggle">
              <input type="checkbox" name="required" value="1">
              <span class="form-toggle__slider"></span>
              <span class="form-toggle__label">Required</span>
            </label>
            <label class="form-toggle">
              <input type="checkbox" name="searchable" value="1">
              <span class="form-toggle__slider"></span>
              <span class="form-toggle__label">Searchable</span>
            </label>
          </div>

          <button type="submit" class="btn btn--primary btn--block">
            <i data-lucide="plus" class="w-4 h-4"></i>
            Add Field
          </button>
        </form>
      </div>
    </div>
  </div>
</div>

@push('head')
<style>
.drag-handle {
  cursor: grab; opacity: 0.4; transition: opacity 0.15s;
}
.drag-handle:hover { opacity: 1; }
.field-row.dragging {
  opacity: 0.5; background: rgba(99,102,241,0.05);
}
.form-toggle {
  display: flex; align-items: center; gap: 0.6rem; cursor: pointer; user-select: none;
}
.form-toggle input { display: none; }
.form-toggle__slider {
  width: 36px; height: 20px; background: rgba(255,255,255,0.1);
  border-radius: 10px; position: relative; transition: background 0.2s; flex-shrink: 0;
}
.form-toggle__slider::after {
  content: ''; position: absolute; width: 16px; height: 16px; border-radius: 50%;
  background: #64748b; top: 2px; left: 2px; transition: all 0.2s;
}
.form-toggle input:checked + .form-toggle__slider { background: rgba(99,102,241,0.4); }
.form-toggle input:checked + .form-toggle__slider::after { background: #818cf8; transform: translateX(16px); }
.form-toggle__label { font-size: 0.8rem; color: #cbd5e1; }
.badge--outline {
  background: transparent; border: 1px solid rgba(255,255,255,0.12);
  color: #94a3b8; font-size: 0.7rem;
}
.btn--block { width: 100%; }
</style>
@endpush

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
  // Auto-generate machine name from label
  const labelInput = document.getElementById('field-label-input');
  const machineInput = document.getElementById('field-machine-input');
  let machineEdited = false;

  if (machineInput) {
    machineInput.addEventListener('input', () => { machineEdited = true; });
  }
  if (labelInput && machineInput) {
    labelInput.addEventListener('input', () => {
      if (!machineEdited) {
        machineInput.value = labelInput.value
          .toLowerCase()
          .replace(/[^a-z0-9]+/g, '_')
          .replace(/^_|_$/g, '');
      }
    });
  }
});
</script>
@endpush
@endsection
