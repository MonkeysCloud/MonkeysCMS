@extends('layouts.admin')

@section('title', 'Workflow Settings')
@section('page_title', 'Workflow Settings')

@section('breadcrumb')
<a href="/admin" class="breadcrumb__item">Dashboard</a>
<span class="breadcrumb__sep">›</span>
<a href="/admin/workflow" class="breadcrumb__item">Workflow</a>
<span class="breadcrumb__sep">›</span>
<span class="breadcrumb__item breadcrumb__item--active">Settings</span>
@endsection

@section('page_actions')
<a href="/admin/workflow" class="btn btn--ghost btn--sm">
  <i data-lucide="arrow-left" class="w-4 h-4"></i> Review Queue
</a>
@endsection

@section('content')

{{-- ── Status Flow Editor ────────────────────────────────────────── --}}
<div class="card mb-4">
  <div class="card__header">
    <h3 class="card__title"><i data-lucide="git-branch" class="w-4 h-4"></i> Workflow Statuses</h3>
    <button class="btn btn--primary btn--xs" onclick="openAddStatus()">
      <i data-lucide="plus" class="w-3 h-3"></i> Add Status
    </button>
  </div>
  <div class="card__body p-0">
    <div class="text-xs text-muted" style="padding:.5rem 1rem .25rem;">
      Drag to reorder · System statuses cannot be deleted · Toggle flags to set behavior
    </div>
    <div class="wfs-list" id="wfs-list">
      @foreach($statuses as $s)
      <div class="wfs-item" data-name="{{ $s['machine_name'] }}" draggable="true">
        <div class="wfs-item__grip"><i data-lucide="grip-vertical" class="w-4 h-4"></i></div>
        <div class="wfs-item__color" style="background:{{ $s['color'] }}"></div>
        <div class="wfs-item__icon"><i data-lucide="{{ $s['icon'] }}" class="w-4 h-4"></i></div>
        <div class="wfs-item__info">
          <span class="wfs-item__label">{{ $s['label'] }}</span>
          <span class="wfs-item__name">{{ $s['machine_name'] }}</span>
        </div>
        <div class="wfs-item__flags">
          @if(!empty($s['is_default']))
          <span class="wfs-flag wfs-flag--default" title="Default status for new content">Default</span>
          @endif
          @if(!empty($s['is_published']))
          <span class="wfs-flag wfs-flag--published" title="Content is publicly visible in this status">Published</span>
          @endif
          @if(!empty($s['is_review']))
          <span class="wfs-flag wfs-flag--review" title="This status appears in the review queue">Review</span>
          @endif
          @if(!empty($s['is_system']))
          <span class="wfs-flag wfs-flag--system" title="System status (cannot be deleted)">System</span>
          @endif
        </div>
        <div class="wfs-item__actions">
          <button class="btn btn--ghost btn--xs" onclick="editStatus('{{ $s['machine_name'] }}', {{ json_encode($s) }})">
            <i data-lucide="pencil" class="w-3 h-3"></i>
          </button>
          @if(empty($s['is_system']))
          <button class="btn btn--ghost btn--xs" style="color:#f87171"
                  onclick="deleteStatus('{{ $s['machine_name'] }}', '{{ $s['label'] }}')">
            <i data-lucide="trash-2" class="w-3 h-3"></i>
          </button>
          @endif
        </div>
      </div>
      @endforeach
    </div>
  </div>
</div>

{{-- ── Visual Flow Diagram ───────────────────────────────────────── --}}
<div class="card mb-4">
  <div class="card__header">
    <h3 class="card__title"><i data-lucide="workflow" class="w-4 h-4"></i> Status Flow</h3>
  </div>
  <div class="card__body">
    <div class="wfs-flow" id="wfs-flow">
      @foreach($statuses as $i => $s)
      <div class="wfs-flow__node" style="--node-color:{{ $s['color'] }}">
        <i data-lucide="{{ $s['icon'] }}" class="w-4 h-4"></i>
        <span>{{ $s['label'] }}</span>
      </div>
      @if($i < count($statuses) - 1)
      <div class="wfs-flow__arrow"><i data-lucide="chevron-right" class="w-4 h-4"></i></div>
      @endif
      @endforeach
    </div>
  </div>
</div>

{{-- ── Per Content Type Config ───────────────────────────────────── --}}
<div class="card">
  <div class="card__header">
    <h3 class="card__title"><i data-lucide="settings-2" class="w-4 h-4"></i> Content Type Rules</h3>
  </div>
  <div class="card__body p-0">
    <table class="table">
      <thead>
        <tr>
          <th>Content Type</th>
          <th>Require Review</th>
          <th>Notify on Submit</th>
          <th>Reviewer Roles</th>
        </tr>
      </thead>
      <tbody>
        @foreach($types as $ct)
        @php
          $cfg = $configMap[$ct->type_id] ?? null;
        @endphp
        <tr data-ct="{{ $ct->type_id }}">
          <td>
            <div class="wfs-ct-label">
              <span>{{ $ct->icon ?? '📄' }}</span>
              <strong>{{ $ct->label }}</strong>
              <span class="text-xs text-muted">({{ $ct->type_id }})</span>
            </div>
          </td>
          <td>
            <label class="form-check">
              <input type="checkbox" class="form-check-input" data-field="require_review"
                     {{ (!empty($cfg) && !empty($cfg['require_review'])) ? 'checked' : '' }}>
            </label>
          </td>
          <td>
            <label class="form-check">
              <input type="checkbox" class="form-check-input" data-field="notify_on_submit"
                     {{ (empty($cfg) || !empty($cfg['notify_on_submit'])) ? 'checked' : '' }}>
            </label>
          </td>
          <td>
            <div class="wfs-roles">
              @foreach(['admin', 'editor', 'author'] as $role)
              <label class="form-check" style="gap:.25rem">
                <input type="checkbox" class="form-check-input" data-role="{{ $role }}"
                       {{ $cfg && in_array($role, json_decode($cfg['reviewer_roles'] ?? '["editor","admin"]', true) ?: []) ? 'checked' : (!$cfg && in_array($role, ['editor', 'admin']) ? 'checked' : '') }}>
                <span class="text-xs">{{ ucfirst($role) }}</span>
              </label>
              @endforeach
            </div>
          </td>
        </tr>
        @endforeach
      </tbody>
    </table>
    <div style="padding:.75rem 1rem;display:flex;justify-content:flex-end">
      <button type="button" class="btn btn--primary btn--sm" id="save-rules-btn" onclick="saveWorkflowRules()">
        <i data-lucide="save" class="w-4 h-4"></i> Save Rules
      </button>
    </div>
  </div>
</div>

{{-- ── Add/Edit Status Modal ─────────────────────────────────────── --}}
<div class="wf-modal" id="status-modal" hidden>
  <div class="wf-modal__backdrop" onclick="document.getElementById('status-modal').hidden=true"></div>
  <div class="wf-modal__content">
    <div class="wf-modal__header">
      <h3 id="status-modal-title">Add Status</h3>
      <button onclick="document.getElementById('status-modal').hidden=true" class="btn btn--ghost btn--xs">&times;</button>
    </div>
    <div class="wf-modal__body">
      <form id="status-form" onsubmit="submitStatusForm(event)">
        <input type="hidden" id="sf-mode" value="create">
        <input type="hidden" id="sf-original-name" value="">
        <div class="form-group">
          <label class="form-label">Label <span class="text-danger">*</span></label>
          <input type="text" class="form-input" id="sf-label" placeholder="e.g. Pending Legal Review" required>
        </div>
        <div class="form-group">
          <label class="form-label">Machine Name</label>
          <input type="text" class="form-input" id="sf-name" pattern="[a-z0-9_]*" placeholder="Auto-generated from label">
          <p class="form-help">Lowercase, underscores only. Leave blank to auto-generate. Cannot be changed after creation.</p>
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:.75rem">
          <div class="form-group">
            <label class="form-label">Color</label>
            <input type="color" class="form-input" id="sf-color" value="#94a3b8" style="height:38px;padding:2px">
          </div>
          <div class="form-group">
            <label class="form-label">Icon (Lucide)</label>
            <input type="text" class="form-input" id="sf-icon" value="circle" placeholder="circle">
          </div>
        </div>
        <div style="display:flex;gap:1rem;margin-bottom:.5rem">
          <label class="form-check">
            <input type="checkbox" class="form-check-input" id="sf-is-published">
            <span>Published (publicly visible)</span>
          </label>
          <label class="form-check">
            <input type="checkbox" class="form-check-input" id="sf-is-review">
            <span>Review (appears in queue)</span>
          </label>
        </div>
        <button type="submit" class="btn btn--primary btn--sm" style="width:100%">
          <i data-lucide="check" class="w-4 h-4"></i> <span id="sf-submit-text">Create Status</span>
        </button>
      </form>
    </div>
  </div>
</div>

@endsection

@push('scripts')
<script>
// ── Workflow Config Save ────────────────────────────────────────

async function saveWorkflowRules() {
  const btn = document.getElementById('save-rules-btn');
  btn.disabled = true;
  btn.innerHTML = '<i data-lucide="loader" class="w-4 h-4 spin"></i> Saving…';

  const workflow = {};
  document.querySelectorAll('tr[data-ct]').forEach(row => {
    const ct = row.dataset.ct;
    workflow[ct] = {
      require_review: row.querySelector('[data-field="require_review"]').checked,
      notify_on_submit: row.querySelector('[data-field="notify_on_submit"]').checked,
      reviewer_roles: [...row.querySelectorAll('[data-role]:checked')].map(el => el.dataset.role),
    };
  });

  try {
    const resp = await CMS.fetch('/admin/workflow/settings', {
      method: 'POST',
      body: JSON.stringify({ workflow }),
    });
    const data = await resp.json();
    if (data.success) {
      CMS.toast?.('Settings saved', 'success') || location.reload();
    } else {
      alert(data.error || 'Failed to save');
    }
  } catch (err) {
    alert('Error: ' + (err.message || 'Network error'));
  } finally {
    btn.disabled = false;
    btn.innerHTML = '<i data-lucide="save" class="w-4 h-4"></i> Save Rules';
    if (window.lucide) lucide.createIcons({ nodes: [btn] });
  }
}
// ── Status CRUD ──────────────────────────────────────────────────

function openAddStatus() {
  document.getElementById('status-modal-title').textContent = 'Add Status';
  document.getElementById('sf-mode').value = 'create';
  document.getElementById('sf-original-name').value = '';
  document.getElementById('sf-name').value = '';
  document.getElementById('sf-name').disabled = false;
  document.getElementById('sf-label').value = '';
  document.getElementById('sf-color').value = '#94a3b8';
  document.getElementById('sf-icon').value = 'circle';
  document.getElementById('sf-is-published').checked = false;
  document.getElementById('sf-is-review').checked = false;
  document.getElementById('sf-submit-text').textContent = 'Create Status';
  document.getElementById('status-modal').hidden = false;
}

function editStatus(name, data) {
  document.getElementById('status-modal-title').textContent = 'Edit Status';
  document.getElementById('sf-mode').value = 'edit';
  document.getElementById('sf-original-name').value = name;
  document.getElementById('sf-name').value = name;
  document.getElementById('sf-name').disabled = true;
  document.getElementById('sf-label').value = data.label || '';
  document.getElementById('sf-color').value = data.color || '#94a3b8';
  document.getElementById('sf-icon').value = data.icon || 'circle';
  document.getElementById('sf-is-published').checked = !!parseInt(data.is_published);
  document.getElementById('sf-is-review').checked = !!parseInt(data.is_review);
  document.getElementById('sf-submit-text').textContent = 'Update Status';
  document.getElementById('status-modal').hidden = false;
}

function slugify(str) {
  return str.toLowerCase().trim()
    .replace(/[^a-z0-9\s_-]/g, '')
    .replace(/[\s-]+/g, '_')
    .replace(/^_+|_+$/g, '');
}

async function submitStatusForm(e) {
  e.preventDefault();
  const mode = document.getElementById('sf-mode').value;
  const label = document.getElementById('sf-label').value.trim();
  let machineName = document.getElementById('sf-name').value.trim();

  // Auto-generate machine name from label if empty
  if (!machineName && mode === 'create') {
    machineName = slugify(label);
  }

  if (!machineName) {
    alert('Machine name is required.');
    return;
  }

  const payload = {
    machine_name: machineName,
    label: label,
    color: document.getElementById('sf-color').value,
    icon: document.getElementById('sf-icon').value,
    is_published: document.getElementById('sf-is-published').checked,
    is_review: document.getElementById('sf-is-review').checked,
  };

  let url = '/admin/workflow/statuses';
  if (mode === 'edit') {
    url = `/admin/workflow/statuses/${document.getElementById('sf-original-name').value}/update`;
  }

  try {
    const resp = await CMS.fetch(url, { method: 'POST', body: JSON.stringify(payload) });
    const data = await resp.json();
    if (data.success) {
      location.reload();
    } else {
      alert(data.error || 'Failed to save status. Make sure the workflow migration has been run.');
    }
  } catch (err) {
    alert('Error: ' + (err.message || 'Network error. Check that the workflow_statuses table exists.'));
  }
}

async function deleteStatus(name, label) {
  if (!confirm(`Delete status "${label}"? Any content using this status will need reassignment.`)) return;

  try {
    const resp = await CMS.fetch(`/admin/workflow/statuses/${name}/delete`, { method: 'POST', body: '{}' });
    const data = await resp.json();
    if (data.success) location.reload();
    else alert(data.error || 'Cannot delete system status');
  } catch (err) {
    alert('Error: ' + err.message);
  }
}

// ── Drag-to-Reorder ─────────────────────────────────────────────

(function() {
  const list = document.getElementById('wfs-list');
  let dragSrc = null;

  list.addEventListener('dragstart', e => {
    const item = e.target.closest('.wfs-item');
    if (!item) return;
    dragSrc = item;
    item.classList.add('wfs-item--dragging');
    e.dataTransfer.effectAllowed = 'move';
  });

  list.addEventListener('dragover', e => {
    e.preventDefault();
    const item = e.target.closest('.wfs-item');
    if (!item || item === dragSrc) return;
    const rect = item.getBoundingClientRect();
    const after = e.clientY > rect.top + rect.height / 2;
    if (after) {
      item.after(dragSrc);
    } else {
      item.before(dragSrc);
    }
  });

  list.addEventListener('dragend', async e => {
    const item = e.target.closest('.wfs-item');
    if (item) item.classList.remove('wfs-item--dragging');

    // Collect new order
    const order = [...list.querySelectorAll('.wfs-item')].map(el => el.dataset.name);

    try {
      await CMS.fetch('/admin/workflow/statuses/reorder', {
        method: 'POST',
        body: JSON.stringify({ order }),
      });
    } catch (err) {
      console.error('Reorder failed:', err);
    }
  });
})();
</script>
@endpush

@push('head')
<style>
/* ── Status List ───────────────────────────────────────────────────── */
.wfs-list {
  padding: .25rem 0;
}

.wfs-item {
  display: flex;
  align-items: center;
  gap: .6rem;
  padding: .5rem 1rem;
  border-bottom: 1px solid rgba(255,255,255,.03);
  transition: background .15s;
}

.wfs-item:hover { background: rgba(255,255,255,.02); }
.wfs-item--dragging { opacity: .4; }

.wfs-item__grip {
  cursor: grab;
  color: #475569;
  flex-shrink: 0;
}

.wfs-item__color {
  width: 12px;
  height: 12px;
  border-radius: 50%;
  flex-shrink: 0;
}

.wfs-item__icon {
  color: #94a3b8;
  flex-shrink: 0;
}

.wfs-item__info {
  flex: 1;
  display: flex;
  align-items: baseline;
  gap: .5rem;
}

.wfs-item__label {
  font-size: .85rem;
  font-weight: 600;
  color: #e2e8f0;
}

.wfs-item__name {
  font-size: .7rem;
  color: #64748b;
  font-family: 'JetBrains Mono', monospace;
}

.wfs-item__flags {
  display: flex;
  gap: .25rem;
}

.wfs-flag {
  font-size: .6rem;
  padding: .1rem .35rem;
  border-radius: 4px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: .03em;
}

.wfs-flag--default   { background: rgba(251,191,36,.1); color: #fbbf24; }
.wfs-flag--published { background: rgba(34,197,94,.1);  color: #22c55e; }
.wfs-flag--review    { background: rgba(59,130,246,.1);  color: #3b82f6; }
.wfs-flag--system    { background: rgba(148,163,184,.1); color: #94a3b8; }

.wfs-item__actions {
  display: flex;
  gap: .2rem;
}

/* ── Visual Flow ───────────────────────────────────────────────────── */
.wfs-flow {
  display: flex;
  align-items: center;
  gap: .5rem;
  overflow-x: auto;
  padding: .25rem 0;
}

.wfs-flow__node {
  display: flex;
  align-items: center;
  gap: .35rem;
  padding: .4rem .75rem;
  border-radius: 8px;
  border: 1px solid color-mix(in srgb, var(--node-color, #94a3b8) 30%, transparent);
  background: color-mix(in srgb, var(--node-color, #94a3b8) 8%, transparent);
  color: var(--node-color, #94a3b8);
  font-size: .78rem;
  font-weight: 600;
  white-space: nowrap;
  flex-shrink: 0;
}

.wfs-flow__arrow {
  color: #475569;
  flex-shrink: 0;
}

/* ── Content Type Config ───────────────────────────────────────────── */
.wfs-ct-label {
  display: flex;
  align-items: center;
  gap: .4rem;
}

.wfs-roles {
  display: flex;
  gap: .6rem;
}

/* ── Modal (reuse workflow modal styles) ───────────────────────────── */
.wf-modal {
  position: fixed;
  inset: 0;
  z-index: 1000;
  display: flex;
  align-items: center;
  justify-content: center;
}

.wf-modal__backdrop {
  position: absolute;
  inset: 0;
  background: rgba(0,0,0,.6);
}

.wf-modal__content {
  position: relative;
  width: 480px;
  max-height: 80vh;
  background: #1e2035;
  border: 1px solid rgba(255,255,255,.06);
  border-radius: 16px;
  overflow: hidden;
  display: flex;
  flex-direction: column;
}

.wf-modal__header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: .75rem 1rem;
  border-bottom: 1px solid rgba(255,255,255,.06);
}

.wf-modal__header h3 {
  font-size: .9rem;
  font-weight: 600;
  color: #e2e8f0;
  margin: 0;
}

.wf-modal__body {
  padding: 1rem;
  overflow-y: auto;
  flex: 1;
}
</style>
@endpush
