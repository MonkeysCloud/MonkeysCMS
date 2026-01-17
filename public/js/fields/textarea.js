/**
 * Textarea Field Widget
 */
(function() {
    'use strict';

    window.CmsTextarea = {
        init: function(elementId, options) {
            const textarea = document.getElementById(elementId);
            if (!textarea) return;

            const defaults = {
                autoResize: false,
                showCounter: false,
                maxLength: null
            };
            const settings = { ...defaults, ...options };

            // Auto Resize
            if (settings.autoResize) {
                const resize = () => {
                    textarea.style.height = 'auto'; // Reset
                    textarea.style.height = textarea.scrollHeight + 'px';
                };
                textarea.addEventListener('input', resize);
                // Initial resize
                setTimeout(resize, 0); 
            }

            // Character Counter
            if (settings.showCounter && settings.maxLength) {
                const wrapper = textarea.closest('.field-textarea') || textarea.parentElement;
                
                // Find or create counter element
                let counterEl = wrapper.querySelector('.field-textarea__counter');
                if (!counterEl) {
                    counterEl = document.createElement('div');
                    counterEl.className = 'field-textarea__counter';
                    wrapper.appendChild(counterEl);
                }

                const updateCounter = () => {
                    const length = textarea.value.length;
                    const max = settings.maxLength;
                    counterEl.textContent = `${length} / ${max}`;
                    
                    // Style updates
                    counterEl.classList.remove('field-textarea__counter--warning', 'field-textarea__counter--limit');
                    if (length >= max) {
                        counterEl.classList.add('field-textarea__counter--limit');
                    } else if (length >= max * 0.9) {
                        counterEl.classList.add('field-textarea__counter--warning');
                    }
                };

                textarea.addEventListener('input', updateCounter);
                updateCounter(); // Initial update
            }
        }
    };

    // Initialize all textarea fields in a context
    function initAll(context) {
        context = context || document;
        context.querySelectorAll('.field-textarea[data-field-id]').forEach(function(wrapper) {
            if (wrapper.dataset.initialized) return;
            wrapper.dataset.initialized = 'true';
            var textarea = wrapper.querySelector('textarea');
            if (textarea && textarea.id) {
                var options = {};
                if (wrapper.dataset.autoResize === 'true') options.autoResize = true;
                if (wrapper.dataset.showCounter === 'true') options.showCounter = true;
                if (wrapper.dataset.maxLength) options.maxLength = parseInt(wrapper.dataset.maxLength);
                window.CmsTextarea.init(textarea.id, options);
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
        window.CmsBehaviors.register('textarea', {
            selector: '.field-textarea',
            attach: initAll
        });
    }
})();
