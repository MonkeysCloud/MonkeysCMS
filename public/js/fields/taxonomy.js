/**
 * Taxonomy Field Widget
 * MonkeysCMS Field Widget System
 */

(function() {
    'use strict';

    // Store field configurations
    const taxonomyFields = {};

    /**
     * CmsTaxonomy - Global namespace for taxonomy widget
     */
    window.CmsTaxonomy = {
        /**
         * Initialize a taxonomy field (checkboxes/select)
         */
        init: function(elementId) {
            const wrapper = document.getElementById(elementId)?.closest('.field-taxonomy') 
                || document.querySelector(`[data-field-id="${elementId}"]`);
            
            if (!wrapper || wrapper.dataset.initialized) return;
            wrapper.dataset.initialized = 'true';

            const hiddenInput = document.getElementById(elementId);
            const displayStyle = wrapper.classList.contains('field-taxonomy--select') ? 'select' : 'checkboxes';

            if (displayStyle === 'select') {
                this.initSelect(elementId, wrapper, hiddenInput);
            } else {
                this.initCheckboxes(elementId, wrapper, hiddenInput);
            }
        },

        /**
         * Initialize checkboxes
         */
        initCheckboxes: function(elementId, wrapper, hiddenInput) {
            const checkboxes = wrapper.querySelectorAll('.field-taxonomy__checkbox-input, .field-taxonomy__tree-checkbox');
            
            const updateValue = () => {
                const checked = Array.from(checkboxes)
                    .filter(cb => cb.checked)
                    .map(cb => parseInt(cb.dataset.termId));
                hiddenInput.value = JSON.stringify(checked);
            };

            checkboxes.forEach(cb => {
                cb.addEventListener('change', updateValue);
            });

            // Set initial values from hidden input
            try {
                const values = JSON.parse(hiddenInput.value || '[]');
                checkboxes.forEach(cb => {
                    cb.checked = values.includes(parseInt(cb.dataset.termId));
                });
            } catch (e) {}
        },

        /**
         * Initialize select dropdown
         */
        initSelect: function(elementId, wrapper, hiddenInput) {
            const select = wrapper.querySelector('.field-taxonomy__select');
            if (!select) return;

            const updateValue = () => {
                const multiple = select.multiple;
                if (multiple) {
                    const values = Array.from(select.selectedOptions).map(opt => parseInt(opt.value)).filter(v => !isNaN(v));
                    hiddenInput.value = JSON.stringify(values);
                } else {
                    const value = parseInt(select.value);
                    hiddenInput.value = isNaN(value) ? '[]' : JSON.stringify([value]);
                }
            };

            select.addEventListener('change', updateValue);

            // Set initial values from hidden input
            try {
                const values = JSON.parse(hiddenInput.value || '[]');
                Array.from(select.options).forEach(opt => {
                    opt.selected = values.includes(parseInt(opt.value));
                });
            } catch (e) {}
        },

        /**
         * Initialize tags/autocomplete mode
         */
        initTags: function(elementId, apiUrl) {
            initTaxonomyAutocomplete(elementId, '', [], { multiple: true, allowCreate: false });
        }
    };

    /**
     * Initialize Taxonomy Tree
     */
    window.initTaxonomyTree = function(fieldId, vocabulary, selectedIds, multiple) {
        taxonomyFields[fieldId] = {
            vocabulary: vocabulary,
            selectedIds: selectedIds || [],
            multiple: multiple
        };

        const container = document.getElementById(fieldId + '_tree');
        if (!container) return;

        // Load terms
        loadTerms(vocabulary)
            .then(terms => {
                renderTree(fieldId, container, terms, selectedIds, multiple);
            })
            .catch(error => {
                console.error('Error loading terms:', error);
                container.innerHTML = '<div class="field-taxonomy-tree__error">Error loading terms</div>';
            });
    };

    /**
     * Initialize Taxonomy Autocomplete
     */
    window.initTaxonomyAutocomplete = function(fieldId, vocabulary, selectedIds, options) {
        taxonomyFields[fieldId] = {
            vocabulary: vocabulary,
            selectedIds: selectedIds || [],
            options: Object.assign({
                multiple: false,
                allowCreate: false
            }, options)
        };

        const wrapper = document.getElementById(fieldId + '_wrapper');
        const searchInput = document.getElementById(fieldId + '_search');
        const resultsContainer = document.getElementById(fieldId + '_results');

        if (!searchInput) return;

        // Load initial selected terms
        if (selectedIds.length) {
            loadSelectedTerms(fieldId, vocabulary, selectedIds);
        }

        // Search input handler
        let searchTimeout;
        searchInput.addEventListener('input', function() {
            clearTimeout(searchTimeout);
            const query = this.value.trim();

            if (query.length < 1) {
                resultsContainer.style.display = 'none';
                return;
            }

            searchTimeout = setTimeout(() => {
                searchTerms(fieldId, vocabulary, query);
            }, 200);
        });

        // Handle Enter key for creating new terms
        if (options.allowCreate) {
            searchInput.addEventListener('keydown', function(e) {
                if (e.key === 'Enter' && this.value.trim()) {
                    e.preventDefault();
                    createTerm(fieldId, vocabulary, this.value.trim());
                }
            });
        }

        // Close results on outside click
        document.addEventListener('click', function(e) {
            if (!wrapper.contains(e.target)) {
                resultsContainer.style.display = 'none';
            }
        });
    };

    /**
     * Load Terms from API
     */
    function loadTerms(vocabulary) {
        return fetch(`/admin/taxonomies/${vocabulary}/terms?format=tree`, {
            headers: { 'Accept': 'application/json' }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success && data.data) {
                return data.data;
            }
            return [];
        });
    }

    /**
     * Render Tree View
     */
    function renderTree(fieldId, container, terms, selectedIds, multiple) {
        const hidden = document.getElementById(fieldId);

        function renderNode(node, depth = 0) {
            const term = node.term || node;
            const children = node.children || [];
            const isSelected = selectedIds.includes(term.id);

            let html = `
                <div class="field-taxonomy-tree__item" style="padding-left: ${depth * 20}px;">
                    <label class="field-taxonomy-tree__label">
                        <input type="${multiple ? 'checkbox' : 'radio'}" 
                               name="${fieldId}_tree_input" 
                               value="${term.id}"
                               ${isSelected ? 'checked' : ''}
                               onchange="window.updateTaxonomyTreeSelection('${fieldId}')">
                        <span>${term.name}</span>
                    </label>
                </div>
            `;

            if (children.length) {
                html += children.map(child => renderNode(child, depth + 1)).join('');
            }

            return html;
        }

        container.innerHTML = terms.map(term => renderNode(term)).join('');

        // Initialize hidden value
        updateTaxonomyTreeSelection(fieldId);
    }

    /**
     * Update Tree Selection
     */
    window.updateTaxonomyTreeSelection = function(fieldId) {
        const container = document.getElementById(fieldId + '_tree');
        const hidden = document.getElementById(fieldId);
        if (!container || !hidden) return;

        const checked = container.querySelectorAll('input:checked');
        const ids = Array.from(checked).map(input => parseInt(input.value));
        hidden.value = JSON.stringify(ids);

        // Update config
        if (taxonomyFields[fieldId]) {
            taxonomyFields[fieldId].selectedIds = ids;
        }
    };

    /**
     * Load Selected Terms for Autocomplete
     */
    function loadSelectedTerms(fieldId, vocabulary, termIds) {
        const selectedContainer = document.getElementById(fieldId + '_selected');
        if (!selectedContainer) return;

        fetch(`/admin/taxonomies/${vocabulary}/terms?ids=${termIds.join(',')}`, {
            headers: { 'Accept': 'application/json' }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success && data.data) {
                renderSelectedTags(fieldId, data.data);
            }
        });
    }

    /**
     * Render Selected Tags
     */
    function renderSelectedTags(fieldId, terms) {
        const selectedContainer = document.getElementById(fieldId + '_selected');
        if (!selectedContainer) return;

        selectedContainer.innerHTML = terms.map(term => `
            <span class="field-taxonomy-autocomplete__tag" data-id="${term.id}">
                ${term.name}
                <button type="button" class="field-taxonomy-autocomplete__remove" onclick="window.removeTaxonomyTag('${fieldId}', ${term.id})">&times;</button>
            </span>
        `).join('');
    }

    /**
     * Search Terms
     */
    function searchTerms(fieldId, vocabulary, query) {
        const config = taxonomyFields[fieldId];
        const resultsContainer = document.getElementById(fieldId + '_results');

        fetch(`/admin/taxonomies/${vocabulary}/terms?search=${encodeURIComponent(query)}`, {
            headers: { 'Accept': 'application/json' }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success && data.data) {
                renderAutocompleteResults(fieldId, data.data, query);
            } else {
                renderAutocompleteResults(fieldId, [], query);
            }
            resultsContainer.style.display = '';
        });
    }

    /**
     * Render Autocomplete Results
     */
    function renderAutocompleteResults(fieldId, results, query) {
        const config = taxonomyFields[fieldId];
        const resultsContainer = document.getElementById(fieldId + '_results');
        if (!resultsContainer) return;

        // Filter out already selected
        const filtered = results.filter(t => !config.selectedIds.includes(t.id));

        let html = '';

        if (filtered.length) {
            html = filtered.map(term => `
                <div class="field-taxonomy-autocomplete__result" onclick="window.selectTaxonomyTerm('${fieldId}', ${term.id}, '${term.name.replace(/'/g, "\\'")}')">${term.name}</div>
            `).join('');
        }

        // Add "Create new" option if allowed
        if (config.options && config.options.allowCreate && query) {
            const exists = results.some(t => t.name.toLowerCase() === query.toLowerCase());
            if (!exists) {
                html += `
                    <div class="field-taxonomy-autocomplete__result field-taxonomy-autocomplete__result--create" onclick="window.createTaxonomyTerm('${fieldId}', '${config.vocabulary}', '${query.replace(/'/g, "\\'")}')">
                        + Create "${query}"
                    </div>
                `;
            }
        }

        if (!html) {
            html = '<div class="field-taxonomy-autocomplete__no-results">No results found</div>';
        }

        resultsContainer.innerHTML = html;
    }

    /**
     * Select Taxonomy Term
     */
    window.selectTaxonomyTerm = function(fieldId, termId, termName) {
        const config = taxonomyFields[fieldId];
        if (!config) return;

        const { multiple } = config.options || {};
        const selectedContainer = document.getElementById(fieldId + '_selected');
        const resultsContainer = document.getElementById(fieldId + '_results');
        const searchInput = document.getElementById(fieldId + '_search');
        const hidden = document.getElementById(fieldId);

        if (multiple) {
            if (!config.selectedIds.includes(termId)) {
                config.selectedIds.push(termId);

                // Add tag
                const tag = document.createElement('span');
                tag.className = 'field-taxonomy-autocomplete__tag';
                tag.dataset.id = termId;
                tag.innerHTML = `
                    ${termName}
                    <button type="button" class="field-taxonomy-autocomplete__remove" onclick="window.removeTaxonomyTag('${fieldId}', ${termId})">&times;</button>
                `;
                selectedContainer.appendChild(tag);
            }
        } else {
            config.selectedIds = [termId];
            selectedContainer.innerHTML = `
                <span class="field-taxonomy-autocomplete__tag" data-id="${termId}">
                    ${termName}
                    <button type="button" class="field-taxonomy-autocomplete__remove" onclick="window.removeTaxonomyTag('${fieldId}', ${termId})">&times;</button>
                </span>
            `;
        }

        // Update hidden input
        hidden.value = JSON.stringify(config.selectedIds);

        // Clear search
        if (searchInput) searchInput.value = '';
        resultsContainer.style.display = 'none';
    };

    /**
     * Remove Taxonomy Tag
     */
    window.removeTaxonomyTag = function(fieldId, termId) {
        const config = taxonomyFields[fieldId];
        if (!config) return;

        const selectedContainer = document.getElementById(fieldId + '_selected');
        const hidden = document.getElementById(fieldId);

        // Remove from array
        config.selectedIds = config.selectedIds.filter(id => id !== termId);

        // Remove tag element
        const tag = selectedContainer.querySelector(`[data-id="${termId}"]`);
        if (tag) tag.remove();

        // Update hidden input
        hidden.value = JSON.stringify(config.selectedIds);
    };

    /**
     * Create New Taxonomy Term
     */
    window.createTaxonomyTerm = function(fieldId, vocabulary, name) {
        const config = taxonomyFields[fieldId];
        const resultsContainer = document.getElementById(fieldId + '_results');
        const searchInput = document.getElementById(fieldId + '_search');

        fetch(`/admin/taxonomies/${vocabulary}/terms`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json'
            },
            body: JSON.stringify({ name: name })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success && data.data) {
                window.selectTaxonomyTerm(fieldId, data.data.id, data.data.name);
            } else {
                alert('Failed to create term: ' + (data.error || 'Unknown error'));
            }
        })
        .catch(error => {
            console.error('Error creating term:', error);
            alert('Failed to create term');
        });
    };

    // ─────────────────────────────────────────────────────────────
    // Self-Initialization Pattern
    // ─────────────────────────────────────────────────────────────

    function initAll(context) {
        context = context || document;
        
        // Initialize all taxonomy fields in context
        context.querySelectorAll('.field-taxonomy[data-field-id]').forEach(function(wrapper) {
            if (wrapper.dataset.initialized) return;
            
            const fieldId = wrapper.dataset.fieldId;
            const displayStyle = wrapper.className.match(/field-taxonomy--(\w+)/)?.[1] || 'checkboxes';
            
            if (displayStyle === 'tree') {
                // Tree mode - handled by initTaxonomyTree if needed
                wrapper.dataset.initialized = 'true';
            } else if (displayStyle === 'tags' || displayStyle === 'autocomplete') {
                // Tags/Autocomplete mode - handled by initTaxonomyAutocomplete if needed
                wrapper.dataset.initialized = 'true';
            } else {
                // Checkboxes or select mode
                window.CmsTaxonomy.init(fieldId);
            }
        });
    }

    // Self-initialize on page load
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function() { initAll(document); });
    } else {
        initAll(document);
    }

    // Handle dynamically added repeater items
    document.addEventListener('cms:content-changed', function(e) {
        if (e.detail && e.detail.target) {
            initAll(e.detail.target);
        }
    });

    // Register with global behaviors system (if available)
    if (window.CmsBehaviors) {
        window.CmsBehaviors.register('taxonomy', {
            selector: '.field-taxonomy',
            attach: initAll
        });
    }

})();
