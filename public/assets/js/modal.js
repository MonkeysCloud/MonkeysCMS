/**
 * MonkeysCMS — Global Modal System (JS API)
 *
 * Usage:
 *   const modal = CMS.modal({
 *     title: 'Edit Item',
 *     size: 'md',               // sm | md | lg | xl | full
 *     body: '<div>...</div>',   // HTML string or DOM element
 *     footer: [                 // optional button definitions
 *       { label: 'Cancel', class: 'btn btn--ghost', action: 'close' },
 *       { label: 'Save',   class: 'btn btn--primary', action: (modal) => { ... } },
 *     ],
 *     onClose: () => {},        // callback on close
 *     closeOnOverlay: true,     // close when clicking overlay
 *     closeOnEscape: true,      // close on Escape key
 *   });
 *
 *   modal.close();              // programmatic close
 *   modal.el                    // the .cms-modal element
 *   modal.overlay               // the .cms-modal-overlay element
 *
 * Confirm helper:
 *   CMS.confirm({
 *     title: 'Delete item?',
 *     message: 'This cannot be undone.',
 *     confirmLabel: 'Delete',
 *     confirmClass: 'btn btn--danger',
 *     onConfirm: () => { ... },
 *   });
 *
 * Prompt helper:
 *   CMS.prompt({
 *     title: 'Enter name',
 *     placeholder: 'My item',
 *     value: '',
 *     onSubmit: (value) => { ... },
 *   });
 */
(function() {
  'use strict';

  window.CMS = window.CMS || {};

  /**
   * Create and show a modal.
   */
  CMS.modal = function(options = {}) {
    const {
      title      = '',
      size       = 'md',
      body       = '',
      footer     = null,
      onClose    = null,
      closeOnOverlay = true,
      closeOnEscape  = true,
    } = options;

    // ── Build DOM ────────────────────────────────────────────────────────
    const overlay = document.createElement('div');
    overlay.className = 'cms-modal-overlay';

    const modal = document.createElement('div');
    modal.className = `cms-modal cms-modal--${size}`;

    // Header
    if (title) {
      const header = document.createElement('div');
      header.className = 'cms-modal__header';
      header.innerHTML = `
        <h3 class="cms-modal__title"></h3>
        <button type="button" class="cms-modal__close" aria-label="Close">&times;</button>
      `;
      header.querySelector('.cms-modal__title').textContent = title;
      header.querySelector('.cms-modal__close').addEventListener('click', close);
      modal.appendChild(header);
    }

    // Body
    const bodyEl = document.createElement('div');
    bodyEl.className = 'cms-modal__body';
    if (typeof body === 'string') {
      bodyEl.innerHTML = body;
    } else if (body instanceof HTMLElement) {
      bodyEl.appendChild(body);
    }
    modal.appendChild(bodyEl);

    // Footer
    if (footer && Array.isArray(footer)) {
      const footerEl = document.createElement('div');
      footerEl.className = 'cms-modal__footer';

      footer.forEach(btn => {
        const button = document.createElement('button');
        button.type = 'button';
        button.className = btn.class || 'btn';
        button.textContent = btn.label || 'OK';
        if (btn.id) button.id = btn.id;

        button.addEventListener('click', () => {
          if (btn.action === 'close') {
            close();
          } else if (typeof btn.action === 'function') {
            btn.action(instance);
          }
        });

        footerEl.appendChild(button);
      });

      modal.appendChild(footerEl);
    }

    overlay.appendChild(modal);
    document.body.appendChild(overlay);
    document.body.classList.add('cms-modal-open');

    // ── Show with animation ──────────────────────────────────────────────
    requestAnimationFrame(() => {
      requestAnimationFrame(() => {
        overlay.classList.add('is-open');
      });
    });

    // ── Close Function ───────────────────────────────────────────────────
    function close() {
      overlay.classList.remove('is-open');
      document.body.classList.remove('cms-modal-open');

      overlay.addEventListener('transitionend', () => {
        overlay.remove();
        if (typeof onClose === 'function') onClose();
      }, { once: true });

      // Fallback: if no transition fires, remove after 300ms
      setTimeout(() => {
        if (overlay.parentNode) {
          overlay.remove();
          if (typeof onClose === 'function') onClose();
        }
      }, 300);
    }

    // ── Event Listeners ──────────────────────────────────────────────────
    if (closeOnOverlay) {
      overlay.addEventListener('click', (e) => {
        if (e.target === overlay) close();
      });
    }

    if (closeOnEscape) {
      const escHandler = (e) => {
        if (e.key === 'Escape') {
          close();
          document.removeEventListener('keydown', escHandler);
        }
      };
      document.addEventListener('keydown', escHandler);
    }

    // ── Public Instance ──────────────────────────────────────────────────
    const instance = {
      overlay,
      el: modal,
      body: bodyEl,
      close,
    };

    return instance;
  };

  /**
   * Confirm dialog — returns a promise that resolves to true/false.
   */
  CMS.confirm = function(options = {}) {
    const {
      title        = 'Are you sure?',
      message      = '',
      confirmLabel = 'Confirm',
      cancelLabel  = 'Cancel',
      confirmClass = 'btn btn--primary',
      cancelClass  = 'btn btn--ghost',
      size         = 'sm',
      onConfirm    = null,
      onCancel     = null,
    } = options;

    return new Promise(resolve => {
      const modal = CMS.modal({
        title,
        size,
        body: message ? `<p style="margin:0; line-height:1.6;">${message}</p>` : '',
        footer: [
          { label: cancelLabel, class: cancelClass, action: () => {
            modal.close();
            if (typeof onCancel === 'function') onCancel();
            resolve(false);
          }},
          { label: confirmLabel, class: confirmClass, action: () => {
            modal.close();
            if (typeof onConfirm === 'function') onConfirm();
            resolve(true);
          }},
        ],
        closeOnOverlay: true,
        closeOnEscape: true,
        onClose: () => resolve(false),
      });
    });
  };

  /**
   * Prompt dialog — returns a promise that resolves to the input value or null.
   */
  CMS.prompt = function(options = {}) {
    const {
      title       = 'Enter value',
      placeholder = '',
      value       = '',
      inputType   = 'text',
      size        = 'sm',
      submitLabel = 'OK',
      cancelLabel = 'Cancel',
    } = options;

    return new Promise(resolve => {
      const inputId = 'cms-prompt-input-' + Date.now();
      const modal = CMS.modal({
        title,
        size,
        body: `
          <div class="form-group">
            <input type="${inputType}" class="form-input" id="${inputId}"
                   placeholder="${placeholder}" value="${value}" autocomplete="off">
          </div>
        `,
        footer: [
          { label: cancelLabel, class: 'btn btn--ghost', action: () => {
            modal.close();
            resolve(null);
          }},
          { label: submitLabel, class: 'btn btn--primary', action: () => {
            const val = modal.body.querySelector('#' + inputId).value;
            modal.close();
            resolve(val);
          }},
        ],
        onClose: () => resolve(null),
      });

      // Auto-focus and submit on Enter
      const input = modal.body.querySelector('#' + inputId);
      setTimeout(() => input?.focus(), 50);
      input?.addEventListener('keydown', e => {
        if (e.key === 'Enter') {
          modal.close();
          resolve(input.value);
        }
      });
    });
  };
  /**
   * Auto-bind [data-confirm] attribute on forms and buttons/links.
   *
   * Usage in HTML:
   *   <form data-confirm="Delete this item?" data-confirm-title="Delete" data-confirm-class="btn btn--danger">
   *   <button data-confirm="Are you sure?">Do it</button>
   *   <a href="/delete" data-confirm="Really?">Delete</a>
   *
   * The native confirm() is replaced with a styled CMS.confirm() modal.
   */
  function bindDataConfirm() {
    document.addEventListener('submit', function(e) {
      const form = e.target.closest('[data-confirm]');
      if (!form) return;

      // Skip if already confirmed
      if (form._cmsConfirmed) {
        form._cmsConfirmed = false;
        return;
      }

      e.preventDefault();

      CMS.confirm({
        title: form.dataset.confirmTitle || 'Are you sure?',
        message: form.dataset.confirm,
        confirmLabel: form.dataset.confirmLabel || 'Confirm',
        confirmClass: form.dataset.confirmClass || 'btn btn--danger',
        onConfirm: () => {
          form._cmsConfirmed = true;
          form.submit();
        },
      });
    }, true);

    document.addEventListener('click', function(e) {
      const el = e.target.closest('a[data-confirm], button[data-confirm]:not([type="submit"])');
      if (!el) return;

      // Skip if inside a form with data-confirm (handled by submit)
      if (el.closest('form[data-confirm]')) return;

      e.preventDefault();

      CMS.confirm({
        title: el.dataset.confirmTitle || 'Are you sure?',
        message: el.dataset.confirm,
        confirmLabel: el.dataset.confirmLabel || 'Confirm',
        confirmClass: el.dataset.confirmClass || 'btn btn--danger',
        onConfirm: () => {
          if (el.tagName === 'A' && el.href) {
            window.location.href = el.href;
          } else if (el.form) {
            el.form.submit();
          } else {
            el.click();
          }
        },
      });
    }, true);
  }

  // Init on DOM ready
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', bindDataConfirm);
  } else {
    bindDataConfirm();
  }
})();
