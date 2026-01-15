/**
 * MonkeysCMS Switch (Toggle) Widget
 * Handles toggle switch interactions
 */
(function() {
    'use strict';

    window.CmsSwitch = {
        init: function(fieldId, options) {
            options = options || {};
            const checkbox = document.getElementById(fieldId);
            if (!checkbox) return;

            const label = checkbox.closest('.field-switch');
            if (!label) return;

            // Apply size variant if specified
            if (options.size) {
                label.classList.add('field-switch--' + options.size);
            }

            // Apply color variant if specified
            if (options.color) {
                label.classList.add('field-switch--' + options.color);
            }

            // Keyboard accessibility
            checkbox.addEventListener('keydown', function(e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    this.checked = !this.checked;
                    this.dispatchEvent(new Event('change', { bubbles: true }));
                }
            });

            // Optional onChange callback
            if (options.onChange && typeof options.onChange === 'function') {
                checkbox.addEventListener('change', function() {
                    options.onChange(this.checked);
                });
            }

            // Trigger initial state callback if needed
            if (options.onInit && typeof options.onInit === 'function') {
                options.onInit(checkbox.checked);
            }
        },

        // Programmatically toggle
        toggle: function(fieldId) {
            const checkbox = document.getElementById(fieldId);
            if (checkbox) {
                checkbox.checked = !checkbox.checked;
                checkbox.dispatchEvent(new Event('change', { bubbles: true }));
            }
        },

        // Programmatically set value
        setValue: function(fieldId, value) {
            const checkbox = document.getElementById(fieldId);
            if (checkbox) {
                checkbox.checked = !!value;
                checkbox.dispatchEvent(new Event('change', { bubbles: true }));
            }
        },

        // Get current value
        getValue: function(fieldId) {
            const checkbox = document.getElementById(fieldId);
            return checkbox ? checkbox.checked : null;
        }
    };
})();
