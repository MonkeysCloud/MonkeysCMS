/**
 * Link Field Widget
 */
(function() {
    'use strict';

    window.CmsLink = {
        init: function(fieldId) {
            const wrapper = document.querySelector(`[data-field-id="${fieldId}"]`);
            if (!wrapper) return;

            const hiddenInput = document.getElementById(fieldId);
            const urlInput = document.getElementById(fieldId + '_url');
            const titleInput = document.getElementById(fieldId + '_title');
            const targetSelect = document.getElementById(fieldId + '_target');

            function updateValue() {
                const data = {
                    url: urlInput ? urlInput.value : '',
                    title: titleInput ? titleInput.value : '',
                    target: targetSelect ? targetSelect.value : '_self'
                };
                if (hiddenInput) {
                    hiddenInput.value = JSON.stringify(data);
                }
            }

            if (urlInput) {
                urlInput.addEventListener('input', updateValue);
            }
            if (titleInput) {
                titleInput.addEventListener('input', updateValue);
            }
            if (targetSelect) {
                targetSelect.addEventListener('change', updateValue);
            }
        }
    };

    // Initialize all link fields in a context
    function initAll(context) {
        context = context || document;
        context.querySelectorAll('.field-link[data-field-id]').forEach(function(wrapper) {
            if (wrapper.dataset.initialized) return;
            wrapper.dataset.initialized = 'true';
            var fieldId = wrapper.dataset.fieldId;
            if (fieldId) {
                window.CmsLink.init(fieldId);
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
        window.CmsBehaviors.register('link', {
            selector: '.field-link',
            attach: initAll
        });
    }
})();
