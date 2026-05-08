@extends('layouts.admin')
@section('title', 'Import Configuration')

@section('toolbar_actions')
<a href="/admin/config/export" class="btn btn--sm btn--ghost">
  <i data-lucide="download" class="w-4 h-4"></i> Export Config
</a>
@endsection

@section('content')
<div class="admin-content config-page">

  <div class="config-header">
    <div class="config-header__intro">
      <h2 class="config-header__title">
        <i data-lucide="upload" class="w-6 h-6"></i>
        Import Configuration
      </h2>
      <p class="config-header__desc">
        Import configuration from <code>config/sync/</code> or upload a <code>.zip</code> archive.
        Review changes below before applying.
      </p>
    </div>
  </div>

  {{-- Upload Archive --}}
  <div class="config-card">
    <div class="config-card__header">
      <h3 class="config-card__title">
        <i data-lucide="archive" class="w-4 h-4"></i>
        Upload Archive
      </h3>
    </div>
    <div class="config-upload-zone" id="uploadZone">
      <input type="file" id="archiveInput" accept=".zip" hidden>
      <div class="config-upload-zone__content">
        <i data-lucide="upload-cloud" class="w-10 h-10"></i>
        <p><strong>Drop a .zip archive here</strong></p>
        <p class="text-muted">or <button type="button" class="btn btn--xs btn--ghost" id="browseBtnInner">browse</button></p>
      </div>
    </div>
  </div>

  {{-- Diff Preview --}}
  <div class="config-card" id="diffCard">
    <div class="config-card__header">
      <h3 class="config-card__title">
        <i data-lucide="git-compare" class="w-4 h-4"></i>
        Changes Preview
      </h3>
      <div class="diff-stats" id="diffStats">
        @if(!empty($diff))
        @php
          $creates = count(array_filter($diff, fn($d) => $d['status'] === 'create'));
          $updates = count(array_filter($diff, fn($d) => $d['status'] === 'update'));
          $orphans = count(array_filter($diff, fn($d) => $d['status'] === 'orphan'));
        @endphp
        <span class="diff-stat diff-stat--create">+{{ $creates }} new</span>
        <span class="diff-stat diff-stat--update">~{{ $updates }} changed</span>
        <span class="diff-stat diff-stat--orphan">?{{ $orphans }} orphan</span>
        @else
        <span class="diff-stat diff-stat--sync">✓ In sync</span>
        @endif
      </div>
    </div>

    <div class="diff-list" id="diffList">
      @if(!empty($diff))
        @foreach($diff as $key => $entry)
        <div class="diff-item diff-item--{{ $entry['status'] }}">
          <span class="diff-item__icon">
            @if($entry['status'] === 'create')
            <i data-lucide="plus-circle" class="w-4 h-4"></i>
            @elseif($entry['status'] === 'update')
            <i data-lucide="edit-3" class="w-4 h-4"></i>
            @else
            <i data-lucide="help-circle" class="w-4 h-4"></i>
            @endif
          </span>
          <span class="diff-item__key">{{ $key }}</span>
          <span class="diff-item__status">{{ $entry['status'] }}</span>
        </div>
        @endforeach
      @elseif($hasSyncFiles)
        <div class="diff-empty">
          <i data-lucide="check-circle" class="w-8 h-8"></i>
          <p>Configuration is in sync. No changes to apply.</p>
        </div>
      @else
        <div class="diff-empty">
          <i data-lucide="folder-x" class="w-8 h-8"></i>
          <p>No files found in <code>config/sync/</code>. Export first or upload an archive.</p>
        </div>
      @endif
    </div>
  </div>

  {{-- Import Actions --}}
  @if(!empty($diff) || $hasSyncFiles)
  <div class="config-card">
    <div class="config-card__header">
      <h3 class="config-card__title">
        <i data-lucide="play-circle" class="w-4 h-4"></i>
        Apply Changes
      </h3>
    </div>
    <div class="config-import-actions">
      <div class="config-mode-selector">
        <label class="config-mode-option">
          <input type="radio" name="importMode" value="merge" checked>
          <div class="config-mode-option__body">
            <div class="config-mode-option__header">
              <i data-lucide="git-merge" class="w-4 h-4"></i>
              <strong>Merge</strong>
            </div>
            <small>Create new items only. Existing items are left untouched.</small>
          </div>
        </label>

        <label class="config-mode-option">
          <input type="radio" name="importMode" value="overwrite">
          <div class="config-mode-option__body">
            <div class="config-mode-option__header">
              <i data-lucide="replace" class="w-4 h-4"></i>
              <strong>Overwrite</strong>
            </div>
            <small>Create new items and update existing ones to match sync files.</small>
          </div>
        </label>

        <label class="config-mode-option config-mode-option--danger">
          <input type="radio" name="importMode" value="sync">
          <div class="config-mode-option__body">
            <div class="config-mode-option__header">
              <i data-lucide="refresh-cw" class="w-4 h-4"></i>
              <strong>Full Sync</strong>
            </div>
            <small>Make database match sync files exactly. Overwrites existing items.</small>
          </div>
        </label>
      </div>

      <div class="config-import-buttons">
        <button type="button" class="btn btn--primary btn--lg" id="importBtn">
          <i data-lucide="upload" class="w-4 h-4"></i>
          Import Configuration
        </button>
      </div>
    </div>
  </div>
  @endif

  {{-- Sync Files --}}
  @if($hasSyncFiles)
  <div class="config-card">
    <div class="config-card__header">
      <h3 class="config-card__title">
        <i data-lucide="folder-open" class="w-4 h-4"></i>
        Files in config/sync/
      </h3>
      <span class="badge badge--muted">{{ count($syncFiles) }} files</span>
    </div>
    <div class="config-file-list">
      @foreach($syncFiles as $file)
      <div class="config-file-item">
        <i data-lucide="file-code" class="w-3.5 h-3.5"></i>
        <span>{{ $file }}</span>
      </div>
      @endforeach
    </div>
  </div>
  @endif

</div>

@push('head')
<style>
.config-page { padding: 1.5rem 2rem; max-width: 1000px; }

.config-header { margin-bottom: 2rem; }
.config-header__title {
  display: flex; align-items: center; gap: 0.5rem;
  font-size: 1.25rem; font-weight: 700; color: #e2e8f0;
  margin-bottom: 0.5rem;
}
.config-header__desc {
  font-size: 0.85rem; color: #94a3b8; line-height: 1.6;
}
.config-header__desc code {
  background: rgba(99,102,241,0.12); color: #a5b4fc;
  padding: 0.1rem 0.4rem; border-radius: 4px; font-size: 0.8rem;
}

.config-card {
  background: rgba(15,17,28,0.6);
  border: 1px solid rgba(255,255,255,0.06);
  border-radius: 14px; margin-bottom: 1.5rem;
  overflow: hidden;
}
.config-card__header {
  display: flex; align-items: center; justify-content: space-between;
  padding: 1rem 1.25rem;
  border-bottom: 1px solid rgba(255,255,255,0.04);
}
.config-card__title {
  display: flex; align-items: center; gap: 0.4rem;
  font-size: 0.9rem; font-weight: 600; color: #e2e8f0;
}

/* ── Upload Zone ──────────────────────────────────── */
.config-upload-zone {
  padding: 2rem 1.25rem;
  display: flex; align-items: center; justify-content: center;
  cursor: pointer;
}
.config-upload-zone--dragover {
  background: rgba(99,102,241,0.05);
  border-color: rgba(99,102,241,0.3);
}
.config-upload-zone__content {
  text-align: center; color: #64748b;
}
.config-upload-zone__content i { margin-bottom: 0.75rem; color: #4f46e5; }
.config-upload-zone__content strong { color: #94a3b8; font-size: 0.9rem; }
.config-upload-zone__content .text-muted { font-size: 0.78rem; margin-top: 0.25rem; }

/* ── Diff ──────────────────────────────────────────── */
.diff-stats { display: flex; gap: 0.5rem; }
.diff-stat {
  font-size: 0.72rem; font-weight: 600;
  padding: 0.2rem 0.5rem; border-radius: 5px;
}
.diff-stat--create { background: rgba(34,197,94,0.1); color: #4ade80; }
.diff-stat--update { background: rgba(251,191,36,0.1); color: #fbbf24; }
.diff-stat--orphan { background: rgba(100,116,139,0.1); color: #94a3b8; }
.diff-stat--sync { background: rgba(34,197,94,0.1); color: #4ade80; }

.diff-list {
  max-height: 400px; overflow-y: auto;
  padding: 0.5rem 0.75rem;
}
.diff-item {
  display: flex; align-items: center; gap: 0.6rem;
  padding: 0.55rem 0.75rem; border-radius: 8px;
  transition: background 0.15s;
}
.diff-item:hover { background: rgba(255,255,255,0.02); }
.diff-item--create .diff-item__icon { color: #4ade80; }
.diff-item--update .diff-item__icon { color: #fbbf24; }
.diff-item--orphan .diff-item__icon { color: #94a3b8; }
.diff-item__key {
  flex: 1; font-size: 0.82rem; color: #e2e8f0;
  font-family: 'SF Mono', 'Fira Code', monospace;
}
.diff-item__status {
  font-size: 0.68rem; font-weight: 600;
  text-transform: uppercase; letter-spacing: 0.04em;
  padding: 0.15rem 0.4rem; border-radius: 4px;
}
.diff-item--create .diff-item__status { background: rgba(34,197,94,0.1); color: #4ade80; }
.diff-item--update .diff-item__status { background: rgba(251,191,36,0.1); color: #fbbf24; }
.diff-item--orphan .diff-item__status { background: rgba(100,116,139,0.1); color: #94a3b8; }

.diff-empty {
  padding: 2.5rem 1rem; text-align: center; color: #64748b;
}
.diff-empty i { margin-bottom: 0.75rem; color: #475569; }
.diff-empty p { font-size: 0.85rem; }
.diff-empty code {
  background: rgba(99,102,241,0.12); color: #a5b4fc;
  padding: 0.1rem 0.4rem; border-radius: 4px; font-size: 0.8rem;
}

/* ── Import Actions ───────────────────────────────── */
.config-import-actions {
  padding: 1.25rem;
}
.config-mode-selector {
  display: grid; grid-template-columns: repeat(3, 1fr);
  gap: 0.75rem; margin-bottom: 1.25rem;
}
.config-mode-option {
  display: flex; align-items: flex-start; gap: 0.6rem;
  padding: 1rem; border-radius: 10px; cursor: pointer;
  border: 1px solid rgba(255,255,255,0.06);
  background: rgba(255,255,255,0.02);
  transition: all 0.2s;
}
.config-mode-option:hover { border-color: rgba(99,102,241,0.3); background: rgba(99,102,241,0.04); }
.config-mode-option input[type="radio"] { display: none; }
.config-mode-option input:checked + .config-mode-option__body {
  color: #a5b4fc;
}
.config-mode-option:has(input:checked) {
  border-color: rgba(99,102,241,0.5);
  background: rgba(99,102,241,0.08);
  box-shadow: 0 0 0 1px rgba(99,102,241,0.2);
}
.config-mode-option--danger:has(input:checked) {
  border-color: rgba(239,68,68,0.4);
  background: rgba(239,68,68,0.06);
  box-shadow: 0 0 0 1px rgba(239,68,68,0.15);
}
.config-mode-option--danger:has(input:checked) .config-mode-option__body { color: #fca5a5; }
.config-mode-option__body { flex: 1; }
.config-mode-option__header {
  display: flex; align-items: center; gap: 0.35rem; margin-bottom: 0.3rem;
}
.config-mode-option__header strong { font-size: 0.85rem; color: #e2e8f0; }
.config-mode-option__header i { color: #818cf8; }
.config-mode-option--danger .config-mode-option__header i { color: #f87171; }
.config-mode-option small { font-size: 0.72rem; color: #64748b; line-height: 1.4; }
.config-import-buttons {
  display: flex; justify-content: flex-end;
  padding-top: 0.75rem; border-top: 1px solid rgba(255,255,255,0.04);
}

/* ── File List ─────────────────────────────────────── */
.config-file-list {
  max-height: 300px; overflow-y: auto;
  padding: 0.75rem 1.25rem;
  display: grid; grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
  gap: 0.25rem;
}
.config-file-item {
  display: flex; align-items: center; gap: 0.4rem;
  font-size: 0.75rem; color: #94a3b8;
  padding: 0.35rem 0.5rem; border-radius: 6px;
}
.config-file-item i { color: #818cf8; flex-shrink: 0; }

/* ── Toast ──────────────────────────────────────────── */
.config-toast {
  position: fixed; bottom: 2rem; right: 2rem; z-index: 99999;
  padding: 0.75rem 1.25rem; border-radius: 10px;
  font-size: 0.82rem; font-weight: 500;
  background: rgba(99,102,241,0.95); color: #fff;
  box-shadow: 0 8px 30px rgba(0,0,0,0.3);
  animation: configToastIn 0.3s ease;
}
.config-toast--error { background: rgba(239,68,68,0.95); }
.config-toast--success { background: rgba(34,197,94,0.9); }
@keyframes configToastIn {
  from { transform: translateY(20px); opacity: 0; }
  to { transform: translateY(0); opacity: 1; }
}

.spin { animation: spin 1s linear infinite; }
@keyframes spin { from { transform: rotate(0); } to { transform: rotate(360deg); } }
</style>
@endpush

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
  const uploadZone = document.getElementById('uploadZone');
  const archiveInput = document.getElementById('archiveInput');

  // Browse button
  document.getElementById('browseBtnInner')?.addEventListener('click', (e) => {
    e.stopPropagation();
    archiveInput.click();
  });
  uploadZone?.addEventListener('click', () => archiveInput.click());

  // Drag and drop
  ['dragenter', 'dragover'].forEach(ev => {
    uploadZone?.addEventListener(ev, e => { e.preventDefault(); uploadZone.classList.add('config-upload-zone--dragover'); });
  });
  ['dragleave', 'drop'].forEach(ev => {
    uploadZone?.addEventListener(ev, e => { e.preventDefault(); uploadZone.classList.remove('config-upload-zone--dragover'); });
  });
  uploadZone?.addEventListener('drop', e => {
    const file = e.dataTransfer?.files?.[0];
    if (file && file.name.endsWith('.zip')) uploadArchive(file);
  });
  archiveInput?.addEventListener('change', () => {
    if (archiveInput.files?.[0]) uploadArchive(archiveInput.files[0]);
  });

  async function uploadArchive(file) {
    showToast('Uploading archive...');
    const formData = new FormData();
    formData.append('archive', file);

    try {
      const resp = await fetch('/admin/config/import/preview', {
        method: 'POST',
        body: formData,
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
      });
      const data = await resp.json();
      if (!resp.ok) throw new Error(data.error || 'Upload failed');
      showToast('Archive uploaded! Review changes below.', false, true);
      setTimeout(() => window.location.reload(), 1000);
    } catch (err) {
      showToast(err.message, true);
    }
  }

  // Import button
  document.getElementById('importBtn')?.addEventListener('click', async () => {
    const btn = document.getElementById('importBtn');
    const mode = document.querySelector('input[name="importMode"]:checked')?.value ?? 'merge';

    btn.disabled = true;
    btn.innerHTML = '<i data-lucide="loader" class="w-4 h-4 spin"></i> Importing...';
    if (window.lucide) lucide.createIcons();

    try {
      const resp = await CMS.fetch('/admin/config/import', {
        method: 'POST',
        body: JSON.stringify({
          overwrite: mode === 'overwrite' || mode === 'sync',
          sync: mode === 'sync',
        }),
      });
      const data = await resp.json();
      if (!resp.ok) throw new Error(data.error || 'Import failed');

      const summary = data.result?.summary || 'Import complete';
      showToast(summary, false, true);
      setTimeout(() => window.location.reload(), 1500);
    } catch (err) {
      showToast(err.message, true);
      btn.disabled = false;
      btn.innerHTML = '<i data-lucide="upload" class="w-4 h-4"></i> Import Configuration';
      if (window.lucide) lucide.createIcons();
    }
  });

  function showToast(msg, isError = false, isSuccess = false) {
    document.querySelectorAll('.config-toast').forEach(t => t.remove());
    const toast = document.createElement('div');
    toast.className = 'config-toast' + (isError ? ' config-toast--error' : '') + (isSuccess ? ' config-toast--success' : '');
    toast.textContent = msg;
    document.body.appendChild(toast);
    setTimeout(() => { toast.style.opacity = '0'; toast.style.transform = 'translateY(20px)'; toast.style.transition = 'all 0.3s'; setTimeout(() => toast.remove(), 300); }, 3000);
  }
});
</script>
@endpush

@endsection
