/**
 * MonkeysCMS Icon Picker Widget JavaScript
 */

(function() {
    'use strict';

    /**
     * Toggle icon picker dropdown
     */
    window.toggleIconPicker = function(fieldId) {
        const dropdown = document.getElementById(fieldId + '_dropdown');
        if (!dropdown) return;
        
        const isVisible = dropdown.style.display !== 'none';
        
        // Close all other dropdowns
        document.querySelectorAll('.icon-picker__dropdown').forEach(d => {
            d.style.display = 'none';
        });
        
        if (!isVisible) {
            dropdown.style.display = 'flex';
            
            // Focus search if present
            const search = dropdown.querySelector('input[type="text"]');
            if (search) {
                search.focus();
            }
        }
    };

    /**
     * Select an icon
     */
    window.selectIcon = function(fieldId, iconName) {
        const input = document.getElementById(fieldId);
        const preview = document.getElementById(fieldId + '_preview');
        const label = document.querySelector('#' + fieldId + '_wrapper .icon-picker__label');
        const clearBtn = document.querySelector('#' + fieldId + '_wrapper .icon-picker__clear');
        const dropdown = document.getElementById(fieldId + '_dropdown');
        const grid = document.getElementById(fieldId + '_grid');
        
        if (!input) return;
        
        // Update input value
        input.value = iconName;
        
        // Update selected state in grid
        if (grid) {
            grid.querySelectorAll('.icon-picker__icon').forEach(icon => {
                icon.classList.toggle('icon-picker__icon--selected', icon.dataset.name === iconName);
            });
        }
        
        // Update preview
        if (preview) {
            const selectedIcon = grid?.querySelector(`[data-name="${iconName}"]`);
            if (selectedIcon) {
                preview.innerHTML = selectedIcon.innerHTML;
            }
        }
        
        // Update label
        if (label) {
            label.textContent = iconName;
        }
        
        // Show clear button
        if (clearBtn) {
            clearBtn.style.display = '';
        }
        
        // Close dropdown
        if (dropdown) {
            dropdown.style.display = 'none';
        }
        
        // Trigger change event
        input.dispatchEvent(new Event('change', { bubbles: true }));
    };

    /**
     * Clear selected icon
     */
    window.clearIcon = function(fieldId) {
        const input = document.getElementById(fieldId);
        const preview = document.getElementById(fieldId + '_preview');
        const label = document.querySelector('#' + fieldId + '_wrapper .icon-picker__label');
        const clearBtn = document.querySelector('#' + fieldId + '_wrapper .icon-picker__clear');
        const grid = document.getElementById(fieldId + '_grid');
        
        if (!input) return;
        
        // Clear input value
        input.value = '';
        
        // Clear selected state
        if (grid) {
            grid.querySelectorAll('.icon-picker__icon--selected').forEach(icon => {
                icon.classList.remove('icon-picker__icon--selected');
            });
        }
        
        // Update preview
        if (preview) {
            preview.innerHTML = '<span class="icon-picker__empty">No icon</span>';
        }
        
        // Update label
        if (label) {
            label.textContent = 'Select icon';
        }
        
        // Hide clear button
        if (clearBtn) {
            clearBtn.style.display = 'none';
        }
        
        // Trigger change event
        input.dispatchEvent(new Event('change', { bubbles: true }));
    };

    /**
     * Filter icons by search term
     */
    window.filterIcons = function(fieldId, searchTerm) {
        const grid = document.getElementById(fieldId + '_grid');
        if (!grid) return;
        
        const term = searchTerm.toLowerCase().trim();
        const icons = grid.querySelectorAll('.icon-picker__icon');
        
        icons.forEach(icon => {
            const name = (icon.dataset.name || '').toLowerCase();
            const category = (icon.dataset.category || '').toLowerCase();
            const matches = !term || name.includes(term) || category.includes(term);
            
            icon.classList.toggle('icon-picker__icon--hidden', !matches);
        });
    };

    /**
     * Filter icons by category
     */
    window.filterIconCategory = function(fieldId, category, button) {
        const grid = document.getElementById(fieldId + '_grid');
        const wrapper = document.getElementById(fieldId + '_wrapper');
        if (!grid) return;
        
        // Update active button
        if (wrapper) {
            wrapper.querySelectorAll('.icon-picker__category').forEach(btn => {
                btn.classList.remove('icon-picker__category--active');
            });
            if (button) {
                button.classList.add('icon-picker__category--active');
            }
        }
        
        // Filter icons
        const icons = grid.querySelectorAll('.icon-picker__icon');
        icons.forEach(icon => {
            const iconCategory = icon.dataset.category || '';
            const matches = !category || iconCategory === category;
            
            icon.classList.toggle('icon-picker__icon--hidden', !matches);
        });
    };

    /**
     * Close dropdown on outside click
     */
    document.addEventListener('click', function(e) {
        const target = e.target;
        
        // Check if click is inside an icon picker
        const wrapper = target.closest('.field-icon-picker');
        
        // Close all dropdowns not related to clicked wrapper
        document.querySelectorAll('.icon-picker__dropdown').forEach(dropdown => {
            const dropdownWrapper = dropdown.closest('.field-icon-picker');
            if (dropdownWrapper !== wrapper) {
                dropdown.style.display = 'none';
            }
        });
    });

    /**
     * Handle keyboard navigation
     */
    document.addEventListener('keydown', function(e) {
        // Close on Escape
        if (e.key === 'Escape') {
            document.querySelectorAll('.icon-picker__dropdown').forEach(dropdown => {
                dropdown.style.display = 'none';
            });
        }
    });

})();
