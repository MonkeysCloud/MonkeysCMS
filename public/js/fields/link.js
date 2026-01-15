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
})();
