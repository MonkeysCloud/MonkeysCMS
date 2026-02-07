/**
 * Menu Tree - Nested Drag and Drop
 */
(function() {
    'use strict';

    function initMenuTree() {
        const container = document.getElementById('menu-tree-container');
        if (!container) return;

        const reorderUrl = container.dataset.reorderUrl;
        const statusEl = document.querySelector('.sortable-status');
        
        // Options for all lists
        const sortableOptions = {
            group: 'menu', // Allow dragging between lists
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
        function saveTree() {
            if (statusEl) {
                statusEl.textContent = 'Saving...';
                statusEl.classList.remove('text-green-600', 'text-red-600');
                statusEl.classList.add('text-gray-500');
            }

            // Traverse DOM to build flat tree with parent_ids
            const items = [];
            
            function traverse(list, parentId = null) {
                Array.from(list.children).forEach((li, index) => {
                    const id = li.dataset.id;
                    if (!id) return;

                    items.push({
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

            // Use XHR with HTMX headers
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content ||
                              document.querySelector('input[name="csrf_token"]')?.value || '';
            const xhr = new XMLHttpRequest();
            xhr.open('POST', reorderUrl, true);
            xhr.setRequestHeader('Content-Type', 'application/json');
            xhr.setRequestHeader('HX-Request', 'true');
            if (csrfToken) {
                xhr.setRequestHeader('X-CSRF-TOKEN', csrfToken);
            }
            xhr.onreadystatechange = function() {
                if (xhr.readyState === 4) {
                    if (xhr.status === 200) {
                        if (statusEl) {
                            statusEl.textContent = 'Saved!';
                            statusEl.classList.add('text-green-600');
                            setTimeout(() => { statusEl.textContent = ''; }, 2000);
                        }
                    } else {
                        console.error('Save failed', xhr.status);
                        if (statusEl) {
                            statusEl.textContent = 'Error saving order';
                            statusEl.classList.add('text-red-600');
                        }
                    }
                }
            };
            xhr.send(JSON.stringify({ items: items }));
        }
    }

    // Init on load
    document.addEventListener('DOMContentLoaded', initMenuTree);
    
    // Init after HTMX swap (if applicable)
    document.body.addEventListener('htmx:afterSwap', function(event) {
        if (event.detail.target.id === 'menu-tree-container' || 
            event.detail.target.querySelector('#menu-tree-container')) {
            initMenuTree();
        }
    });

})();
