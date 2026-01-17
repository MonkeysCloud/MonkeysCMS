/**
 * Code Field Widget
 */
(function() {
    'use strict';

    window.CmsCode = {
        init: function(elementId, mode, theme, lineNumbers) {
            const textarea = document.getElementById(elementId);
            if (!textarea) return;

            // Prevent double initialization
            if (textarea.nextElementSibling && textarea.nextElementSibling.classList.contains('CodeMirror')) {
                return;
            }

            if (typeof CodeMirror === 'undefined') {
                console.warn('CodeMirror is not loaded');
                return;
            }

            // Read config from data attributes if arguments are missing
            const wrapper = textarea.closest('.field-code');
            if (wrapper) {
                if (!mode) mode = wrapper.dataset.language;
                if (!theme) theme = wrapper.dataset.theme;
                if (lineNumbers === undefined) lineNumbers = wrapper.dataset.lineNumbers === 'true';
            }

            // Map languages to CodeMirror modes
            const modeMap = {
                'javascript': 'javascript',
                'typescript': 'javascript',
                'json': 'javascript',
                'css': 'css',
                'scss': 'css',
                'html': 'htmlmixed',
                'xml': 'xml',
                'php': 'php',
                'python': 'python',
                'java': 'clike',
                'csharp': 'clike',
                'cpp': 'clike',
                'c': 'clike'
            };

            const getMode = (lang) => {
                const m = modeMap[lang] || lang;
                if (m === 'php') return 'application/x-httpd-php';
                if (m === 'json') return 'application/json';
                if (m === 'typescript') return 'application/typescript';
                return m;
            };

            const editor = CodeMirror.fromTextArea(textarea, {
                mode: getMode(mode),
                theme: theme || 'dracula',
                lineNumbers: lineNumbers !== false,
                lineWrapping: true,
                viewportMargin: Infinity,
                indentUnit: 4
            });

            // Handle language selector
            const selector = document.getElementById(elementId + '_language');
            if (selector) {
                selector.addEventListener('change', function() {
                    editor.setOption('mode', getMode(this.value));
                    // Store new preference optionally?
                });
            }

            // Sync back to textarea on change for form submission
            editor.on('change', function() {
                textarea.value = editor.getValue();
            });
            
            // Refresh on visibility change (tabs)
            const wrapperEl = textarea.closest('.field-code');
            if (wrapperEl) {
                const observer = new IntersectionObserver((entries) => {
                    if (entries[0].isIntersecting) {
                        editor.refresh();
                    }
                });
                observer.observe(wrapperEl);
            }
        }
    };

    // Initialize all code fields in a context
    function initAll(context) {
        context = context || document;
        context.querySelectorAll('.field-code').forEach(function(wrapper) {
            var textarea = wrapper.querySelector('textarea');
            if (textarea && textarea.id) {
                window.CmsCode.init(textarea.id);
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
        window.CmsBehaviors.register('code', {
            selector: '.field-code',
            attach: initAll
        });
    }
})();
