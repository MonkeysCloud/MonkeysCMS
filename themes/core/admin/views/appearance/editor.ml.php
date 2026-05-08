@extends('layouts.admin')
@section('title', 'Theme Editor')

@section('toolbar_actions')
<a href="/admin/appearance" class="btn btn--sm btn--ghost">
  <i data-lucide="arrow-left" class="w-4 h-4"></i> Back to Themes
</a>
@endsection

@section('content')
<div class="admin-content editor-page">

  <div class="editor-layout">

    {{-- Sidebar: theme selector + file tree --}}
    <div class="editor-sidebar">
      <div class="editor-sidebar__theme-select">
        <label class="form-label text-xs">Select Theme</label>
        <select class="form-select" id="theme-selector" onchange="window.location.href='/admin/appearance/editor?theme='+this.value">
          <optgroup label="Frontend Themes">
            @foreach($themes ?? [] as $t)
              @if($t->type === 'frontend')
              <option value="{{ $t->name }}" {{ $currentTheme === $t->name ? 'selected' : '' }}>
                {{ $t->label }} ({{ $t->tier }})
              </option>
              @endif
            @endforeach
          </optgroup>
          <optgroup label="Admin Themes">
            @foreach($themes ?? [] as $t)
              @if($t->type === 'admin')
              <option value="{{ $t->name }}" {{ $currentTheme === $t->name ? 'selected' : '' }}>
                {{ $t->label }} ({{ $t->tier }})
              </option>
              @endif
            @endforeach
          </optgroup>
        </select>
      </div>

      @if($theme)
      <div class="editor-sidebar__info">
        <span class="editor-sidebar__tier editor-sidebar__tier--{{ $theme->tier }}">{{ ucfirst($theme->tier) }}</span>
        <span class="text-muted text-xs">v{{ $theme->version }}</span>
        @if($theme->tier === 'core')
        <span class="editor-sidebar__readonly">
          <i data-lucide="lock" class="w-3 h-3"></i> Read-only
        </span>
        @endif
      </div>
      @endif

      <div class="editor-sidebar__files">
        <div class="editor-sidebar__files-header">
          <i data-lucide="folder-tree" class="w-3.5 h-3.5"></i>
          <span>Files</span>
        </div>
        <div class="file-tree" id="file-tree">
          @php
            $grouped = [];
            foreach ($files ?? [] as $f) {
              $dir = dirname($f['path']);
              if ($dir === '.') $dir = '/';
              $grouped[$dir][] = $f;
            }
            ksort($grouped);
          @endphp

          @foreach($grouped as $dir => $dirFiles)
          <div class="file-tree__group">
            <div class="file-tree__dir">
              <i data-lucide="folder" class="w-3 h-3"></i>
              {{ $dir === '/' ? 'Root' : $dir }}
            </div>
            @foreach($dirFiles as $f)
            @php
              $icons = ['css' => 'palette', 'js' => 'braces', 'php' => 'code-2', 'mlc' => 'settings', 'html' => 'file-code', 'json' => 'file-json'];
              $icon = $icons[$f['type']] ?? 'file';
              $isActive = ($selectedFile === $f['path']);
            @endphp
            <a href="/admin/appearance/editor?theme={{ $currentTheme }}&file={{ urlencode($f['path']) }}"
               class="file-tree__file {{ $isActive ? 'file-tree__file--active' : '' }}"
               title="{{ $f['path'] }}">
              <i data-lucide="{{ $icon }}" class="w-3 h-3"></i>
              <span class="file-tree__name">{{ $f['name'] }}</span>
              <span class="file-tree__size">{{ number_format($f['size'] / 1024, 1) }}KB</span>
            </a>
            @endforeach
          </div>
          @endforeach
        </div>
      </div>
    </div>

    {{-- Main: code editor --}}
    <div class="editor-main">
      @if($selectedFile)
      <div class="editor-main__header">
        <div class="editor-main__path">
          <i data-lucide="file-code" class="w-4 h-4"></i>
          <span>{{ $currentTheme }}/{{ $selectedFile }}</span>
        </div>
        <div class="editor-main__actions">
          @if($theme && $theme->tier !== 'core')
          <button class="btn btn--sm btn--primary" id="save-file-btn">
            <i data-lucide="save" class="w-3.5 h-3.5"></i> Save
          </button>
          @else
          <span class="text-muted text-xs">
            <i data-lucide="lock" class="w-3 h-3"></i> Core theme — read-only
          </span>
          @endif
        </div>
      </div>
      <div class="editor-main__editor">
        <textarea id="code-editor" class="code-editor"
                  data-theme="{{ $currentTheme }}"
                  data-file="{{ $selectedFile }}"
                  spellcheck="false"
                  {{ ($theme && $theme->tier === 'core') ? 'readonly' : '' }}
        >{{ $fileContent }}</textarea>
      </div>
      @else
      <div class="editor-main__empty">
        <i data-lucide="file-code-2" class="w-12 h-12"></i>
        <h3>Select a file to edit</h3>
        <p>Choose a file from the sidebar to view or edit its contents.</p>
      </div>
      @endif
    </div>

  </div>

</div>

@push('head')
<style>
.editor-page { padding: 0; height: calc(100vh - 80px); overflow: hidden; }

.editor-layout {
  display: flex; height: 100%;
}

/* ── Sidebar ─────────────────────────────────────────────── */
.editor-sidebar {
  width: 280px; flex-shrink: 0;
  background: rgba(10,12,20,0.7);
  border-right: 1px solid rgba(255,255,255,0.06);
  display: flex; flex-direction: column;
  overflow: hidden;
}
.editor-sidebar__theme-select {
  padding: 1rem; border-bottom: 1px solid rgba(255,255,255,0.04);
}
.editor-sidebar__info {
  padding: 0.5rem 1rem;
  display: flex; align-items: center; gap: 0.5rem;
  border-bottom: 1px solid rgba(255,255,255,0.04);
}
.editor-sidebar__tier {
  font-size: 0.6rem; font-weight: 700;
  text-transform: uppercase; letter-spacing: 0.05em;
  padding: 0.1rem 0.4rem; border-radius: 3px;
}
.editor-sidebar__tier--core { background: rgba(59,130,246,0.15); color: #60a5fa; }
.editor-sidebar__tier--contrib { background: rgba(168,85,247,0.15); color: #c084fc; }
.editor-sidebar__tier--custom { background: rgba(34,197,94,0.15); color: #4ade80; }
.editor-sidebar__readonly {
  display: flex; align-items: center; gap: 0.2rem;
  font-size: 0.65rem; color: #fbbf24;
  background: rgba(251,191,36,0.1);
  padding: 0.1rem 0.4rem; border-radius: 3px;
}

.editor-sidebar__files { flex: 1; overflow-y: auto; }
.editor-sidebar__files-header {
  display: flex; align-items: center; gap: 0.4rem;
  padding: 0.6rem 1rem; font-size: 0.72rem; font-weight: 600;
  color: #94a3b8; text-transform: uppercase; letter-spacing: 0.04em;
}

/* ── File Tree ───────────────────────────────────────────── */
.file-tree__group { margin-bottom: 0.25rem; }
.file-tree__dir {
  display: flex; align-items: center; gap: 0.4rem;
  padding: 0.35rem 1rem; font-size: 0.72rem; font-weight: 600;
  color: #818cf8;
}
.file-tree__file {
  display: flex; align-items: center; gap: 0.35rem;
  padding: 0.3rem 1rem 0.3rem 1.75rem;
  font-size: 0.72rem; color: #94a3b8;
  text-decoration: none; border-radius: 0;
  transition: all 0.15s;
  border-left: 2px solid transparent;
}
.file-tree__file:hover {
  background: rgba(255,255,255,0.03);
  color: #e2e8f0;
}
.file-tree__file--active {
  background: rgba(99,102,241,0.08);
  color: #a5b4fc;
  border-left-color: #818cf8;
}
.file-tree__name { flex: 1; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.file-tree__size { font-size: 0.6rem; color: #475569; }

/* ── Form Controls ───────────────────────────────────────── */
.form-select {
  width: 100%; padding: 0.45rem 0.7rem;
  background: rgba(15,17,28,0.6);
  border: 1px solid rgba(255,255,255,0.08);
  border-radius: 8px; color: #e2e8f0;
  font-size: 0.78rem;
}
.form-select:focus {
  outline: none;
  border-color: rgba(99,102,241,0.5);
}

/* ── Main Editor ─────────────────────────────────────────── */
.editor-main {
  flex: 1; display: flex; flex-direction: column;
  overflow: hidden;
}
.editor-main__header {
  display: flex; align-items: center; justify-content: space-between;
  padding: 0.6rem 1rem;
  background: rgba(15,17,28,0.5);
  border-bottom: 1px solid rgba(255,255,255,0.04);
}
.editor-main__path {
  display: flex; align-items: center; gap: 0.4rem;
  font-size: 0.78rem; color: #94a3b8;
  font-family: 'JetBrains Mono', 'Fira Code', monospace;
}
.editor-main__actions { display: flex; gap: 0.5rem; }

.editor-main__editor { flex: 1; overflow: hidden; }
.code-editor {
  width: 100%; height: 100%;
  padding: 1rem 1.25rem;
  background: rgba(10,12,20,0.5);
  border: none; outline: none;
  color: #e2e8f0; resize: none;
  font-family: 'JetBrains Mono', 'Fira Code', 'SF Mono', 'Cascadia Code', monospace;
  font-size: 0.82rem; line-height: 1.7;
  tab-size: 2;
  white-space: pre;
  overflow: auto;
}
.code-editor:focus {
  background: rgba(10,12,20,0.7);
}
.code-editor[readonly] {
  opacity: 0.7; cursor: not-allowed;
}

.editor-main__empty {
  flex: 1; display: flex; flex-direction: column;
  align-items: center; justify-content: center;
  color: rgba(255,255,255,0.15); gap: 0.5rem;
}
.editor-main__empty h3 {
  font-size: 1rem; font-weight: 500; color: rgba(255,255,255,0.3);
}
.editor-main__empty p {
  font-size: 0.8rem; color: rgba(255,255,255,0.15);
}

/* ── Toast ───────────────────────────────────────────────── */
.editor-toast {
  position: fixed; bottom: 2rem; right: 2rem; z-index: 99999;
  padding: 0.75rem 1.25rem; border-radius: 10px;
  font-size: 0.82rem; font-weight: 500;
  background: rgba(34,197,94,0.95); color: #fff;
  box-shadow: 0 8px 30px rgba(0,0,0,0.3);
  animation: editorToastIn 0.3s ease;
}
.editor-toast--error { background: rgba(239,68,68,0.95); }
@keyframes editorToastIn {
  from { transform: translateY(20px); opacity: 0; }
  to { transform: translateY(0); opacity: 1; }
}
</style>
<link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
@endpush

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
  const editor = document.getElementById('code-editor');
  const saveBtn = document.getElementById('save-file-btn');

  if (!editor || !saveBtn) return;

  // Tab support in textarea
  editor.addEventListener('keydown', (e) => {
    if (e.key === 'Tab') {
      e.preventDefault();
      const start = editor.selectionStart;
      const end = editor.selectionEnd;
      editor.value = editor.value.substring(0, start) + '  ' + editor.value.substring(end);
      editor.selectionStart = editor.selectionEnd = start + 2;
    }

    // Ctrl+S / Cmd+S
    if ((e.ctrlKey || e.metaKey) && e.key === 's') {
      e.preventDefault();
      saveBtn.click();
    }
  });

  // Save handler
  saveBtn.addEventListener('click', async () => {
    const theme = editor.dataset.theme;
    const file = editor.dataset.file;
    const content = editor.value;

    saveBtn.disabled = true;
    saveBtn.innerHTML = '<i data-lucide="loader" class="w-3.5 h-3.5 spin"></i> Saving...';
    if (window.lucide) lucide.createIcons();

    try {
      const resp = await CMS.fetch('/admin/appearance/editor/save', {
        method: 'POST',
        body: JSON.stringify({ theme, file, content }),
      });

      const data = await resp.json();
      if (!resp.ok) throw new Error(data.error || 'Failed to save');

      showEditorToast(data.message || 'File saved!');
    } catch (err) {
      showEditorToast(err.message, true);
    } finally {
      saveBtn.disabled = false;
      saveBtn.innerHTML = '<i data-lucide="save" class="w-3.5 h-3.5"></i> Save';
      if (window.lucide) lucide.createIcons();
    }
  });

  function showEditorToast(message, isError = false) {
    const toast = document.createElement('div');
    toast.className = 'editor-toast' + (isError ? ' editor-toast--error' : '');
    toast.textContent = message;
    document.body.appendChild(toast);
    setTimeout(() => {
      toast.style.opacity = '0';
      toast.style.transform = 'translateY(20px)';
      toast.style.transition = 'all 0.3s';
      setTimeout(() => toast.remove(), 300);
    }, 3000);
  }
});
</script>
@endpush

@endsection
