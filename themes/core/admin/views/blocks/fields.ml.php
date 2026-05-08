@extends('layouts.admin')

@section('title', $title ?? 'Block Fields')
@section('page_title', $title ?? 'Block Fields')

@section('breadcrumb')
<a href="/admin" class="breadcrumb__item">Dashboard</a>
<span class="breadcrumb__sep">›</span>
<a href="/admin/blocks" class="breadcrumb__item">Blocks</a>
<span class="breadcrumb__sep">›</span>
<a href="/admin/blocks/{{ $blockType['id'] }}/edit" class="breadcrumb__item">{{ $blockType['label'] }}</a>
<span class="breadcrumb__sep">›</span>
<span class="breadcrumb__item breadcrumb__item--active">Fields</span>
@endsection

@section('page_actions')
<a href="/admin/blocks/{{ $blockType['id'] }}/edit" class="btn btn--ghost btn--sm">
  <i data-lucide="arrow-left" class="w-4 h-4"></i> Back to Edit
</a>
@endsection

@section('content')

{{-- Block Type Info Card --}}
<div class="bf-info">
  <div class="bf-info__icon">
    <i data-lucide="{{ $blockType['icon'] ?? 'puzzle' }}" class="w-6 h-6"></i>
  </div>
  <div class="bf-info__meta">
    <h3 class="bf-info__title">{{ $blockType['label'] }}</h3>
    <span class="bf-info__machine">{{ $blockType['id'] }}</span>
  </div>
  @if($blockType['source'] === 'code')
  <span class="badge badge--info badge--sm"><i data-lucide="code-2" class="w-3 h-3"></i> Code-defined</span>
  @else
  <span class="badge badge--success badge--sm"><i data-lucide="database" class="w-3 h-3"></i> Custom</span>
  @endif
  <span class="badge badge--default badge--sm">{{ count($fields) }} {{ count($fields) === 1 ? 'field' : 'fields' }}</span>
</div>

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

@if(!empty($fields))
<div class="bf-table-wrap" id="fields-app" $m-data="{ editing: null }">

  {{-- Hidden reorder form --}}
  <form id="reorder-form" action="/admin/blocks/{{ $blockType['id'] }}/fields/reorder" method="POST" style="display:none">
    <input type="hidden" name="order" id="reorder-input">
  </form>

  <table class="admin-table" id="fields-table">
    <thead>
      <tr>
        <th style="width:2.5rem"></th>
        <th>Label</th>
        <th>Machine Name</th>
        <th>Type</th>
        <th class="text-center">Required</th>
        <th>Default</th>
        <th class="text-right">Actions</th>
      </tr>
    </thead>
    <tbody id="fields-tbody">
      @foreach($fields as $name => $def)
      {{-- Display Row --}}
      <tr class="bf-field-row" data-field="{{ $name }}" $m-show="editing !== '{{ $name }}'">
        <td class="text-center bf-drag-handle" style="cursor:grab; color: #64748b">
          <i data-lucide="grip-vertical" class="w-4 h-4"></i>
        </td>
        <td>
          <span class="font-medium text-sm" style="color:#e2e8f0">{{ $def['label'] ?? ucfirst(str_replace('_', ' ', $name)) }}</span>
        </td>
        <td>
          <code style="font-size:0.75rem; color:#818cf8; background:rgba(99,102,241,0.1); padding:0.15rem 0.4rem; border-radius:4px">{{ $name }}</code>
        </td>
        <td>
          <span class="badge badge--default badge--sm">{{ $def['type'] ?? 'string' }}</span>
        </td>
        <td class="text-center">
          @if(!empty($def['required']))
          <i data-lucide="check-circle" class="w-4 h-4" style="color:#10b981"></i>
          @else
          <span style="color:#64748b">—</span>
          @endif
        </td>
        <td>
          @if(isset($def['default']) && $def['default'] !== null)
          <code style="font-size:0.7rem; color:#94a3b8">{{ is_array($def['default']) ? json_encode($def['default']) : $def['default'] }}</code>
          @else
          <span style="color:#475569">—</span>
          @endif
        </td>
        <td class="text-right">
          @if($blockType['source'] !== 'code')
          <button type="button" class="btn btn--sm btn--ghost" $m-on:click="editing = '{{ $name }}'" title="Edit field">
            <i data-lucide="pencil" class="w-4 h-4"></i>
          </button>
          <form action="/admin/blocks/{{ $blockType['id'] }}/fields/{{ $name }}/delete" method="POST" style="display:inline"
                data-confirm="Remove field '{{ $name }}'? This may break existing block instances." data-confirm-title="Remove Field">
            <button type="submit" class="btn btn--sm btn--ghost text-danger">
              <i data-lucide="trash-2" class="w-4 h-4"></i>
            </button>
          </form>
          @endif
        </td>
      </tr>

      {{-- Inline Edit Row (shown when editing) --}}
      @if($blockType['source'] !== 'code')
      <tr class="bf-edit-row" $m-show="editing === '{{ $name }}'">
        <td colspan="7">
          <form action="/admin/blocks/{{ $blockType['id'] }}/fields/{{ $name }}/update" method="POST" class="bf-edit-form">
            <div class="bf-edit-grid">
              <div class="form-group">
                <label class="form-label text-sm">Label</label>
                <input type="text" name="label" class="form-input form-input--sm"
                       value="{{ $def['label'] ?? ucfirst(str_replace('_', ' ', $name)) }}">
              </div>
              <div class="form-group">
                <label class="form-label text-sm">Help Text</label>
                <input type="text" name="help_text" class="form-input form-input--sm"
                       value="{{ $def['help_text'] ?? '' }}" placeholder="Optional hint for editors">
              </div>
              <div class="form-group">
                <label class="form-label text-sm">Default Value</label>
                <input type="text" name="default" class="form-input form-input--sm"
                       value="{{ is_array($def['default'] ?? null) ? json_encode($def['default']) : ($def['default'] ?? '') }}" placeholder="Optional">
              </div>
              <div class="form-group" style="display:flex; align-items:flex-end;">
                <label class="flex items-center gap-2 text-sm" style="color:#94a3b8; padding-bottom:0.5rem">
                  <input type="hidden" name="required" value="0">
                  <input type="checkbox" name="required" value="1" class="form-checkbox" {{ !empty($def['required']) ? 'checked' : '' }}>
                  Required
                </label>
              </div>
            </div>
            <div class="bf-edit-actions">
              <button type="submit" class="btn btn--primary btn--sm">
                <i data-lucide="check" class="w-4 h-4"></i> Save
              </button>
              <button type="button" class="btn btn--ghost btn--sm" $m-on:click="editing = null">
                Cancel
              </button>
            </div>
          </form>
        </td>
      </tr>
      @endif
      @endforeach
    </tbody>
  </table>
</div>
@else
<div class="empty-state" style="margin: 2rem 0">
  <div class="empty-state__icon"><i data-lucide="list" class="w-10 h-10"></i></div>
  <div class="empty-state__title">No fields defined</div>
  <p class="text-muted text-sm">Add fields below to define the block's data structure.</p>
</div>
@endif

{{-- Add Field Form (only for custom blocks) --}}
@if($blockType['source'] !== 'code')
<div class="bf-add-card">
  <div class="bf-add-card__header">
    <i data-lucide="plus-circle" class="w-5 h-5" style="color:#818cf8"></i>
    <span class="bf-add-card__title">Add New Field</span>
  </div>
  <form action="/admin/blocks/{{ $blockType['id'] }}/fields" method="POST">
    <div class="bf-field-grid">
      <div class="form-group">
        <label class="form-label text-sm">Label *</label>
        <input type="text" name="label" id="field-label" class="form-input form-input--sm" required
               placeholder="e.g. Heading Text">
      </div>

      <div class="form-group">
        <label class="form-label text-sm">Machine Name *</label>
        <input type="text" name="machine_name" id="field-machine" class="form-input form-input--sm" required
               placeholder="e.g. heading_text" pattern="[a-z][a-z0-9_]*">
        <small class="text-xs" style="color:#64748b">Lowercase, underscores. Used in templates as <code style="color:#818cf8">{{ '$' }}field_name</code></small>
      </div>

      <div class="form-group">
        <label class="form-label text-sm">Field Type *</label>
        <select name="field_type" id="field-type" class="form-input form-input--sm">
          @foreach($fieldTypes as $ft)
          <option value="{{ $ft->value }}">{{ $ft->label() }}</option>
          @endforeach
        </select>
      </div>

      <div class="form-group">
        <label class="form-label text-sm">Default Value</label>
        <input type="text" name="default" id="field-default" class="form-input form-input--sm"
               placeholder="Optional">
      </div>

      <div class="form-group">
        <label class="form-label text-sm">Help Text</label>
        <input type="text" name="help_text" id="field-help" class="form-input form-input--sm"
               placeholder="Optional hint for editors">
      </div>

      <div class="form-group">
        <label class="form-label text-sm">Options</label>
        <textarea name="options" id="field-options" class="form-input form-input--sm" rows="2"
                  placeholder="value|Label (one per line)"></textarea>
        <small class="text-xs" style="color:#64748b">For select, multiselect, radio types.</small>
      </div>
    </div>

    <div class="bf-add-card__footer">
      <label class="flex items-center gap-2 text-sm" style="color:#94a3b8">
        <input type="checkbox" name="required" value="1" class="form-checkbox">
        Required field
      </label>
      <button type="submit" class="btn btn--primary btn--sm">
        <i data-lucide="plus" class="w-4 h-4"></i> Add Field
      </button>
    </div>
  </form>
</div>
@else
<div class="bf-readonly-notice">
  <i data-lucide="info" class="w-4 h-4"></i>
  <span>This block type is code-defined. Fields can only be modified in the PHP class.</span>
</div>
@endif

{{-- Label → Machine Name auto-generation --}}
<script>
document.addEventListener('DOMContentLoaded', () => {
  const label = document.getElementById('field-label');
  const machine = document.getElementById('field-machine');
  if (!label || !machine) return;

  let userEdited = false;
  machine.addEventListener('input', () => { userEdited = true; });

  label.addEventListener('input', () => {
    if (userEdited) return;
    machine.value = label.value
      .toLowerCase()
      .replace(/[^a-z0-9]+/g, '_')
      .replace(/^_|_$/g, '');
  });
});
</script>

{{-- MonkeysJS init for inline edit toggle --}}
@if(!empty($fields))
@verbatim
<script type="module">
import { createApp, setPrefix } from 'monkeysjs';

setPrefix('$m-');

const app = createApp({});
app.mount('#fields-app');

// Native drag-and-drop for field reordering
const tbody = document.getElementById('fields-tbody');
if (tbody) {
  let dragRow = null;

  tbody.querySelectorAll('.bf-drag-handle').forEach(handle => {
    const row = handle.closest('.bf-field-row');
    if (!row) return;
    row.draggable = true;

    handle.addEventListener('mousedown', () => { row.draggable = true; });

    row.addEventListener('dragstart', (e) => {
      dragRow = row;
      row.classList.add('bf-dragging');
      e.dataTransfer.effectAllowed = 'move';
      e.dataTransfer.setData('text/plain', row.dataset.field);
    });

    row.addEventListener('dragend', () => {
      if (dragRow) dragRow.classList.remove('bf-dragging');
      dragRow = null;
      tbody.querySelectorAll('.bf-field-row').forEach(r => r.classList.remove('bf-drag-over'));
    });
  });

  tbody.addEventListener('dragover', (e) => {
    e.preventDefault();
    e.dataTransfer.dropEffect = 'move';

    const target = e.target.closest('.bf-field-row');
    if (!target || target === dragRow) return;

    tbody.querySelectorAll('.bf-field-row').forEach(r => r.classList.remove('bf-drag-over'));
    target.classList.add('bf-drag-over');
  });

  tbody.addEventListener('drop', (e) => {
    e.preventDefault();
    const target = e.target.closest('.bf-field-row');
    if (!target || !dragRow || target === dragRow) return;

    const dragEditRow = dragRow.nextElementSibling?.classList.contains('bf-edit-row')
      ? dragRow.nextElementSibling : null;

    const targetRect = target.getBoundingClientRect();
    const afterTarget = e.clientY > targetRect.top + targetRect.height / 2;

    if (afterTarget) {
      const targetEdit = target.nextElementSibling?.classList.contains('bf-edit-row')
        ? target.nextElementSibling : target;
      targetEdit.after(dragRow);
      if (dragEditRow) dragRow.after(dragEditRow);
    } else {
      target.before(dragRow);
      if (dragEditRow) dragRow.after(dragEditRow);
    }

    const order = [...tbody.querySelectorAll('.bf-field-row')].map(r => r.dataset.field);

    document.getElementById('reorder-input').value = JSON.stringify(order);
    document.getElementById('reorder-form').submit();
  });
}
</script>
@endverbatim
@endif

@push('head')
<style>
/* ── Block Fields Info ───────────────────────────────────────── */
.bf-info {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  padding: 1.125rem 1.5rem;
  background: rgba(20,22,38,0.6);
  border: 1px solid rgba(255,255,255,0.06);
  border-radius: 16px;
  margin-bottom: 1.5rem;
}
.bf-info__icon {
  width: 44px; height: 44px;
  display: flex; align-items: center; justify-content: center;
  background: linear-gradient(135deg, rgba(99,102,241,0.15), rgba(139,92,246,0.1));
  border-radius: 12px;
  color: #818cf8;
  flex-shrink: 0;
}
.bf-info__meta { flex: 1; min-width: 0; }
.bf-info__title {
  font-size: 1rem; font-weight: 600; color: #e2e8f0;
  margin: 0; line-height: 1.3;
}
.bf-info__machine {
  font-size: 0.7rem; color: #64748b;
  font-family: var(--font-mono, monospace);
}

/* ── Fields Table ────────────────────────────────────────────── */
.bf-table-wrap {
  background: rgba(20,22,38,0.6);
  border: 1px solid rgba(255,255,255,0.06);
  border-radius: 16px;
  overflow: hidden;
  margin-bottom: 1.5rem;
}
.bf-table-wrap .admin-table {
  width: 100%;
  border-collapse: collapse;
  font-size: 0.8125rem;
}
.bf-table-wrap .admin-table th {
  text-align: left;
  padding: 0.75rem 1rem;
  font-weight: 600;
  font-size: 0.72rem;
  color: rgba(166,173,200,0.6);
  text-transform: uppercase;
  letter-spacing: 0.05em;
  border-bottom: 1px solid rgba(255,255,255,0.06);
  background: rgba(255,255,255,0.02);
  white-space: nowrap;
}
.bf-table-wrap .admin-table td {
  padding: 0.75rem 1rem;
  color: #cdd6f4;
  border-bottom: 1px solid rgba(255,255,255,0.03);
  vertical-align: middle;
}
.bf-table-wrap .admin-table tr:last-child td {
  border-bottom: none;
}
.bf-table-wrap .admin-table tr:hover td {
  background: rgba(255,255,255,0.015);
}
.bf-table-wrap .admin-table code {
  padding: 0.15rem 0.45rem;
  background: rgba(99,102,241,0.1);
  border-radius: 4px;
  font-size: 0.76rem;
  color: #c4b5fd;
  font-family: 'JetBrains Mono', monospace;
}

/* ── Drag & Drop ─────────────────────────────────────────────── */
.bf-field-row { transition: opacity 0.15s, background 0.15s; }
.bf-field-row.bf-dragging { opacity: 0.3; }
.bf-field-row.bf-drag-over td {
  border-top: 2px solid #818cf8 !important;
}

/* ── Inline Edit Row ─────────────────────────────────────────── */
.bf-edit-row td {
  padding: 1rem 1.25rem !important;
  background: rgba(99,102,241,0.04);
  border-bottom: 1px solid rgba(99,102,241,0.1) !important;
}
.bf-edit-form { }
.bf-edit-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
  gap: 0.75rem;
}
.bf-edit-actions {
  display: flex;
  gap: 0.5rem;
  margin-top: 0.75rem;
  padding-top: 0.75rem;
  border-top: 1px solid rgba(255,255,255,0.04);
}

/* ── Add Field Card ──────────────────────────────────────────── */
.bf-add-card {
  background: rgba(20,22,38,0.4);
  border: 1px solid rgba(255,255,255,0.06);
  border-radius: 16px;
  padding: 1.5rem;
}
.bf-add-card__header {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  font-size: 0.9375rem;
  font-weight: 600;
  color: #e2e8f0;
  margin-bottom: 1.25rem;
  padding-bottom: 0.75rem;
  border-bottom: 1px solid rgba(255,255,255,0.04);
}
.bf-field-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
  gap: 1rem;
}
.bf-add-card__footer {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-top: 1.25rem;
  padding-top: 1rem;
  border-top: 1px solid rgba(255,255,255,0.04);
}

/* ── Read-only Notice ────────────────────────────────────────── */
.bf-readonly-notice {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  padding: 0.875rem 1.25rem;
  background: rgba(59,130,246,0.08);
  border: 1px solid rgba(59,130,246,0.15);
  border-radius: 12px;
  color: #93c5fd;
  font-size: 0.8125rem;
  margin-top: 1rem;
}
</style>
@endpush

@endsection
