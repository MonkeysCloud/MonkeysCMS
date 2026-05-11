/**
 * CMS Media Picker — Reusable media browser modal.
 *
 * Usage:
 *   CMS.mediaPicker({
 *     type: 'image',           // 'image' | 'all'
 *     onSelect(media) { ... }, // { id, url, name, alt, ... }
 *   });
 */
(function () {
  'use strict';

  let modal = null;
  let onSelectCb = null;
  let selectedItem = null;
  let currentType = 'image';

  function createModal() {
    if (modal) return modal;

    const el = document.createElement('div');
    el.id = 'media-picker-modal';
    el.className = 'mp-overlay';
    el.innerHTML = `
      <div class="mp-dialog">
        <div class="mp-header">
          <h2 class="mp-header__title">
            <svg class="mp-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><path d="m21 15-5-5L5 21"/></svg>
            Media Library
          </h2>
          <div class="mp-header__actions">
            <div class="mp-search">
              <svg class="mp-icon mp-search__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
              <input type="text" class="mp-search__input" id="mp-search" placeholder="Search media…" autocomplete="off">
            </div>
            <label class="mp-upload-btn" id="mp-upload-label">
              <svg class="mp-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" x2="12" y1="3" y2="15"/></svg>
              Upload
              <input type="file" id="mp-file-input" accept="image/*" multiple hidden>
            </label>
            <button type="button" class="mp-close" id="mp-close" title="Close">
              <svg class="mp-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" x2="6" y1="6" y2="18"/><line x1="6" x2="18" y1="6" y2="18"/></svg>
            </button>
          </div>
        </div>

        <div class="mp-body">
          <div class="mp-grid" id="mp-grid"></div>
          <div class="mp-loading" id="mp-loading" hidden>
            <div class="mp-spinner"></div>
            Loading media…
          </div>
          <div class="mp-empty" id="mp-empty" hidden>
            <svg class="mp-icon mp-empty__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><path d="m21 15-5-5L5 21"/></svg>
            <p>No media found</p>
          </div>
        </div>

        <div class="mp-footer" id="mp-footer">
          <div class="mp-footer__info" id="mp-selected-info">No item selected</div>
          <div class="mp-footer__actions">
            <button type="button" class="mp-btn mp-btn--ghost" id="mp-cancel">Cancel</button>
            <button type="button" class="mp-btn mp-btn--primary" id="mp-select" disabled>Select</button>
          </div>
        </div>
      </div>
    `;
    document.body.appendChild(el);
    modal = el;

    // Event listeners
    el.querySelector('#mp-close').addEventListener('click', close);
    el.querySelector('#mp-cancel').addEventListener('click', close);
    el.querySelector('#mp-select').addEventListener('click', confirmSelect);
    el.addEventListener('click', (e) => { if (e.target === el) close(); });

    // Search
    let searchTimer = null;
    el.querySelector('#mp-search').addEventListener('input', (e) => {
      clearTimeout(searchTimer);
      searchTimer = setTimeout(() => loadMedia(e.target.value), 300);
    });

    // Upload
    el.querySelector('#mp-file-input').addEventListener('change', handleUpload);

    // Keyboard
    document.addEventListener('keydown', (e) => {
      if (e.key === 'Escape' && modal?.classList.contains('is-open')) close();
    });

    return modal;
  }

  async function loadMedia(search = '') {
    const grid = modal.querySelector('#mp-grid');
    const loading = modal.querySelector('#mp-loading');
    const empty = modal.querySelector('#mp-empty');

    grid.innerHTML = '';
    loading.hidden = false;
    empty.hidden = true;
    selectedItem = null;
    updateFooter();

    try {
      const url = `/admin/media/browse.json?type=${encodeURIComponent(currentType)}&q=${encodeURIComponent(search)}`;
      const resp = await CMS.fetch(url);
      const data = await resp.json();

      loading.hidden = true;

      if (!data.items || data.items.length === 0) {
        empty.hidden = false;
        return;
      }

      data.items.forEach(item => {
        const card = document.createElement('div');
        card.className = 'mp-item';
        card.dataset.id = item.id;

        if (item.type === 'image' && item.thumb) {
          card.innerHTML = `
            <div class="mp-item__thumb">
              <img src="${item.thumb}" alt="${item.alt || item.name}" loading="lazy">
            </div>
            <div class="mp-item__name">${item.name}</div>
            <div class="mp-item__meta">${item.size}${item.width ? ` · ${item.width}×${item.height}` : ''}</div>
          `;
        } else {
          const icon = item.type === 'document' ? '📄' : item.type === 'video' ? '🎬' : '📁';
          card.innerHTML = `
            <div class="mp-item__thumb mp-item__thumb--file">
              <span class="mp-item__file-icon">${icon}</span>
            </div>
            <div class="mp-item__name">${item.name}</div>
            <div class="mp-item__meta">${item.size}</div>
          `;
        }

        card.addEventListener('click', () => selectItem(card, item));
        card.addEventListener('dblclick', () => { selectItem(card, item); confirmSelect(); });
        grid.appendChild(card);
      });
    } catch (err) {
      loading.hidden = true;
      grid.innerHTML = `<div class="mp-error">Failed to load media: ${err.message}</div>`;
    }
  }

  function selectItem(card, item) {
    // Deselect previous
    modal.querySelectorAll('.mp-item.is-selected').forEach(el => el.classList.remove('is-selected'));
    card.classList.add('is-selected');
    selectedItem = item;
    updateFooter();
  }

  function updateFooter() {
    const info = modal.querySelector('#mp-selected-info');
    const btn = modal.querySelector('#mp-select');

    if (selectedItem) {
      info.innerHTML = `<strong>${selectedItem.name}</strong> <span class="text-muted">${selectedItem.size}</span>`;
      btn.disabled = false;
    } else {
      info.textContent = 'No item selected';
      btn.disabled = true;
    }
  }

  function confirmSelect() {
    if (!selectedItem) return;
    if (onSelectCb) onSelectCb(selectedItem);
    close();
  }

  async function handleUpload(e) {
    const files = e.target.files;
    if (!files.length) return;

    const formData = new FormData();
    for (const file of files) {
      formData.append('files[]', file);
    }

    const grid = modal.querySelector('#mp-grid');
    const uploadLabel = modal.querySelector('#mp-upload-label');
    uploadLabel.classList.add('is-uploading');

    try {
      const resp = await CMS.fetch('/admin/media/upload', {
        method: 'POST',
        body: formData,
        // Don't set Content-Type — let browser set boundary
        headers: {},
      });

      if (resp.ok) {
        // Reload the grid
        const searchVal = modal.querySelector('#mp-search').value;
        await loadMedia(searchVal);
      }
    } catch (err) {
      console.error('Upload failed:', err);
    } finally {
      uploadLabel.classList.remove('is-uploading');
      e.target.value = ''; // Reset file input
    }
  }

  function open(options = {}) {
    currentType = options.type || 'image';
    onSelectCb = options.onSelect || null;

    createModal();
    modal.classList.add('is-open');
    document.body.style.overflow = 'hidden';

    loadMedia();
    setTimeout(() => modal.querySelector('#mp-search')?.focus(), 100);
  }

  function close() {
    if (!modal) return;
    modal.classList.remove('is-open');
    document.body.style.overflow = '';
    selectedItem = null;
  }

  // Public API
  window.CMS = window.CMS || {};
  CMS.mediaPicker = open;
})();
