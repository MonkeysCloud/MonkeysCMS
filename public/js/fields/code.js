/**
 * Code Field Widget
 */
(function() {
    'use strict';

    window.CmsCode = {
        init: function(elementId, mode, theme, lineNumbers) {
            const textarea = document.getElementById(elementId);
            if (!textarea) return;

            if (typeof CodeMirror === 'undefined') {
                console.warn('CodeMirror is not loaded');
                return;
            }

            CodeMirror.fromTextArea(textarea, {
                mode: mode || 'htmlmixed',
                theme: theme || 'dracula',
                lineNumbers: lineNumbers !== false,
                lineWrapping: true,
                viewportMargin: Infinity
            });
        }
    };
})();
