/**
 * Media Field Widgets - Image, File, Gallery, Video
 * MonkeysCMS Field Widget System
 */
(function() {
    'use strict';

    // Global CmsMedia object
    window.CmsMedia = {
        initImage: function(fieldId) {
            const wrapper = document.querySelector(`[data-field-id="${fieldId}"]`);
            if (!wrapper) return;

            const browseBtn = wrapper.querySelector('.field-image__browse');
            const removeBtn = wrapper.querySelector('.field-image__remove');
            const fileInput = wrapper.querySelector('.field-image__file');

            if (browseBtn) {
                browseBtn.addEventListener('click', function() {
                    window.openMediaBrowser(fieldId, 'image');
                });
            }

            if (removeBtn) {
                removeBtn.addEventListener('click', function() {
                    window.clearMediaField(fieldId);
                });
            }

            if (fileInput) {
                fileInput.addEventListener('change', function(e) {
                    if (this.files && this.files[0]) {
                        window.uploadMediaField(fieldId, this.files[0]);
                    }
                });
            }
        },

        initGallery: function(fieldId) {
            const wrapper = document.querySelector(`[data-field-id="${fieldId}"]`);
            if (!wrapper) return;

            const addBtn = wrapper.querySelector('.field-gallery__add');
            
            if (addBtn) {
                addBtn.addEventListener('click', function() {
                    // For gallery, pass the specific callback context or handle multiple
                    window.openMediaBrowser(fieldId, 'image');
                });
            }

            // Delegate remove clicks
            wrapper.addEventListener('click', function(e) {
                if (e.target.closest('.field-gallery__remove')) {
                    const item = e.target.closest('.field-gallery__item');
                    if (item) item.remove();
                    window.updateGalleryValue(fieldId);
                }
            });
        }
    };

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

        if (!mediaModal) {
            createMediaModal();
        }

        loadMediaItems(type);

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
        // Don't nullify currentFieldId immediately if we need it for callbacks, 
        // but typically we do it after selection. 
        // currentFieldId = null; 
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
            <div class="media-browser__item" data-id="${item.id}" data-url="${item.url || ''}" onclick="window.toggleMediaSelection(this)">
                ${item.is_image 
                    ? `<img src="${item.url}" alt="${item.filename || ''}">`
                    : `<div class="media-browser__file-icon">📄</div>`}
                <div class="media-browser__item-name">${item.filename || 'Untitled'}</div>
            </div>
        `).join('');
    }

    /**
     * Toggle Media Selection
     */
    window.toggleMediaSelection = function(element) {
        document.querySelectorAll('.media-browser__item--selected').forEach(el => {
            el.classList.remove('media-browser__item--selected');
        });
        element.classList.add('media-browser__item--selected');

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
        const mediaUrl = selected.dataset.url; // Use data-url for preview

        // Check widget type by wrapper class
        const wrapper = document.querySelector(`[data-field-id="${currentFieldId}"]`);
        
        if (wrapper && wrapper.classList.contains('field-gallery')) {
            window.addGalleryItem(currentFieldId, mediaId, mediaUrl);
        } else {
            setFieldValue(currentFieldId, mediaId, mediaUrl);
        }
        
        window.closeMediaBrowser();
    };

    /**
     * Search Media
     */
    window.searchMedia = function(query) {
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
        const token = document.querySelector('meta[name="csrf-token"]')?.content;
        
        for (let i = 0; i < files.length; i++) {
            formData.append('files[]', files[i]);
        }

        fetch('/admin/media/upload', {
            method: 'POST',
            body: formData,
            headers: {
                'X-CSRF-TOKEN': token || ''
            }
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
        const token = document.querySelector('meta[name="csrf-token"]')?.content;
        formData.append('file', file);

        const wrapper = document.querySelector(`[data-field-id="${fieldId}"]`);
        if (wrapper) {
            wrapper.classList.add('field-media--uploading');
        }

        fetch('/admin/media/upload', {
            method: 'POST',
            body: formData,
            headers: {
                'X-CSRF-TOKEN': token || ''
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success && data.data) {
                setFieldValue(fieldId, data.data.id, data.data.url);
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
     * Set Field Value and Update UI (Single Image)
     */
    function setFieldValue(fieldId, mediaId, mediaUrl) {
        const hidden = document.getElementById(fieldId);
        const wrapper = document.querySelector(`[data-field-id="${fieldId}"]`);
        if (!wrapper) return;

        const preview = wrapper.querySelector('.field-image__preview');
        const img = wrapper.querySelector('.field-image__img');
        const placeholder = wrapper.querySelector('.field-image__placeholder');
        const removeBtn = wrapper.querySelector('.field-image__remove');

        if (hidden) hidden.value = mediaId;

        if (mediaId && mediaUrl) {
            if (img) {
                img.src = mediaUrl;
                img.style.display = '';
            } else if (preview) {
                 // Create img if missing
                 const newImg = document.createElement('img');
                 newImg.className = 'field-image__img';
                 newImg.src = mediaUrl;
                 // Insert before placeholder
                 preview.insertBefore(newImg, placeholder);
            }

            if (placeholder) placeholder.style.display = 'none';
            if (removeBtn) removeBtn.style.display = '';
            if (preview) preview.classList.remove('field-image__preview--empty');
        } else {
            if (img) img.style.display = 'none';
            if (placeholder) placeholder.style.display = '';
            if (removeBtn) removeBtn.style.display = 'none';
            if (preview) preview.classList.add('field-image__preview--empty');
        }
    }

    /**
     * Add Item to Gallery
     */
    window.addGalleryItem = function(fieldId, mediaId, mediaUrl) {
        const wrapper = document.querySelector(`[data-field-id="${fieldId}"]`);
        if (!wrapper) return;

        const grid = wrapper.querySelector('.field-gallery__grid');
        const index = grid.children.length;

        const item = document.createElement('div');
        item.className = 'field-gallery__item';
        item.dataset.index = index;
        item.draggable = true;
        item.innerHTML = `
            <img src="${mediaUrl || `/media/${mediaId}/thumbnail`}" alt="">
            <button type="button" class="field-gallery__remove" data-action="remove">×</button>
        `;

        grid.appendChild(item);
        window.updateGalleryValue(fieldId);
    };

    /**
     * Update Gallery JSON Value
     */
    window.updateGalleryValue = function(fieldId) {
        const wrapper = document.querySelector(`[data-field-id="${fieldId}"]`);
        if (!wrapper) return;

        const hidden = document.getElementById(fieldId);
        const grid = wrapper.querySelector('.field-gallery__grid');
        const items = grid.querySelectorAll('.field-gallery__item img');
        
        const urls = Array.from(items).map(img => img.src);
        
        // Gallery usually stores URLs or specific structures. Adjust as needed.
        // If your backend expects IDs, we need to store IDs in data attributes.
        // Based on GalleryWidget.php, it seems to store URLs in JSON.
        
        if (hidden) {
            hidden.value = JSON.stringify(urls);
        }
    };

})();
