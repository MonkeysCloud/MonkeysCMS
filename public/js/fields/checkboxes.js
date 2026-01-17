/**
 * Checkboxes Field Widget
 */
(function() {
    'use strict';

    window.CmsCheckboxes = {
        init: function(elementId) {
            const wrapper = document.getElementById(elementId);
            if (!wrapper) return;
            if (wrapper.dataset.initialized) return;

            wrapper.dataset.initialized = 'true';

            const selectAllBtn = wrapper.querySelector('[data-action="select-all"]');
            const deselectAllBtn = wrapper.querySelector('[data-action="deselect-all"]');
            const checkboxes = wrapper.querySelectorAll('input[type="checkbox"]');

            if (selectAllBtn) {
                selectAllBtn.addEventListener('click', (e) => {
                    e.preventDefault();
                    checkboxes.forEach(cb => {
                        cb.checked = true;
                        cb.dispatchEvent(new Event('change')); // Trigger change for any listeners
                    });
                });
            }

            if (deselectAllBtn) {
                deselectAllBtn.addEventListener('click', (e) => {
                    e.preventDefault();
                    checkboxes.forEach(cb => {
                        cb.checked = false;
                        cb.dispatchEvent(new Event('change'));
                    });
                });
            }
        }
    };

    // Initialize all checkbox fields in a context
    function initAll(context) {
        context = context || document;
        context.querySelectorAll('.field-checkboxes[data-field-id]').forEach(function(wrapper) {
            var fieldId = wrapper.dataset.fieldId;
            if (fieldId) {
                window.CmsCheckboxes.init(fieldId);
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
        window.CmsBehaviors.register('checkboxes', {
            selector: '.field-checkboxes',
            attach: initAll
        });
    }
})();
