/**
 * MonkeysCMS Email Field Widget
 * Provides live email validation feedback
 */
window.CmsEmail = {
    init: function(fieldId) {
        const input = document.getElementById(fieldId);
        if (!input) return;

        const wrapper = input.closest('.field-email');
        if (!wrapper) return;

        // Create validation icons if not present
        let inputWrapper = input.parentElement;
        if (!inputWrapper.classList.contains('field-email__input-wrapper')) {
            // Wrap input if needed
            const newWrapper = document.createElement('div');
            newWrapper.className = 'field-email__input-wrapper';
            input.parentNode.insertBefore(newWrapper, input);
            newWrapper.appendChild(input);
            inputWrapper = newWrapper;
        }

        // Add icons if not present
        if (!inputWrapper.querySelector('.field-email__icon--valid')) {
            const validIcon = document.createElement('span');
            validIcon.className = 'field-email__icon field-email__icon--valid';
            validIcon.innerHTML = '✓';
            inputWrapper.appendChild(validIcon);
        }

        if (!inputWrapper.querySelector('.field-email__icon--invalid')) {
            const invalidIcon = document.createElement('span');
            invalidIcon.className = 'field-email__icon field-email__icon--invalid';
            invalidIcon.innerHTML = '✗';
            inputWrapper.appendChild(invalidIcon);
        }

        // Create message element if not present
        let messageEl = wrapper.querySelector('.field-email__message');
        if (!messageEl) {
            messageEl = document.createElement('div');
            messageEl.className = 'field-email__message';
            wrapper.appendChild(messageEl);
        }

        // Validate on input
        input.addEventListener('input', function() {
            CmsEmail.validate(this, messageEl);
        });

        // Validate on blur
        input.addEventListener('blur', function() {
            CmsEmail.validate(this, messageEl);
        });

        // Initial validation if has value
        if (input.value) {
            CmsEmail.validate(input, messageEl);
        }
    },

    validate: function(input, messageEl) {
        const value = input.value.trim();
        
        // Remove existing classes
        input.classList.remove('is-valid', 'is-invalid');
        messageEl.classList.remove('field-email__message--valid', 'field-email__message--invalid');
        messageEl.textContent = '';

        if (!value) {
            return; // Empty is neutral (required validation handled elsewhere)
        }

        // Email regex pattern
        const emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        
        if (emailPattern.test(value)) {
            input.classList.add('is-valid');
            messageEl.classList.add('field-email__message--valid');
            messageEl.textContent = 'Valid email address';
        } else {
            input.classList.add('is-invalid');
            messageEl.classList.add('field-email__message--invalid');
            messageEl.textContent = 'Please enter a valid email address';
        }
    },

    isValid: function(email) {
        const emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return emailPattern.test(email.trim());
    }
};

// Initialize all email fields in a context
(function() {
    'use strict';

    function initAll(context) {
        context = context || document;
        context.querySelectorAll('.field-email[data-field-id]').forEach(function(wrapper) {
            if (wrapper.dataset.initialized) return;
            wrapper.dataset.initialized = 'true';
            var input = wrapper.querySelector('input[type="email"]');
            if (input && input.id) {
                window.CmsEmail.init(input.id);
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
        window.CmsBehaviors.register('email', {
            selector: '.field-email',
            attach: initAll
        });
    }
})();
