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
})();
