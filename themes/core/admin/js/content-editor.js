/**
 * Content Editor — JS for the content create/edit form.
 *
 * Handles: slug generation, SEO counters, body editor (WYSIWYG/Markdown/Plain),
 * author autocomplete.
 *
 * Expects: #content-form[data-is-new="true"|"false"]
 */
document.addEventListener('DOMContentLoaded', () => {
  const contentForm = document.getElementById('content-form');
  if (!contentForm) return;

  const isNew = contentForm.dataset.isNew === 'true';

  // ── Slug Generation ─────────────────────────────────────────
  const titleInput = document.getElementById('edit-title');
  const slugInput = document.getElementById('edit-slug');
  const regenBtn = document.getElementById('regenerate-slug');
  let slugEdited = !isNew;

  function generateSlug(text) {
    return text.toLowerCase()
      .replace(/[^a-z0-9\s-]/g, '')
      .replace(/\s+/g, '-')
      .replace(/-+/g, '-')
      .replace(/^-|-$/g, '');
  }

  if (titleInput && slugInput) {
    slugInput.addEventListener('input', () => { slugEdited = true; });
    titleInput.addEventListener('input', () => {
      if (!slugEdited) {
        slugInput.value = generateSlug(titleInput.value);
      }
    });
  }

  if (regenBtn && titleInput && slugInput) {
    regenBtn.addEventListener('click', () => {
      slugInput.value = generateSlug(titleInput.value);
      slugEdited = false;
    });
  }

  // ── SEO Character Counters ──────────────────────────────────
  const metaTitleInput = document.querySelector('input[name="meta_title"]');
  const metaDescInput = document.querySelector('textarea[name="meta_description"]');
  const metaTitleCount = document.getElementById('meta-title-count');
  const metaDescCount = document.getElementById('meta-desc-count');

  if (metaTitleInput && metaTitleCount) {
    metaTitleCount.textContent = metaTitleInput.value.length;
    metaTitleInput.addEventListener('input', () => { metaTitleCount.textContent = metaTitleInput.value.length; });
  }
  if (metaDescInput && metaDescCount) {
    metaDescCount.textContent = metaDescInput.value.length;
    metaDescInput.addEventListener('input', () => { metaDescCount.textContent = metaDescInput.value.length; });
  }

  // ── Body Editor (WYSIWYG / Markdown / Plain) ────────────────
  const wysiwygEditor = document.getElementById('wysiwyg-editor');
  const bodyTextarea = document.getElementById('edit-body');
  const formatInput = document.getElementById('body-format-input');
  const wysiwygToolbar = document.getElementById('wysiwyg-toolbar');
  const markdownToolbar = document.getElementById('markdown-toolbar');
  const formatTabs = document.querySelectorAll('.body-format-tab');
  const btnSource = document.getElementById('btn-source');
  const btnLink = document.getElementById('btn-link');
  let isSourceView = false;

  // Set initial mode from saved format
  const savedFormat = formatInput?.value || 'wysiwyg';
  if (savedFormat !== 'wysiwyg') {
    switchFormat(savedFormat);
    formatTabs.forEach(t => {
      t.classList.toggle('is-active', t.dataset.format === savedFormat);
    });
  }

  // Format tab switching
  formatTabs.forEach(tab => {
    tab.addEventListener('click', () => {
      formatTabs.forEach(t => t.classList.remove('is-active'));
      tab.classList.add('is-active');
      switchFormat(tab.dataset.format);
    });
  });

  function switchFormat(format) {
    formatInput.value = format;
    isSourceView = false;

    if (format === 'wysiwyg') {
      wysiwygEditor.hidden = false;
      wysiwygToolbar.hidden = false;
      markdownToolbar.hidden = true;
      bodyTextarea.hidden = true;
      if (bodyTextarea.value && !wysiwygEditor.innerHTML.trim()) {
        wysiwygEditor.innerHTML = bodyTextarea.value;
      }
    } else {
      wysiwygEditor.hidden = true;
      wysiwygToolbar.hidden = true;
      markdownToolbar.hidden = format !== 'markdown';
      bodyTextarea.hidden = false;
      if (wysiwygEditor.innerHTML.trim()) {
        bodyTextarea.value = wysiwygEditor.innerHTML;
      }
    }
  }

  // WYSIWYG toolbar commands
  if (wysiwygToolbar) {
    wysiwygToolbar.querySelectorAll('[data-cmd]').forEach(btn => {
      btn.addEventListener('click', () => {
        const cmd = btn.dataset.cmd;
        const val = btn.dataset.value || null;
        document.execCommand(cmd, false, val);
        wysiwygEditor.focus();
      });
    });
  }

  // Link button
  if (btnLink) {
    btnLink.addEventListener('click', () => {
      const url = prompt('Enter URL:', 'https://');
      if (url) document.execCommand('createLink', false, url);
    });
  }

  // Source view toggle
  if (btnSource) {
    btnSource.addEventListener('click', () => {
      isSourceView = !isSourceView;
      if (isSourceView) {
        wysiwygEditor.hidden = true;
        bodyTextarea.hidden = false;
        bodyTextarea.value = wysiwygEditor.innerHTML;
      } else {
        wysiwygEditor.innerHTML = bodyTextarea.value;
        wysiwygEditor.hidden = false;
        bodyTextarea.hidden = true;
      }
      btnSource.classList.toggle('is-active', isSourceView);
    });
  }

  // Markdown toolbar
  if (markdownToolbar) {
    markdownToolbar.querySelectorAll('[data-md]').forEach(btn => {
      btn.addEventListener('click', () => {
        const action = btn.dataset.md;
        const ta = bodyTextarea;
        const start = ta.selectionStart;
        const end = ta.selectionEnd;
        const sel = ta.value.substring(start, end);
        let insert = '';

        switch (action) {
          case 'bold':    insert = `**${sel || 'text'}**`; break;
          case 'italic':  insert = `*${sel || 'text'}*`; break;
          case 'h2':      insert = `\n## ${sel || 'Heading'}\n`; break;
          case 'h3':      insert = `\n### ${sel || 'Heading'}\n`; break;
          case 'ul':      insert = `\n- ${sel || 'Item'}\n`; break;
          case 'ol':      insert = `\n1. ${sel || 'Item'}\n`; break;
          case 'link':    insert = `[${sel || 'text'}](url)`; break;
          case 'code':    insert = `\n\`\`\`\n${sel || 'code'}\n\`\`\`\n`; break;
        }

        ta.setRangeText(insert, start, end, 'end');
        ta.focus();
      });
    });
  }

  // Sync WYSIWYG to textarea before form submit
  if (contentForm) {
    contentForm.addEventListener('submit', () => {
      if (formatInput.value === 'wysiwyg' && !isSourceView) {
        bodyTextarea.value = wysiwygEditor.innerHTML;
      }
      bodyTextarea.hidden = false;
      bodyTextarea.name = 'body';
    });
  }

  // Update empty state
  if (wysiwygEditor) {
    wysiwygEditor.addEventListener('input', () => {
      wysiwygEditor.dataset.empty = wysiwygEditor.innerHTML.trim() === '' || wysiwygEditor.innerHTML === '<br>' ? 'true' : 'false';
    });
  }

  // ── Author Autocomplete ─────────────────────────────────────
  const authorSearch = document.getElementById('author-search');
  const authorId = document.getElementById('author-id');
  const authorDropdown = document.getElementById('author-dropdown');
  let authorTimer = null;

  if (authorSearch) {
    authorSearch.addEventListener('input', function () {
      clearTimeout(authorTimer);
      const q = this.value.trim();
      authorTimer = setTimeout(() => fetchAuthors(q), 250);
    });

    authorSearch.addEventListener('focus', function () {
      if (!authorDropdown.innerHTML) fetchAuthors('');
    });

    document.addEventListener('click', function (e) {
      if (!e.target.closest('#author-autocomplete')) {
        authorDropdown.classList.remove('is-open');
      }
    });
  }

  function fetchAuthors(q) {
    fetch('/api/cms/users/search?q=' + encodeURIComponent(q))
      .then(r => r.json())
      .then(data => {
        const users = data.data || [];
        if (users.length === 0) {
          authorDropdown.innerHTML = '<div class="author-autocomplete__empty">No users found</div>';
        } else {
          authorDropdown.innerHTML = users.map(u =>
            `<div class="author-autocomplete__item${u.id == authorId.value ? ' is-selected' : ''}" data-id="${u.id}" data-name="${u.name}">
              <div class="author-autocomplete__avatar">${u.name.charAt(0).toUpperCase()}</div>
              <div class="author-autocomplete__info">
                <div class="author-autocomplete__name">${u.name}</div>
                <div class="author-autocomplete__email">${u.email}</div>
              </div>
            </div>`
          ).join('');
        }
        authorDropdown.classList.add('is-open');

        authorDropdown.querySelectorAll('.author-autocomplete__item').forEach(item => {
          item.addEventListener('click', function () {
            authorId.value = this.dataset.id;
            authorSearch.value = this.dataset.name;
            authorDropdown.classList.remove('is-open');
          });
        });
      });
  }
});
