@extends('layouts.admin')

@section('title', $isNew ? 'Create Menu' : 'Edit Menu')
@section('page_title', $title)

@section('breadcrumb')
<a href="/admin" class="breadcrumb__item">Dashboard</a>
<span class="breadcrumb__sep">›</span>
<a href="/admin/menus" class="breadcrumb__item">Menus</a>
<span class="breadcrumb__sep">›</span>
<span class="breadcrumb__item breadcrumb__item--active">{{ $isNew ? 'Create' : 'Edit' }}</span>
@endsection

@section('content')
<div class="menu-form-page" id="menu-app">

  {{-- Menu Details Form (rendered by FormRenderer) --}}
  <div class="menu-form__details">
    {!! $formHtml !!}
  </div>

  {{-- Item Management (edit mode only) --}}
  @if(!$isNew)
  <div class="menu-items-section mt-6">

    {{-- Section header --}}
    <div class="menu-items__header">
      <div class="menu-items__header-info">
        <h2 class="menu-items__title">
          <i data-lucide="list-tree" class="w-5 h-5"></i>
          Menu Items
        </h2>
        <span class="badge badge--sm badge--muted" $m-text="state.items.length + ' items'"></span>
      </div>
      <div class="menu-items__header-actions">
        <button type="button" class="btn btn--primary btn--sm" $m-on:click="openAddModal()">
          <i data-lucide="plus" class="w-4 h-4"></i>
          <span>Add Item</span>
        </button>
      </div>
    </div>

    {{-- Items Table --}}
    <div class="card">
      <div class="card__body card__body--flush">

        {{-- Table with items --}}
        <div $m-show="state.items.length > 0">
          <table class="menu-items-table" id="menu-items-table">
            <thead>
              <tr>
                <th style="width: 36px"></th>
                <th>Title</th>
                <th>URL</th>
                <th style="width: 80px" class="text-center">Status</th>
                <th class="text-right" style="width: 100px">Actions</th>
              </tr>
            </thead>
            <tbody id="menu-items-tbody">
              <tr $m-for="(item, idx) in state.tree"
                  class="menu-item-row"
                  draggable="true"
                  $m-on:dragstart="onDragStart(idx, $event)"
                  $m-on:dragover.prevent="onDragOver(idx, $event)"
                  $m-on:drop.prevent="onDrop(idx, $event)"
                  $m-on:dragend="onDragEnd()">
                <td class="item-drag">
                  <i data-lucide="grip-vertical" class="w-3.5 h-3.5"></i>
                </td>
                <td>
                  <div class="item-name-wrap" $m-bind:style="'padding-left:' + ((item._depth || 0) * 28) + 'px'">
                    <span class="item-indent-icon" $m-show="item._depth > 0">
                      <i data-lucide="corner-down-right" class="w-3 h-3"></i>
                    </span>
                    <div class="item-name-content">
                      <button class="item-name-link" $m-on:click="openEditModal(item)" $m-text="item.title"></button>
                      <span class="item-badge item-badge--code" $m-show="item._source === 'code'">code</span>
                    </div>
                  </div>
                </td>
                <td>
                  <code class="item-url-code" $m-show="item.url" $m-text="item.url"></code>
                </td>
                <td class="text-center">
                  <span class="badge--status-active" $m-show="item.enabled">Active</span>
                  <span class="badge--status-disabled" $m-show="!item.enabled">Disabled</span>
                </td>
                <td>
                  <div class="item-actions">
                    <button class="btn btn--xs btn--ghost" $m-on:click="openEditModal(item)" title="Edit">
                      <i data-lucide="pencil" class="w-3.5 h-3.5"></i>
                    </button>
                    <button class="btn btn--xs btn--ghost btn--danger"
                            $m-show="item._source !== 'code'"
                            $m-on:click="deleteItem(item.id, idx)" title="Delete">
                      <i data-lucide="trash-2" class="w-3.5 h-3.5"></i>
                    </button>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        {{-- Empty state --}}
        <div $m-show="state.items.length === 0" class="empty-state py-10">
          <div class="empty-state__icon"><i data-lucide="list-tree" class="w-10 h-10"></i></div>
          <div class="empty-state__title">No menu items yet</div>
          <p class="text-muted text-sm mb-4">Start building your menu by adding items.</p>
          <button type="button" class="btn btn--primary btn--sm" $m-on:click="openAddModal()">
            <i data-lucide="plus" class="w-4 h-4"></i>
            <span>Add First Item</span>
          </button>
        </div>

      </div>
    </div>

    {{-- Save order bar --}}
    <div $m-show="state.orderDirty" class="save-order-bar mt-4">
      <span class="text-muted text-sm">Unsaved changes to item order</span>
      <button class="btn btn--primary btn--sm" $m-on:click="saveOrder()" $m-bind:disabled="state.saving">
        <i data-lucide="save" class="w-4 h-4"></i>
        <span $m-text="state.saving ? 'Saving...' : 'Save Order'"></span>
      </button>
    </div>

  </div>
  @endif

  {{-- Add / Edit Item Modal --}}
  @if(!$isNew)
  <div class="modal-overlay" id="item-modal" style="display:none" $m-show="state.showModal" $m-on:click.self="closeModal()">
    <div class="modal-dialog">
      <div class="modal-dialog__header">
        <div class="modal-dialog__header-icon">
          <i data-lucide="list-tree" class="w-5 h-5"></i>
        </div>
        <div>
          <h3 class="modal-dialog__title" $m-text="state.editingItem ? 'Edit Item' : 'Add Item'"></h3>
          <p class="modal-dialog__subtitle" $m-text="state.editingItem ? 'Update item details' : 'Add a new menu item'"></p>
        </div>
        <button type="button" class="modal-dialog__close" $m-on:click="closeModal()">
          <i data-lucide="x" class="w-4 h-4"></i>
        </button>
      </div>

      <div class="modal-dialog__body">
        <form $m-on:submit.prevent="saveItem()">
          <div class="form-group">
            <label class="form-label">Title <span class="required">*</span></label>
            <input type="text" class="form-input" $m-model="state.formItem.title" placeholder="Link text" required>
          </div>
          <div class="form-group">
            <label class="form-label">URL</label>
            <input type="text" class="form-input" $m-model="state.formItem.url" placeholder="/path or https://...">
            <span class="form-hint">Leave empty for non-link items (e.g. parent groups).</span>
          </div>
          <div class="form-row">
            <div class="form-group form-group--half">
              <label class="form-label">Icon</label>
              <input type="text" class="form-input form-input--sm" $m-model="state.formItem.icon" placeholder="lucide icon name">
            </div>
            <div class="form-group form-group--half">
              <label class="form-label">Target</label>
              <select class="form-input form-input--sm" $m-model="state.formItem.target">
                <option value="">Same window</option>
                <option value="_blank">New tab</option>
              </select>
            </div>
          </div>
          <div class="form-group">
            <label class="form-label">Parent</label>
            <select class="form-input form-input--sm" $m-model="state.formItem.parent_id">
              <option value="">— Top level —</option>
              <option $m-for="item in state.parentOptions" $m-bind:value="item.id" $m-text="item._prefix + item.title"></option>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label form-label--inline">
              <input type="checkbox" $m-model="state.formItem.enabled">
              <span>Enabled</span>
            </label>
          </div>
        </form>
      </div>

      <div class="modal-dialog__footer">
        <button type="button" class="btn btn--ghost" $m-on:click="closeModal()">Cancel</button>
        <button type="button" class="btn btn--primary" $m-on:click="saveItem()" $m-bind:disabled="state.saving">
          <i data-lucide="save" class="w-4 h-4"></i>
          <span $m-text="state.saving ? 'Saving...' : (state.editingItem ? 'Update Item' : 'Add Item')"></span>
        </button>
      </div>
    </div>
  </div>
  @endif

</div>

@push('head')
<link rel="stylesheet" href="/themes/core/admin/css/menus.css?v={{ time() }}">
@endpush

@push('scripts')
{{-- Inject PHP data before the verbatim ES module --}}
<script>
  window.__menuData = {
    menuId: '{{ $menu->id ?? '' }}',
    items: {!! json_encode($items ?? []) !!}
  };
</script>
@verbatim
<script type="module">
  import { createApp, reactive, setPrefix } from 'monkeysjs';

  // Use $m- prefix to avoid ML template compiler conflicts with :attr shorthands
  setPrefix('$m-');

  const menuId = window.__menuData.menuId;
  const rawItems = window.__menuData.items || [];

  function buildTree(items) {
    const lookup = {};
    items.forEach(i => { lookup[i.id] = { ...i, _depth: 0, _source: i.attributes?._source || 'db' }; });

    const tree = [];
    function addChildren(parentId, depth) {
      items
        .filter(i => (i.parent_id || null) == parentId)
        .sort((a, b) => a.weight - b.weight)
        .forEach(i => {
          const node = lookup[i.id];
          node._depth = depth;
          tree.push(node);
          addChildren(i.id, depth + 1);
        });
    }
    addChildren(null, 0);
    return tree;
  }

  /** Build parent select options with depth prefix (excluding item being edited) */
  function buildParentOptions(items, excludeId) {
    const tree = buildTree(items.filter(i => i.id !== excludeId));
    return tree.map(node => ({
      ...node,
      _prefix: '\u00A0\u00A0'.repeat(node._depth || 0) + (node._depth > 0 ? '└ ' : ''),
    }));
  }

  const app = createApp({
    state: reactive({
      items: rawItems,
      tree: buildTree(rawItems),
      saving: false,
      orderDirty: false,
      dragIdx: null,
      showModal: false,
      editingItem: null,
      parentOptions: [],
      formItem: {
        title: '',
        url: '',
        icon: '',
        target: '',
        parent_id: '',
        enabled: true,
      },
    }),

    openAddModal() {
      this.state.editingItem = null;
      this.state.formItem = { title: '', url: '', icon: '', target: '', parent_id: '', enabled: true };
      this.state.parentOptions = buildParentOptions(this.state.items, null);
      this.state.showModal = true;
      setTimeout(() => {
        const inp = document.querySelector('.modal-dialog input[type="text"]');
        if (inp) inp.focus();
      }, 100);
    },

    openEditModal(item) {
      this.state.editingItem = item;
      this.state.formItem = {
        title: item.title || '',
        url: item.url || '',
        icon: item.icon || '',
        target: item.target || '',
        parent_id: item.parent_id ? String(item.parent_id) : '',
        enabled: item.enabled !== false,
      };
      this.state.parentOptions = buildParentOptions(this.state.items, item.id);
      this.state.showModal = true;
    },

    closeModal() {
      this.state.showModal = false;
      this.state.editingItem = null;
    },

    async saveItem() {
      const { formItem, items, editingItem } = this.state;
      if (!formItem.title.trim()) return;

      this.state.saving = true;
      try {
        const payload = {
          title: formItem.title,
          url: formItem.url,
          icon: formItem.icon,
          target: formItem.target,
          parent_id: formItem.parent_id || null,
          enabled: formItem.enabled ? 1 : 0,
          weight: editingItem ? editingItem.weight : items.length,
        };

        if (editingItem) {
          payload.item_id = editingItem.id;
        }

        const res = await CMS.fetch(`/admin/menus/${menuId}/items`, {
          method: 'POST',
          body: JSON.stringify(payload),
        });
        const data = await res.json();

        if (data.success) {
          const saved = data.item;

          if (editingItem) {
            const idx = this.state.items.findIndex(i => i.id === editingItem.id);
            if (idx !== -1) {
              this.state.items[idx] = saved;
            }
          } else {
            this.state.items.push(saved);
          }

          this.state.tree = buildTree(this.state.items);
          this.closeModal();

          setTimeout(() => { if (window.lucide) lucide.createIcons(); }, 50);
        } else {
          alert(data.error || 'Failed to save item');
        }
      } catch (err) {
        alert('Failed to save item');
      } finally {
        this.state.saving = false;
      }
    },

    async deleteItem(itemId, idx) {
      if (!confirm('Delete this menu item?')) return;

      this.state.saving = true;
      try {
        const res = await CMS.fetch(`/admin/menus/${menuId}/items/${itemId}/delete`, { method: 'POST' });
        const data = await res.json();
        if (data.success) {
          const removeIds = new Set([itemId]);
          let changed = true;
          while (changed) {
            changed = false;
            this.state.items.forEach(i => {
              if (i.parent_id && removeIds.has(i.parent_id) && !removeIds.has(i.id)) {
                removeIds.add(i.id);
                changed = true;
              }
            });
          }
          this.state.items = this.state.items.filter(i => !removeIds.has(i.id));
          this.state.tree = buildTree(this.state.items);
        }
      } catch (err) {
        alert('Failed to delete item');
      } finally {
        this.state.saving = false;
      }
    },

    // ── Drag & Drop ─────────────────────────────────────────────────
    onDragStart(idx, e) {
      this.state.dragIdx = idx;
      e.dataTransfer.effectAllowed = 'move';
    },

    onDragOver(idx, e) {
      e.dataTransfer.dropEffect = 'move';
    },

    onDrop(targetIdx, e) {
      const fromIdx = this.state.dragIdx;
      if (fromIdx === null || fromIdx === targetIdx) return;

      const tree = [...this.state.tree];
      const [moved] = tree.splice(fromIdx, 1);
      tree.splice(targetIdx, 0, moved);
      this.state.tree = tree;
      this.state.orderDirty = true;
      this.state.dragIdx = null;
    },

    onDragEnd() {
      this.state.dragIdx = null;
    },

    async saveOrder() {
      this.state.saving = true;
      try {
        const order = this.state.tree.map((item, idx) => ({
          id: item.id,
          parent_id: item.parent_id || null,
          weight: idx,
        }));

        const res = await CMS.fetch(`/admin/menus/${menuId}/reorder`, {
          method: 'POST',
          body: JSON.stringify({ order }),
        });
        const data = await res.json();
        if (data.success) {
          this.state.orderDirty = false;
        }
      } catch (err) {
        alert('Failed to save order');
      } finally {
        this.state.saving = false;
      }
    },
  });

  app.mount('#menu-app');

  // ── Machine name auto-gen ──────────────────────────────────────────
  const labelInput = document.getElementById('label');
  const machineInput = document.getElementById('machine_name');
  if (labelInput && machineInput) {
    let userEdited = machineInput.value !== '';
    machineInput.addEventListener('input', () => { userEdited = true; });
    labelInput.addEventListener('input', () => {
      if (!userEdited) {
        machineInput.value = labelInput.value
          .toLowerCase().trim()
          .replace(/[^a-z0-9]+/g, '_')
          .replace(/^_|_$/g, '');
      }
    });
  }

  // Close modal on Escape
  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape' && app.data?.state?.showModal) {
      app.data.state.showModal = false;
    }
  });
</script>
@endverbatim
@endpush

@endsection
