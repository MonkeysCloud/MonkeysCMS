/**
 * Repeater Field Widget
 * MonkeysCMS Field Widget System
 */

(function() {
    'use strict';

    // Store repeater configurations
    const repeaters = {};

    /**
     * Initialize Repeater Field
     */
    window.initRepeater = function(fieldId, subfields, initialItems, options) {
        repeaters[fieldId] = {
            subfields: subfields,
            items: initialItems || [],
            options: Object.assign({
                minItems: 0,
                maxItems: -1,
                sortable: true,
                collapsed: false,
                itemLabel: 'Item'
            }, options),
            nextIndex: initialItems ? initialItems.length : 0
        };

        const wrapper = document.getElementById(fieldId + '_wrapper');
        if (!wrapper) return;

        // Initialize sortable if enabled
        if (options.sortable && typeof Sortable !== 'undefined') {
            initSortable(fieldId);
        } else if (options.sortable) {
            // Fallback drag and drop
            initDragAndDrop(fieldId);
        }

        // Initialize toggle buttons
        initToggleButtons(fieldId);

        // Update add button state
        updateAddButtonState(fieldId);
    };

    /**
     * Add New Repeater Item
     */
    window.addRepeaterItem = function(fieldId) {
        const config = repeaters[fieldId];
        if (!config) return;

        const wrapper = document.getElementById(fieldId + '_wrapper');
        const container = document.getElementById(fieldId + '_items');
        const template = document.getElementById(fieldId + '_template');

        if (!container || !template) return;

        // Check max items
        const currentCount = container.querySelectorAll('.field-repeater__item').length;
        if (config.options.maxItems > 0 && currentCount >= config.options.maxItems) {
            return;
        }

        // Clone template and replace index placeholder
        const newIndex = config.nextIndex++;
        let html = template.innerHTML.replace(/__INDEX__/g, newIndex);
        
        // Create element
        const temp = document.createElement('div');
        temp.innerHTML = html;
        const newItem = temp.firstElementChild;

        // Update item label
        const label = newItem.querySelector('.field-repeater__item-label');
        if (label) {
            label.textContent = config.options.itemLabel + ' ' + (currentCount + 1);
        }

        // Add to container
        container.appendChild(newItem);

        // Initialize toggle
        initToggleButton(newItem.querySelector('.field-repeater__item-toggle'), newItem);

        // Update add button state
        updateAddButtonState(fieldId);

        // Scroll to new item
        newItem.scrollIntoView({ behavior: 'smooth', block: 'nearest' });

        // Focus first input
        const firstInput = newItem.querySelector('input, textarea, select');
        if (firstInput) {
            firstInput.focus();
        }
    };

    /**
     * Remove Repeater Item
     */
    window.removeRepeaterItem = function(button) {
        const item = button.closest('.field-repeater__item');
        if (!item) return;

        const wrapper = item.closest('.field-repeater');
        const fieldId = wrapper.dataset.field || wrapper.id.replace('_wrapper', '');
        const config = repeaters[fieldId];
        const container = item.parentElement;

        // Check min items
        const currentCount = container.querySelectorAll('.field-repeater__item').length;
        if (config && config.options.minItems > 0 && currentCount <= config.options.minItems) {
            alert('Minimum ' + config.options.minItems + ' item(s) required.');
            return;
        }

        // Confirm removal
        if (!confirm('Remove this item?')) return;

        // Remove with animation
        item.style.opacity = '0';
        item.style.transform = 'translateX(-20px)';
        setTimeout(() => {
            item.remove();
            renumberItems(fieldId);
            updateAddButtonState(fieldId);
        }, 200);
    };

    /**
     * Renumber Items After Removal/Reorder
     */
    function renumberItems(fieldId) {
        const config = repeaters[fieldId];
        if (!config) return;

        const container = document.getElementById(fieldId + '_items');
        if (!container) return;

        const items = container.querySelectorAll('.field-repeater__item');
        items.forEach((item, index) => {
            // Update label
            const label = item.querySelector('.field-repeater__item-label');
            if (label) {
                label.textContent = config.options.itemLabel + ' ' + (index + 1);
            }

            // Update input names
            item.querySelectorAll('[name]').forEach(input => {
                const name = input.name;
                const newName = name.replace(/\[\d+\]/, '[' + index + ']');
                input.name = newName;
            });

            // Update IDs
            item.querySelectorAll('[id]').forEach(el => {
                const id = el.id;
                const newId = id.replace(/_\d+_/, '_' + index + '_');
                el.id = newId;
            });

            // Update data-index
            item.dataset.index = index;
        });
    }

    /**
     * Update Add Button State
     */
    function updateAddButtonState(fieldId) {
        const config = repeaters[fieldId];
        if (!config) return;

        const wrapper = document.getElementById(fieldId + '_wrapper');
        const addBtn = wrapper?.querySelector('.field-repeater__add');
        const container = document.getElementById(fieldId + '_items');

        if (!addBtn || !container) return;

        const currentCount = container.querySelectorAll('.field-repeater__item').length;
        const maxItems = config.options.maxItems;

        if (maxItems > 0 && currentCount >= maxItems) {
            addBtn.disabled = true;
            addBtn.style.display = 'none';
        } else {
            addBtn.disabled = false;
            addBtn.style.display = '';
        }
    }

    /**
     * Initialize Toggle Buttons
     */
    function initToggleButtons(fieldId) {
        const container = document.getElementById(fieldId + '_items');
        if (!container) return;

        container.querySelectorAll('.field-repeater__item').forEach(item => {
            const toggle = item.querySelector('.field-repeater__item-toggle');
            if (toggle) {
                initToggleButton(toggle, item);
            }
        });
    }

    /**
     * Initialize Single Toggle Button
     */
    function initToggleButton(toggle, item) {
        if (!toggle || !item) return;

        toggle.addEventListener('click', function() {
            item.classList.toggle('field-repeater__item--collapsed');
            this.textContent = item.classList.contains('field-repeater__item--collapsed') ? '▶' : '▼';
        });
    }

    /**
     * Initialize Sortable (using SortableJS if available)
     */
    function initSortable(fieldId) {
        const container = document.getElementById(fieldId + '_items');
        if (!container || typeof Sortable === 'undefined') return;

        new Sortable(container, {
            handle: '.field-repeater__item-drag',
            animation: 150,
            ghostClass: 'field-repeater__item--ghost',
            onEnd: function() {
                renumberItems(fieldId);
            }
        });
    }

    /**
     * Initialize Basic Drag and Drop (fallback)
     */
    function initDragAndDrop(fieldId) {
        const container = document.getElementById(fieldId + '_items');
        if (!container) return;

        let draggedItem = null;

        container.querySelectorAll('.field-repeater__item-drag').forEach(handle => {
            const item = handle.closest('.field-repeater__item');
            
            handle.addEventListener('mousedown', function(e) {
                item.draggable = true;
            });

            item.addEventListener('dragstart', function(e) {
                draggedItem = item;
                item.classList.add('field-repeater__item--dragging');
                e.dataTransfer.effectAllowed = 'move';
            });

            item.addEventListener('dragend', function() {
                item.draggable = false;
                item.classList.remove('field-repeater__item--dragging');
                draggedItem = null;
                renumberItems(fieldId);
            });

            item.addEventListener('dragover', function(e) {
                e.preventDefault();
                if (draggedItem && draggedItem !== item) {
                    const rect = item.getBoundingClientRect();
                    const midY = rect.top + rect.height / 2;
                    if (e.clientY < midY) {
                        container.insertBefore(draggedItem, item);
                    } else {
                        container.insertBefore(draggedItem, item.nextSibling);
                    }
                }
            });
        });
    }

})();
