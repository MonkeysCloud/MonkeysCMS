/**
 * Markdown Editor Functionality
 * Handles toolbar actions, mode switching, and preview rendering
 */

const CmsMarkdown = {
    /**
     * Initialize all markdown editors on the page
     */
    init() {
        document.querySelectorAll('.field-markdown').forEach(editor => {
            // Skip if already initialized
            if (editor.dataset.initialized === 'true') {
                return;
            }
            this.initializeEditor(editor);
            editor.dataset.initialized = 'true';
        });
    },

    /**
     * Initialize a single markdown editor
     */
    initializeEditor(editor) {
        const fieldId = editor.dataset.fieldId;
        const textarea = editor.querySelector('.field-markdown__textarea, .field-markdown__input');
        const preview = editor.querySelector('.field-markdown__preview-content');
        const toolbar = editor.querySelector('.field-markdown__toolbar');
        const modes = editor.querySelector('.field-markdown__modes');

        if (!textarea) return;

        // Set initial mode
        editor.dataset.mode = editor.dataset.mode || 'edit';

        // Bind toolbar buttons
        if (toolbar) {
            toolbar.querySelectorAll('button[data-action]').forEach(btn => {
                btn.addEventListener('click', (e) => {
                    e.preventDefault();
                    this.handleToolbarAction(btn.dataset.action, textarea);
                });
            });
        }

        // Bind mode buttons
        if (modes) {
            modes.querySelectorAll('button[data-mode]').forEach(btn => {
                btn.addEventListener('click', (e) => {
                    e.preventDefault();
                    this.setMode(editor, btn.dataset.mode);
                });
            });
        }

        // Update preview on input if preview exists
        if (preview) {
            textarea.addEventListener('input', () => {
                this.updatePreview(textarea.value, preview);
            });
            // Initial preview render
            this.updatePreview(textarea.value, preview);
        }
    },

    /**
     * Handle toolbar button actions
     */
    handleToolbarAction(action, textarea) {
        const start = textarea.selectionStart;
        const end = textarea.selectionEnd;
        const selectedText = textarea.value.substring(start, end);
        const before = textarea.value.substring(0, start);
        const after = textarea.value.substring(end);

        let replacement = '';
        let cursorOffset = 0;

        switch (action) {
            case 'bold':
                replacement = `**${selectedText || 'bold text'}**`;
                cursorOffset = selectedText ? replacement.length : 2;
                break;
            case 'italic':
                replacement = `*${selectedText || 'italic text'}*`;
                cursorOffset = selectedText ? replacement.length : 1;
                break;
            case 'heading':
                replacement = `## ${selectedText || 'Heading'}`;
                cursorOffset = replacement.length;
                break;
            case 'link':
                replacement = `[${selectedText || 'link text'}](url)`;
                cursorOffset = selectedText ? replacement.length - 4 : 1;
                break;
            case 'image':
                replacement = `![${selectedText || 'alt text'}](image-url)`;
                cursorOffset = selectedText ? replacement.length - 11 : 2;
                break;
            case 'code':
                if (selectedText.includes('\n')) {
                    replacement = `\`\`\`\n${selectedText || 'code'}\n\`\`\``;
                    cursorOffset = selectedText ? replacement.length - 4 : 4;
                } else {
                    replacement = `\`${selectedText || 'code'}\``;
                    cursorOffset = selectedText ? replacement.length : 1;
                }
                break;
            case 'quote':
                replacement = `> ${selectedText || 'quote'}`;
                cursorOffset = replacement.length;
                break;
            case 'ul':
                replacement = `- ${selectedText || 'list item'}`;
                cursorOffset = replacement.length;
                break;
            case 'ol':
                replacement = `1. ${selectedText || 'list item'}`;
                cursorOffset = replacement.length;
                break;
        }

        textarea.value = before + replacement + after;
        textarea.focus();
        textarea.setSelectionRange(start + cursorOffset, start + cursorOffset);

        // Trigger input event to update preview
        textarea.dispatchEvent(new Event('input'));
    },

    /**
     * Set editor mode (edit, preview, split)
     */
    setMode(editor, mode) {
        editor.dataset.mode = mode;

        // Update active button
        const modes = editor.querySelector('.field-markdown__modes');
        if (modes) {
            modes.querySelectorAll('button').forEach(btn => {
                btn.classList.toggle('active', btn.dataset.mode === mode);
            });
        }

        // Handle split mode container class
        const container = editor.querySelector('.field-markdown__container');
        if (container) {
            container.classList.toggle('field-markdown__container--split', mode === 'split');
        }
    },

    /**
     * Update markdown preview
     */
    updatePreview(markdown, previewElement) {
        if (!previewElement) return;

        try {
            // Use marked.js if available
            if (typeof marked !== 'undefined') {
                previewElement.innerHTML = marked.parse(markdown || '');
            } else {
                // Fallback: basic markdown rendering
                previewElement.innerHTML = this.basicMarkdownRender(markdown || '');
            }
        } catch (error) {
            console.error('Markdown preview error:', error);
            previewElement.textContent = markdown || '';
        }
    },

    /**
     * Basic markdown rendering fallback
     */
    basicMarkdownRender(text) {
        return text
            .replace(/^### (.*$)/gim, '<h3>$1</h3>')
            .replace(/^## (.*$)/gim, '<h2>$1</h2>')
            .replace(/^# (.*$)/gim, '<h1>$1</h1>')
            .replace(/\*\*(.*?)\*\*/gim, '<strong>$1</strong>')
            .replace(/\*(.*?)\*/gim, '<em>$1</em>')
            .replace(/\[([^\]]+)\]\(([^)]+)\)/gim, '<a href="$2">$1</a>')
            .replace(/\n/gim, '<br>');
    }
};

// Auto-initialize on DOM ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => CmsMarkdown.init());
} else {
    CmsMarkdown.init();
}

// Handle dynamically added repeater items
document.addEventListener('cms:content-changed', function(e) {
    if (e.detail && e.detail.target) {
        e.detail.target.querySelectorAll('.field-markdown').forEach(editor => {
            if (editor.dataset.initialized !== 'true') {
                CmsMarkdown.initializeEditor(editor);
                editor.dataset.initialized = 'true';
            }
        });
    }
});

// Register with global behaviors system (if available)
if (window.CmsBehaviors) {
    window.CmsBehaviors.register('markdown', {
        selector: '.field-markdown',
        attach: function(context) {
            context.querySelectorAll(this.selector).forEach(function(editor) {
                if (editor.dataset.initialized !== 'true') {
                    CmsMarkdown.initializeEditor(editor);
                    editor.dataset.initialized = 'true';
                }
            });
        }
    });
}
