@extends('layouts.admin')

@section('title', $title ?? 'Webform Builder')

@section('content')
<div class="admin-page">

  {{-- ── Header ───────────────────────────────────────────────────────── --}}
  <div class="admin-page__header" style="display:flex;align-items:center;justify-content:space-between">
    <div>
      <h1 class="admin-page__title">
        <i data-lucide="file-input" class="w-6 h-6"></i>
        {{ $isNew ? 'Create Webform' : 'Edit: ' . $webform->label }}
      </h1>
    </div>
    <div style="display:flex;gap:.5rem">
      <a href="/admin/webforms" class="btn btn--ghost btn--sm">
        <i data-lucide="arrow-left" class="w-4 h-4"></i> Back
      </a>
      <button class="btn btn--primary btn--sm" id="save-btn" onclick="saveForm()">
        <i data-lucide="save" class="w-4 h-4"></i> Save
      </button>
    </div>
  </div>

  {{-- ── Builder Layout: 2 columns ────────────────────────────────────── --}}
  <div style="display:grid;grid-template-columns:1fr 320px;gap:1.5rem;align-items:start">

    {{-- Left: Form Config + Fields --}}
    <div style="display:flex;flex-direction:column;gap:1.5rem">

      {{-- Basic Info Card --}}
      <div class="card">
        <div class="card__header"><h3 class="card__title"><i data-lucide="info" class="w-4 h-4"></i> Basic Info</h3></div>
        <div class="card__body">
          <div class="form-group">
            <label class="form-label">Form Label *</label>
            <input type="text" class="form-input" id="wf-label"
                   value="{{ $webform->label }}" placeholder="Contact Us"
                   oninput="autoSlug()">
          </div>
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem">
            <div class="form-group">
              <label class="form-label">Machine Name</label>
              <input type="text" class="form-input" id="wf-slug"
                     value="{{ $webform->machine_name }}" placeholder="contact_us">
              <span class="form-hint">Auto-generated from label if empty</span>
            </div>
            <div class="form-group">
              <label class="form-label">Status</label>
              <select class="form-input" id="wf-status">
                <option value="open" {{ $webform->status === 'open' ? 'selected' : '' }}>Open</option>
                <option value="closed" {{ $webform->status === 'closed' ? 'selected' : '' }}>Closed</option>
                <option value="scheduled" {{ $webform->status === 'scheduled' ? 'selected' : '' }}>Scheduled</option>
              </select>
            </div>
          </div>
          <div class="form-group">
            <label class="form-label">Description</label>
            <textarea class="form-input" id="wf-description" rows="2" placeholder="Optional form description">{{ $webform->description }}</textarea>
          </div>
        </div>
      </div>

      {{-- Pages (Tabs) --}}
      <div class="card">
        <div class="card__header" style="display:flex;align-items:center;justify-content:space-between">
          <h3 class="card__title"><i data-lucide="layers" class="w-4 h-4"></i> Pages &amp; Fields</h3>
          <button class="btn btn--ghost btn--xs" onclick="addPage()">
            <i data-lucide="plus" class="w-3.5 h-3.5"></i> Add Page
          </button>
        </div>
        <div class="card__body p-0">
          {{-- Page tabs --}}
          <div id="page-tabs" style="display:flex;border-bottom:1px solid var(--border);overflow-x:auto"></div>

          {{-- Fields area per page --}}
          <div id="fields-container" style="min-height:200px;padding:1rem">
            <div id="fields-list" style="display:flex;flex-direction:column;gap:.5rem"></div>
            <button class="btn btn--ghost btn--sm" style="margin-top:.75rem;width:100%;border:2px dashed var(--border)" onclick="openFieldPalette()">
              <i data-lucide="plus" class="w-4 h-4"></i> Add Field
            </button>
          </div>
        </div>
      </div>

    </div>

    {{-- Right: Settings Panel --}}
    <div style="display:flex;flex-direction:column;gap:1.5rem">

      {{-- Submit Settings --}}
      <div class="card">
        <div class="card__header"><h3 class="card__title"><i data-lucide="send" class="w-4 h-4"></i> Submit</h3></div>
        <div class="card__body">
          <div class="form-group">
            <label class="form-label">Button Label</label>
            <input type="text" class="form-input" id="wf-submit-label"
                   value="{{ $webform->submit_label }}" placeholder="Submit">
          </div>
          <div class="form-group">
            <label class="form-label">Confirmation Message</label>
            <textarea class="form-input" id="wf-confirmation" rows="3"
                      placeholder="Thank you for your submission!">{{ $webform->confirmation }}</textarea>
          </div>
          <div class="form-group">
            <label class="form-label">Redirect URL <span class="text-xs text-muted">(optional)</span></label>
            <input type="text" class="form-input" id="wf-redirect" value="{{ $webform->redirect_url }}"
                   placeholder="/thank-you">
          </div>
        </div>
      </div>

      {{-- Notifications --}}
      <div class="card">
        <div class="card__header"><h3 class="card__title"><i data-lucide="bell" class="w-4 h-4"></i> Notifications</h3></div>
        <div class="card__body">
          <div class="form-group">
            <label class="form-label">Notify Emails</label>
            <input type="text" class="form-input" id="wf-notify-emails"
                   value="{{ $webform->notify_emails }}" placeholder="admin@example.com, editor@example.com">
            <span class="form-hint">Comma-separated. Leave empty to disable.</span>
          </div>
        </div>
      </div>

      {{-- Limits & Scheduling --}}
      <div class="card">
        <div class="card__header"><h3 class="card__title"><i data-lucide="clock" class="w-4 h-4"></i> Limits</h3></div>
        <div class="card__body">
          <div class="form-group">
            <label class="form-label">Max Submissions</label>
            <input type="number" class="form-input" id="wf-max-subs"
                   value="{{ $webform->max_submissions }}" placeholder="Unlimited" min="0">
          </div>
          <div class="form-group">
            <label class="form-label">Open At</label>
            <input type="datetime-local" class="form-input" id="wf-open-at"
                   value="{{ $webform->open_at?->format('Y-m-d\TH:i') }}">
          </div>
          <div class="form-group">
            <label class="form-label">Close At</label>
            <input type="datetime-local" class="form-input" id="wf-close-at"
                   value="{{ $webform->close_at?->format('Y-m-d\TH:i') }}">
          </div>
        </div>
      </div>

      {{-- Security --}}
      <div class="card">
        <div class="card__header"><h3 class="card__title"><i data-lucide="shield" class="w-4 h-4"></i> Security</h3></div>
        <div class="card__body">
          <label class="form-toggle">
            <input type="checkbox" id="wf-recaptcha" {{ $webform->recaptcha_enabled ? 'checked' : '' }}>
            <span class="form-toggle__label">Enable reCAPTCHA</span>
          </label>
        </div>
      </div>

    </div>
  </div>

</div>

{{-- ── Field Palette Modal ────────────────────────────────────────────── --}}
<div class="wf-modal" id="field-palette-modal" hidden>
  <div class="wf-modal__backdrop" onclick="this.parentElement.hidden=true"></div>
  <div class="wf-modal__content" style="max-width:500px">
    <div class="wf-modal__header">
      <h3 id="field-palette-title">Add Field</h3>
      <button class="wf-modal__close" onclick="this.closest('.wf-modal').hidden=true">&times;</button>
    </div>
    <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:.5rem;padding:1rem">
      <button class="btn btn--ghost btn--sm" onclick="addField('text')"><i data-lucide="type" class="w-4 h-4"></i> Text</button>
      <button class="btn btn--ghost btn--sm" onclick="addField('email')"><i data-lucide="mail" class="w-4 h-4"></i> Email</button>
      <button class="btn btn--ghost btn--sm" onclick="addField('textarea')"><i data-lucide="align-left" class="w-4 h-4"></i> Textarea</button>
      <button class="btn btn--ghost btn--sm" onclick="addField('number')"><i data-lucide="hash" class="w-4 h-4"></i> Number</button>
      <button class="btn btn--ghost btn--sm" onclick="addField('phone')"><i data-lucide="phone" class="w-4 h-4"></i> Phone</button>
      <button class="btn btn--ghost btn--sm" onclick="addField('url')"><i data-lucide="link" class="w-4 h-4"></i> URL</button>
      <button class="btn btn--ghost btn--sm" onclick="addField('select')"><i data-lucide="chevron-down" class="w-4 h-4"></i> Select</button>
      <button class="btn btn--ghost btn--sm" onclick="addField('radio')"><i data-lucide="circle-dot" class="w-4 h-4"></i> Radio</button>
      <button class="btn btn--ghost btn--sm" onclick="addField('checkbox')"><i data-lucide="check-square" class="w-4 h-4"></i> Checkbox</button>
      <button class="btn btn--ghost btn--sm" onclick="addField('date')"><i data-lucide="calendar" class="w-4 h-4"></i> Date</button>
      <button class="btn btn--ghost btn--sm" onclick="addField('file')"><i data-lucide="upload" class="w-4 h-4"></i> File</button>
      <button class="btn btn--ghost btn--sm" onclick="addField('hidden')"><i data-lucide="eye-off" class="w-4 h-4"></i> Hidden</button>
    </div>
  </div>
</div>

{{-- ── Field Editor Modal ─────────────────────────────────────────────── --}}
<div class="wf-modal" id="field-editor-modal" hidden>
  <div class="wf-modal__backdrop" onclick="this.parentElement.hidden=true"></div>
  <div class="wf-modal__content" style="max-width:550px">
    <div class="wf-modal__header">
      <h3 id="field-editor-title">Edit Field</h3>
      <button class="wf-modal__close" onclick="this.closest('.wf-modal').hidden=true">&times;</button>
    </div>
    <div style="padding:1rem;display:flex;flex-direction:column;gap:.75rem">
      <input type="hidden" id="fe-index">
      <div class="form-group">
        <label class="form-label">Label *</label>
        <input type="text" class="form-input" id="fe-label" oninput="feAutoSlug()">
      </div>
      <div class="form-group">
        <label class="form-label">Machine Name</label>
        <input type="text" class="form-input" id="fe-name">
      </div>
      <div class="form-group">
        <label class="form-label">Placeholder</label>
        <input type="text" class="form-input" id="fe-placeholder">
      </div>
      <div class="form-group">
        <label class="form-label">Help Text</label>
        <input type="text" class="form-input" id="fe-help">
      </div>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:.75rem">
        <div class="form-group">
          <label class="form-label">Min Length</label>
          <input type="number" class="form-input" id="fe-min" min="0">
        </div>
        <div class="form-group">
          <label class="form-label">Max Length</label>
          <input type="number" class="form-input" id="fe-max" min="0">
        </div>
      </div>
      <div class="form-group" id="fe-options-group" hidden>
        <label class="form-label">Options <span class="text-xs text-muted">(one per line: value|Label)</span></label>
        <textarea class="form-input" id="fe-options" rows="4" placeholder="opt1|Option One&#10;opt2|Option Two"></textarea>
      </div>
      <label class="form-toggle">
        <input type="checkbox" id="fe-required">
        <span class="form-toggle__label">Required</span>
      </label>
      <button class="btn btn--primary btn--sm" style="width:100%" onclick="saveFieldEditor()">
        <i data-lucide="check" class="w-4 h-4"></i> Save Field
      </button>
    </div>
  </div>
</div>

@endsection

@push('scripts')
<script>
// ═══════════════════════════════════════════════════════════════════════════
// Webform Builder State
// ═══════════════════════════════════════════════════════════════════════════

const WF_ID = {{ $webform->id ?? 'null' }};
const IS_NEW = {{ $isNew ? 'true' : 'false' }};

let formState = {
  fields: {!! json_encode($webform->fields) !!},
  pages:  {!! json_encode($webform->pages) !!},
  currentPage: 0,
};

// ── Init ────────────────────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', () => {
  renderPageTabs();
  renderFields();
});

// ═══════════════════════════════════════════════════════════════════════════
// Pages
// ═══════════════════════════════════════════════════════════════════════════

function renderPageTabs() {
  const container = document.getElementById('page-tabs');
  container.innerHTML = '';
  formState.pages.forEach((pg, i) => {
    const tab = document.createElement('button');
    tab.className = 'btn btn--ghost btn--xs' + (i === formState.currentPage ? ' btn--active' : '');
    tab.style.cssText = 'border-radius:0;border-bottom:2px solid ' + (i === formState.currentPage ? 'var(--primary)' : 'transparent');
    tab.textContent = pg.title || ('Page ' + (i + 1));
    tab.onclick = () => { formState.currentPage = i; renderPageTabs(); renderFields(); };

    // Right-click to rename
    tab.oncontextmenu = (e) => {
      e.preventDefault();
      const name = prompt('Page title:', pg.title);
      if (name !== null) { formState.pages[i].title = name; renderPageTabs(); }
    };
    container.appendChild(tab);
  });

  // Remove page button (if > 1)
  if (formState.pages.length > 1) {
    const rm = document.createElement('button');
    rm.className = 'btn btn--ghost btn--xs text-danger';
    rm.innerHTML = '<i data-lucide="x" class="w-3 h-3"></i>';
    rm.title = 'Remove current page';
    rm.onclick = removePage;
    container.appendChild(rm);
  }
  if (window.lucide) lucide.createIcons({ nodes: [container] });
}

function addPage() {
  formState.pages.push({ title: 'Page ' + (formState.pages.length + 1), weight: formState.pages.length });
  formState.currentPage = formState.pages.length - 1;
  renderPageTabs();
  renderFields();
}

function removePage() {
  if (formState.pages.length <= 1) return;
  if (!confirm('Remove this page and its fields?')) return;
  const pg = formState.currentPage;
  formState.fields = formState.fields.filter(f => (f.page ?? 0) !== pg);
  // Re-index pages for remaining fields
  formState.fields.forEach(f => { if ((f.page ?? 0) > pg) f.page--; });
  formState.pages.splice(pg, 1);
  formState.currentPage = Math.min(pg, formState.pages.length - 1);
  renderPageTabs();
  renderFields();
}

// ═══════════════════════════════════════════════════════════════════════════
// Fields
// ═══════════════════════════════════════════════════════════════════════════

function getPageFields() {
  return formState.fields
    .map((f, i) => ({ ...f, _idx: i }))
    .filter(f => (f.page ?? 0) === formState.currentPage)
    .sort((a, b) => (a.weight ?? 0) - (b.weight ?? 0));
}

const FIELD_ICONS = {
  text: 'type', email: 'mail', textarea: 'align-left', number: 'hash',
  phone: 'phone', url: 'link', select: 'chevron-down', radio: 'circle-dot',
  checkbox: 'check-square', date: 'calendar', file: 'upload', hidden: 'eye-off',
};

function renderFields() {
  const list = document.getElementById('fields-list');
  const fields = getPageFields();
  list.innerHTML = '';

  if (fields.length === 0) {
    list.innerHTML = '<p style="text-align:center;color:var(--text-muted);padding:2rem 0">No fields on this page yet. Click "Add Field" below.</p>';
    return;
  }

  fields.forEach((f, sortIdx) => {
    const card = document.createElement('div');
    card.className = 'wf-field-card';
    card.dataset.idx = f._idx;
    card.draggable = true;
    card.ondragstart = (e) => e.dataTransfer.setData('text/plain', f._idx);
    card.ondragover = (e) => { e.preventDefault(); card.style.borderTop = '2px solid var(--primary)'; };
    card.ondragleave = () => card.style.borderTop = '';
    card.ondrop = (e) => { e.preventDefault(); card.style.borderTop = ''; reorderField(+e.dataTransfer.getData('text/plain'), f._idx); };

    const icon = FIELD_ICONS[f.type] || 'type';
    card.innerHTML = `
      <div style="display:flex;align-items:center;gap:.75rem;flex:1;cursor:grab">
        <i data-lucide="grip-vertical" class="w-4 h-4" style="opacity:.3"></i>
        <i data-lucide="${icon}" class="w-4 h-4" style="color:var(--primary)"></i>
        <div>
          <strong style="font-size:.875rem">${esc(f.label || f.name)}</strong>
          <span class="text-xs text-muted" style="margin-left:.5rem">${f.type}${f.required ? ' · required' : ''}</span>
        </div>
      </div>
      <div style="display:flex;gap:.25rem">
        <button class="btn btn--ghost btn--xs" onclick="editField(${f._idx})" title="Edit"><i data-lucide="pencil" class="w-3.5 h-3.5"></i></button>
        <button class="btn btn--ghost btn--xs text-danger" onclick="removeField(${f._idx})" title="Remove"><i data-lucide="trash-2" class="w-3.5 h-3.5"></i></button>
      </div>
    `;
    list.appendChild(card);
  });

  if (window.lucide) lucide.createIcons({ nodes: [list] });
}

function reorderField(fromIdx, toIdx) {
  if (fromIdx === toIdx) return;
  const fromWeight = formState.fields[fromIdx]?.weight ?? 0;
  const toWeight = formState.fields[toIdx]?.weight ?? 0;
  formState.fields[fromIdx].weight = toWeight;
  formState.fields[toIdx].weight = fromWeight;
  renderFields();
}

function openFieldPalette() {
  document.getElementById('field-palette-modal').hidden = false;
  if (window.lucide) lucide.createIcons();
}

function addField(type) {
  document.getElementById('field-palette-modal').hidden = true;
  const maxWeight = formState.fields.reduce((m, f) => Math.max(m, f.weight ?? 0), -1);
  const name = type + '_' + (formState.fields.length + 1);
  formState.fields.push({
    name, type,
    label: capitalize(type) + ' Field',
    placeholder: '',
    help: '',
    required: false,
    weight: maxWeight + 10,
    page: formState.currentPage,
    rules: {},
    options: {},
    default_value: null,
    width: 'full',
  });
  renderFields();
  // Auto-open editor for the new field
  editField(formState.fields.length - 1);
}

function removeField(idx) {
  if (!confirm('Remove this field?')) return;
  formState.fields.splice(idx, 1);
  renderFields();
}

// ═══════════════════════════════════════════════════════════════════════════
// Field Editor
// ═══════════════════════════════════════════════════════════════════════════

function editField(idx) {
  const f = formState.fields[idx];
  if (!f) return;
  document.getElementById('fe-index').value = idx;
  document.getElementById('fe-label').value = f.label || '';
  document.getElementById('fe-name').value = f.name || '';
  document.getElementById('fe-placeholder').value = f.placeholder || '';
  document.getElementById('fe-help').value = f.help || '';
  document.getElementById('fe-min').value = f.rules?.min ?? '';
  document.getElementById('fe-max').value = f.rules?.max ?? '';
  document.getElementById('fe-required').checked = !!f.required;
  document.getElementById('field-editor-title').textContent = 'Edit: ' + (f.label || f.name);

  // Options (for select/radio/checkbox)
  const hasOptions = ['select', 'radio', 'checkbox'].includes(f.type);
  document.getElementById('fe-options-group').hidden = !hasOptions;
  if (hasOptions && f.options) {
    const lines = Object.entries(f.options).map(([k, v]) => k + '|' + v).join('\n');
    document.getElementById('fe-options').value = lines;
  } else {
    document.getElementById('fe-options').value = '';
  }

  document.getElementById('field-editor-modal').hidden = false;
}

function feAutoSlug() {
  const label = document.getElementById('fe-label').value;
  document.getElementById('fe-name').value = label.toLowerCase().trim().replace(/[^a-z0-9]+/g, '_').replace(/^_|_$/g, '');
}

function saveFieldEditor() {
  const idx = +document.getElementById('fe-index').value;
  const f = formState.fields[idx];
  if (!f) return;

  f.label = document.getElementById('fe-label').value.trim();
  f.name = document.getElementById('fe-name').value.trim() || f.name;
  f.placeholder = document.getElementById('fe-placeholder').value;
  f.help = document.getElementById('fe-help').value;
  f.required = document.getElementById('fe-required').checked;
  const min = document.getElementById('fe-min').value;
  const max = document.getElementById('fe-max').value;
  f.rules = {};
  if (min) f.rules.min = +min;
  if (max) f.rules.max = +max;

  // Parse options
  if (['select', 'radio', 'checkbox'].includes(f.type)) {
    const opts = {};
    document.getElementById('fe-options').value.split('\n').forEach(line => {
      line = line.trim();
      if (!line) return;
      const [key, ...rest] = line.split('|');
      opts[key.trim()] = rest.length ? rest.join('|').trim() : key.trim();
    });
    f.options = opts;
  }

  document.getElementById('field-editor-modal').hidden = true;
  renderFields();
}

// ═══════════════════════════════════════════════════════════════════════════
// Save Form
// ═══════════════════════════════════════════════════════════════════════════

async function saveForm() {
  const btn = document.getElementById('save-btn');
  btn.disabled = true;
  btn.innerHTML = '<i data-lucide="loader" class="w-4 h-4 spin"></i> Saving…';

  const payload = {
    label: document.getElementById('wf-label').value.trim(),
    machine_name: document.getElementById('wf-slug').value.trim(),
    description: document.getElementById('wf-description').value,
    status: document.getElementById('wf-status').value,
    submit_label: document.getElementById('wf-submit-label').value || 'Submit',
    confirmation: document.getElementById('wf-confirmation').value,
    redirect_url: document.getElementById('wf-redirect').value,
    notify_emails: document.getElementById('wf-notify-emails').value,
    max_submissions: document.getElementById('wf-max-subs').value || null,
    open_at: document.getElementById('wf-open-at').value || null,
    close_at: document.getElementById('wf-close-at').value || null,
    recaptcha_enabled: document.getElementById('wf-recaptcha').checked,
    fields: formState.fields,
    pages: formState.pages,
  };

  if (!payload.label) {
    alert('Form label is required.');
    btn.disabled = false;
    btn.innerHTML = '<i data-lucide="save" class="w-4 h-4"></i> Save';
    return;
  }

  try {
    const url = IS_NEW ? '/admin/webforms' : `/admin/webforms/${WF_ID}`;
    const method = IS_NEW ? 'POST' : 'PUT';
    const resp = await CMS.fetch(url, { method, body: JSON.stringify(payload) });
    const data = await resp.json();

    if (data.success) {
      CMS.toast?.('Form saved', 'success');
      if (IS_NEW && data.id) {
        window.location.href = `/admin/webforms/${data.id}`;
      }
    } else {
      alert(data.error || 'Save failed');
    }
  } catch (e) {
    alert('Error: ' + e.message);
  } finally {
    btn.disabled = false;
    btn.innerHTML = '<i data-lucide="save" class="w-4 h-4"></i> Save';
    if (window.lucide) lucide.createIcons({ nodes: [btn] });
  }
}

// ── Helpers ──────────────────────────────────────────────────────────────

function autoSlug() {
  const label = document.getElementById('wf-label').value;
  if (IS_NEW || !document.getElementById('wf-slug').dataset.touched) {
    document.getElementById('wf-slug').value = label.toLowerCase().trim().replace(/[^a-z0-9]+/g, '_').replace(/^_|_$/g, '');
  }
}

function esc(str) {
  const d = document.createElement('div');
  d.textContent = str;
  return d.innerHTML;
}

function capitalize(s) {
  return s.charAt(0).toUpperCase() + s.slice(1);
}
</script>
<style>
.wf-field-card {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: .5rem .75rem;
  border: 1px solid var(--border);
  border-radius: var(--radius-md, 8px);
  background: var(--surface, #fff);
  transition: box-shadow .15s;
}
.wf-field-card:hover {
  box-shadow: 0 2px 8px rgba(0,0,0,.08);
}
.btn--active {
  font-weight: 600;
  color: var(--primary);
}
</style>
@endpush
