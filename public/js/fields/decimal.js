/**
 * MonkeysCMS Decimal Field Widget
 * Provides live decimal validation feedback
 */
window.CmsDecimal = {
    init: function(fieldId, options = {}) {
        const input = document.getElementById(fieldId);
        if (!input) return;

        const wrapper = input.closest('.field-decimal');
        if (!wrapper) return;

        const decimals = options.decimals || 2;
        const min = options.min;
        const max = options.max;

        // Create message element if not present
        let messageEl = wrapper.querySelector('.field-decimal__message');
        if (!messageEl) {
            messageEl = document.createElement('div');
            messageEl.className = 'field-decimal__message';
            wrapper.appendChild(messageEl);
        }

        // Format on blur
        input.addEventListener('blur', function() {
            CmsDecimal.formatInput(this, decimals);
            CmsDecimal.validate(this, messageEl, { decimals, min, max });
        });

        // Validate on input
        input.addEventListener('input', function() {
            CmsDecimal.validate(this, messageEl, { decimals, min, max });
        });

        // Initial validation if has value
        if (input.value) {
            CmsDecimal.validate(input, messageEl, { decimals, min, max });
        }
    },

    formatInput: function(input, decimals) {
        const value = parseFloat(input.value);
        if (!isNaN(value)) {
            input.value = value.toFixed(decimals);
        }
    },

    validate: function(input, messageEl, options = {}) {
        const value = input.value.trim();
        const { decimals = 2, min, max } = options;
        
        // Remove existing classes
        input.classList.remove('is-valid', 'is-invalid');
        messageEl.classList.remove('field-decimal__message--valid', 'field-decimal__message--invalid');
        messageEl.textContent = '';

        if (!value) {
            return; // Empty is neutral
        }

        // Check if valid number
        const numValue = parseFloat(value);
        if (isNaN(numValue)) {
            input.classList.add('is-invalid');
            messageEl.classList.add('field-decimal__message--invalid');
            messageEl.textContent = 'Please enter a valid number';
            return false;
        }

        // Check min
        if (min !== undefined && min !== null && numValue < min) {
            input.classList.add('is-invalid');
            messageEl.classList.add('field-decimal__message--invalid');
            messageEl.textContent = `Value must be at least ${min}`;
            return false;
        }

        // Check max
        if (max !== undefined && max !== null && numValue > max) {
            input.classList.add('is-invalid');
            messageEl.classList.add('field-decimal__message--invalid');
            messageEl.textContent = `Value must be at most ${max}`;
            return false;
        }

        // Valid
        input.classList.add('is-valid');
        messageEl.classList.add('field-decimal__message--valid');
        messageEl.textContent = 'Valid';
        return true;
    },

    isValid: function(value) {
        return !isNaN(parseFloat(value));
    }
};
