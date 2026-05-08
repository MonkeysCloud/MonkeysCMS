{{-- Media Browser Modal — Picker for content forms --}}
{{-- Include this component in any page that uses the MediaPickerWidget --}}
<div class="media-browser-modal" id="media-browser-modal" hidden>
  <div class="media-browser-modal__backdrop" data-action="close-media-browser"></div>
  <div class="media-browser-modal__panel">

    {{-- Header --}}
    <div class="media-browser-modal__header">
      <h3 class="media-browser-modal__title">
        <i data-lucide="image" class="w-5 h-5"></i> Select Media
      </h3>
      <div class="media-browser-modal__actions">
        <input type="text" class="form-input form-input--sm" id="media-browser-search"
               placeholder="Search media..." autocomplete="off">
        <button type="button" class="btn btn--sm btn--ghost" data-action="close-media-browser">
          <i data-lucide="x" class="w-4 h-4"></i>
        </button>
      </div>
    </div>

    {{-- Tabs --}}
    <div class="media-browser-modal__tabs">
      <button type="button" class="media-browser-tab active" data-tab="library">
        <i data-lucide="grid-3x3" class="w-4 h-4"></i> Library
      </button>
      <button type="button" class="media-browser-tab" data-tab="upload">
        <i data-lucide="upload" class="w-4 h-4"></i> Upload
      </button>
    </div>

    {{-- Library Panel --}}
    <div class="media-browser-modal__body" id="media-browser-library">
      <div class="media-browser-modal__filters">
        <button type="button" class="media-filter-tab active" data-filter-type="">All</button>
        <button type="button" class="media-filter-tab" data-filter-type="image">Images</button>
        <button type="button" class="media-filter-tab" data-filter-type="video">Videos</button>
        <button type="button" class="media-filter-tab" data-filter-type="application">Documents</button>
      </div>
      <div class="media-browser-grid" id="media-browser-grid">
        <div class="media-browser-loading">
          <i data-lucide="loader-2" class="w-6 h-6 animate-spin"></i>
          Loading media...
        </div>
      </div>
    </div>

    {{-- Upload Panel --}}
    <div class="media-browser-modal__body" id="media-browser-upload" hidden>
      <div class="media-dropzone" id="media-browser-dropzone">
        <div class="media-dropzone__content">
          <i data-lucide="cloud-upload" class="w-10 h-10 media-dropzone__icon"></i>
          <p class="media-dropzone__title">Drop file here or click to upload</p>
        </div>
        <input type="file" id="media-browser-file-input" class="media-dropzone__input"
               accept="image/*,video/*,audio/*,application/pdf">
      </div>
      <div id="media-browser-upload-progress" hidden>
        <div class="media-upload-progress-bar">
          <div class="media-upload-progress-bar__fill" id="media-browser-progress-fill"></div>
        </div>
        <p class="media-upload-progress-text" id="media-browser-progress-text">Uploading...</p>
      </div>
    </div>

    {{-- Footer --}}
    <div class="media-browser-modal__footer">
      <span class="media-browser-modal__selected" id="media-browser-selected-info">No item selected</span>
      <div class="media-browser-modal__footer-actions">
        <button type="button" class="btn btn--ghost" data-action="close-media-browser">Cancel</button>
        <button type="button" class="btn btn--primary" id="media-browser-confirm" disabled>
          <i data-lucide="check" class="w-4 h-4"></i> Select
        </button>
      </div>
    </div>

  </div>
</div>
