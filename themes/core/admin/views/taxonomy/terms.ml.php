@extends('layouts.admin')

@section('title', $title)
@section('page_title', $title)

@section('breadcrumb')
<a href="/admin" class="breadcrumb__item">Dashboard</a>
<span class="breadcrumb__sep">›</span>
<a href="/admin/taxonomy" class="breadcrumb__item">Taxonomy</a>
<span class="breadcrumb__sep">›</span>
<span class="breadcrumb__item breadcrumb__item--active">{{ $vocabulary->label }}</span>
@endsection

@section('content')
<div class="terms-page">

  {{-- Flash Messages --}}
  @php
    $success = $_GET['success'] ?? null;
    $error = $_GET['error'] ?? null;
  @endphp

  @if($success)
  <div class="alert alert--success mb-4">
    <i data-lucide="check-circle-2" class="w-4 h-4"></i>
    <span>{{ $success }}</span>
  </div>
  @endif

  @if($error)
  <div class="alert alert--error mb-4">
    <i data-lucide="alert-circle" class="w-4 h-4"></i>
    <span>{{ $error }}</span>
  </div>
  @endif

  {{-- Header --}}
  <div class="terms-header">
    <div class="terms-header__info">
      <div class="terms-header__badges">
        @if($vocabulary->hierarchical)
          <span class="badge badge--sm badge--info">Hierarchical</span>
        @else
          <span class="badge badge--sm badge--muted">Flat</span>
        @endif
        @if($vocabulary->multiple)
          <span class="badge badge--sm badge--success">Multiple</span>
        @endif
      </div>
      @if($vocabulary->description)
      <p class="text-muted text-sm">{{ $vocabulary->description }}</p>
      @endif
    </div>
    <div class="terms-header__actions">
      <a href="/admin/taxonomy/{{ $vocabulary->id }}/edit" class="btn btn--ghost btn--sm">
        <i data-lucide="settings" class="w-4 h-4"></i>
        <span>Settings</span>
      </a>
      <button type="button" class="btn btn--sm btn--outline-primary" id="ai-generate-btn" onclick="openAiModal()">
        <i data-lucide="sparkles" class="w-4 h-4"></i>
        <span>AI Generate</span>
      </button>
      <a href="/admin/taxonomy/{{ $vocabulary->id }}/terms/create" class="btn btn--primary btn--sm">
        <i data-lucide="plus" class="w-4 h-4"></i>
        <span>Add Term</span>
      </a>
    </div>
  </div>

  {{-- Terms Table --}}
  <div class="card">
    <div class="card__body card__body--flush">
      @if(!empty($terms))
      <table class="data-table terms-table" id="terms-table">
        <thead>
          <tr>
            <th style="width: 40px"></th>
            <th>Name</th>
            <th>Slug</th>
            <th style="width: 80px">Weight</th>
            <th class="text-right" style="width: 120px">Actions</th>
          </tr>
        </thead>
        <tbody>
          @php
            function renderTermRows(array $terms, object $vocabulary, int $depth = 0): string {
              $html = '';
              foreach ($terms as $term) {
                $depthClass = $depth > 0 ? ' term-row--child' : '';
                $paddingLeft = $depth * 28; // 28px per level
                $html .= '<tr class="term-row' . $depthClass . '" data-term-id="' . $term->id . '" data-depth="' . $depth . '">';
                $html .= '<td class="term-drag"><i data-lucide="grip-vertical" class="w-3.5 h-3.5 text-muted"></i></td>';
                $html .= '<td>';
                $html .= '<div class="term-name-wrap" style="padding-left:' . $paddingLeft . 'px">';
                if ($depth > 0) {
                  $html .= '<i data-lucide="corner-down-right" class="w-3 h-3 text-muted term-indent-icon"></i>';
                }
                $html .= '<div class="term-name-content">';
                $html .= '<a href="/admin/taxonomy/' . $vocabulary->id . '/terms/' . $term->id . '/edit" class="term-name">';
                $html .= htmlspecialchars($term->name);
                $html .= '</a>';
                if (!empty($term->description)) {
                  $html .= '<div class="term-desc">' . htmlspecialchars(mb_strimwidth($term->description, 0, 80, '…')) . '</div>';
                }
                $html .= '</div></div>';
                $html .= '</td>';
                $html .= '<td><code class="slug-code">' . htmlspecialchars($term->slug) . '</code></td>';
                $html .= '<td class="text-center"><span class="term-weight">' . $term->weight . '</span></td>';
                $html .= '<td class="text-right">';
                $html .= '<a href="/admin/taxonomy/' . $vocabulary->id . '/terms/' . $term->id . '/edit" class="btn btn--xs btn--ghost" title="Edit"><i data-lucide="pencil" class="w-3.5 h-3.5"></i></a>';
                $html .= '<form action="/admin/taxonomy/' . $vocabulary->id . '/terms/' . $term->id . '/delete" method="POST" class="inline" data-confirm="Delete this term?" data-confirm-title="Delete Term"><button type="submit" class="btn btn--xs btn--ghost btn--danger" title="Delete"><i data-lucide="trash-2" class="w-3.5 h-3.5"></i></button></form>';
                $html .= '</td>';
                $html .= '</tr>';
                if (!empty($term->children)) {
                  $html .= renderTermRows($term->children, $vocabulary, $depth + 1);
                }
              }
              return $html;
            }
          @endphp
          {!! renderTermRows($terms, $vocabulary) !!}
        </tbody>
      </table>
      @else
      <div class="empty-state py-10">
        <div class="empty-state__icon"><i data-lucide="tag" class="w-10 h-10"></i></div>
        <div class="empty-state__title">No terms yet</div>
        <p class="text-muted text-sm mb-4">Add terms manually or let AI generate them for you.</p>
        <div class="empty-state__actions">
          <button type="button" class="btn btn--sm btn--outline-primary" onclick="openAiModal()">
            <i data-lucide="sparkles" class="w-4 h-4"></i>
            <span>AI Generate Terms</span>
          </button>
          <a href="/admin/taxonomy/{{ $vocabulary->id }}/terms/create" class="btn btn--primary btn--sm">
            <i data-lucide="plus" class="w-4 h-4"></i>
            <span>Add Term</span>
          </a>
        </div>
      </div>
      @endif
    </div>
  </div>

</div>

{{-- AI Generate Modal --}}
<div class="ai-modal-overlay" id="ai-modal" style="display:none">
  <div class="ai-modal">
    <div class="ai-modal__header">
      <div class="ai-modal__header-icon">
        <i data-lucide="sparkles" class="w-5 h-5"></i>
      </div>
      <div>
        <h3 class="ai-modal__title">AI Generate Terms</h3>
        <p class="ai-modal__subtitle">Generate taxonomy terms for <strong>{{ $vocabulary->label }}</strong></p>
      </div>
      <button type="button" class="ai-modal__close" onclick="closeAiModal()">
        <i data-lucide="x" class="w-4 h-4"></i>
      </button>
    </div>

    <div class="ai-modal__body">
      {{-- Step 1: Config --}}
      <div id="ai-step-config">
        <div class="form-group">
          <label class="form-label">Number of terms</label>
          <input type="number" id="ai-term-count" class="form-input form-input--sm" value="10" min="1" max="50">
          <span class="form-hint">How many terms to generate (max 50).</span>
        </div>
        <div class="form-group">
          <label class="form-label">Additional context <span class="text-muted">(optional)</span></label>
          <textarea id="ai-context" class="form-input" rows="2" placeholder="e.g. Focus on web development topics, include frameworks and languages..."></textarea>
          <span class="form-hint">Guide the AI on what kind of terms to generate.</span>
        </div>
      </div>

      {{-- Step 2: Loading --}}
      <div id="ai-step-loading" style="display:none" class="ai-loading">
        <div class="ai-loading__spinner"></div>
        <p class="ai-loading__text">Generating terms with AI...</p>
      </div>

      {{-- Step 3: Results --}}
      <div id="ai-step-results" style="display:none">
        <div class="ai-results-header">
          <span class="ai-results-count"></span>
          <label class="ai-select-all">
            <input type="checkbox" id="ai-select-all" checked onchange="toggleAllTerms(this.checked)">
            Select all
          </label>
        </div>
        <div class="ai-results-list" id="ai-results-list"></div>
      </div>

      {{-- Step 4: Error --}}
      <div id="ai-step-error" style="display:none">
        <div class="alert alert--error">
          <i data-lucide="alert-circle" class="w-4 h-4"></i>
          <span id="ai-error-msg">Something went wrong.</span>
        </div>
      </div>
    </div>

    <div class="ai-modal__footer">
      <button type="button" class="btn btn--ghost" onclick="closeAiModal()">Cancel</button>
      <button type="button" class="btn btn--primary" id="ai-action-btn" onclick="generateTerms()">
        <i data-lucide="sparkles" class="w-4 h-4"></i>
        Generate
      </button>
    </div>
  </div>
</div>

@push('head')
<style>
.terms-page { padding: 1.5rem 2rem; max-width: 1100px; }

.terms-header {
  display: flex; justify-content: space-between; align-items: center;
  margin-bottom: 1.25rem;
}
.terms-header__info { display: flex; flex-direction: column; gap: 0.35rem; }
.terms-header__badges { display: flex; gap: 0.35rem; }
.terms-header__actions { display: flex; gap: 0.5rem; }
.terms-header__actions .btn span { margin-left: 0.25rem; }

.terms-table th,
.terms-table td { padding: 0.65rem 1rem; vertical-align: middle; }

.term-drag { cursor: grab; color: #475569; }
.term-drag:active { cursor: grabbing; }

.term-name-wrap {
  display: flex !important;
  flex-direction: row !important;
  align-items: flex-start !important;
  gap: 0.4rem;
  transition: padding-left 0.2s ease;
  width: 100%;
}
.term-indent-icon { flex-shrink: 0; margin-top: 0.2rem; display: block; }
.term-name-content { flex: 1 1 auto; min-width: 0; display: block; }

.term-name {
  color: #e2e8f0; font-weight: 500; text-decoration: none;
  transition: color 0.15s;
}
.term-name:hover { color: #a5b4fc; }
.term-desc { font-size: 0.7rem; color: #64748b; margin-top: 0.15rem; }

.term-row--child { background: rgba(255,255,255,0.015); }

.slug-code {
  font-size: 0.75rem; color: #94a3b8;
  font-family: 'JetBrains Mono', monospace;
  background: rgba(255,255,255,0.04);
  padding: 0.15rem 0.45rem; border-radius: 4px;
}
.term-weight {
  font-size: 0.75rem; color: #64748b;
  font-family: 'JetBrains Mono', monospace;
}

.badge--sm { font-size: 0.6rem; padding: 0.15rem 0.4rem; border-radius: 4px; }
.badge--info { background: rgba(99,102,241,0.12); color: #a5b4fc; }
.badge--muted { background: rgba(100,116,139,0.1); color: #64748b; }
.badge--success { background: rgba(34,197,94,0.12); color: #4ade80; }
.btn--danger { color: #f87171 !important; }
.btn--danger:hover { background: rgba(248,113,113,0.1) !important; }
.inline { display: inline; }
.text-center { text-align: center; }
.mb-4 { margin-bottom: 1rem; }

/* AI Outline Button */
.btn--outline-primary {
  background: transparent;
  border: 1px solid rgba(99,102,241,0.4);
  color: #a5b4fc;
  transition: all 0.2s;
}
.btn--outline-primary:hover {
  background: rgba(99,102,241,0.12);
  border-color: rgba(99,102,241,0.7);
}

/* Empty state */
.empty-state__actions { display: flex; gap: 0.5rem; justify-content: center; }

/* AI Modal */
.ai-modal-overlay {
  position: fixed; inset: 0; z-index: 9999;
  background: rgba(0,0,0,0.6);
  backdrop-filter: blur(4px);
  display: flex; align-items: center; justify-content: center;
  animation: fadeIn 0.15s ease;
}
@keyframes fadeIn { from { opacity: 0; } }

.ai-modal {
  background: #1a1f2e;
  border: 1px solid rgba(255,255,255,0.08);
  border-radius: 16px;
  width: 560px; max-width: 95vw; max-height: 85vh;
  display: flex; flex-direction: column;
  box-shadow: 0 25px 50px rgba(0,0,0,0.5);
  animation: slideUp 0.2s ease;
}
@keyframes slideUp { from { transform: translateY(16px); opacity: 0; } }

.ai-modal__header {
  display: flex; align-items: flex-start; gap: 0.75rem;
  padding: 1.25rem 1.5rem;
  border-bottom: 1px solid rgba(255,255,255,0.06);
}
.ai-modal__header-icon {
  width: 36px; height: 36px; flex-shrink: 0;
  display: flex; align-items: center; justify-content: center;
  background: linear-gradient(135deg, rgba(99,102,241,0.2), rgba(168,85,247,0.15));
  border-radius: 10px; color: #a5b4fc;
}
.ai-modal__title { font-size: 1rem; font-weight: 600; color: #e2e8f0; margin: 0; }
.ai-modal__subtitle { font-size: 0.75rem; color: #94a3b8; margin: 0.15rem 0 0; }
.ai-modal__close {
  margin-left: auto; background: none; border: none; color: #64748b;
  cursor: pointer; padding: 0.25rem; border-radius: 6px;
  transition: color 0.15s, background 0.15s;
}
.ai-modal__close:hover { color: #e2e8f0; background: rgba(255,255,255,0.06); }

.ai-modal__body {
  padding: 1.25rem 1.5rem;
  overflow-y: auto; flex: 1;
}

.ai-modal__footer {
  display: flex; justify-content: flex-end; gap: 0.5rem;
  padding: 1rem 1.5rem;
  border-top: 1px solid rgba(255,255,255,0.06);
}

/* Loading */
.ai-loading { text-align: center; padding: 2rem 0; }
.ai-loading__spinner {
  width: 36px; height: 36px; margin: 0 auto 1rem;
  border: 3px solid rgba(99,102,241,0.15);
  border-top-color: #818cf8;
  border-radius: 50%;
  animation: spin 0.8s linear infinite;
}
@keyframes spin { to { transform: rotate(360deg); } }
.animate-spin { animation: spin 0.8s linear infinite; }
.ai-loading__text { color: #94a3b8; font-size: 0.85rem; }

/* Results */
.ai-results-header {
  display: flex; justify-content: space-between; align-items: center;
  margin-bottom: 0.75rem;
}
.ai-results-count { font-size: 0.8rem; color: #94a3b8; }
.ai-select-all {
  display: flex; align-items: center; gap: 0.4rem;
  font-size: 0.8rem; color: #94a3b8; cursor: pointer;
}
.ai-results-list {
  max-height: 380px; overflow-y: auto;
  display: flex; flex-direction: column; gap: 0.35rem;
}
.ai-term-item {
  display: flex; align-items: flex-start; gap: 0.6rem;
  padding: 0.6rem 0.75rem;
  background: rgba(255,255,255,0.03);
  border: 1px solid rgba(255,255,255,0.05);
  border-radius: 8px;
  transition: border-color 0.15s, background 0.15s;
}
.ai-term-item:hover { border-color: rgba(99,102,241,0.2); }
.ai-term-item--selected { border-color: rgba(99,102,241,0.3); background: rgba(99,102,241,0.04); }
.ai-term-item input[type="checkbox"] { margin-top: 0.15rem; flex-shrink: 0; }
.ai-term-item__info { flex: 1; min-width: 0; }
.ai-term-item__name { font-size: 0.85rem; font-weight: 500; color: #e2e8f0; }
.ai-term-item__desc { font-size: 0.72rem; color: #64748b; margin-top: 0.1rem; }
.ai-term-item__parent {
  font-size: 0.65rem; color: #818cf8; margin-top: 0.15rem;
  display: flex; align-items: center; gap: 0.2rem;
}
/* DnD */
.term-row--dragging { opacity: 0.3; }
.term-row--placeholder td { padding: 0 !important; border: none !important; }
.placeholder-bar {
  height: 3px; background: #818cf8; border-radius: 2px;
  transition: margin-left 0.15s ease;
}
.term-row { transition: background 0.15s; }
.term-drag { cursor: grab; color: #475569; user-select: none; }
.term-drag:active { cursor: grabbing; }

/* Save status toast */
.dnd-status {
  position: fixed; bottom: 1.5rem; right: 1.5rem;
  padding: 0.5rem 1rem; border-radius: 8px;
  font-size: 0.8rem; font-weight: 500;
  z-index: 9999; pointer-events: none;
  opacity: 0; transition: opacity 0.2s;
}
.dnd-status--saving {
  opacity: 1; background: rgba(99,102,241,0.15); color: #a5b4fc;
  border: 1px solid rgba(99,102,241,0.3);
}
.dnd-status--saved {
  opacity: 1; background: rgba(34,197,94,0.15); color: #4ade80;
  border: 1px solid rgba(34,197,94,0.3);
}
.dnd-status--error {
  opacity: 1; background: rgba(248,113,113,0.15); color: #f87171;
  border: 1px solid rgba(248,113,113,0.3);
}
</style>
@endpush

@push('scripts')
<script src="/themes/core/admin/js/term-tree-dnd.js?v={{ time() }}"></script>
<script>
const VOCAB_ID = {{ $vocabulary->id }};
const VOCAB_NAME = '{{ addslashes($vocabulary->label) }}';
const VOCAB_DESC = '{{ addslashes($vocabulary->description ?? '') }}';
const VOCAB_HIERARCHICAL = {{ $vocabulary->hierarchical ? 'true' : 'false' }};

// Collect existing terms for the AI context
const existingTermNames = [];
document.querySelectorAll('.term-name').forEach(el => existingTermNames.push(el.textContent.trim()));

function openAiModal() {
  document.getElementById('ai-modal').style.display = 'flex';
  showStep('config');
  updateActionButton('generate');
}

function closeAiModal() {
  document.getElementById('ai-modal').style.display = 'none';
}

function showStep(step) {
  ['config', 'loading', 'results', 'error'].forEach(s => {
    document.getElementById('ai-step-' + s).style.display = s === step ? '' : 'none';
  });
}

function updateActionButton(mode) {
  const btn = document.getElementById('ai-action-btn');
  if (mode === 'generate') {
    btn.innerHTML = '<i data-lucide="sparkles" class="w-4 h-4"></i> Generate';
    btn.onclick = generateTerms;
    btn.disabled = false;
  } else if (mode === 'save') {
    btn.innerHTML = '<i data-lucide="save" class="w-4 h-4"></i> Save Selected';
    btn.onclick = saveSelectedTerms;
    btn.disabled = false;
  } else if (mode === 'saving') {
    btn.innerHTML = '<i data-lucide="loader-2" class="w-4 h-4 animate-spin"></i> Saving...';
    btn.disabled = true;
  }
  // Re-init Lucide icons in the button
  if (window.lucide) lucide.createIcons();
}

async function generateTerms() {
  const count = parseInt(document.getElementById('ai-term-count').value) || 10;
  const context = document.getElementById('ai-context').value.trim();

  showStep('loading');
  const btn = document.getElementById('ai-action-btn');
  btn.disabled = true;
  btn.innerHTML = '<i data-lucide="loader-2" class="w-4 h-4 animate-spin"></i> Generating...';
  if (window.lucide) lucide.createIcons();

  try {
    const body = {
      vocabulary_name: VOCAB_NAME,
      vocabulary_description: VOCAB_DESC + (context ? ' ' + context : ''),
      count: count,
      hierarchical: VOCAB_HIERARCHICAL,
      existing_terms: JSON.stringify(existingTermNames),
    };

    const resp = await fetch('/api/cms/apex/taxonomy/generate-terms', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(body),
    });

    const data = await resp.json();

    if (!resp.ok || data.error) {
      throw new Error(data.error || 'AI request failed');
    }

    renderResults(data.terms);
    showStep('results');
    updateActionButton('save');
  } catch (err) {
    document.getElementById('ai-error-msg').textContent = err.message;
    showStep('error');
    updateActionButton('generate');
    document.getElementById('ai-action-btn').disabled = false;
  }
}

let generatedTerms = [];

function renderResults(terms) {
  generatedTerms = terms;
  const list = document.getElementById('ai-results-list');
  const countEl = document.querySelector('.ai-results-count');

  countEl.textContent = `${terms.length} terms generated`;
  list.innerHTML = '';

  terms.forEach((term, i) => {
    const div = document.createElement('div');
    div.className = 'ai-term-item ai-term-item--selected';
    div.innerHTML = `
      <input type="checkbox" checked data-term-index="${i}" onchange="toggleTermItem(this)">
      <div class="ai-term-item__info">
        <div class="ai-term-item__name">${escapeHtml(term.name)}</div>
        ${term.description ? `<div class="ai-term-item__desc">${escapeHtml(term.description)}</div>` : ''}
        ${term.parent ? `<div class="ai-term-item__parent"><i data-lucide="corner-down-right" class="w-3 h-3"></i> under ${escapeHtml(term.parent)}</div>` : ''}
      </div>
    `;
    list.appendChild(div);
  });

  document.getElementById('ai-select-all').checked = true;
  if (window.lucide) lucide.createIcons();
}

function toggleTermItem(checkbox) {
  const item = checkbox.closest('.ai-term-item');
  item.classList.toggle('ai-term-item--selected', checkbox.checked);
}

function toggleAllTerms(checked) {
  document.querySelectorAll('.ai-term-item input[type="checkbox"]').forEach(cb => {
    cb.checked = checked;
    toggleTermItem(cb);
  });
}

async function saveSelectedTerms() {
  const checkboxes = document.querySelectorAll('.ai-term-item input[type="checkbox"]:checked');
  const selectedTerms = [];
  checkboxes.forEach(cb => {
    const idx = parseInt(cb.dataset.termIndex);
    selectedTerms.push(generatedTerms[idx]);
  });

  if (selectedTerms.length === 0) {
    alert('Select at least one term to save.');
    return;
  }

  updateActionButton('saving');

  try {
    const resp = await CMS.fetch(`/admin/taxonomy/${VOCAB_ID}/terms/bulk`, {
      method: 'POST',
      body: JSON.stringify({ terms: selectedTerms }),
    });

    const data = await resp.json();

    if (!resp.ok || data.error) {
      throw new Error(data.error || 'Save failed');
    }

    const msg = `${data.created} term(s) created via AI.`;
    sessionStorage.setItem('taxonomy_flash', msg);
    window.location.href = `/admin/taxonomy/${VOCAB_ID}/terms`;
  } catch (err) {
    document.getElementById('ai-error-msg').textContent = 'Failed to save: ' + err.message;
    showStep('error');
    updateActionButton('save');
  }
}

function escapeHtml(str) {
  const div = document.createElement('div');
  div.textContent = str;
  return div.innerHTML;
}

// Close on overlay click (only the dark backdrop, not the modal content)
document.getElementById('ai-modal')?.addEventListener('click', (e) => {
  if (e.target === e.currentTarget) closeAiModal();
});

// Close on Escape
document.addEventListener('keydown', (e) => {
  if (e.key === 'Escape') closeAiModal();
});

// Show flash from sessionStorage (AI bulk save)
document.addEventListener('DOMContentLoaded', () => {
  const flash = sessionStorage.getItem('taxonomy_flash');
  if (flash) {
    sessionStorage.removeItem('taxonomy_flash');
    const div = document.createElement('div');
    div.className = 'alert alert--success mb-4';
    div.style.animation = 'fadeIn 0.3s ease';
    div.innerHTML = '<i data-lucide="check-circle-2" class="w-4 h-4"></i><span>' + flash + '</span>';
    const page = document.querySelector('.terms-page');
    if (page) page.insertBefore(div, page.firstChild);
    if (window.lucide) lucide.createIcons();
    setTimeout(() => { div.style.opacity = '0'; div.style.transition = 'opacity 0.3s'; setTimeout(() => div.remove(), 300); }, 4000);
  }

  // Init drag-and-drop for terms
  if (typeof TermTreeDnD !== 'undefined') {
    TermTreeDnD.init({ vocabId: VOCAB_ID });
  }
});
</script>
@endpush

@endsection
