/**
 * Entity Reference Field Widget
 * MonkeysCMS Field Widget System
 */

(function() {
    'use strict';

    // Store field configurations
    const entityReferences = {};

    /**
     * Initialize Entity Reference Field
     */
    window.initEntityReference = function(fieldId, selectedIds, options) {
        entityReferences[fieldId] = {
            selectedIds: selectedIds || [],
            options: Object.assign({
                targetType: 'content',
                targetBundle: '',
                multiple: false,
                allowCreate: false
            }, options)
        };

        const wrapper = document.getElementById(fieldId + '_wrapper');
        if (!wrapper) return;

        const searchInput = document.getElementById(fieldId + '_search');
        const resultsContainer = document.getElementById(fieldId + '_results');

        // Load initial selected items
        loadSelectedEntities(fieldId);

        // Search input handler
        if (searchInput) {
            let searchTimeout;
            searchInput.addEventListener('input', function() {
                clearTimeout(searchTimeout);
                const query = this.value.trim();
                
                if (query.length < 2) {
                    resultsContainer.style.display = 'none';
                    return;
                }

                searchTimeout = setTimeout(() => {
                    searchEntities(fieldId, query);
                }, 300);
            });

            searchInput.addEventListener('focus', function() {
                if (this.value.trim().length >= 2) {
                    resultsContainer.style.display = '';
                }
            });

            // Close results on outside click
            document.addEventListener('click', function(e) {
                if (!wrapper.contains(e.target)) {
                    resultsContainer.style.display = 'none';
                }
            });
        }
    };

    /**
     * Load Selected Entities
     */
    function loadSelectedEntities(fieldId) {
        const config = entityReferences[fieldId];
        if (!config || !config.selectedIds.length) return;

        const { targetType, targetBundle } = config.options;
        const endpoint = `/admin/${targetType}s/lookup?ids=${config.selectedIds.join(',')}`;

        fetch(endpoint, { headers: { 'Accept': 'application/json' } })
            .then(response => response.json())
            .then(data => {
                if (data.success && data.data) {
                    renderSelectedEntities(fieldId, data.data);
                }
            })
            .catch(error => {
                console.error('Error loading entities:', error);
            });
    }

    /**
     * Render Selected Entities
     */
    function renderSelectedEntities(fieldId, entities) {
        const container = document.getElementById(fieldId + '_selected');
        if (!container) return;

        container.innerHTML = entities.map(entity => `
            <div class="field-entity-reference__item" data-id="${entity.id}">
                <span class="field-entity-reference__item-label">${entity.title || entity.name || 'Item #' + entity.id}</span>
                <button type="button" class="field-entity-reference__item-remove" onclick="window.removeEntityReference('${fieldId}', ${entity.id})">&times;</button>
            </div>
        `).join('');
    }

    /**
     * Search Entities
     */
    function searchEntities(fieldId, query) {
        const config = entityReferences[fieldId];
        if (!config) return;

        const { targetType, targetBundle } = config.options;
        const resultsContainer = document.getElementById(fieldId + '_results');

        let endpoint = `/admin/${targetType}s/search?q=${encodeURIComponent(query)}`;
        if (targetBundle) {
            endpoint += `&type=${encodeURIComponent(targetBundle)}`;
        }

        fetch(endpoint, { headers: { 'Accept': 'application/json' } })
            .then(response => response.json())
            .then(data => {
                if (data.success && data.data) {
                    renderSearchResults(fieldId, data.data);
                    resultsContainer.style.display = '';
                } else {
                    resultsContainer.innerHTML = '<div class="field-entity-reference__no-results">No results found</div>';
                    resultsContainer.style.display = '';
                }
            })
            .catch(error => {
                console.error('Search error:', error);
                resultsContainer.innerHTML = '<div class="field-entity-reference__error">Search error</div>';
                resultsContainer.style.display = '';
            });
    }

    /**
     * Render Search Results
     */
    function renderSearchResults(fieldId, results) {
        const config = entityReferences[fieldId];
        const resultsContainer = document.getElementById(fieldId + '_results');
        if (!resultsContainer) return;

        // Filter out already selected items
        const filteredResults = results.filter(r => !config.selectedIds.includes(r.id));

        if (!filteredResults.length) {
            resultsContainer.innerHTML = '<div class="field-entity-reference__no-results">No results found</div>';
            return;
        }

        resultsContainer.innerHTML = filteredResults.map(entity => `
            <div class="field-entity-reference__result" onclick="window.selectEntityReference('${fieldId}', ${entity.id}, '${(entity.title || entity.name || '').replace(/'/g, "\\'")}')">
                <span class="field-entity-reference__result-label">${entity.title || entity.name || 'Item #' + entity.id}</span>
                ${entity.type ? `<span class="field-entity-reference__result-type">${entity.type}</span>` : ''}
            </div>
        `).join('');
    }

    /**
     * Select Entity
     */
    window.selectEntityReference = function(fieldId, entityId, entityLabel) {
        const config = entityReferences[fieldId];
        if (!config) return;

        const { multiple } = config.options;
        const selectedContainer = document.getElementById(fieldId + '_selected');
        const resultsContainer = document.getElementById(fieldId + '_results');
        const searchInput = document.getElementById(fieldId + '_search');
        const hidden = document.getElementById(fieldId);
        const wrapper = document.getElementById(fieldId + '_wrapper');

        // Add to selected
        if (multiple) {
            if (!config.selectedIds.includes(entityId)) {
                config.selectedIds.push(entityId);
                
                // Add visual element
                const item = document.createElement('div');
                item.className = 'field-entity-reference__item';
                item.dataset.id = entityId;
                item.innerHTML = `
                    <span class="field-entity-reference__item-label">${entityLabel}</span>
                    <button type="button" class="field-entity-reference__item-remove" onclick="window.removeEntityReference('${fieldId}', ${entityId})">&times;</button>
                `;
                selectedContainer.appendChild(item);

                // Add hidden input
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = hidden.name.replace(/\[\]$/, '') + '[]';
                input.value = entityId;
                input.className = 'field-entity-reference__value';
                wrapper.appendChild(input);
            }
        } else {
            config.selectedIds = [entityId];
            
            // Update display
            selectedContainer.innerHTML = `
                <div class="field-entity-reference__item" data-id="${entityId}">
                    <span class="field-entity-reference__item-label">${entityLabel}</span>
                    <button type="button" class="field-entity-reference__item-remove" onclick="window.removeEntityReference('${fieldId}', ${entityId})">&times;</button>
                </div>
            `;

            // Update hidden input
            hidden.value = entityId;
        }

        // Clear search
        if (searchInput) {
            searchInput.value = '';
        }
        resultsContainer.style.display = 'none';
    };

    /**
     * Remove Entity Reference
     */
    window.removeEntityReference = function(fieldId, entityId) {
        const config = entityReferences[fieldId];
        if (!config) return;

        const { multiple } = config.options;
        const selectedContainer = document.getElementById(fieldId + '_selected');
        const hidden = document.getElementById(fieldId);
        const wrapper = document.getElementById(fieldId + '_wrapper');

        // Remove from array
        config.selectedIds = config.selectedIds.filter(id => id !== entityId);

        // Remove visual element
        const item = selectedContainer.querySelector(`[data-id="${entityId}"]`);
        if (item) item.remove();

        if (multiple) {
            // Remove hidden input
            const input = wrapper.querySelector(`.field-entity-reference__value[value="${entityId}"]`);
            if (input) input.remove();
        } else {
            hidden.value = '';
        }
    };

})();
