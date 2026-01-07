/**
 * Field Sortable - Auto-save on Drag
 * Uses AJAX to persist field order immediately after drag.
 */
(function() {
    'use strict';

    function initFieldSortable() {
        const containers = document.querySelectorAll('.field-sortable-container');
        if (!containers.length) return;

        containers.forEach(container => {
            const reorderUrl = container.dataset.reorderUrl;
            const context = container.dataset.context; // 'form' or 'display'
            const statusEl = container.querySelector('.sortable-status');
            const tbody = container.querySelector('.field-sortable-list');
            
            if (!tbody || !reorderUrl) return;

            new Sortable(tbody, {
                animation: 150,
                handle: '.handle',
                ghostClass: 'bg-blue-50',
                onEnd: function(evt) {
                    saveOrder();
                }
            });

            async function saveOrder() {
                if (statusEl) {
                    statusEl.textContent = 'Saving...';
                    statusEl.classList.remove('text-green-600', 'text-red-600');
                    statusEl.classList.add('text-gray-500');
                }

                // Build weights object from current DOM order
                const weights = {};
                const rows = tbody.querySelectorAll('tr[data-field]');
                rows.forEach((row, index) => {
                    const fieldName = row.dataset.field;
                    if (fieldName) {
                        weights[fieldName] = index;
                    }
                    // Also update the visible input
                    const weightInput = row.querySelector('.weight-input');
                    if (weightInput) {
                        weightInput.value = index;
                    }
                });

                // Get CSRF token from meta tag OR hidden input
                const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || 
                                  document.querySelector('input[name="csrf_token"]')?.value ||
                                  document.querySelector('input[name="csrf-token"]')?.value;

                try {
                    const response = await fetch(reorderUrl, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': csrfToken
                        },
                        body: JSON.stringify({ weights: weights, context: context })
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
        });
    }

    // Init on load
    document.addEventListener('DOMContentLoaded', initFieldSortable);
})();
