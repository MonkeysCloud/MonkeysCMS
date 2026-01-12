/**
 * Taxonomy Tree - Nested Drag and Drop
 */
(function() {
    'use strict';

    function initTaxonomyTree() {
        const container = document.getElementById('taxonomy-tree-container');
        if (!container) return;

        const reorderUrl = container.dataset.reorderUrl;
        const statusEl = document.querySelector('.sortable-status');
        
        // Initialize nested sortables
        const nestedSortables = [];
        
        // Options for all lists
        const sortableOptions = {
            group: 'taxonomy', // Allow dragging between lists
            animation: 150,
            fallbackOnBody: true,
            swapThreshold: 0.65,
            handle: '.handle',
            ghostClass: 'bg-blue-50',
            onEnd: function(evt) {
                saveTree();
            }
        };

        // Initialize root list
        const rootList = container.querySelector('.root-list');
        if (rootList) {
            new Sortable(rootList, sortableOptions);
        }

        // Initialize all nested lists
        document.querySelectorAll('.nested-sortable').forEach(el => {
            if (el !== rootList) {
                new Sortable(el, sortableOptions);
            }
        });

        // Save Function
        async function saveTree() {
            if (statusEl) {
                statusEl.textContent = 'Saving...';
                statusEl.classList.remove('text-green-600', 'text-red-600');
                statusEl.classList.add('text-gray-500');
            }

            // Traverse DOM to build flat tree with parent_ids
            const terms = [];
            
            function traverse(list, parentId = null) {
                Array.from(list.children).forEach((li, index) => {
                    const id = li.dataset.id;
                    if (!id) return;

                    terms.push({
                        id: id,
                        parent_id: parentId,
                        weight: index
                    });

                    // Check for children
                    const childUl = li.querySelector('ul');
                    if (childUl && childUl.children.length > 0) {
                        traverse(childUl, id);
                    }
                });
            }

            traverse(rootList);

            try {
                const response = await fetch(reorderUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content
                    },
                    body: JSON.stringify({ terms: terms })
                });

                if (!response.ok) throw new Error('Failed to save');

                if (statusEl) {
                    statusEl.textContent = 'Saved!';
                    statusEl.classList.add('text-green-600');
                    setTimeout(() => { statusEl.textContent = ''; }, 2000);
                }
            } catch (e) {
                console.error('Save failed', e);
                if (statusEl) {
                    statusEl.textContent = 'Error saving order';
                    statusEl.classList.add('text-red-600');
                }
            }
        }
    }

    // Init on load
    document.addEventListener('DOMContentLoaded', initTaxonomyTree);
    
    // Init after HTMX swap (if applicable)
    document.body.addEventListener('htmx:afterSwap', function(event) {
        if (event.detail.target.id === 'taxonomy-tree-container' || 
            event.detail.target.querySelector('#taxonomy-tree-container')) {
            initTaxonomyTree();
        }
    });

})();
