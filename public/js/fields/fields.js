/**
 * MonkeysCMS Field Widgets JavaScript
 * Interactive functionality for form field widgets
 */

(function() {
    'use strict';

    // =========================================================================
    // Utility Functions
    // =========================================================================

    /**
     * Debounce function calls
     */
    function debounce(func, wait) {
        let timeout;
        return function executedFunction(...args) {
            const later = () => {
                clearTimeout(timeout);
                func(...args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    }

    /**
     * Generate slug from text
     */
    function slugify(text) {
        return text
            .toString()
            .toLowerCase()
            .trim()
            .replace(/\s+/g, '-')
            .replace(/[^\w\-]+/g, '')
            .replace(/\-\-+/g, '-')
            .replace(/^-+/, '')
            .replace(/-+$/, '');
    }

    /**
     * Escape HTML
     */
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    // =========================================================================
    // Slug Field
    // =========================================================================

    window.generateSlug = function(fieldId, sourceField) {
        const slugInput = document.getElementById(fieldId);
        const sourceInput = document.querySelector(`[name="${sourceField}"]`);
        
        if (slugInput && sourceInput) {
            slugInput.value = slugify(sourceInput.value);
        }
    };

    // Auto-generate slug on title change
    document.addEventListener('input', function(e) {
        if (e.target.matches('[name="title"], [name="name"]')) {
            const form = e.target.closest('form');
            const slugInput = form?.querySelector('[data-source]');
            
            if (slugInput && !slugInput.dataset.edited) {
                slugInput.value = slugify(e.target.value);
            }
        }
    });

    // Mark slug as manually edited
    document.addEventListener('input', function(e) {
        if (e.target.matches('[data-source]')) {
            e.target.dataset.edited = 'true';
        }
    });

    // =========================================================================
    // Media Browser
    // =========================================================================

    window.openMediaBrowser = function(fieldId, type = 'image') {
        // Open media browser modal
        const modal = document.createElement('div');
        modal.className = 'media-browser-modal';
        modal.innerHTML = `
            <div class="media-browser-modal__content">
                <div class="media-browser-modal__header">
                    <h3>Select ${type === 'image' ? 'Image' : 'File'}</h3>
                    <button type="button" onclick="closeMediaBrowser(this)">×</button>
                </div>
                <div class="media-browser-modal__body">
                    <div class="media-browser__loading">Loading media...</div>
                </div>
            </div>
        `;
        document.body.appendChild(modal);
        
        // Load media
        loadMediaItems(modal, fieldId, type);
    };

    window.closeMediaBrowser = function(button) {
        const modal = button.closest('.media-browser-modal');
        if (modal) {
            modal.remove();
        }
    };

    function loadMediaItems(modal, fieldId, type) {
        const body = modal.querySelector('.media-browser-modal__body');
        
        // Use XHR with HTMX headers
        const xhr = new XMLHttpRequest();
        xhr.open('GET', `/admin/media?type=${type}&limit=50`, true);
        xhr.setRequestHeader('Accept', 'application/json');
        xhr.setRequestHeader('HX-Request', 'true');
        xhr.onreadystatechange = function() {
            if (xhr.readyState === 4) {
                if (xhr.status === 200) {
                    try {
                        const data = JSON.parse(xhr.responseText);
                        if (data.success && data.data.length > 0) {
                            let html = '<div class="media-browser__grid">';
                            data.data.forEach(item => {
                                html += `
                                    <div class="media-browser__item" onclick="selectMediaItem('${fieldId}', ${item.id}, '${escapeHtml(item.url)}')">
                                        <img src="${escapeHtml(item.thumbnail || item.url)}" alt="${escapeHtml(item.filename)}">
                                        <span>${escapeHtml(item.filename)}</span>
                                    </div>
                                `;
                            });
                            html += '</div>';
                            body.innerHTML = html;
                        } else {
                            body.innerHTML = '<div class="media-browser__empty">No media found</div>';
                        }
                    } catch (e) {
                        body.innerHTML = '<div class="media-browser__error">Failed to load media</div>';
                    }
                } else {
                    body.innerHTML = '<div class="media-browser__error">Failed to load media</div>';
                }
            }
        };
        xhr.send();
    }

    window.selectMediaItem = function(fieldId, mediaId, url) {
        const input = document.getElementById(fieldId);
        const preview = document.getElementById(fieldId + '_preview');
        const img = document.getElementById(fieldId + '_img');
        const removeBtn = document.querySelector(`#${fieldId}_wrapper .field-image__remove`);
        
        if (input) {
            input.value = mediaId;
        }
        
        if (preview) {
            preview.classList.remove('field-image__preview--empty');
        }
        
        if (img) {
            img.src = url;
        }
        
        if (removeBtn) {
            removeBtn.style.display = 'inline-block';
        }
        
        // Close modal
        const modal = document.querySelector('.media-browser-modal');
        if (modal) {
            modal.remove();
        }
    };

    window.clearMediaField = function(fieldId) {
        const input = document.getElementById(fieldId);
        const preview = document.getElementById(fieldId + '_preview');
        const img = document.getElementById(fieldId + '_img');
        const removeBtn = document.querySelector(`#${fieldId}_wrapper .field-image__remove`);
        const info = document.getElementById(fieldId + '_info');
        
        if (input) {
            input.value = '';
        }
        
        if (preview) {
            preview.classList.add('field-image__preview--empty');
        }
        
        if (img) {
            img.src = '';
        }
        
        if (removeBtn) {
            removeBtn.style.display = 'none';
        }
        
        if (info) {
            info.remove();
        }
    };

    window.uploadMediaField = function(fieldId, file) {
        if (!file) return;
        
        const formData = new FormData();
        formData.append('file', file);
        
        // Use XHR with HTMX headers
        const xhr = new XMLHttpRequest();
        xhr.open('POST', '/admin/media/upload', true);
        xhr.setRequestHeader('HX-Request', 'true');
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content ||
                          document.querySelector('input[name="csrf_token"]')?.value || '';
        if (csrfToken) {
            xhr.setRequestHeader('X-CSRF-TOKEN', csrfToken);
        }
        xhr.onreadystatechange = function() {
            if (xhr.readyState === 4) {
                if (xhr.status === 200 || xhr.status === 201) {
                    try {
                        const data = JSON.parse(xhr.responseText);
                        if (data.success) {
                            selectMediaItem(fieldId, data.data.id, data.data.url);
                        } else {
                            alert('Upload failed: ' + (data.error || 'Unknown error'));
                        }
                    } catch (e) {
                        alert('Upload failed: Parse error');
                    }
                } else {
                    alert('Upload failed: ' + xhr.status);
                }
            }
        };
        xhr.send(formData);
    };

    // =========================================================================
    // Gallery Field
    // =========================================================================

    window.initGalleryField = function(fieldId, images, maxImages) {
        const wrapper = document.getElementById(fieldId + '_wrapper');
        if (!wrapper) return;
        
        wrapper.dataset.images = JSON.stringify(images);
        
        if (wrapper.dataset.sortable === 'true') {
            // Enable drag & drop sorting
            enableSortable(wrapper.querySelector('.field-gallery__grid'));
        }
    };

    window.removeGalleryItem = function(fieldId, index) {
        const wrapper = document.getElementById(fieldId + '_wrapper');
        const grid = document.getElementById(fieldId + '_grid');
        const item = grid.querySelector(`[data-id][data-index="${index}"]`) || 
                     grid.children[index];
        
        if (item) {
            item.remove();
            updateGalleryField(fieldId);
        }
    };

    function updateGalleryField(fieldId) {
        const wrapper = document.getElementById(fieldId + '_wrapper');
        const grid = document.getElementById(fieldId + '_grid');
        const maxImages = parseInt(wrapper.dataset.max) || 20;
        const currentCount = grid.children.length;
        
        // Show/hide add button
        const addBtn = wrapper.querySelector('.field-gallery__add');
        if (addBtn) {
            addBtn.closest('.field-gallery__actions').style.display = 
                currentCount < maxImages ? '' : 'none';
        }
    }

    // =========================================================================
    // Repeater Field
    // =========================================================================

    window.initRepeater = function(fieldId, subfields, options) {
        const wrapper = document.getElementById(fieldId + '_wrapper');
        if (!wrapper) return;
        
        wrapper.dataset.subfields = JSON.stringify(subfields);
        
        if (options.sortable) {
            enableSortable(wrapper.querySelector('.field-repeater__items'));
        }
    };

    window.addRepeaterItem = function(fieldId) {
        const wrapper = document.getElementById(fieldId + '_wrapper');
        const items = document.getElementById(fieldId + '_items');
        const template = document.getElementById(fieldId + '_template');
        const maxItems = parseInt(wrapper.dataset.max) || -1;
        const currentCount = items.children.length;
        
        if (maxItems >= 0 && currentCount >= maxItems) {
            return;
        }
        
        const newIndex = currentCount;
        let html = template.innerHTML.replace(/__INDEX__/g, newIndex);
        
        const temp = document.createElement('div');
        temp.innerHTML = html;
        const newItem = temp.firstElementChild;
        
        // Update item number
        const numSpan = newItem.querySelector('.field-repeater__num');
        if (numSpan) {
            numSpan.textContent = newIndex + 1;
        }
        
        // Expand new item
        newItem.classList.remove('field-repeater__item--collapsed');
        
        items.appendChild(newItem);
        updateRepeaterButtons(fieldId);
    };

    window.removeRepeaterItem = function(button) {
        const item = button.closest('.field-repeater__item');
        const wrapper = item.closest('.field-repeater');
        const fieldId = wrapper.id.replace('_wrapper', '');
        
        item.remove();
        renumberRepeaterItems(fieldId);
        updateRepeaterButtons(fieldId);
    };

    window.toggleRepeaterItem = function(button) {
        const item = button.closest('.field-repeater__item');
        item.classList.toggle('field-repeater__item--collapsed');
        button.textContent = item.classList.contains('field-repeater__item--collapsed') ? '▶' : '▼';
    };

    function renumberRepeaterItems(fieldId) {
        const items = document.getElementById(fieldId + '_items');
        const itemElements = items.querySelectorAll('.field-repeater__item');
        
        itemElements.forEach((item, index) => {
            item.dataset.index = index;
            
            // Update item number display
            const numSpan = item.querySelector('.field-repeater__num');
            if (numSpan) {
                numSpan.textContent = index + 1;
            }
            
            // Update input names
            const inputs = item.querySelectorAll('[name]');
            inputs.forEach(input => {
                input.name = input.name.replace(/\[\d+\]/, `[${index}]`);
            });
        });
    }

    function updateRepeaterButtons(fieldId) {
        const wrapper = document.getElementById(fieldId + '_wrapper');
        const items = document.getElementById(fieldId + '_items');
        const minItems = parseInt(wrapper.dataset.min) || 0;
        const maxItems = parseInt(wrapper.dataset.max) || -1;
        const currentCount = items.children.length;
        
        // Show/hide add button
        const addBtn = wrapper.querySelector('.field-repeater__add');
        if (addBtn) {
            addBtn.closest('.field-repeater__actions').style.display = 
                (maxItems < 0 || currentCount < maxItems) ? '' : 'none';
        }
        
        // Enable/disable remove buttons
        const removeButtons = items.querySelectorAll('.field-repeater__remove');
        removeButtons.forEach(btn => {
            btn.disabled = currentCount <= minItems;
        });
    }

    // =========================================================================
    // Entity Reference / Taxonomy / User Reference
    // =========================================================================

    window.initEntityReference = function(fieldId, initialValues, options) {
        const wrapper = document.getElementById(fieldId + '_wrapper');
        const searchInput = document.getElementById(fieldId + '_search');
        const resultsDiv = document.getElementById(fieldId + '_results');
        const selectedDiv = document.getElementById(fieldId + '_selected');
        const hiddenInput = document.getElementById(fieldId);
        
        if (!wrapper) return;
        
        let selectedItems = [];
        
        // Load initial values
        if (initialValues && initialValues.length > 0) {
            // In real implementation, fetch entity details
            initialValues.forEach(id => {
                selectedItems.push({ id: id, label: `Item #${id}` });
            });
            renderSelected();
        }
        
        // Search handler
        searchInput.addEventListener('input', debounce(function() {
            const query = this.value.trim();
            if (query.length < 2) {
                resultsDiv.style.display = 'none';
                return;
            }
            
            searchEntities(query, options.targetType, options.targetBundle)
                .then(results => {
                    renderResults(results);
                });
        }, 300));
        
        // Click outside to close
        document.addEventListener('click', function(e) {
            if (!wrapper.contains(e.target)) {
                resultsDiv.style.display = 'none';
            }
        });
        
        function searchEntities(query, type, bundle) {
            return new Promise((resolve) => {
                const url = `/admin/api/search/${type}?q=${encodeURIComponent(query)}&bundle=${bundle}`;
                const xhr = new XMLHttpRequest();
                xhr.open('GET', url, true);
                xhr.setRequestHeader('Accept', 'application/json');
                xhr.setRequestHeader('HX-Request', 'true');
                xhr.onreadystatechange = function() {
                    if (xhr.readyState === 4) {
                        if (xhr.status === 200) {
                            try {
                                const data = JSON.parse(xhr.responseText);
                                resolve(data.success ? data.data : []);
                            } catch (e) {
                                resolve([]);
                            }
                        } else {
                            resolve([]);
                        }
                    }
                };
                xhr.send();
            });
        }
        
        function renderResults(results) {
            if (results.length === 0) {
                resultsDiv.innerHTML = '<div class="entity-reference__no-results">No results found</div>';
            } else {
                let html = '';
                results.forEach(item => {
                    const isSelected = selectedItems.some(s => s.id === item.id);
                    html += `
                        <div class="entity-reference__result ${isSelected ? 'entity-reference__result--selected' : ''}"
                             onclick="selectEntityItem('${fieldId}', ${item.id}, '${escapeHtml(item.label)}')">
                            ${escapeHtml(item.label)}
                        </div>
                    `;
                });
                resultsDiv.innerHTML = html;
            }
            resultsDiv.style.display = 'block';
        }
        
        function renderSelected() {
            if (selectedItems.length === 0) {
                selectedDiv.innerHTML = '';
                hiddenInput.value = '';
                return;
            }
            
            let html = '';
            selectedItems.forEach((item, index) => {
                html += `
                    <span class="entity-reference__tag">
                        ${escapeHtml(item.label)}
                        <button type="button" onclick="removeEntityItem('${fieldId}', ${index})">×</button>
                    </span>
                `;
            });
            selectedDiv.innerHTML = html;
            hiddenInput.value = selectedItems.map(i => i.id).join(',');
        }
        
        window[`selectEntityItem_${fieldId}`] = function(id, label) {
            if (!options.multiple) {
                selectedItems = [{ id, label }];
            } else if (!selectedItems.some(i => i.id === id)) {
                selectedItems.push({ id, label });
            }
            renderSelected();
            searchInput.value = '';
            resultsDiv.style.display = 'none';
        };
        
        window[`removeEntityItem_${fieldId}`] = function(index) {
            selectedItems.splice(index, 1);
            renderSelected();
        };
    };

    window.selectEntityItem = function(fieldId, id, label) {
        const fn = window[`selectEntityItem_${fieldId}`];
        if (fn) fn(id, label);
    };

    window.removeEntityItem = function(fieldId, index) {
        const fn = window[`removeEntityItem_${fieldId}`];
        if (fn) fn(index);
    };

    // =========================================================================
    // Taxonomy Field
    // =========================================================================

    window.initTaxonomyTree = function(fieldId, vocabulary, selected, multiple) {
        const treeDiv = document.getElementById(fieldId + '_tree');
        const hiddenInput = document.getElementById(fieldId);
        
        if (!treeDiv) return;
        
        // Use XHR with HTMX headers
        const xhr = new XMLHttpRequest();
        xhr.open('GET', `/admin/taxonomies/${vocabulary}/terms?format=tree`, true);
        xhr.setRequestHeader('Accept', 'application/json');
        xhr.setRequestHeader('HX-Request', 'true');
        xhr.onreadystatechange = function() {
            if (xhr.readyState === 4) {
                if (xhr.status === 200) {
                    try {
                        const data = JSON.parse(xhr.responseText);
                        if (data.success) {
                            treeDiv.innerHTML = renderTree(data.data, selected, multiple, fieldId);
                        } else {
                            treeDiv.innerHTML = '<div class="error">Failed to load terms</div>';
                        }
                    } catch (e) {
                        treeDiv.innerHTML = '<div class="error">Failed to load terms</div>';
                    }
                } else {
                    treeDiv.innerHTML = '<div class="error">Failed to load terms</div>';
                }
            }
        };
        xhr.send();
        
        function renderTree(nodes, selected, multiple, fieldId, level = 0) {
            let html = '<ul class="taxonomy-tree__level taxonomy-tree__level--' + level + '">';
            nodes.forEach(node => {
                const term = node.term;
                const isSelected = selected.includes(term.id);
                const inputType = multiple ? 'checkbox' : 'radio';
                
                html += '<li class="taxonomy-tree__item">';
                html += `<label>
                    <input type="${inputType}" name="${fieldId}[]" value="${term.id}"${isSelected ? ' checked' : ''}>
                    ${escapeHtml(term.name)}
                </label>`;
                
                if (node.children && node.children.length > 0) {
                    html += renderTree(node.children, selected, multiple, fieldId, level + 1);
                }
                
                html += '</li>';
            });
            html += '</ul>';
            return html;
        }
    };

    window.initTaxonomyAutocomplete = function(fieldId, vocabulary, selected, options) {
        // Similar to entity reference but for taxonomy terms
        initEntityReference(fieldId, selected, {
            targetType: 'taxonomy',
            targetBundle: vocabulary,
            multiple: options.multiple,
            allowCreate: options.allowCreate
        });
    };

    // =========================================================================
    // Geolocation Field
    // =========================================================================

    window.geolocateField = function(fieldId) {
        if (!navigator.geolocation) {
            alert('Geolocation is not supported by your browser');
            return;
        }
        
        navigator.geolocation.getCurrentPosition(
            function(position) {
                const latInput = document.getElementById(fieldId + '_lat');
                const lngInput = document.getElementById(fieldId + '_lng');
                
                if (latInput) latInput.value = position.coords.latitude.toFixed(6);
                if (lngInput) lngInput.value = position.coords.longitude.toFixed(6);
                
                // Update map if available
                if (window[`updateGeoMap_${fieldId}`]) {
                    window[`updateGeoMap_${fieldId}`](position.coords.latitude, position.coords.longitude);
                }
            },
            function(error) {
                alert('Unable to retrieve your location: ' + error.message);
            }
        );
    };

    window.initGeoMap = function(fieldId, initialCoords, zoom) {
        const mapDiv = document.getElementById(fieldId + '_map');
        if (!mapDiv || typeof L === 'undefined') return; // Requires Leaflet
        
        const lat = initialCoords.lat || 0;
        const lng = initialCoords.lng || 0;
        
        const map = L.map(mapDiv).setView([lat, lng], zoom);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '© OpenStreetMap contributors'
        }).addTo(map);
        
        let marker = null;
        if (lat && lng) {
            marker = L.marker([lat, lng]).addTo(map);
        }
        
        map.on('click', function(e) {
            const { lat, lng } = e.latlng;
            
            document.getElementById(fieldId + '_lat').value = lat.toFixed(6);
            document.getElementById(fieldId + '_lng').value = lng.toFixed(6);
            
            if (marker) {
                marker.setLatLng(e.latlng);
            } else {
                marker = L.marker(e.latlng).addTo(map);
            }
        });
        
        window[`updateGeoMap_${fieldId}`] = function(lat, lng) {
            map.setView([lat, lng], zoom);
            if (marker) {
                marker.setLatLng([lat, lng]);
            } else {
                marker = L.marker([lat, lng]).addTo(map);
            }
        };
    };

    // =========================================================================
    // JSON Editor
    // =========================================================================

    window.initJsonEditor = function(fieldId) {
        const textarea = document.getElementById(fieldId);
        const errorDiv = document.getElementById(fieldId + '_error');
        
        if (!textarea) return;
        
        textarea.addEventListener('input', debounce(function() {
            try {
                JSON.parse(this.value);
                errorDiv.style.display = 'none';
                textarea.classList.remove('field-json__editor--invalid');
            } catch (e) {
                errorDiv.textContent = 'Invalid JSON: ' + e.message;
                errorDiv.style.display = 'block';
                textarea.classList.add('field-json__editor--invalid');
            }
        }, 500));
    };

    // =========================================================================
    // Video Preview
    // =========================================================================

    window.previewVideo = function(fieldId, url) {
        const previewDiv = document.getElementById(fieldId + '_preview');
        if (!previewDiv) return;
        
        if (!url) {
            previewDiv.innerHTML = '';
            return;
        }
        
        // YouTube
        const ytMatch = url.match(/(?:youtube\.com\/(?:[^\/]+\/.+\/|(?:v|e(?:mbed)?)\/|.*[?&]v=)|youtu\.be\/)([^"&?\/\s]{11})/i);
        if (ytMatch) {
            previewDiv.innerHTML = `<iframe src="https://www.youtube.com/embed/${ytMatch[1]}" frameborder="0" allowfullscreen></iframe>`;
            return;
        }
        
        // Vimeo
        const vimeoMatch = url.match(/vimeo\.com\/(\d+)/);
        if (vimeoMatch) {
            previewDiv.innerHTML = `<iframe src="https://player.vimeo.com/video/${vimeoMatch[1]}" frameborder="0" allowfullscreen></iframe>`;
            return;
        }
        
        // Direct video
        if (/\.(mp4|webm|ogg)$/i.test(url)) {
            previewDiv.innerHTML = `<video src="${escapeHtml(url)}" controls></video>`;
            return;
        }
        
        previewDiv.innerHTML = '<div class="field-video__error">Unsupported video URL</div>';
    };

    // =========================================================================
    // Markdown Preview
    // =========================================================================

    window.initMarkdownPreview = function(fieldId) {
        const textarea = document.getElementById(fieldId);
        const previewContent = document.getElementById(fieldId + '_preview_content');
        
        if (!textarea || !previewContent) return;
        
        const updatePreview = debounce(function() {
            // Simple markdown parsing (for demo - use a proper library in production)
            let html = textarea.value
                .replace(/^### (.*$)/gim, '<h3>$1</h3>')
                .replace(/^## (.*$)/gim, '<h2>$1</h2>')
                .replace(/^# (.*$)/gim, '<h1>$1</h1>')
                .replace(/\*\*(.*)\*\*/gim, '<strong>$1</strong>')
                .replace(/\*(.*)\*/gim, '<em>$1</em>')
                .replace(/!\[(.*?)\]\((.*?)\)/gim, '<img alt="$1" src="$2">')
                .replace(/\[(.*?)\]\((.*?)\)/gim, '<a href="$2">$1</a>')
                .replace(/`(.*?)`/gim, '<code>$1</code>')
                .replace(/\n/gim, '<br>');
            
            previewContent.innerHTML = html;
        }, 300);
        
        textarea.addEventListener('input', updatePreview);
        updatePreview();
    };

    window.markdownAction = function(fieldId, action) {
        const textarea = document.getElementById(fieldId);
        if (!textarea) return;
        
        const start = textarea.selectionStart;
        const end = textarea.selectionEnd;
        const text = textarea.value;
        const selected = text.substring(start, end);
        
        let replacement = '';
        let cursorOffset = 0;
        
        switch (action) {
            case 'bold':
                replacement = `**${selected || 'bold text'}**`;
                cursorOffset = selected ? 0 : -2;
                break;
            case 'italic':
                replacement = `*${selected || 'italic text'}*`;
                cursorOffset = selected ? 0 : -1;
                break;
            case 'heading':
                replacement = `\n## ${selected || 'Heading'}\n`;
                break;
            case 'link':
                const url = prompt('Enter URL:');
                if (url) {
                    replacement = `[${selected || 'link text'}](${url})`;
                }
                break;
            case 'image':
                const imgUrl = prompt('Enter image URL:');
                if (imgUrl) {
                    replacement = `![${selected || 'alt text'}](${imgUrl})`;
                }
                break;
            case 'code':
                replacement = `\`${selected || 'code'}\``;
                break;
            case 'ul':
                replacement = `\n- ${selected || 'list item'}\n`;
                break;
            case 'ol':
                replacement = `\n1. ${selected || 'list item'}\n`;
                break;
            case 'quote':
                replacement = `\n> ${selected || 'quote'}\n`;
                break;
        }
        
        if (replacement) {
            textarea.value = text.substring(0, start) + replacement + text.substring(end);
            textarea.focus();
            textarea.selectionStart = textarea.selectionEnd = start + replacement.length + cursorOffset;
            textarea.dispatchEvent(new Event('input'));
        }
    };

    // =========================================================================
    // Sortable Helper
    // =========================================================================

    function enableSortable(container) {
        if (!container) return;
        
        let draggedItem = null;
        
        container.addEventListener('dragstart', function(e) {
            draggedItem = e.target.closest('[draggable]') || e.target.closest('.field-repeater__item') || e.target.closest('.field-gallery__item');
            if (draggedItem) {
                draggedItem.classList.add('dragging');
                e.dataTransfer.effectAllowed = 'move';
            }
        });
        
        container.addEventListener('dragend', function(e) {
            if (draggedItem) {
                draggedItem.classList.remove('dragging');
                draggedItem = null;
            }
        });
        
        container.addEventListener('dragover', function(e) {
            e.preventDefault();
            const afterElement = getDragAfterElement(container, e.clientY);
            if (afterElement == null) {
                container.appendChild(draggedItem);
            } else {
                container.insertBefore(draggedItem, afterElement);
            }
        });
        
        function getDragAfterElement(container, y) {
            const draggableElements = [...container.querySelectorAll('.field-repeater__item:not(.dragging), .field-gallery__item:not(.dragging)')];
            
            return draggableElements.reduce((closest, child) => {
                const box = child.getBoundingClientRect();
                const offset = y - box.top - box.height / 2;
                if (offset < 0 && offset > closest.offset) {
                    return { offset: offset, element: child };
                } else {
                    return closest;
                }
            }, { offset: Number.NEGATIVE_INFINITY }).element;
        }
        
        // Make items draggable
        container.querySelectorAll('.field-repeater__item, .field-gallery__item').forEach(item => {
            item.setAttribute('draggable', 'true');
        });
    }

    // =========================================================================
    // Initialize on DOM ready
    // =========================================================================

    document.addEventListener('DOMContentLoaded', function() {
        // Auto-init character counters
        document.querySelectorAll('textarea[maxlength]').forEach(textarea => {
            const counter = textarea.parentElement.querySelector('.field-textarea__counter');
            if (counter) {
                textarea.addEventListener('input', function() {
                    const count = this.value.length;
                    const max = this.maxLength;
                    counter.querySelector('.field-textarea__count').textContent = count;
                });
            }
        });
        
        // Auto-init range outputs
        document.querySelectorAll('input[type="range"]').forEach(range => {
            const output = range.parentElement.querySelector('.field-range__value');
            if (output) {
                range.addEventListener('input', function() {
                    output.textContent = this.value;
                });
            }
        });
    });

})();
