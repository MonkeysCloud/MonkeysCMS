/**
 * MonkeysCMS Editor Initialization
 * Supports Quill (HTML) and EasyMDE (Markdown)
 */
(function() {
    'use strict';

    // Toolbar configurations for Quill
    var quillToolbarPresets = {
        minimal: [['bold', 'italic'], [{ 'list': 'ordered'}, { 'list': 'bullet' }]],
        simple: [[{ 'header': [1, 2, 3, false] }], ['bold', 'italic', 'underline'], [{ 'list': 'ordered'}, { 'list': 'bullet' }], ['link']],
        full: [[{ 'header': [1, 2, 3, 4, 5, 6, false] }], ['bold', 'italic', 'underline', 'strike'], [{ 'color': [] }, { 'background': [] }], [{ 'align': [] }], [{ 'list': 'ordered'}, { 'list': 'bullet' }], [{ 'indent': '-1'}, { 'indent': '+1' }], ['link', 'image', 'video'], ['blockquote', 'code-block'], ['clean']],
        default: [[{ 'header': [1, 2, 3, false] }], ['bold', 'italic', 'underline', 'strike'], [{ 'color': [] }, { 'background': [] }], [{ 'list': 'ordered'}, { 'list': 'bullet' }], [{ 'align': [] }], ['link', 'image'], ['blockquote', 'code-block'], ['clean']]
    };

    // Track initialized editors
    var instances = {};

    function initQuill(textarea) {
        if (!textarea || !textarea.id || instances[textarea.id]) return;

        var theme = textarea.dataset.quillTheme || 'snow';
        var toolbarKey = textarea.dataset.quillToolbar || 'default';
        var toolbar = quillToolbarPresets[toolbarKey] || quillToolbarPresets.default;

        var wrapper = document.createElement('div');
        wrapper.id = textarea.id + '-quill';
        wrapper.className = 'quill-editor-wrapper';
        wrapper.innerHTML = textarea.value || '';
        
        textarea.parentNode.insertBefore(wrapper, textarea.nextSibling);
        textarea.style.display = 'none';

        var quill = new Quill(wrapper, {
            theme: theme,
            modules: { toolbar: toolbar },
            placeholder: textarea.placeholder || 'Start writing...'
        });

        // specific styling fixes
        wrapper.style.height = 'auto';
        wrapper.style.minHeight = '200px';
        wrapper.style.marginBottom = '1.5rem';
        var editorElement = wrapper.querySelector('.ql-editor');
        if (editorElement) editorElement.style.minHeight = '200px';

        quill.on('text-change', function() {
            textarea.value = quill.root.innerHTML;
        });

        instances[textarea.id] = { type: 'quill', instance: quill, wrapper: wrapper };
    }

    function removeQuill(textarea) {
        var data = instances[textarea.id];
        if (!data || data.type !== 'quill') return;

        textarea.value = data.instance.root.innerHTML;
        
        // Remove toolbar (Quill places it before the wrapper)
        var toolbar = data.wrapper.previousSibling;
        if (toolbar && toolbar.classList && toolbar.classList.contains('ql-toolbar')) {
            toolbar.parentNode.removeChild(toolbar);
        }

        if (data.wrapper && data.wrapper.parentNode) {
            data.wrapper.parentNode.removeChild(data.wrapper);
        }
        textarea.style.display = '';
        delete instances[textarea.id];
    }

    function initMarkdown(textarea) {
        if (!textarea || !textarea.id || instances[textarea.id]) return;

        if (typeof EasyMDE === 'undefined') {
            console.warn('EasyMDE not loaded');
            return;
        }

        var mde = new EasyMDE({
            element: textarea,
            forceSync: true,
            spellChecker: false,
            minHeight: '300px',
            placeholder: textarea.placeholder || 'Start writing...',
            status: ['lines', 'words', 'cursor'],
            toolbar: ['bold', 'italic', 'heading', '|', 'quote', 'unordered-list', 'ordered-list', '|', 'link', 'image', '|', 'preview', 'side-by-side', 'fullscreen', '|', 'guide']
        });

        instances[textarea.id] = { type: 'markdown', instance: mde };
    }

    function removeMarkdown(textarea) {
        var data = instances[textarea.id];
        if (!data || data.type !== 'markdown') return;

        // EasyMDE handles value sync automatically with forceSync, but let's be safe
        textarea.value = data.instance.value();
        data.instance.toTextArea();
        delete instances[textarea.id];
    }

    function removeEditor(textarea) {
        if (!instances[textarea.id]) return;
        if (instances[textarea.id].type === 'quill') removeQuill(textarea);
        else if (instances[textarea.id].type === 'markdown') removeMarkdown(textarea);
    }

    function setupFormatToggle(textarea) {
        var formatSelectId = textarea.dataset.quillFormatSelect;
        if (!formatSelectId) return;

        var formatSelect = document.getElementById(formatSelectId);
        if (!formatSelect) return;

        function toggle() {
            removeEditor(textarea);
            if (formatSelect.value === 'html') {
                initQuill(textarea);
            } else if (formatSelect.value === 'markdown') {
                initMarkdown(textarea);
            }
            // else: plain text
        }

        toggle();
        formatSelect.addEventListener('change', toggle);
    }

    function initAll(context) {
        context = context || document;
        var textareas = context.querySelectorAll('[data-quill]');
        textareas.forEach(function(textarea) {
            if (textarea.dataset.quillFormatSelect) {
                setupFormatToggle(textarea);
            } else {
                initQuill(textarea); // Default to Quill if no toggle
            }
        });
    }

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

    window.MonkeysEditor = {
        initQuill: initQuill,
        initMarkdown: initMarkdown,
        remove: removeEditor,
        instances: instances
    };
    // Alias for backward compatibility
    window.MonkeysQuill = window.MonkeysEditor;

    // Register with global behaviors system (if available)
    if (window.CmsBehaviors) {
        window.CmsBehaviors.register('quill', {
            selector: '[data-quill]',
            attach: function(context) {
                context.querySelectorAll(this.selector).forEach(function(textarea) {
                    if (textarea.dataset.quillFormatSelect) {
                        setupFormatToggle(textarea);
                    } else {
                        initQuill(textarea);
                    }
                });
            }
        });
    }

})();
