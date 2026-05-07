{{-- ═══ Mosaic Visual Editor — Full-Screen Layout ═══ --}}
{{-- Standalone layout: no admin sidebar/header, three-panel UI --}}
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="robots" content="noindex, nofollow">
  <meta name="csrf-token" content="{{ $cms['csrf_token'] ?? '' }}">
  <title>Mosaic Editor — {{ $node->title }}</title>

  {{-- Admin base styles (shared with admin layout) --}}
  <link rel="stylesheet" href="{{ vite_asset('themes/core/admin/css/admin.css') }}">

  {{-- Global CSS (injected by ThemeResolverMiddleware — includes auto-discovered blocks.css) --}}
  {!! $cms_head ?? '' !!}

  {{-- Mosaic editor styles --}}
  <link rel="stylesheet" href="/themes/core/admin/css/mosaic-editor.css?v={{ time() }}">

  {{-- Lucide Icons --}}
  <script src="https://unpkg.com/lucide@latest"></script>
</head>
<body class="mosaic-app">

{{-- ═══ Global CMS namespace (mirrors admin layout) ═══ --}}
<script>
window.CMS = window.CMS || {};
CMS.csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
CMS.fetch = function(url, options = {}) {
  options.headers = Object.assign({
    'X-CSRF-TOKEN': CMS.csrfToken,
  }, options.headers || {});
  if (options.body && typeof options.body === 'string' && !options.headers['Content-Type']) {
    options.headers['Content-Type'] = 'application/json';
  }
  return fetch(url, options);
};
</script>

{{-- ═══ Toolbar (fixed top) ═══ --}}
<header class="me-toolbar" id="mosaic-toolbar">
  <div class="me-toolbar__left">
    <a href="/admin/content/{{ $node->id }}/edit" class="me-toolbar__back" title="Back to content editor">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m15 18-6-6 6-6"/></svg>
    </a>
    <div class="me-toolbar__title">
      <span class="me-toolbar__node-title">{{ $node->title }}</span>
      <span class="me-toolbar__badge">{{ $node->content_type }}</span>
    </div>
  </div>

  <div class="me-toolbar__center">
    {{-- Undo / Redo --}}
    <div class="me-toolbar__group">
      <button class="me-toolbar__btn" id="btn-undo" onclick="MosaicEditor.undo()" disabled title="Undo (⌘Z)">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="1 4 1 10 7 10"/><path d="M3.51 15a9 9 0 1 0 2.13-9.36L1 10"/></svg>
      </button>
      <button class="me-toolbar__btn" id="btn-redo" onclick="MosaicEditor.redo()" disabled title="Redo (⌘⇧Z)">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="23 4 23 10 17 10"/><path d="M20.49 15a9 9 0 1 1-2.13-9.36L23 10"/></svg>
      </button>
    </div>

    {{-- Device Preview --}}
    <div class="me-toolbar__group">
      <button class="me-toolbar__btn is-active" data-device="desktop" onclick="MosaicEditor.setDevice('desktop')" title="Desktop">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect width="20" height="14" x="2" y="3" rx="2"/><line x1="8" x2="16" y1="21" y2="21"/><line x1="12" x2="12" y1="17" y2="21"/></svg>
      </button>
      <button class="me-toolbar__btn" data-device="tablet" onclick="MosaicEditor.setDevice('tablet')" title="Tablet">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect width="16" height="20" x="4" y="2" rx="2"/><line x1="12" x2="12.01" y1="18" y2="18"/></svg>
      </button>
      <button class="me-toolbar__btn" data-device="mobile" onclick="MosaicEditor.setDevice('mobile')" title="Mobile">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect width="14" height="20" x="5" y="2" rx="2"/><line x1="12" x2="12.01" y1="18" y2="18"/></svg>
      </button>
    </div>

    {{-- Zoom Controls --}}
    <div class="me-toolbar__group">
      <select class="me-toolbar__select" id="canvas-zoom" onchange="MosaicEditor.setZoom(this.value)" title="Canvas Zoom">
        <option value="50">50%</option>
        <option value="75">75%</option>
        <option value="100" selected>100%</option>
      </select>
    </div>

    {{-- Theme Switcher --}}
    @if(!empty($availableThemes) && count($availableThemes) > 0)
    <div class="me-toolbar__group">
      <select class="me-toolbar__select" id="theme-preview-select" onchange="MosaicEditor.switchTheme(this.value)" title="Preview Theme">
        @foreach($availableThemes as $t)
          <option value="{{ $t['name'] }}" {{ $t['isActive'] ? 'selected' : '' }}>
            {{ $t['label'] }}
          </option>
        @endforeach
      </select>
    </div>
    @endif
  </div>

  <div class="me-toolbar__right">
    {{-- Revision --}}
    <div class="me-toolbar__revision" id="revision-trigger">
      <button class="me-toolbar__btn me-toolbar__btn--rev" onclick="MosaicEditor.toggleRevisions()" title="Revision history">
        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
        <span id="mosaic-revision-label">Rev {{ $mosaic ? $mosaic->revision : 0 }}</span>
      </button>
      <div class="me-revisions-dropdown" id="revisions-dropdown">
        <div class="me-revisions-dropdown__header">Revision History</div>
        <div class="me-revisions-dropdown__list" id="revisions-list">
          <div class="me-revisions-dropdown__loading">Loading…</div>
        </div>
      </div>
    </div>

    {{-- Save status --}}
    <span class="me-toolbar__status" id="mosaic-save-status">
      <span class="me-status-dot me-status-dot--saved"></span> Saved
    </span>
  </div>
</header>

{{-- ═══ Main Editor Body ═══ --}}
<div class="me-body"
     id="mosaic-editor"
     data-node-id="{{ $node->id }}"
     data-content-type="{{ $node->content_type }}"
     data-sections='{!! htmlspecialchars(json_encode($sections ?: []), ENT_QUOTES, "UTF-8") !!}'
     data-block-types='{!! htmlspecialchars(json_encode($blockTypesFlat ?? []), ENT_QUOTES, "UTF-8") !!}'
     data-grouped-blocks='{!! htmlspecialchars(json_encode($blockTypes ?? []), ENT_QUOTES, "UTF-8") !!}'
     data-layouts='{!! htmlspecialchars(json_encode($layouts ?? []), ENT_QUOTES, "UTF-8") !!}'
     data-node-fields='{!! htmlspecialchars(json_encode($nodeFields ?? []), ENT_QUOTES, "UTF-8") !!}'
     data-revision="{{ $mosaic ? $mosaic->revision : 0 }}"
     data-front-css='{!! htmlspecialchars(json_encode($frontCssUrls ?? []), ENT_QUOTES, "UTF-8") !!}'
     data-active-theme="{{ $activeThemeName ?? 'front' }}">

  {{-- ─── Left Sidebar ─── --}}
  <aside class="me-left" id="me-left-sidebar">
    <div class="me-left__tabs">
      <button class="me-left__tab is-active" data-tab="sections" onclick="MosaicEditor.switchLeftTab('sections')">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect width="18" height="18" x="3" y="3" rx="2"/><line x1="3" x2="21" y1="9" y2="9"/><line x1="3" x2="21" y1="15" y2="15"/></svg>
        Sections
      </button>
      <button class="me-left__tab" data-tab="blocks" onclick="MosaicEditor.switchLeftTab('blocks')">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m12 3-1.9 5.8a2 2 0 0 1-1.3 1.3L3 12l5.8 1.9a2 2 0 0 1 1.3 1.3L12 21l1.9-5.8a2 2 0 0 1 1.3-1.3L21 12l-5.8-1.9a2 2 0 0 1-1.3-1.3Z"/></svg>
        Blocks
      </button>
    </div>

    {{-- Sections Panel --}}
    <div class="me-left__panel is-active" id="panel-sections">
      <div class="me-left__panel-body" id="sections-panel-body"></div>
    </div>

    {{-- Blocks Panel --}}
    <div class="me-left__panel" id="panel-blocks">
      <div class="me-left__search">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
        <input type="text" placeholder="Search blocks…" oninput="MosaicEditor.filterBlocks(this.value)" id="block-search-input">
      </div>
      <div class="me-left__panel-body" id="blocks-panel-body"></div>
    </div>
  </aside>

  {{-- ─── Canvas (center) ─── --}}
  <main class="me-canvas" id="me-canvas">
    <div class="me-canvas__device-frame" id="canvas-device-frame">
      <mosaic-canvas id="mosaic-canvas-content"></mosaic-canvas>
    </div>
  </main>

  {{-- ─── Right Sidebar (Block Inspector / Section Settings) ─── --}}
  <aside class="me-right" id="me-right-sidebar">
    <div class="me-right__header" id="right-header">
      <span class="me-right__title" id="right-title">Inspector</span>
      <button class="me-right__close" onclick="MosaicEditor.closeRight()" title="Close">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" x2="6" y1="6" y2="18"/><line x1="6" x2="18" y1="6" y2="18"/></svg>
      </button>
    </div>
    <div class="me-right__body" id="right-body">
      <div class="me-right__empty">
        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" opacity="0.3"><path d="m12 3-1.9 5.8a2 2 0 0 1-1.3 1.3L3 12l5.8 1.9a2 2 0 0 1 1.3 1.3L12 21l1.9-5.8a2 2 0 0 1 1.3-1.3L21 12l-5.8-1.9a2 2 0 0 1-1.3-1.3Z"/></svg>
        <p>Select a block or section to edit its properties</p>
      </div>
    </div>
  </aside>
</div>

{{-- ═══ Status Bar (bottom) ═══ --}}
<footer class="me-statusbar">
  <span class="me-statusbar__item">
    <span id="statusbar-sections">0</span> sections
    · <span id="statusbar-blocks">0</span> blocks
  </span>
  <span class="me-statusbar__shortcuts">
    <kbd>⌘S</kbd> Save
    <kbd>⌘Z</kbd> Undo
    <kbd>⌘⇧Z</kbd> Redo
    <kbd>Del</kbd> Delete
    <kbd>Esc</kbd> Deselect
  </span>
</footer>

{{-- ═══ Media Browser Modal (for image/video blocks) ═══ --}}
@include('components.media-browser-modal')

{{-- ═══ Global JS libraries (injected by ThemeResolverMiddleware) ═══ --}}
{!! $cms_scripts ?? '' !!}

{{-- ═══ Media Browser JS ═══ --}}
<script src="/themes/core/admin/js/media-browser.js?v={{ time() }}"></script>

{{-- ═══ Mosaic Editor JS ═══ --}}
<script src="/themes/core/admin/js/mosaic-editor.js?v={{ time() }}"></script>
</body>
</html>
