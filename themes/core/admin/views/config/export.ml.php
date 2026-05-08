@extends('layouts.admin')
@section('title', 'Export Configuration')

@section('toolbar_actions')
<a href="/admin/config/import" class="btn btn--sm btn--ghost">
  <i data-lucide="upload" class="w-4 h-4"></i> Import Config
</a>
@endsection

@section('content')
<div class="admin-content config-page">

  <div class="config-header">
    <div class="config-header__intro">
      <h2 class="config-header__title">
        <i data-lucide="download" class="w-6 h-6"></i>
        Export Configuration
      </h2>
      <p class="config-header__desc">
        Export your site configuration to <code>config/sync/</code> as individual <code>.mlc</code> files.
        Each config item becomes its own file — perfect for version control.
      </p>
    </div>
  </div>

  {{-- Section Picker --}}
  <div class="config-card">
    <div class="config-card__header">
      <h3 class="config-card__title">
        <i data-lucide="layers" class="w-4 h-4"></i>
        Select Sections
      </h3>
      <button type="button" class="btn btn--xs btn--ghost" id="toggleAll">Select All</button>
    </div>

    <div class="config-sections" id="sectionList">
      @foreach($collectors as $key => $collector)
      <label class="config-section-item">
        <input type="checkbox" name="sections[]" value="{{ $key }}" checked class="section-check">
        <div class="config-section-item__body">
          <span class="config-section-item__icon">
            @if($key === 'settings')
            <i data-lucide="settings" class="w-5 h-5"></i>
            @elseif($key === 'content_type')
            <i data-lucide="database" class="w-5 h-5"></i>
            @elseif($key === 'vocabulary')
            <i data-lucide="tags" class="w-5 h-5"></i>
            @elseif($key === 'menu')
            <i data-lucide="menu" class="w-5 h-5"></i>
            @elseif($key === 'role')
            <i data-lucide="shield" class="w-5 h-5"></i>
            @elseif($key === 'plugin')
            <i data-lucide="puzzle" class="w-5 h-5"></i>
            @else
            <i data-lucide="file-cog" class="w-5 h-5"></i>
            @endif
          </span>
          <div>
            <strong>{{ $collector->getLabel() }}</strong>
            <span class="config-section-item__key">{{ $key }}</span>
          </div>
        </div>
        <span class="config-section-item__check"></span>
      </label>
      @endforeach
    </div>
  </div>

  {{-- Export Actions --}}
  <div class="config-card">
    <div class="config-card__header">
      <h3 class="config-card__title">
        <i data-lucide="hard-drive-download" class="w-4 h-4"></i>
        Export Options
      </h3>
    </div>

    <div class="config-actions-grid">
      <div class="config-action-option">
        <div class="config-action-option__info">
          <h4>Export to <code>config/sync/</code></h4>
          <p>Write .mlc files to the sync directory. Ideal for git version control workflows.</p>
        </div>
        <button type="button" class="btn btn--primary" id="exportSync">
          <i data-lucide="folder-sync" class="w-4 h-4"></i>
          Export to Sync
        </button>
      </div>

      <div class="config-action-option">
        <div class="config-action-option__info">
          <h4>Download Archive</h4>
          <p>Download a .zip file for migration or backup. Can be imported on another site.</p>
        </div>
        <button type="button" class="btn btn--ghost" id="downloadArchive">
          <i data-lucide="archive" class="w-4 h-4"></i>
          Download .zip
        </button>
      </div>
    </div>
  </div>

  @if($hasSyncFiles)
  {{-- Current Sync Files --}}
  <div class="config-card">
    <div class="config-card__header">
      <h3 class="config-card__title">
        <i data-lucide="folder-open" class="w-4 h-4"></i>
        Current Sync Files
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

/* ── Section Picker ───────────────────────────────── */
.config-sections { padding: 0.5rem; }
.config-section-item {
  display: flex; align-items: center; gap: 0.75rem;
  padding: 0.75rem 1rem; border-radius: 10px;
  cursor: pointer; transition: background 0.15s;
}
.config-section-item:hover { background: rgba(255,255,255,0.03); }
.config-section-item input[type="checkbox"] { display: none; }
.config-section-item__body {
  display: flex; align-items: center; gap: 0.75rem;
  flex: 1;
}
.config-section-item__body strong {
  font-size: 0.85rem; color: #e2e8f0; font-weight: 600;
}
.config-section-item__icon {
  width: 36px; height: 36px; border-radius: 8px;
  display: flex; align-items: center; justify-content: center;
  background: rgba(99,102,241,0.1); color: #818cf8;
  transition: all 0.2s;
}
.config-section-item__key {
  display: block; font-size: 0.7rem; color: #64748b; margin-top: 0.1rem;
}
.config-section-item__check {
  width: 20px; height: 20px; border-radius: 5px;
  border: 2px solid rgba(255,255,255,0.1);
  transition: all 0.2s; flex-shrink: 0;
  position: relative;
}
.config-section-item input:checked ~ .config-section-item__check {
  background: #6366f1; border-color: #6366f1;
}
.config-section-item input:checked ~ .config-section-item__check::after {
  content: '✓'; position: absolute; inset: 0;
  display: flex; align-items: center; justify-content: center;
  color: #fff; font-size: 0.7rem; font-weight: 700;
}
.config-section-item input:checked ~ .config-section-item__body .config-section-item__icon {
  background: rgba(99,102,241,0.2); color: #a5b4fc;
}

/* ── Export Actions ────────────────────────────────── */
.config-actions-grid { padding: 1.25rem; display: grid; gap: 1rem; }
.config-action-option {
  display: flex; align-items: center; justify-content: space-between;
  gap: 1.5rem; padding: 1.25rem;
  background: rgba(255,255,255,0.02);
  border: 1px solid rgba(255,255,255,0.04);
  border-radius: 10px;
}
.config-action-option h4 {
  font-size: 0.88rem; font-weight: 600; color: #e2e8f0;
  margin-bottom: 0.25rem;
}
.config-action-option h4 code {
  background: rgba(99,102,241,0.12); color: #a5b4fc;
  padding: 0.1rem 0.35rem; border-radius: 3px; font-size: 0.82rem;
}
.config-action-option p {
  font-size: 0.78rem; color: #94a3b8; line-height: 1.5;
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
  const checks = document.querySelectorAll('.section-check');
  const toggleBtn = document.getElementById('toggleAll');
  let allChecked = true;

  toggleBtn?.addEventListener('click', () => {
    allChecked = !allChecked;
    checks.forEach(c => c.checked = allChecked);
    toggleBtn.textContent = allChecked ? 'Deselect All' : 'Select All';
  });

  function getSelected() {
    return [...checks].filter(c => c.checked).map(c => c.value);
  }

  // Export to sync/
  document.getElementById('exportSync')?.addEventListener('click', async () => {
    const btn = document.getElementById('exportSync');
    const sections = getSelected();
    if (!sections.length) { showToast('Select at least one section', true); return; }

    btn.disabled = true;
    btn.innerHTML = '<i data-lucide="loader" class="w-4 h-4 spin"></i> Exporting...';
    if (window.lucide) lucide.createIcons();

    try {
      const resp = await CMS.fetch('/admin/config/export', {
        method: 'POST',
        body: JSON.stringify({ sections, format: 'sync' }),
      });
      const data = await resp.json();
      if (!resp.ok) throw new Error(data.error || 'Export failed');
      showToast(data.message || 'Export complete!', false, true);
      setTimeout(() => window.location.reload(), 1200);
    } catch (err) {
      showToast(err.message, true);
    } finally {
      btn.disabled = false;
      btn.innerHTML = '<i data-lucide="folder-sync" class="w-4 h-4"></i> Export to Sync';
      if (window.lucide) lucide.createIcons();
    }
  });

  // Archive download — navigate to GET route
  document.getElementById('downloadArchive')?.addEventListener('click', () => {
    const sections = getSelected();
    if (!sections.length) { showToast('Select at least one section', true); return; }
    const url = '/admin/config/export/archive?sections=' + encodeURIComponent(sections.join(','));
    window.location.href = url;
  });

  function showToast(msg, isError = false, isSuccess = false) {
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
