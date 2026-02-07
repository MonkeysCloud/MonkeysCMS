/**
 * Content Bulk Operations
 * Handles checkbox selection and bulk actions for content lists
 */

(function() {
    'use strict';

    // Initialize when DOM is ready
    document.addEventListener('DOMContentLoaded', function() {
        initBulkOperations();
    });

    function initBulkOperations() {
        var bulkForm = document.getElementById('bulkActionForm');
        if (!bulkForm) return;

        // Attach event listener to bulk action form
        bulkForm.addEventListener('submit', handleBulkSubmit);
    }

    // Toggle all checkboxes
    window.toggleSelectAll = function(checkbox) {
        var items = document.querySelectorAll('.item-checkbox');
        items.forEach(function(item) {
            item.checked = checkbox.checked;
        });
        updateBulkActions();
    };

    // Update bulk actions bar based on selection
    window.updateBulkActions = function() {
        var checked = document.querySelectorAll('.item-checkbox:checked');
        var count = checked.length;
        var bar = document.getElementById('bulkActionsBar');
        var countSpan = document.getElementById('selectedCount');
        var selectAll = document.getElementById('selectAll');
        var allItems = document.querySelectorAll('.item-checkbox');
        
        if (!bar || !countSpan) return;
        
        countSpan.textContent = count;
        
        if (count > 0) {
            bar.classList.remove('hidden');
        } else {
            bar.classList.add('hidden');
        }
        
        // Update select all checkbox state
        if (selectAll) {
            if (count === 0) {
                selectAll.checked = false;
                selectAll.indeterminate = false;
            } else if (count === allItems.length) {
                selectAll.checked = true;
                selectAll.indeterminate = false;
            } else {
                selectAll.indeterminate = true;
            }
        }
    };

    // Clear all selections
    window.clearSelection = function() {
        var items = document.querySelectorAll('.item-checkbox');
        items.forEach(function(item) {
            item.checked = false;
        });
        var selectAll = document.getElementById('selectAll');
        if (selectAll) selectAll.checked = false;
        updateBulkActions();
    };

    // Handle bulk action form submission
    function handleBulkSubmit(e) {
        var action = document.getElementById('bulkAction').value;
        var checked = document.querySelectorAll('.item-checkbox:checked');
        
        if (!action) {
            e.preventDefault();
            alert('Please select an action');
            return;
        }
        
        if (checked.length === 0) {
            e.preventDefault();
            alert('Please select at least one item');
            return;
        }
        
        if (action === 'delete') {
            if (!confirm('Are you sure you want to delete ' + checked.length + ' item(s)? This cannot be undone.')) {
                e.preventDefault();
                return;
            }
        }
        
        // Add checked items to form (they may not be inside the form)
        var form = document.getElementById('bulkActionForm');
        checked.forEach(function(item) {
            var input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'items[]';
            input.value = item.value;
            form.appendChild(input);
        });
    }
})();
