/**
 * CMS WYSIWYG — Global Rich-Text Editor
 *
 * Usage:
 *   Wrap any textarea with [data-wysiwyg] and the script will automatically
 *   inject a toolbar + contenteditable editor above it.
 *
 *   <div data-wysiwyg>
 *     <textarea name="body">initial content</textarea>
 *   </div>
 *
 *   Options via data attributes on the wrapper:
 *     data-wysiwyg-min-height="150"  — editor minimum height (px, default 140)
 *     data-wysiwyg-placeholder="..."  — placeholder text
 *
 *   To re-initialise after dynamic DOM updates:
 *     CMS.wysiwyg.init()
 */
(function () {
  'use strict';

  /* ── Toolbar HTML ──────────────────────────────────────────── */
  function buildToolbar() {
    return `
      <div class="wysiwyg-toolbar cms-wysiwyg__toolbar">
        <button type="button" class="wysiwyg-toolbar__btn" data-cmd="bold" title="Bold (Ctrl+B)">
          <i data-lucide="bold" class="w-4 h-4"></i>
        </button>
        <button type="button" class="wysiwyg-toolbar__btn" data-cmd="italic" title="Italic (Ctrl+I)">
          <i data-lucide="italic" class="w-4 h-4"></i>
        </button>
        <button type="button" class="wysiwyg-toolbar__btn" data-cmd="underline" title="Underline">
          <i data-lucide="underline" class="w-4 h-4"></i>
        </button>
        <button type="button" class="wysiwyg-toolbar__btn" data-cmd="strikethrough" title="Strikethrough">
          <i data-lucide="strikethrough" class="w-4 h-4"></i>
        </button>
        <span class="wysiwyg-toolbar__sep"></span>
        <button type="button" class="wysiwyg-toolbar__btn" data-cmd="formatBlock" data-value="H2" title="Heading 2">
          <i data-lucide="heading-2" class="w-4 h-4"></i>
        </button>
        <button type="button" class="wysiwyg-toolbar__btn" data-cmd="formatBlock" data-value="H3" title="Heading 3">
          <i data-lucide="heading-3" class="w-4 h-4"></i>
        </button>
        <button type="button" class="wysiwyg-toolbar__btn" data-cmd="formatBlock" data-value="P" title="Paragraph">
          <i data-lucide="pilcrow" class="w-4 h-4"></i>
        </button>
        <span class="wysiwyg-toolbar__sep"></span>
        <button type="button" class="wysiwyg-toolbar__btn" data-cmd="insertUnorderedList" title="Bullet List">
          <i data-lucide="list" class="w-4 h-4"></i>
        </button>
        <button type="button" class="wysiwyg-toolbar__btn" data-cmd="insertOrderedList" title="Numbered List">
          <i data-lucide="list-ordered" class="w-4 h-4"></i>
        </button>
        <button type="button" class="wysiwyg-toolbar__btn" data-cmd="formatBlock" data-value="BLOCKQUOTE" title="Blockquote">
          <i data-lucide="quote" class="w-4 h-4"></i>
        </button>
        <span class="wysiwyg-toolbar__sep"></span>
        <button type="button" class="wysiwyg-toolbar__btn cms-wysiwyg__link-btn" title="Insert Link">
          <i data-lucide="link" class="w-4 h-4"></i>
        </button>
        <button type="button" class="wysiwyg-toolbar__btn" data-cmd="removeFormat" title="Clear Formatting">
          <i data-lucide="eraser" class="w-4 h-4"></i>
        </button>
        <button type="button" class="wysiwyg-toolbar__btn cms-wysiwyg__source-btn" title="Toggle Source">
          <i data-lucide="code" class="w-4 h-4"></i>
        </button>
      </div>`;
  }

  /* ── Initialise a single wrapper ───────────────────────────── */
  function initWrapper(wrap) {
    if (wrap.dataset.cmsWysiwygReady) return;
    wrap.dataset.cmsWysiwygReady = '1';

    const textarea = wrap.querySelector('textarea');
    if (!textarea) return;

    const minHeight = wrap.dataset.wysiwygMinHeight || '140';
    const placeholder = wrap.dataset.wysiwygPlaceholder || 'Start writing…';

    // Add wrapper class for CSS
    wrap.classList.add('cms-wysiwyg');

    // Build editor DOM
    const toolbarHtml = buildToolbar();
    const editorDiv = document.createElement('div');
    editorDiv.className = 'wysiwyg-editor cms-wysiwyg__editor';
    editorDiv.contentEditable = 'true';
    editorDiv.style.minHeight = minHeight + 'px';
    editorDiv.dataset.placeholder = placeholder;
    editorDiv.innerHTML = textarea.value || '';

    // Mark empty state for placeholder
    if (!editorDiv.textContent.trim()) {
      editorDiv.dataset.empty = 'true';
    }

    // Insert toolbar + editor before the textarea
    const toolbarContainer = document.createElement('div');
    toolbarContainer.innerHTML = toolbarHtml;
    const toolbar = toolbarContainer.firstElementChild;

    wrap.insertBefore(toolbar, textarea);
    wrap.insertBefore(editorDiv, textarea);

    // Hide the textarea
    textarea.hidden = true;
    textarea.style.display = 'none';

    // ── Toolbar commands ────────────────────────────────────────
    toolbar.querySelectorAll('.wysiwyg-toolbar__btn[data-cmd]').forEach(btn => {
      btn.addEventListener('click', (e) => {
        e.preventDefault();
        e.stopPropagation();
        const cmd = btn.dataset.cmd;
        const val = btn.dataset.value || null;
        editorDiv.focus();
        document.execCommand(cmd, false, val);
        syncToTextarea();
      });
    });

    // ── Link button ─────────────────────────────────────────────
    const linkBtn = toolbar.querySelector('.cms-wysiwyg__link-btn');
    if (linkBtn) {
      linkBtn.addEventListener('click', (e) => {
        e.preventDefault();
        const url = prompt('Enter URL:');
        if (url) {
          editorDiv.focus();
          document.execCommand('createLink', false, url);
          syncToTextarea();
        }
      });
    }

    // ── Source toggle ────────────────────────────────────────────
    const sourceBtn = toolbar.querySelector('.cms-wysiwyg__source-btn');
    let sourceMode = false;
    if (sourceBtn) {
      sourceBtn.addEventListener('click', (e) => {
        e.preventDefault();
        sourceMode = !sourceMode;
        if (sourceMode) {
          // Switch to source
          textarea.hidden = false;
          textarea.style.display = '';
          textarea.value = editorDiv.innerHTML;
          editorDiv.hidden = true;
          sourceBtn.classList.add('is-active');
        } else {
          // Switch to visual
          editorDiv.innerHTML = textarea.value;
          editorDiv.hidden = false;
          textarea.hidden = true;
          textarea.style.display = 'none';
          sourceBtn.classList.remove('is-active');
        }
      });
    }

    // ── Sync functions ──────────────────────────────────────────
    function syncToTextarea() {
      textarea.value = editorDiv.innerHTML;
      editorDiv.dataset.empty = editorDiv.textContent.trim() ? 'false' : 'true';
    }

    editorDiv.addEventListener('input', syncToTextarea);
    editorDiv.addEventListener('blur', syncToTextarea);

    // ── Keyboard shortcuts ──────────────────────────────────────
    editorDiv.addEventListener('keydown', (e) => {
      if ((e.ctrlKey || e.metaKey) && e.key === 'b') {
        e.preventDefault();
        document.execCommand('bold');
        syncToTextarea();
      }
      if ((e.ctrlKey || e.metaKey) && e.key === 'i') {
        e.preventDefault();
        document.execCommand('italic');
        syncToTextarea();
      }
      if ((e.ctrlKey || e.metaKey) && e.key === 'u') {
        e.preventDefault();
        document.execCommand('underline');
        syncToTextarea();
      }
    });

    // ── Sync before form submit ─────────────────────────────────
    const form = wrap.closest('form');
    if (form && !form.dataset.cmsWysiwygSubmit) {
      form.dataset.cmsWysiwygSubmit = '1';
      form.addEventListener('submit', () => {
        form.querySelectorAll('[data-wysiwyg]').forEach(w => {
          const ed = w.querySelector('.cms-wysiwyg__editor');
          const ta = w.querySelector('textarea');
          if (ed && ta && !ed.hidden) {
            ta.value = ed.innerHTML;
          }
        });
      });
    }

    // Init Lucide icons for the toolbar
    if (window.lucide) {
      lucide.createIcons({ nodes: toolbar.querySelectorAll('[data-lucide]') });
    }
  }

  /* ── Public API ────────────────────────────────────────────── */
  function initAll() {
    document.querySelectorAll('[data-wysiwyg]').forEach(initWrapper);
  }

  // Expose globally
  window.CMS = window.CMS || {};
  window.CMS.wysiwyg = { init: initAll, initElement: initWrapper };

  // Auto-init on DOM ready
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initAll);
  } else {
    initAll();
  }
})();
