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
            const preview = wrapper.querySelector('.field-image__preview');

            // Click on preview area to browse
            if (preview) {
                preview.addEventListener('click', function() {
                    window.openMediaBrowser(fieldId, 'image');
                });
                preview.style.cursor = 'pointer';
            }

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

            const browseBtn = wrapper.querySelector('.field-gallery__browse');
            const fileInput = wrapper.querySelector('.field-gallery__file');
            
            if (browseBtn) {
                browseBtn.addEventListener('click', function() {
                    window.openMediaBrowser(fieldId, 'image', true); // true = multiple
                });
            }

            if (fileInput) {
                fileInput.addEventListener('change', function(e) {
                    if (this.files && this.files.length > 0) {
                        // Handle multiple file uploads
                        Array.from(this.files).forEach(file => {
                            window.uploadGalleryImage(fieldId, file);
                        });
                        this.value = ''; // Reset input for future uploads
                    }
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

            // Make sortable if needed
            this.initGallerySortable(wrapper, fieldId);
        },

        initGallerySortable: function(wrapper, fieldId) {
            const grid = wrapper.querySelector('.field-gallery__grid');
            if (!grid) return;

            let draggedEl = null;

            grid.addEventListener('dragstart', function(e) {
                if (e.target.classList.contains('field-gallery__item')) {
                    draggedEl = e.target;
                    e.target.classList.add('dragging');
                }
            });

            grid.addEventListener('dragend', function(e) {
                if (draggedEl) {
                    draggedEl.classList.remove('dragging');
                    draggedEl = null;
                    window.updateGalleryValue(fieldId);
                }
            });

            grid.addEventListener('dragover', function(e) {
                e.preventDefault();
                const afterElement = getDragAfterElement(grid, e.clientX);
                if (draggedEl) {
                    if (afterElement == null) {
                        grid.appendChild(draggedEl);
                    } else {
                        grid.insertBefore(draggedEl, afterElement);
                    }
                }
            });

            function getDragAfterElement(container, x) {
                const items = [...container.querySelectorAll('.field-gallery__item:not(.dragging)')];
                return items.reduce((closest, child) => {
                    const box = child.getBoundingClientRect();
                    const offset = x - box.left - box.width / 2;
                    if (offset < 0 && offset > closest.offset) {
                        return { offset: offset, element: child };
                    }
                    return closest;
                }, { offset: Number.NEGATIVE_INFINITY }).element;
            }
        },

        initVideo: function(fieldId) {
            const wrapper = document.querySelector(`[data-field-id="${fieldId}"]`);
            if (!wrapper) return;

            const input = wrapper.querySelector('input[type="url"]');
            const preview = document.getElementById(fieldId + '_preview');

            if (input && preview) {
                input.addEventListener('input', function() {
                    const url = this.value.trim();
                    if (!url) {
                        preview.innerHTML = '';
                        return;
                    }

                    // Simple embed logic mirroring PHP
                    let embedHtml = null;
                    const youtubeMatch = url.match(/(?:youtube\.com\/(?:watch\?v=|embed\/)|youtu\.be\/)([a-zA-Z0-9_-]+)/);
                    const vimeoMatch = url.match(/vimeo\.com\/(?:video\/)?(\d+)/);

                    if (youtubeMatch) {
                        embedHtml = `<iframe src="https://www.youtube.com/embed/${youtubeMatch[1]}" frameborder="0" allowfullscreen></iframe>`;
                    } else if (vimeoMatch) {
                        embedHtml = `<iframe src="https://player.vimeo.com/video/${vimeoMatch[1]}" frameborder="0" allowfullscreen></iframe>`;
                    } else if (url.match(/\.(mp4|webm|ogg)$/i)) {
                        embedHtml = `<video src="${url}" controls></video>`;
                    }

                    if (embedHtml) {
                        preview.innerHTML = embedHtml;
                    } else {
                        preview.innerHTML = ''; // Or keep empty if invalid
                    }
                });
            }
        },

        initFile: function(fieldId) {
            const wrapper = document.querySelector(`[data-field-id="${fieldId}"]`);
            if (!wrapper) return;

            const input = wrapper.querySelector('.field-file__input');
            const removeBtn = wrapper.querySelector('.field-file__remove');
            const hidden = document.getElementById(fieldId);
            const info = wrapper.querySelector('.field-file__info');

            if (input) {
                input.addEventListener('change', function(e) {
                    if (this.files && this.files[0]) {
                        window.uploadMediaField(fieldId, this.files[0]);
                    }
                });
            }

            if (removeBtn) {
                removeBtn.addEventListener('click', function() {
                    if (hidden) hidden.value = '';
                    if (info) info.style.display = 'none';
                    if (removeBtn) removeBtn.style.display = 'none';
                    // clear input too
                    if (input) input.value = '';
                });
            }
        }
    };

    // Media Browser Modal State
    let mediaModal = null;
    let currentFieldId = null;
    let currentMediaType = null;
    let currentMultiSelect = false;

    /**
     * Open Media Browser Modal
     */
    window.openMediaBrowser = function(fieldId, type = 'image', multiple = false) {
        currentFieldId = fieldId;
        currentMediaType = type;
        currentMultiSelect = multiple;

        if (!mediaModal) {
            createMediaModal();
        }

        // Clear previous selections
        document.querySelectorAll('.media-browser__item--selected').forEach(el => {
            el.classList.remove('media-browser__item--selected');
        });
        const selectBtn = document.getElementById('media-browser-select');
        if (selectBtn) selectBtn.disabled = true;

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
        if (currentMultiSelect) {
            // Multi-select: toggle this item
            element.classList.toggle('media-browser__item--selected');
        } else {
            // Single select: clear others and select this one
            document.querySelectorAll('.media-browser__item--selected').forEach(el => {
                el.classList.remove('media-browser__item--selected');
            });
            element.classList.add('media-browser__item--selected');
        }

        const hasSelection = document.querySelector('.media-browser__item--selected');
        const selectBtn = document.getElementById('media-browser-select');
        if (selectBtn) selectBtn.disabled = !hasSelection;
    };

    /**
     * Select Media Item (Confirm)
     */
    window.selectMediaItem = function() {
        const selectedItems = document.querySelectorAll('.media-browser__item--selected');
        if (selectedItems.length === 0 || !currentFieldId) return;

        // Check widget type by wrapper class
        const wrapper = document.querySelector(`[data-field-id="${currentFieldId}"]`);
        const isGallery = wrapper && wrapper.classList.contains('field-gallery');

        selectedItems.forEach(selected => {
            const mediaId = selected.dataset.id;
            const mediaUrl = selected.dataset.url;

            if (isGallery) {
                window.addGalleryItem(currentFieldId, mediaId, mediaUrl);
            } else {
                setFieldValue(currentFieldId, mediaId, mediaUrl);
            }
        });
        
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

    /**
     * Upload a file to gallery
     */
    window.uploadGalleryImage = function(fieldId, file) {
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content 
            || document.querySelector('input[name="csrf_token"]')?.value 
            || '';

        const formData = new FormData();
        formData.append('file', file);
        formData.append('csrf_token', csrfToken);

        fetch('/admin/media/upload', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success && data.data) {
                window.addGalleryItem(fieldId, data.data.id, data.data.url);
            } else {
                console.error('Gallery upload failed:', data.error || 'Unknown error');
            }
        })
        .catch(error => {
            console.error('Gallery upload error:', error);
        });
    };

})();
