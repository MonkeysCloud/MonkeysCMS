/**
 * MonkeysCMS Address Widget JS
 */
(function() {
    'use strict';

    window.CmsAddress = {
        init: function(wrapperId) {
            const wrapper = document.getElementById(wrapperId);
            if (!wrapper) return;

            // Find the hidden input that stores the JSON value
            const fieldId = wrapper.dataset.fieldId;
            const hiddenInput = document.getElementById(fieldId);
            if (!hiddenInput) return;

            const inputs = wrapper.querySelectorAll('[data-field]');
            
            const updateValue = () => {
                const data = {};
                inputs.forEach(input => {
                    const field = input.dataset.field;
                    data[field] = input.value;
                });
                hiddenInput.value = JSON.stringify(data);
            };

            inputs.forEach(input => {
                input.addEventListener('input', updateValue);
                input.addEventListener('change', updateValue);
            });
        }
    };

    // Initialize all address fields in a context
    function initAll(context) {
        context = context || document;
        context.querySelectorAll('.field-address[data-field-id]').forEach(function(wrapper) {
            if (wrapper.dataset.initialized) return;
            wrapper.dataset.initialized = 'true';
            window.CmsAddress.init(wrapper.id);
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
        window.CmsBehaviors.register('address', {
            selector: '.field-address',
            attach: initAll
        });
    }
})();
