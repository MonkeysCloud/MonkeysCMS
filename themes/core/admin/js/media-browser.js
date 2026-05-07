/**
 * Media Browser — AJAX-driven media picker modal.
 *
 * Handles:
 *   - Opening/closing the modal
 *   - Loading media grid from the API
 *   - Searching and filtering
 *   - Selecting a media item → writes ID to the target hidden input
 *   - AJAX upload with progress from the upload tab
 */
(function () {
  'use strict';

  const API_BASE = '/api/cms/media';
  let modal, grid, searchInput, confirmBtn, selectedInfo;
  let libraryPanel, uploadPanel;
  let currentTarget = null; // the hidden input field ID
  let selectedId = null;
  let selectedData = null;
  let currentType = '';
  let debounceTimer = null;

  // ── Initialize ────────────────────────────────────────────────────────

  document.addEventListener('DOMContentLoaded', init);

  function init() {
    modal = document.getElementById('media-browser-modal');
    if (!modal) return;

    grid = document.getElementById('media-browser-grid');
    searchInput = document.getElementById('media-browser-search');
    confirmBtn = document.getElementById('media-browser-confirm');
    selectedInfo = document.getElementById('media-browser-selected-info');
    libraryPanel = document.getElementById('media-browser-library');
    uploadPanel = document.getElementById('media-browser-upload');

    // Open media browser
    document.addEventListener('click', function (e) {
      const trigger = e.target.closest('[data-action="open-media-browser"]');
      if (trigger) {
        e.preventDefault();
        currentTarget = trigger.dataset.target;
        openModal();
      }

      // Close
      const close = e.target.closest('[data-action="close-media-browser"]');
      if (close) {
        e.preventDefault();
        closeModal();
      }

      // Remove media from picker
      const remove = e.target.closest('[data-action="remove-media"]');
      if (remove) {
        e.preventDefault();
        const target = remove.dataset.target;
        clearPicker(target);
      }

      // Tab switch
      const tab = e.target.closest('.media-browser-tab[data-tab]');
      if (tab) {
        switchTab(tab.dataset.tab);
      }

      // Type filter
      const filter = e.target.closest('[data-filter-type]');
      if (filter && modal && !modal.hidden) {
        const type = filter.dataset.filterType;
        currentType = type;
        document.querySelectorAll('.media-browser-modal__filters .media-filter-tab').forEach(function (t) {
          t.classList.toggle('active', t.dataset.filterType === type);
        });
        loadMedia();
      }

      // Grid item selection
      const item = e.target.closest('.media-browser-item');
      if (item) {
        selectItem(item);
      }
    });

    // Confirm selection
    if (confirmBtn) {
      confirmBtn.addEventListener('click', confirmSelection);
    }

    // Search with debounce
    if (searchInput) {
      searchInput.addEventListener('input', function () {
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(loadMedia, 300);
      });
    }

    // Upload in modal
    const dropzone = document.getElementById('media-browser-dropzone');
    const fileInput = document.getElementById('media-browser-file-input');

    if (dropzone && fileInput) {
      dropzone.addEventListener('click', function () { fileInput.click(); });

      ['dragenter', 'dragover'].forEach(function (e) {
        dropzone.addEventListener(e, function (ev) {
          ev.preventDefault();
          dropzone.classList.add('media-dropzone--active');
        });
      });

      ['dragleave', 'drop'].forEach(function (e) {
        dropzone.addEventListener(e, function (ev) {
          ev.preventDefault();
          dropzone.classList.remove('media-dropzone--active');
        });
      });

      dropzone.addEventListener('drop', function (ev) {
        if (ev.dataTransfer.files.length > 0) {
          uploadFile(ev.dataTransfer.files[0]);
        }
      });

      fileInput.addEventListener('change', function () {
        if (fileInput.files.length > 0) {
          uploadFile(fileInput.files[0]);
        }
      });
    }

    // Escape key
    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape' && modal && !modal.hidden) {
        closeModal();
      }
    });
  }

  // ── Modal Lifecycle ───────────────────────────────────────────────────

  function openModal() {
    if (!modal) return;
    modal.hidden = false;
    selectedId = null;
    selectedData = null;
    updateSelectedInfo();
    switchTab('library');
    loadMedia();
    if (searchInput) searchInput.focus();
  }

  function closeModal() {
    if (!modal) return;
    modal.hidden = true;
    currentTarget = null;
  }

  function switchTab(tab) {
    document.querySelectorAll('.media-browser-tab[data-tab]').forEach(function (t) {
      t.classList.toggle('active', t.dataset.tab === tab);
    });

    if (libraryPanel) libraryPanel.hidden = (tab !== 'library');
    if (uploadPanel) uploadPanel.hidden = (tab !== 'upload');
  }

  // ── API Calls ─────────────────────────────────────────────────────────

  function loadMedia() {
    if (!grid) return;

    var search = searchInput ? searchInput.value.trim() : '';
    var url = API_BASE + '?per_page=48';
    if (currentType) url += '&type=' + encodeURIComponent(currentType);
    if (search) url += '&search=' + encodeURIComponent(search);

    grid.innerHTML = '<div class="media-browser-loading"><i data-lucide="loader-2" class="w-6 h-6 animate-spin"></i> Loading...</div>';

    fetch(url)
      .then(function (r) { return r.json(); })
      .then(function (json) {
        renderGrid(json.data || []);
      })
      .catch(function () {
        grid.innerHTML = '<div class="media-browser-loading">Failed to load media</div>';
      });
  }

  function renderGrid(items) {
    if (!grid) return;

    if (items.length === 0) {
      grid.innerHTML = '<div class="media-browser-loading">No media found</div>';
      return;
    }

    grid.innerHTML = '';

    items.forEach(function (item) {
      var el = document.createElement('div');
      el.className = 'media-browser-item';
      el.dataset.mediaId = item.id;
      el.dataset.mediaData = JSON.stringify(item);

      if (item.attributes.media_type === 'image') {
        // Use the API thumb endpoint directly — it handles path resolution + fallback
        el.innerHTML = '<img src="' + API_BASE + '/' + item.id + '/thumb" alt="' + (item.attributes.alt || '') + '" loading="lazy">' +
          '<span class="media-browser-item__name">' + (item.attributes.title || item.attributes.original_name) + '</span>';
      } else {
        var icon = item.attributes.media_type === 'video' ? 'video' :
                   item.attributes.media_type === 'audio' ? 'music' : 'file-text';
        el.innerHTML = '<div class="media-browser-item__icon"><i data-lucide="' + icon + '" class="w-8 h-8"></i></div>' +
          '<span class="media-browser-item__name">' + (item.attributes.title || item.attributes.original_name) + '</span>';
      }

      grid.appendChild(el);
    });

    // Re-init Lucide icons
    if (window.lucide) lucide.createIcons();
  }

  // ── Selection ─────────────────────────────────────────────────────────

  function selectItem(el) {
    document.querySelectorAll('.media-browser-item.selected').forEach(function (s) {
      s.classList.remove('selected');
    });

    el.classList.add('selected');
    selectedId = parseInt(el.dataset.mediaId, 10);
    selectedData = JSON.parse(el.dataset.mediaData);
    updateSelectedInfo();
  }

  function updateSelectedInfo() {
    if (selectedInfo) {
      selectedInfo.textContent = selectedData
        ? (selectedData.attributes.title || selectedData.attributes.original_name) + ' (' + selectedData.attributes.formatted_size + ')'
        : 'No item selected';
    }
    if (confirmBtn) {
      confirmBtn.disabled = !selectedId;
    }
  }

  function confirmSelection() {
    if (!selectedId || !currentTarget) return;

    var input = document.getElementById(currentTarget);
    if (input) {
      input.value = selectedId;
    }

    // Update preview
    var preview = document.getElementById(currentTarget + '-preview');
    if (preview && selectedData) {
      preview.style.display = '';
      if (selectedData.attributes.media_type === 'image') {
        preview.innerHTML = '<img src="' + API_BASE + '/' + selectedId + '/thumb" alt="">' +
          '<button type="button" class="media-picker__remove" data-action="remove-media" data-target="' + currentTarget + '">' +
          '<i data-lucide="x" class="w-4 h-4"></i></button>';
      } else {
        preview.innerHTML = '<div style="display:flex;align-items:center;justify-content:center;width:100%;height:100%;color:var(--admin-text-muted)">' +
          '<i data-lucide="file" class="w-8 h-8"></i></div>' +
          '<button type="button" class="media-picker__remove" data-action="remove-media" data-target="' + currentTarget + '">' +
          '<i data-lucide="x" class="w-4 h-4"></i></button>';
      }
      if (window.lucide) lucide.createIcons();
    }

    // Mosaic integration: call back to update block field data
    var trigger = document.querySelector('[data-action="open-media-browser"][data-target="' + currentTarget + '"]');
    if (trigger && trigger.dataset.mosaicCallback) {
      try {
        // Execute the callback, passing selectedId as argument
        var fn = new Function('id', trigger.dataset.mosaicCallback);
        fn(selectedId);
      } catch (e) {
        console.warn('Mosaic callback error:', e);
      }
    }

    closeModal();
  }

  function clearPicker(targetId) {
    var input = document.getElementById(targetId);
    if (input) input.value = '';

    var preview = document.getElementById(targetId + '-preview');
    if (preview) {
      preview.style.display = 'none';
      preview.innerHTML = '';
    }
  }

  // ── Upload ────────────────────────────────────────────────────────────

  function uploadFile(file) {
    var progressContainer = document.getElementById('media-browser-upload-progress');
    var progressFill = document.getElementById('media-browser-progress-fill');
    var progressText = document.getElementById('media-browser-progress-text');

    if (progressContainer) progressContainer.hidden = false;
    if (progressFill) progressFill.style.width = '0%';
    if (progressText) progressText.textContent = 'Uploading...';

    var formData = new FormData();
    formData.append('file', file);

    var xhr = new XMLHttpRequest();
    xhr.open('POST', API_BASE + '/upload', true);

    xhr.upload.addEventListener('progress', function (e) {
      if (e.lengthComputable && progressFill) {
        var pct = Math.round((e.loaded / e.total) * 100);
        progressFill.style.width = pct + '%';
        if (progressText) progressText.textContent = 'Uploading... ' + pct + '%';
      }
    });

    xhr.addEventListener('load', function () {
      if (progressContainer) progressContainer.hidden = true;

      if (xhr.status >= 200 && xhr.status < 300) {
        var result = JSON.parse(xhr.responseText);
        if (result.status === 'ok' && result.data) {
          // Select the uploaded item
          selectedId = result.data.id;
          selectedData = result.data;
          updateSelectedInfo();

          // Switch to library and reload
          switchTab('library');
          loadMedia();
        }
      } else {
        if (progressText) {
          progressText.textContent = 'Upload failed';
          if (progressContainer) progressContainer.hidden = false;
        }
      }
    });

    xhr.addEventListener('error', function () {
      if (progressContainer) progressContainer.hidden = true;
      alert('Upload failed. Please try again.');
    });

    xhr.send(formData);
  }
})();
