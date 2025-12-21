/**
 * Media Field Widgets - Image, File, Gallery, Video
 * MonkeysCMS Field Widget System
 */

(function() {
    'use strict';

    // Media Browser Modal State
    let mediaModal = null;
    let currentFieldId = null;
    let currentMediaType = null;

    /**
     * Open Media Browser Modal
     */
    window.openMediaBrowser = function(fieldId, type = 'image') {
        currentFieldId = fieldId;
        currentMediaType = type;

        // Create modal if not exists
        if (!mediaModal) {
            createMediaModal();
        }

        // Load media items
        loadMediaItems(type);

        // Show modal
        mediaModal.style.display = 'flex';
        document.body.style.overflow = 'hidden';
    };

    /**
     * Close Media Browser Modal
     */
    window.closeMediaBrowser = function() {
        if (mediaModal) {
            mediaModal.style.display = 'none';
            document.body.style.overflow = '';
        }
        currentFieldId = null;
        currentMediaType = null;
    };

    /**
     * Create Media Browser Modal
     */
    function createMediaModal() {
        mediaModal = document.createElement('div');
        mediaModal.className = 'media-browser-modal';
        mediaModal.innerHTML = `
            <div class="media-browser">
                <div class="media-browser__header">
                    <h2>Select Media</h2>
                    <button type="button" class="media-browser__close" onclick="window.closeMediaBrowser()">&times;</button>
                </div>
                <div class="media-browser__toolbar">
                    <input type="text" class="media-browser__search" placeholder="Search media..." oninput="window.searchMedia(this.value)">
                    <button type="button" class="media-browser__upload-btn" onclick="document.getElementById('media-browser-upload').click()">
                        Upload New
                    </button>
                    <input type="file" id="media-browser-upload" multiple style="display: none" onchange="window.uploadMediaBrowserFiles(this.files)">
                </div>
                <div class="media-browser__content">
                    <div class="media-browser__grid" id="media-browser-grid"></div>
                </div>
                <div class="media-browser__footer">
                    <button type="button" class="btn btn-secondary" onclick="window.closeMediaBrowser()">Cancel</button>
                    <button type="button" class="btn btn-primary" onclick="window.selectMediaItem()" disabled id="media-browser-select">Select</button>
                </div>
            </div>
        `;
        document.body.appendChild(mediaModal);

        // Close on backdrop click
        mediaModal.addEventListener('click', function(e) {
            if (e.target === mediaModal) {
                window.closeMediaBrowser();
            }
        });
    }

    /**
     * Load Media Items
     */
    function loadMediaItems(type) {
        const grid = document.getElementById('media-browser-grid');
        if (!grid) return;

        grid.innerHTML = '<div class="media-browser__loading">Loading...</div>';

        // API endpoint based on type
        const endpoint = type === 'image' 
            ? '/admin/media?type=image' 
            : '/admin/media';

        fetch(endpoint, {
            headers: { 'Accept': 'application/json' }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success && data.data) {
                renderMediaItems(grid, data.data);
            } else {
                grid.innerHTML = '<div class="media-browser__empty">No media found</div>';
            }
        })
        .catch(error => {
            console.error('Error loading media:', error);
            grid.innerHTML = '<div class="media-browser__error">Error loading media</div>';
        });
    }

    /**
     * Render Media Items in Grid
     */
    function renderMediaItems(grid, items) {
        if (!items.length) {
            grid.innerHTML = '<div class="media-browser__empty">No media found. Upload some files to get started.</div>';
            return;
        }

        grid.innerHTML = items.map(item => `
            <div class="media-browser__item" data-id="${item.id}" onclick="window.toggleMediaSelection(this)">
                ${item.mime_type && item.mime_type.startsWith('image/') 
                    ? `<img src="/media/${item.id}/thumbnail" alt="${item.filename || ''}">`
                    : `<div class="media-browser__file-icon">📄</div>`}
                <div class="media-browser__item-name">${item.filename || 'Untitled'}</div>
            </div>
        `).join('');
    }

    /**
     * Toggle Media Selection
     */
    window.toggleMediaSelection = function(element) {
        // Remove selection from others
        document.querySelectorAll('.media-browser__item--selected').forEach(el => {
            el.classList.remove('media-browser__item--selected');
        });

        // Select this item
        element.classList.add('media-browser__item--selected');

        // Enable select button
        const selectBtn = document.getElementById('media-browser-select');
        if (selectBtn) selectBtn.disabled = false;
    };

    /**
     * Select Media Item (Confirm)
     */
    window.selectMediaItem = function() {
        const selected = document.querySelector('.media-browser__item--selected');
        if (!selected || !currentFieldId) return;

        const mediaId = selected.dataset.id;
        setFieldValue(currentFieldId, mediaId);
        window.closeMediaBrowser();
    };

    /**
     * Search Media
     */
    window.searchMedia = function(query) {
        // Debounced search
        clearTimeout(window.mediaSearchTimeout);
        window.mediaSearchTimeout = setTimeout(() => {
            const endpoint = currentMediaType === 'image'
                ? `/admin/media?type=image&search=${encodeURIComponent(query)}`
                : `/admin/media?search=${encodeURIComponent(query)}`;

            fetch(endpoint, { headers: { 'Accept': 'application/json' } })
                .then(response => response.json())
                .then(data => {
                    const grid = document.getElementById('media-browser-grid');
                    if (data.success && data.data) {
                        renderMediaItems(grid, data.data);
                    }
                });
        }, 300);
    };

    /**
     * Upload Files from Media Browser
     */
    window.uploadMediaBrowserFiles = function(files) {
        if (!files.length) return;

        const formData = new FormData();
        for (let i = 0; i < files.length; i++) {
            formData.append('files[]', files[i]);
        }

        fetch('/admin/media/upload', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                loadMediaItems(currentMediaType);
            } else {
                alert('Upload failed: ' + (data.error || 'Unknown error'));
            }
        })
        .catch(error => {
            console.error('Upload error:', error);
            alert('Upload failed');
        });
    };

    /**
     * Upload Media for a Specific Field
     */
    window.uploadMediaField = function(fieldId, file) {
        if (!file) return;

        const formData = new FormData();
        formData.append('file', file);

        // Show loading state
        const wrapper = document.getElementById(fieldId + '_wrapper');
        if (wrapper) {
            wrapper.classList.add('field-media--uploading');
        }

        fetch('/admin/media/upload', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success && data.data) {
                setFieldValue(fieldId, data.data.id);
            } else {
                alert('Upload failed: ' + (data.error || 'Unknown error'));
            }
        })
        .catch(error => {
            console.error('Upload error:', error);
            alert('Upload failed');
        })
        .finally(() => {
            if (wrapper) {
                wrapper.classList.remove('field-media--uploading');
            }
        });
    };

    /**
     * Clear Media Field
     */
    window.clearMediaField = function(fieldId) {
        setFieldValue(fieldId, '');
    };

    /**
     * Set Field Value and Update UI
     */
    function setFieldValue(fieldId, mediaId) {
        const hidden = document.getElementById(fieldId);
        const preview = document.getElementById(fieldId + '_preview');
        const img = document.getElementById(fieldId + '_img');
        const removeBtn = document.querySelector(`#${fieldId}_wrapper .field-image__remove, #${fieldId}_wrapper .field-file__remove`);
        const info = document.getElementById(fieldId + '_info');

        if (hidden) {
            hidden.value = mediaId;
        }

        if (mediaId) {
            // Show preview for images
            if (img) {
                img.src = `/media/${mediaId}/thumbnail`;
                img.style.display = '';
            }
            if (preview) {
                preview.classList.remove('field-image__preview--empty');
                const placeholder = preview.querySelector('.field-image__placeholder');
                if (placeholder) placeholder.style.display = 'none';
            }
            if (removeBtn) {
                removeBtn.style.display = '';
            }
            // Update file info if exists
            if (info) {
                info.innerHTML = `
                    <span class="field-file__icon">📄</span>
                    <span class="field-file__name">File #${mediaId}</span>
                    <a href="/media/${mediaId}/download" class="field-file__download" target="_blank">Download</a>
                `;
            }
        } else {
            // Clear preview
            if (img) {
                img.src = '';
                img.style.display = 'none';
            }
            if (preview) {
                preview.classList.add('field-image__preview--empty');
                const placeholder = preview.querySelector('.field-image__placeholder');
                if (placeholder) placeholder.style.display = '';
            }
            if (removeBtn) {
                removeBtn.style.display = 'none';
            }
            if (info) {
                info.innerHTML = '';
            }
        }
    }

    /**
     * Select Image (for repeater subfields)
     */
    window.selectImage = function(inputId) {
        window.openMediaBrowser(inputId, 'image');
    };

})();
