@extends('layouts.admin')

@section('title', 'Search Settings')
@section('page_title', 'Search Settings')

@section('breadcrumb')
<a href="/admin" class="breadcrumb__item">Dashboard</a>
<span class="breadcrumb__sep">›</span>
<a href="/admin/search" class="breadcrumb__item">Search</a>
<span class="breadcrumb__sep">›</span>
<span class="breadcrumb__item breadcrumb__item--active">Settings</span>
@endsection

@section('toolbar_actions')
<div class="toolbar-actions" style="display:flex;gap:.5rem">
  <a href="/admin/search" class="btn btn--ghost btn--sm">
    <i data-lucide="arrow-left" class="w-4 h-4"></i> Back to Search
  </a>
</div>
@endsection

@section('content')
<div class="ss-page">

  {{-- Flash message --}}
  @if(!empty($flash))
  <div class="alert alert--success" style="margin-bottom:1.5rem">{{ $flash }}</div>
  @endif

  <form method="POST" action="/admin/search/settings" id="settings-form">

    {{-- ── Engine Selector ──────────────────────────────────────────── --}}
    <div class="ss-card">
      <div class="ss-card__header">
        <div class="ss-card__icon" style="background:rgba(129,140,248,.12)">
          <i data-lucide="cpu" style="color:#818cf8;width:20px;height:20px"></i>
        </div>
        <div>
          <h3 class="ss-card__title">Active Search Engine</h3>
          <p class="ss-card__subtitle">Choose which backend powers your site search</p>
        </div>
      </div>
      <div class="ss-card__body">
        <div class="ss-engine-grid">
          @foreach($engines as $eName => $eLabel)
          <label class="ss-engine-option {{ ($settings['search_engine'] ?? 'database') === $eName ? 'ss-engine-option--active' : '' }}"
                 data-engine="{{ $eName }}">
            <input type="radio" name="search_engine" value="{{ $eName }}"
              {{ ($settings['search_engine'] ?? 'database') === $eName ? 'checked' : '' }}>
            <div class="ss-engine-option__icon">
              @if($eName === 'database')
              <i data-lucide="database" class="w-6 h-6"></i>
              @elseif($eName === 'elasticsearch')
              <i data-lucide="zap" class="w-6 h-6"></i>
              @elseif($eName === 'solr')
              <i data-lucide="search" class="w-6 h-6"></i>
              @else
              <i data-lucide="box" class="w-6 h-6"></i>
              @endif
            </div>
            <span class="ss-engine-option__name">{{ $eLabel }}</span>
            <span class="ss-engine-option__check">
              <i data-lucide="check" class="w-4 h-4"></i>
            </span>
          </label>
          @endforeach
        </div>
      </div>
    </div>

    {{-- ── Database Info ─────────────────────────────────────────────── --}}
    <div class="ss-card ss-engine-panel" id="panel-database">
      <div class="ss-card__header">
        <div class="ss-card__icon" style="background:rgba(52,211,153,.12)">
          <i data-lucide="database" style="color:#34d399;width:20px;height:20px"></i>
        </div>
        <div>
          <h3 class="ss-card__title">Database Engine</h3>
          <p class="ss-card__subtitle">Zero-configuration — uses your existing database connection</p>
        </div>
        <span class="ss-badge ss-badge--green">Zero Config</span>
      </div>
      <div class="ss-card__body">
        <div class="ss-info-grid">
          <div class="ss-info-item">
            <span class="ss-info-item__label">Driver</span>
            <span class="ss-info-item__value">
              <code class="ss-code">{{ $settings['_db_driver'] ?? 'auto-detected' }}</code>
            </span>
          </div>
          <div class="ss-info-item">
            <span class="ss-info-item__label">Mode</span>
            <span class="ss-info-item__value">
              <code class="ss-code">FULLTEXT</code>
              <span class="ss-tag">+ LIKE fallback</span>
            </span>
          </div>
          <div class="ss-info-item">
            <span class="ss-info-item__label">Features</span>
            <span class="ss-info-item__value">
              <span class="ss-tag">Multi-source</span>
              <span class="ss-tag">EAV fields</span>
              <span class="ss-tag">Boolean mode</span>
            </span>
          </div>
        </div>
        <div class="ss-note">
          <i data-lucide="info" class="w-4 h-4"></i>
          <span>No configuration required. The database engine automatically detects MySQL FULLTEXT support and falls back to LIKE queries for PostgreSQL and SQLite.</span>
        </div>
      </div>
    </div>

    {{-- ── Elasticsearch Settings ────────────────────────────────────── --}}
    <div class="ss-card ss-engine-panel" id="panel-elasticsearch">
      <div class="ss-card__header">
        <div class="ss-card__icon" style="background:rgba(247,223,30,.12)">
          <i data-lucide="zap" style="color:#f7df1e;width:20px;height:20px"></i>
        </div>
        <div>
          <h3 class="ss-card__title">Elasticsearch / OpenSearch</h3>
          <p class="ss-card__subtitle">Enterprise search with fuzzy matching, facets & highlighting</p>
        </div>
      </div>
      <div class="ss-card__body">
        {{-- Connection --}}
        <div class="ss-fieldset">
          <h4 class="ss-fieldset__title">
            <i data-lucide="link" class="w-4 h-4"></i> Connection
          </h4>
          <div class="ss-field-row">
            <div class="ss-field" style="flex:2">
              <label class="form-label">Host URL <span class="ss-required">*</span></label>
              <input type="url" name="elasticsearch_host" class="form-input"
                value="{{ $settings['elasticsearch_host'] ?? '' }}" placeholder="https://localhost:9200">
              <p class="form-help">Full URL including protocol and port</p>
            </div>
            <div class="ss-field" style="flex:1">
              <label class="form-label">Timeout (seconds)</label>
              <input type="number" name="elasticsearch_timeout" class="form-input"
                value="{{ $settings['elasticsearch_timeout'] ?? '30' }}" min="1" max="120">
            </div>
          </div>
          <div class="ss-field-row">
            <div class="ss-field" style="flex:1">
              <label class="form-label">Index Name</label>
              <input type="text" name="elasticsearch_index" class="form-input"
                value="{{ $settings['elasticsearch_index'] ?? 'monkeyscms_content' }}" placeholder="monkeyscms_content">
              <p class="form-help">Elasticsearch index to store documents in</p>
            </div>
            <div class="ss-field" style="flex:1">
              <label class="form-label">Index Prefix</label>
              <input type="text" name="elasticsearch_prefix" class="form-input"
                value="{{ $settings['elasticsearch_prefix'] ?? '' }}" placeholder="e.g. prod_">
              <p class="form-help">Optional prefix for multi-tenant setups</p>
            </div>
          </div>
        </div>

        {{-- Authentication --}}
        <div class="ss-fieldset">
          <h4 class="ss-fieldset__title">
            <i data-lucide="shield" class="w-4 h-4"></i> Authentication
          </h4>
          <div class="ss-field">
            <label class="form-label">API Key</label>
            <input type="password" name="elasticsearch_api_key" class="form-input ss-password-field"
              value="{{ $settings['elasticsearch_api_key'] ?? '' }}" placeholder="Base64 encoded API key"
              autocomplete="off">
            <p class="form-help">Preferred for Elastic Cloud. Takes precedence over username/password.</p>
          </div>
          <div class="ss-field-row">
            <div class="ss-field" style="flex:1">
              <label class="form-label">Username</label>
              <input type="text" name="elasticsearch_username" class="form-input"
                value="{{ $settings['elasticsearch_username'] ?? '' }}" placeholder="elastic" autocomplete="off">
            </div>
            <div class="ss-field" style="flex:1">
              <label class="form-label">Password</label>
              <input type="password" name="elasticsearch_password" class="form-input ss-password-field"
                value="{{ $settings['elasticsearch_password'] ?? '' }}" placeholder="••••••••" autocomplete="off">
            </div>
          </div>
        </div>

        {{-- TLS --}}
        <div class="ss-fieldset ss-fieldset--compact">
          <h4 class="ss-fieldset__title">
            <i data-lucide="lock" class="w-4 h-4"></i> TLS / SSL
          </h4>
          <label class="ss-toggle">
            <input type="checkbox" name="elasticsearch_ssl_verify" value="1"
              {{ ($settings['elasticsearch_ssl_verify'] ?? '1') === '1' ? 'checked' : '' }}>
            <span class="ss-toggle__slider"></span>
            <span class="ss-toggle__label">Verify SSL Certificate</span>
          </label>
          <p class="form-help" style="margin-left:3.5rem">Disable only for self-signed certificates in development</p>
        </div>
      </div>
    </div>

    {{-- ── Solr Settings ─────────────────────────────────────────────── --}}
    <div class="ss-card ss-engine-panel" id="panel-solr">
      <div class="ss-card__header">
        <div class="ss-card__icon" style="background:rgba(217,65,30,.12)">
          <i data-lucide="search" style="color:#d9411e;width:20px;height:20px"></i>
        </div>
        <div>
          <h3 class="ss-card__title">Apache Solr</h3>
          <p class="ss-card__subtitle">Enterprise search with faceting, spellcheck & suggestions</p>
        </div>
      </div>
      <div class="ss-card__body">
        {{-- Connection --}}
        <div class="ss-fieldset">
          <h4 class="ss-fieldset__title">
            <i data-lucide="link" class="w-4 h-4"></i> Connection
          </h4>
          <div class="ss-field-row">
            <div class="ss-field" style="flex:2">
              <label class="form-label">Host URL <span class="ss-required">*</span></label>
              <input type="url" name="solr_host" class="form-input"
                value="{{ $settings['solr_host'] ?? '' }}" placeholder="http://localhost:8983">
              <p class="form-help">Solr server URL including protocol and port</p>
            </div>
            <div class="ss-field" style="flex:1">
              <label class="form-label">Timeout (seconds)</label>
              <input type="number" name="solr_timeout" class="form-input"
                value="{{ $settings['solr_timeout'] ?? '30' }}" min="1" max="120">
            </div>
          </div>
          <div class="ss-field">
            <label class="form-label">Core Name <span class="ss-required">*</span></label>
            <input type="text" name="solr_core" class="form-input"
              value="{{ $settings['solr_core'] ?? 'monkeyscms' }}" placeholder="monkeyscms">
            <p class="form-help">The Solr core or collection name</p>
          </div>
        </div>

        {{-- Authentication --}}
        <div class="ss-fieldset">
          <h4 class="ss-fieldset__title">
            <i data-lucide="shield" class="w-4 h-4"></i> Authentication
          </h4>
          <div class="ss-field-row">
            <div class="ss-field" style="flex:1">
              <label class="form-label">Username</label>
              <input type="text" name="solr_username" class="form-input"
                value="{{ $settings['solr_username'] ?? '' }}" placeholder="solr" autocomplete="off">
            </div>
            <div class="ss-field" style="flex:1">
              <label class="form-label">Password</label>
              <input type="password" name="solr_password" class="form-input ss-password-field"
                value="{{ $settings['solr_password'] ?? '' }}" placeholder="••••••••" autocomplete="off">
            </div>
          </div>
          <p class="form-help">Leave empty if Solr is not configured with Basic Auth</p>
        </div>
      </div>
    </div>

    {{-- ── Save Button ───────────────────────────────────────────────── --}}
    <div class="ss-actions">
      <a href="/admin/search" class="btn btn--ghost">Cancel</a>
      <button type="submit" class="btn btn--primary">
        <i data-lucide="save" class="w-4 h-4"></i> Save Settings
      </button>
    </div>

  </form>
</div>
@endsection

@push('head')
<style>
/* ── Page Layout ─────────────────────────────────────────────── */
.ss-page {
  max-width: 860px;
  padding: 0 1.5rem 2rem;
}

/* ── Card ────────────────────────────────────────────────────── */
.ss-card {
  background: rgba(20, 22, 38, .5);
  border: 1px solid rgba(255,255,255,.06);
  border-radius: 16px;
  margin-bottom: 1.25rem;
  overflow: hidden;
  transition: border-color .2s;
}

.ss-card:hover {
  border-color: rgba(255,255,255,.1);
}

.ss-card__header {
  display: flex;
  align-items: center;
  gap: 1rem;
  padding: 1.25rem 1.5rem;
  border-bottom: 1px solid rgba(255,255,255,.04);
}

.ss-card__icon {
  width: 44px;
  height: 44px;
  border-radius: 12px;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

.ss-card__title {
  font-size: 1.05rem;
  font-weight: 700;
  color: #e2e8f0;
  margin: 0;
  line-height: 1.3;
}

.ss-card__subtitle {
  font-size: .82rem;
  color: #64748b;
  margin: .15rem 0 0;
}

.ss-card__body {
  padding: 1.5rem;
}

/* ── Engine Selector ─────────────────────────────────────────── */
.ss-engine-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
  gap: .75rem;
}

.ss-engine-option {
  position: relative;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: .6rem;
  padding: 1.25rem 1rem;
  background: rgba(15, 23, 42, .6);
  border: 2px solid rgba(255,255,255,.06);
  border-radius: 12px;
  cursor: pointer;
  transition: all .2s;
  text-align: center;
}

.ss-engine-option input[type="radio"] {
  position: absolute;
  opacity: 0;
  pointer-events: none;
}

.ss-engine-option:hover {
  border-color: rgba(129,140,248,.25);
  background: rgba(129,140,248,.04);
}

.ss-engine-option--active {
  border-color: rgba(129,140,248,.5) !important;
  background: rgba(129,140,248,.08) !important;
  box-shadow: 0 0 20px rgba(129,140,248,.1);
}

.ss-engine-option__icon {
  color: #64748b;
  transition: color .2s;
}

.ss-engine-option--active .ss-engine-option__icon {
  color: #818cf8;
}

.ss-engine-option__name {
  font-size: .85rem;
  font-weight: 600;
  color: #94a3b8;
  transition: color .2s;
}

.ss-engine-option--active .ss-engine-option__name {
  color: #e2e8f0;
}

.ss-engine-option__check {
  position: absolute;
  top: .5rem;
  right: .5rem;
  width: 22px;
  height: 22px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  background: rgba(129,140,248,.15);
  color: #818cf8;
  opacity: 0;
  transform: scale(.5);
  transition: all .2s;
}

.ss-engine-option--active .ss-engine-option__check {
  opacity: 1;
  transform: scale(1);
}

/* ── Engine Panels (conditional visibility) ──────────────────── */
.ss-engine-panel {
  display: none;
}

.ss-engine-panel.is-visible {
  display: block;
  animation: ss-fadeIn .3s ease;
}

@keyframes ss-fadeIn {
  from { opacity: 0; transform: translateY(-8px); }
  to   { opacity: 1; transform: translateY(0); }
}

/* ── Fieldset ────────────────────────────────────────────────── */
.ss-fieldset {
  padding: 1rem 0;
  border-bottom: 1px solid rgba(255,255,255,.04);
}

.ss-fieldset:last-child {
  border-bottom: none;
  padding-bottom: 0;
}

.ss-fieldset:first-child {
  padding-top: 0;
}

.ss-fieldset--compact {
  padding: .75rem 0;
}

.ss-fieldset__title {
  display: flex;
  align-items: center;
  gap: .5rem;
  font-size: .78rem;
  font-weight: 600;
  color: #64748b;
  text-transform: uppercase;
  letter-spacing: .04em;
  margin: 0 0 1rem;
}

.ss-fieldset__title i {
  color: #475569;
}

/* ── Field Rows ──────────────────────────────────────────────── */
.ss-field-row {
  display: flex;
  gap: 1rem;
  margin-bottom: .75rem;
}

.ss-field {
  margin-bottom: .75rem;
}

.ss-field:last-child {
  margin-bottom: 0;
}

.ss-required {
  color: #f87171;
}

/* ── Toggle ──────────────────────────────────────────────────── */
.ss-toggle {
  display: flex;
  align-items: center;
  gap: .75rem;
  cursor: pointer;
}

.ss-toggle input[type="checkbox"] {
  position: absolute;
  opacity: 0;
  pointer-events: none;
}

.ss-toggle__slider {
  position: relative;
  width: 44px;
  height: 24px;
  background: rgba(255,255,255,.1);
  border-radius: 12px;
  transition: background .2s;
  flex-shrink: 0;
}

.ss-toggle__slider::after {
  content: '';
  position: absolute;
  top: 3px;
  left: 3px;
  width: 18px;
  height: 18px;
  background: #94a3b8;
  border-radius: 50%;
  transition: transform .2s, background .2s;
}

.ss-toggle input:checked + .ss-toggle__slider {
  background: rgba(129,140,248,.3);
}

.ss-toggle input:checked + .ss-toggle__slider::after {
  transform: translateX(20px);
  background: #818cf8;
}

.ss-toggle__label {
  font-size: .9rem;
  color: #cbd5e1;
  font-weight: 500;
}

/* ── Info Grid (DB panel) ────────────────────────────────────── */
.ss-info-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
  gap: .5rem;
  margin-bottom: 1rem;
}

.ss-info-item {
  display: flex;
  flex-direction: column;
  gap: .4rem;
  padding: .85rem 1rem;
  background: rgba(15, 23, 42, .5);
  border: 1px solid rgba(255,255,255,.04);
  border-radius: 10px;
}

.ss-info-item__label {
  font-size: .72rem;
  font-weight: 600;
  color: #475569;
  text-transform: uppercase;
  letter-spacing: .04em;
}

.ss-info-item__value {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: .35rem;
  font-size: .88rem;
  color: #e2e8f0;
  font-weight: 500;
}

.ss-tag {
  padding: .15rem .5rem;
  border-radius: 6px;
  background: rgba(129,140,248,.08);
  border: 1px solid rgba(129,140,248,.12);
  color: #a5b4fc;
  font-size: .78rem;
  font-weight: 500;
}

.ss-code {
  padding: .15rem .4rem;
  border-radius: 4px;
  background: rgba(129,140,248,.08);
  color: #a5b4fc;
  font-family: 'JetBrains Mono', monospace;
  font-size: .8rem;
}

.ss-note {
  display: flex;
  align-items: flex-start;
  gap: .6rem;
  padding: .75rem 1rem;
  background: rgba(129,140,248,.05);
  border: 1px solid rgba(129,140,248,.1);
  border-radius: 8px;
  color: #94a3b8;
  font-size: .82rem;
  line-height: 1.5;
}

.ss-note i {
  color: #818cf8;
  flex-shrink: 0;
  margin-top: .1rem;
}

/* ── Badge ───────────────────────────────────────────────────── */
.ss-badge {
  margin-left: auto;
  padding: .25rem .75rem;
  border-radius: 20px;
  font-size: .7rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: .05em;
}

.ss-badge--green {
  background: linear-gradient(135deg, rgba(52,211,153,.15), rgba(16,185,129,.15));
  border: 1px solid rgba(52,211,153,.25);
  color: #34d399;
}

/* ── Password Toggle ─────────────────────────────────────────── */
.ss-password-wrap {
  position: relative;
}

/* ── Actions ─────────────────────────────────────────────────── */
.ss-actions {
  display: flex;
  justify-content: flex-end;
  gap: .75rem;
  padding-top: .5rem;
}

/* ── Responsive ──────────────────────────────────────────────── */
/* ── Form Help Text ──────────────────────────────────────────── */
.ss-page .form-help {
  font-size: .8rem;
  color: #64748b;
  margin-top: .35rem;
  line-height: 1.4;
}

.ss-page .form-label {
  font-size: .88rem;
  color: #94a3b8;
  font-weight: 500;
  margin-bottom: .35rem;
}

@media (max-width: 640px) {
  .ss-field-row {
    flex-direction: column;
    gap: 0;
  }
  .ss-engine-grid {
    grid-template-columns: 1fr;
  }
  .ss-info-grid {
    grid-template-columns: 1fr;
  }
}
</style>
@endpush

@push('scripts')
<script>
(function() {
  'use strict';

  const form = document.getElementById('settings-form');
  const radios = form.querySelectorAll('input[name="search_engine"]');
  const options = form.querySelectorAll('.ss-engine-option');
  const panels = form.querySelectorAll('.ss-engine-panel');

  function updatePanels(selected) {
    // Update radio card active state
    options.forEach(opt => {
      opt.classList.toggle('ss-engine-option--active', opt.dataset.engine === selected);
    });

    // Show/hide engine config panels
    panels.forEach(panel => {
      const panelEngine = panel.id.replace('panel-', '');
      panel.classList.toggle('is-visible', panelEngine === selected);
    });
  }

  // Bind radio changes
  radios.forEach(radio => {
    radio.addEventListener('change', () => updatePanels(radio.value));
  });

  // Initial state
  const checked = form.querySelector('input[name="search_engine"]:checked');
  if (checked) updatePanels(checked.value);

  // Re-init lucide icons after DOM changes
  if (window.lucide) lucide.createIcons();
})();
</script>
@endpush
