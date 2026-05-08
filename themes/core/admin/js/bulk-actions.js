/**
 * BulkActions — Reusable bulk operations component for MonkeysCMS admin.
 *
 * Supports both table and grid (media card) layouts.
 * Uses CMS.fetch for AJAX with CSRF token auto-injection.
 *
 * === Table Mode ===
 *   <table data-bulk-actions data-bulk-url="/admin/content/bulk">
 *     <thead>
 *       <tr>
 *         <th><input type="checkbox" data-bulk-select-all></th>
 *         ...
 *       </tr>
 *     </thead>
 *     <tbody>
 *       <tr>
 *         <td><input type="checkbox" data-bulk-item value="123"></td>
 *         ...
 *       </tr>
 *     </tbody>
 *   </table>
 *
 * === Grid Mode (Media) ===
 *   <div data-bulk-actions data-bulk-url="/admin/media/bulk" data-bulk-grid>
 *     <div class="media-card">
 *       <input type="checkbox" data-bulk-item value="123" class="media-card__check">
 *       ...
 *     </div>
 *   </div>
 *
 * === Toolbar ===
 *   <div data-bulk-toolbar style="display:none">
 *     <span data-bulk-count></span> selected
 *     <button data-bulk-action="publish">Publish</button>
 *     <button data-bulk-action="delete" data-bulk-confirm="Delete {count} items?">Delete</button>
 *     <button data-bulk-action="change_role" data-bulk-extra="role_id">Change Role</button>
 *   </div>
 */
(function () {
  'use strict';

  // ── Toast System ──────────────────────────────────────────────────────
  const Toast = {
    _container: null,

    getContainer() {
      if (!this._container) {
        this._container = document.createElement('div');
        this._container.className = 'bulk-toast-container';
        document.body.appendChild(this._container);
      }
      return this._container;
    },

    show(message, type = 'success', duration = 4000) {
      const toast = document.createElement('div');
      toast.className = `bulk-toast bulk-toast--${type}`;

      const icons = {
        success: 'check-circle',
        error: 'alert-circle',
        info: 'info',
      };

      toast.innerHTML = `
        <i data-lucide="${icons[type] || 'info'}" class="w-4 h-4"></i>
        <span>${message}</span>
        <button class="bulk-toast__close" aria-label="Close">&times;</button>
      `;

      toast.querySelector('.bulk-toast__close').addEventListener('click', () => {
        this.dismiss(toast);
      });

      this.getContainer().appendChild(toast);

      // Re-render lucide icons
      if (window.lucide) lucide.createIcons();

      if (duration > 0) {
        setTimeout(() => this.dismiss(toast), duration);
      }
    },

    dismiss(toast) {
      toast.classList.add('bulk-toast--fadeout');
      setTimeout(() => toast.remove(), 300);
    },
  };

  // ── Confirmation Modal ────────────────────────────────────────────────
  function showConfirmModal(title, message, severity = 'danger') {
    return new Promise((resolve) => {
      const overlay = document.createElement('div');
      overlay.className = 'bulk-confirm-overlay';

      overlay.innerHTML = `
        <div class="bulk-confirm-modal">
          <div class="bulk-confirm-modal__header">
            <div class="bulk-confirm-modal__icon bulk-confirm-modal__icon--${severity}">
              <i data-lucide="${severity === 'danger' ? 'alert-triangle' : 'info'}" class="w-5 h-5"></i>
            </div>
            <h3 class="bulk-confirm-modal__title">${title}</h3>
          </div>
          <div class="bulk-confirm-modal__body">
            <p class="bulk-confirm-modal__message">${message}</p>
          </div>
          <div class="bulk-confirm-modal__footer">
            <button class="btn btn--ghost" data-confirm-cancel>Cancel</button>
            <button class="btn ${severity === 'danger' ? 'btn--danger-solid' : 'btn--primary'}" data-confirm-ok>
              ${severity === 'danger' ? 'Delete' : 'Confirm'}
            </button>
          </div>
        </div>
      `;

      const cancelBtn = overlay.querySelector('[data-confirm-cancel]');
      const okBtn = overlay.querySelector('[data-confirm-ok]');

      const close = (result) => {
        overlay.remove();
        resolve(result);
      };

      cancelBtn.addEventListener('click', () => close(false));
      okBtn.addEventListener('click', () => close(true));
      overlay.addEventListener('click', (e) => {
        if (e.target === overlay) close(false);
      });
      document.addEventListener('keydown', function handler(e) {
        if (e.key === 'Escape') {
          document.removeEventListener('keydown', handler);
          close(false);
        }
      });

      document.body.appendChild(overlay);
      if (window.lucide) lucide.createIcons();

      // Focus confirm button
      okBtn.focus();
    });
  }

  // ── BulkActions Class ─────────────────────────────────────────────────
  class BulkActions {
    constructor(container) {
      this.container = container;
      this.url = container.dataset.bulkUrl || '';
      this.isGrid = container.hasAttribute('data-bulk-grid');
      this.selectAll = container.querySelector('[data-bulk-select-all]');
      this.items = () => container.querySelectorAll('[data-bulk-item]');
      this.toolbar = document.querySelector(`[data-bulk-toolbar="${container.id}"]`)
                  || document.querySelector('[data-bulk-toolbar]');
      this.countEl = this.toolbar?.querySelector('[data-bulk-count]');

      this.init();
    }

    init() {
      // Select All checkbox
      if (this.selectAll) {
        this.selectAll.addEventListener('change', () => {
          const checked = this.selectAll.checked;
          this.items().forEach(cb => { cb.checked = checked; });
          this.updateToolbar();
        });
      }

      // Individual checkboxes — use event delegation
      this.container.addEventListener('change', (e) => {
        if (e.target.matches('[data-bulk-item]')) {
          this.updateSelectAll();
          this.updateToolbar();
        }
      });

      // Bulk action buttons
      if (this.toolbar) {
        this.toolbar.querySelectorAll('[data-bulk-action]').forEach(btn => {
          btn.addEventListener('click', async (e) => {
            e.preventDefault();
            const action = btn.dataset.bulkAction;
            const confirmMsg = btn.dataset.bulkConfirm;
            const extraField = btn.dataset.bulkExtra;
            const severity = btn.dataset.bulkSeverity || 'danger';

            await this.execute(action, confirmMsg, severity, extraField);
          });
        });
      }

      // Row click to toggle (table mode only)
      if (!this.isGrid) {
        this.container.querySelectorAll('tbody tr').forEach(row => {
          row.addEventListener('click', (e) => {
            if (e.target.closest('a, button, input, select, .btn, form')) return;
            const cb = row.querySelector('[data-bulk-item]');
            if (cb) {
              cb.checked = !cb.checked;
              this.updateSelectAll();
              this.updateToolbar();
            }
          });
        });
      }
    }

    getSelectedIds() {
      return Array.from(this.items())
        .filter(cb => cb.checked)
        .map(cb => cb.value);
    }

    updateSelectAll() {
      if (!this.selectAll) return;
      const items = this.items();
      const checkedItems = Array.from(items).filter(cb => cb.checked);
      this.selectAll.checked = checkedItems.length === items.length && items.length > 0;
      this.selectAll.indeterminate = checkedItems.length > 0 && checkedItems.length < items.length;
    }

    updateToolbar() {
      const count = this.getSelectedIds().length;

      if (this.toolbar) {
        this.toolbar.style.display = count > 0 ? '' : 'none';
      }

      if (this.countEl) {
        this.countEl.textContent = count;
      }

      // Visual feedback
      this.items().forEach(cb => {
        if (this.isGrid) {
          // Grid mode — highlight cards
          const card = cb.closest('.media-card');
          if (card) {
            card.classList.toggle('media-card--selected', cb.checked);
          }
        } else {
          // Table mode — highlight rows
          const row = cb.closest('tr');
          if (row) {
            row.classList.toggle('row--selected', cb.checked);
          }
        }
      });
    }

    async execute(action, confirmMsg, severity = 'danger', extraField = null) {
      const ids = this.getSelectedIds();
      if (ids.length === 0) return;

      // Build confirm message with count substitution
      if (confirmMsg) {
        const message = confirmMsg
          .replace('{count}', ids.length)
          .replace('{s}', ids.length > 1 ? 's' : '');

        const title = severity === 'danger' ? 'Confirm Deletion' : 'Confirm Action';
        const confirmed = await showConfirmModal(title, message, severity);
        if (!confirmed) return;
      }

      // Collect extra data (e.g., role_id from a select)
      const extra = {};
      if (extraField) {
        const select = this.toolbar?.querySelector(`[name="${extraField}"]`);
        if (select) {
          if (!select.value) {
            Toast.show(`Please select a value for ${extraField}.`, 'error');
            return;
          }
          extra[extraField] = select.value;
        }
      }

      // Execute via AJAX using CMS.fetch
      try {
        this.setLoading(true);

        const payload = { action, ids: ids.map(Number), ...extra };

        const resp = await CMS.fetch(this.url, {
          method: 'POST',
          body: JSON.stringify(payload),
        });

        if (resp.redirected) {
          // Server returned a redirect (non-JSON response) — follow it
          window.location.href = resp.url;
          return;
        }

        // Try to parse JSON, fallback to reload
        let data;
        const contentType = resp.headers.get('content-type') || '';
        if (contentType.includes('application/json')) {
          data = await resp.json();
        } else {
          // Non-JSON response (HTML redirect) — just reload
          window.location.reload();
          return;
        }

        if (!resp.ok) {
          throw new Error(data.error || `Server returned ${resp.status}`);
        }

        // Success
        const count = data.affected || data.count || ids.length;
        Toast.show(
          data.message || `${count} item${count > 1 ? 's' : ''} ${action}ed successfully.`,
          'success',
        );

        // Remove affected items from the DOM (or reload)
        if (action === 'delete') {
          this.removeItems(ids);
        } else {
          // For status changes, reload to reflect new state
          setTimeout(() => window.location.reload(), 800);
        }

        // Reset selection
        this.items().forEach(cb => { cb.checked = false; });
        this.updateSelectAll();
        this.updateToolbar();

      } catch (err) {
        Toast.show(err.message || 'An error occurred.', 'error');
      } finally {
        this.setLoading(false);
      }
    }

    removeItems(ids) {
      ids.forEach(id => {
        const cb = this.container.querySelector(`[data-bulk-item][value="${id}"]`);
        if (!cb) return;

        const el = this.isGrid ? cb.closest('.media-card') : cb.closest('tr');
        if (el) {
          el.style.transition = 'opacity 0.3s, transform 0.3s';
          el.style.opacity = '0';
          el.style.transform = this.isGrid ? 'scale(0.9)' : 'translateX(-20px)';
          setTimeout(() => el.remove(), 300);
        }
      });

      // Update count if exists
      setTimeout(() => {
        const remaining = this.items().length;
        if (remaining === 0) {
          setTimeout(() => window.location.reload(), 300);
        }
      }, 350);
    }

    setLoading(loading) {
      if (this.toolbar) {
        this.toolbar.querySelectorAll('[data-bulk-action]').forEach(btn => {
          btn.disabled = loading;
          if (loading) btn.style.opacity = '0.5';
          else btn.style.opacity = '';
        });
      }
    }
  }

  // ── Auto-discover & initialize ────────────────────────────────────────
  function initBulkActions() {
    document.querySelectorAll('[data-bulk-actions]').forEach(container => {
      if (!container._bulkActions) {
        container._bulkActions = new BulkActions(container);
      }
    });
  }

  // Initialize on DOM ready
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initBulkActions);
  } else {
    initBulkActions();
  }

  // Expose globally
  window.BulkActions = BulkActions;
  window.BulkToast = Toast;
})();
