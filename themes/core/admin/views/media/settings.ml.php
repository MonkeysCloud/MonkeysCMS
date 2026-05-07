@extends('layouts.admin')

@section('title', 'Media Settings')

@section('breadcrumb')
<a href="/admin">Dashboard</a>
<span class="admin-breadcrumb__sep">/</span>
<a href="/admin/media">Media</a>
<span class="admin-breadcrumb__sep">/</span>
<span>Settings</span>
@endsection

@section('content')
<div class="admin-content">

  @php $saved = $_GET['saved'] ?? null; @endphp
  @if($saved)
  <div class="admin-alert admin-alert--success">
    <i data-lucide="check-circle" class="w-4 h-4"></i>
    Media settings saved successfully.
  </div>
  @endif

  {{-- ═══ Form Builder Output ═══ --}}
  {!! $formHtml !!}

  {{-- ═══ Additional Cards (outside form) ═══ --}}
  <div class="admin-settings-grid" style="margin-top: 1.25rem;">

    {{-- Image Styles Manager --}}
    <div class="admin-card">
      <div class="admin-card__header" style="display:flex; align-items:center; justify-content:space-between;">
        <h3 class="admin-card__title">
          <i data-lucide="crop" class="w-5 h-5"></i> Image Styles
        </h3>
        <button type="button" class="btn btn--sm btn--primary" id="add-style-btn">
          <i data-lucide="plus" class="w-4 h-4"></i> Add Style
        </button>
      </div>
      <div class="admin-card__body">
        <table class="admin-table" id="styles-table">
          <thead>
            <tr>
              <th>Name</th>
              <th>Width (px)</th>
              <th>Height (px)</th>
              <th>Fit</th>
              <th style="width:80px;">Actions</th>
            </tr>
          </thead>
          <tbody id="styles-tbody">
            {{-- Rows populated by JS --}}
          </tbody>
        </table>
        <p class="form-hint" style="margin-top:0.75rem;">
          <strong>Fit modes:</strong>
          <code>cover</code> = crop to fill exact size,
          <code>contain</code> = fit within bounds preserving ratio,
          <code>stretch</code> = force exact dimensions.
          Default styles: thumb (150×150 cover), medium (600×600 contain), large (1200×1200 contain).
        </p>
      </div>
    </div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
  // Load existing styles from PHP
  const existingStyles = @json($styles);
  const defaultStyles = {
    thumb:  { width: 150,  height: 150,  fit: 'cover' },
    medium: { width: 600,  height: 600,  fit: 'contain' },
    large:  { width: 1200, height: 1200, fit: 'contain' },
  };

  // Merge defaults with existing
  let styles = Object.keys(existingStyles).length > 0
    ? { ...existingStyles }
    : { ...defaultStyles };

  const tbody = document.getElementById('styles-tbody');
  const addBtn = document.getElementById('add-style-btn');

  // Find the main settings form and inject hidden input
  const mainForm = document.getElementById('media-settings-form')
                || document.querySelector('form[action="/admin/media/settings"]');
  let hiddenInput = null;
  if (mainForm) {
    hiddenInput = document.createElement('input');
    hiddenInput.type = 'hidden';
    hiddenInput.name = 'image_styles';
    mainForm.appendChild(hiddenInput);
  }

  function syncHiddenInput() {
    if (hiddenInput) {
      hiddenInput.value = JSON.stringify(styles);
    }
  }

  function renderTable() {
    tbody.innerHTML = '';
    const names = Object.keys(styles);

    if (names.length === 0) {
      tbody.innerHTML = '<tr><td colspan="5" style="text-align:center; color:var(--text-muted); padding:2rem;">No image styles defined. Click "Add Style" to create one.</td></tr>';
      syncHiddenInput();
      return;
    }

    names.forEach(name => {
      const s = styles[name];
      const tr = document.createElement('tr');
      tr.innerHTML = `
        <td><code style="background:var(--bg-tertiary); padding:2px 8px; border-radius:4px;">${esc(name)}</code></td>
        <td>${parseInt(s.width)}</td>
        <td>${parseInt(s.height)}</td>
        <td><span class="status-badge status-badge--${s.fit === 'cover' ? 'info' : s.fit === 'contain' ? 'success' : 'warning'}">${esc(s.fit)}</span></td>
        <td>
          <div style="display:flex; gap:4px;">
            <button type="button" class="btn btn--xs btn--ghost" title="Edit" data-edit="${esc(name)}">
              <i data-lucide="pencil" class="w-3.5 h-3.5"></i>
            </button>
            <button type="button" class="btn btn--xs btn--ghost btn--danger" title="Delete" data-delete="${esc(name)}">
              <i data-lucide="trash-2" class="w-3.5 h-3.5"></i>
            </button>
          </div>
        </td>
      `;
      tbody.appendChild(tr);
    });

    syncHiddenInput();
    if (window.lucide) lucide.createIcons();
  }

  // --- Add / Edit Dialog (uses global CMS.modal) ---
  function showStyleDialog(existingName = null) {
    const isEdit = existingName !== null;
    const s = isEdit ? styles[existingName] : { width: 300, height: 300, fit: 'contain' };

    const modal = CMS.modal({
      title: isEdit ? 'Edit Style' : 'Add Image Style',
      size: 'sm',
      body: `
        <div style="display:flex; flex-direction:column; gap:1rem;">
          <div class="form-group">
            <label class="form-label">Style Name</label>
            <input type="text" class="form-input" id="style-name" value="${esc(existingName || '')}"
                   placeholder="e.g. hero, card, avatar" ${isEdit ? 'readonly style="opacity:0.7"' : ''}
                   pattern="[a-z0-9_\\-]+" title="Lowercase letters, numbers, hyphens, underscores only">
            <small class="form-hint">Lowercase, no spaces. Used in code: <code>styleUrl($media, 'name')</code></small>
          </div>
          <div style="display:grid; grid-template-columns:1fr 1fr; gap:1rem;">
            <div class="form-group">
              <label class="form-label">Width (px)</label>
              <input type="number" class="form-input" id="style-width" value="${s.width}" min="1" max="4096">
            </div>
            <div class="form-group">
              <label class="form-label">Height (px)</label>
              <input type="number" class="form-input" id="style-height" value="${s.height}" min="1" max="4096">
            </div>
          </div>
          <div class="form-group">
            <label class="form-label">Fit Mode</label>
            <select class="form-input" id="style-fit">
              <option value="cover" ${s.fit === 'cover' ? 'selected' : ''}>Cover — crop to fill exact size</option>
              <option value="contain" ${s.fit === 'contain' ? 'selected' : ''}>Contain — fit within bounds</option>
              <option value="stretch" ${s.fit === 'stretch' ? 'selected' : ''}>Stretch — force exact dimensions</option>
            </select>
          </div>
        </div>
      `,
      footer: [
        { label: 'Cancel', class: 'btn btn--ghost', action: 'close' },
        { label: isEdit ? 'Save Changes' : 'Add Style', class: 'btn btn--primary', action: () => {
          const nameInput = modal.body.querySelector('#style-name');
          const name = nameInput.value.trim().toLowerCase().replace(/[^a-z0-9_-]/g, '');
          const width = parseInt(modal.body.querySelector('#style-width').value) || 300;
          const height = parseInt(modal.body.querySelector('#style-height').value) || 300;
          const fit = modal.body.querySelector('#style-fit').value;

          if (!name) { nameInput.focus(); return; }
          if (!isEdit && styles[name]) {
            alert('A style with that name already exists.');
            return;
          }

          styles[name] = { width, height, fit };
          renderTable();
          modal.close();
        }},
      ],
    });

    if (!isEdit) {
      setTimeout(() => modal.body.querySelector('#style-name')?.focus(), 50);
    }
  }

  // Event delegation
  addBtn.addEventListener('click', () => showStyleDialog());

  tbody.addEventListener('click', e => {
    const editBtn = e.target.closest('[data-edit]');
    const deleteBtn = e.target.closest('[data-delete]');

    if (editBtn) {
      showStyleDialog(editBtn.dataset.edit);
    } else if (deleteBtn) {
      const name = deleteBtn.dataset.delete;
      CMS.confirm({
        title: 'Delete Image Style',
        message: `Are you sure you want to delete the <strong>"${esc(name)}"</strong> style? Existing derivatives will remain on disk.`,
        confirmLabel: 'Delete',
        confirmClass: 'btn btn--danger',
        onConfirm: () => {
          delete styles[name];
          renderTable();
        },
      });
    }
  });

  function esc(s) { const d = document.createElement('div'); d.textContent = s; return d.innerHTML; }

  // Initial render
  renderTable();
});
</script>
@endpush

    {{-- Disk Usage --}}
    <div class="admin-card">
      <div class="admin-card__header">
        <h3 class="admin-card__title">
          <i data-lucide="pie-chart" class="w-5 h-5"></i> Disk Usage
        </h3>
      </div>
      <div class="admin-card__body">
        <div class="media-disk-stats">
          <div class="media-disk-stat">
            <span class="media-disk-stat__value">{{ $diskUsage['formatted_size'] }}</span>
            <span class="media-disk-stat__label">Total Size</span>
          </div>
          <div class="media-disk-stat">
            <span class="media-disk-stat__value">{{ $diskUsage['count'] }}</span>
            <span class="media-disk-stat__label">Total Files</span>
          </div>
          <div class="media-disk-stat">
            <span class="media-disk-stat__value">{{ $diskUsage['by_type']['images'] ?? 0 }}</span>
            <span class="media-disk-stat__label">Images</span>
          </div>
          <div class="media-disk-stat">
            <span class="media-disk-stat__value">{{ $diskUsage['by_type']['videos'] ?? 0 }}</span>
            <span class="media-disk-stat__label">Videos</span>
          </div>
        </div>
      </div>
    </div>

  </div>

</div>
@endsection
