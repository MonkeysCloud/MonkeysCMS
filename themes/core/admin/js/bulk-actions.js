/**
 * BulkActions — Reusable bulk operations component for MonkeysCMS admin tables.
 *
 * Usage:
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
 *   <!-- Bulk actions toolbar (auto-shown when items selected) -->
 *   <div data-bulk-toolbar style="display:none">
 *     <span data-bulk-count></span> selected
 *     <button data-bulk-action="publish">Publish</button>
 *     <button data-bulk-action="draft">Draft</button>
 *     <button data-bulk-action="delete">Delete</button>
 *   </div>
 *
 * The component auto-discovers tables with [data-bulk-actions] and wires up
 * select-all, individual checkboxes, count display, and form submission.
 */
(function () {
  'use strict';

  class BulkActions {
    constructor(table) {
      this.table = table;
      this.url = table.dataset.bulkUrl || '';
      this.selectAll = table.querySelector('[data-bulk-select-all]');
      this.items = () => table.querySelectorAll('[data-bulk-item]');
      this.toolbar = document.querySelector('[data-bulk-toolbar]');
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

      // Individual checkboxes
      this.table.addEventListener('change', (e) => {
        if (e.target.matches('[data-bulk-item]')) {
          this.updateSelectAll();
          this.updateToolbar();
        }
      });

      // Bulk action buttons
      if (this.toolbar) {
        this.toolbar.querySelectorAll('[data-bulk-action]').forEach(btn => {
          btn.addEventListener('click', (e) => {
            e.preventDefault();
            const action = btn.dataset.bulkAction;
            this.execute(action);
          });
        });
      }

      // Row click to toggle (optional: clicking the row toggles the checkbox)
      this.table.querySelectorAll('tbody tr').forEach(row => {
        row.addEventListener('click', (e) => {
          // Don't toggle if clicking a link, button, or the checkbox itself
          if (e.target.closest('a, button, input, .btn')) return;
          const cb = row.querySelector('[data-bulk-item]');
          if (cb) {
            cb.checked = !cb.checked;
            this.updateSelectAll();
            this.updateToolbar();
          }
        });
      });
    }

    getSelectedIds() {
      return Array.from(this.items())
        .filter(cb => cb.checked)
        .map(cb => cb.value);
    }

    updateSelectAll() {
      if (!this.selectAll) return;
      const items = this.items();
      const checked = Array.from(items).filter(cb => cb.checked);
      this.selectAll.checked = checked.length === items.length && items.length > 0;
      this.selectAll.indeterminate = checked.length > 0 && checked.length < items.length;
    }

    updateToolbar() {
      const count = this.getSelectedIds().length;

      if (this.toolbar) {
        this.toolbar.style.display = count > 0 ? '' : 'none';
      }

      if (this.countEl) {
        this.countEl.textContent = count;
      }

      // Add visual feedback to selected rows
      this.items().forEach(cb => {
        const row = cb.closest('tr');
        if (row) {
          row.classList.toggle('row--selected', cb.checked);
        }
      });
    }

    execute(action) {
      const ids = this.getSelectedIds();
      if (ids.length === 0) return;

      // Confirmation for destructive actions
      if (action === 'delete') {
        const confirmed = confirm(
          `Are you sure you want to delete ${ids.length} item${ids.length > 1 ? 's' : ''}? This action cannot be undone.`
        );
        if (!confirmed) return;
      }

      // Submit via hidden form (standard POST, no AJAX)
      const form = document.createElement('form');
      form.method = 'POST';
      form.action = this.url;
      form.style.display = 'none';

      // Action field
      const actionField = document.createElement('input');
      actionField.type = 'hidden';
      actionField.name = 'action';
      actionField.value = action;
      form.appendChild(actionField);

      // ID fields
      ids.forEach(id => {
        const idField = document.createElement('input');
        idField.type = 'hidden';
        idField.name = 'ids[]';
        idField.value = id;
        form.appendChild(idField);
      });

      // CSRF token
      const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
      if (csrf) {
        const csrfField = document.createElement('input');
        csrfField.type = 'hidden';
        csrfField.name = '_token';
        csrfField.value = csrf;
        form.appendChild(csrfField);
      }

      document.body.appendChild(form);
      form.submit();
    }
  }

  // ── Auto-discover & initialize ────────────────────────────────────
  function initBulkActions() {
    document.querySelectorAll('[data-bulk-actions]').forEach(table => {
      if (!table._bulkActions) {
        table._bulkActions = new BulkActions(table);
      }
    });
  }

  // Initialize on DOM ready
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initBulkActions);
  } else {
    initBulkActions();
  }

  // Expose globally for MonkeysJS integration
  window.BulkActions = BulkActions;
})();
