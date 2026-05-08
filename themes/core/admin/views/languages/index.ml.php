@extends('layouts.admin')

@section('title', $title ?? 'Languages')

@section('content')
<div class="admin-page">

  {{-- ── Header ───────────────────────────────────────────────────────── --}}
  <div class="admin-page__header" style="display:flex;align-items:center;justify-content:space-between">
    <div>
      <h1 class="admin-page__title">
        <i data-lucide="globe" class="w-6 h-6"></i> Multilingual
      </h1>
      <p class="admin-page__desc">Manage languages and translation coverage for your site.</p>
    </div>
    <div style="display:flex;align-items:center;gap:1rem">
      {{-- Module toggle --}}
      <label class="form-toggle" style="margin:0">
        <input type="checkbox" id="module-toggle" {{ $isModuleEnabled ? 'checked' : '' }}
               onchange="toggleModule(this.checked)">
        <span class="form-toggle__label" style="font-weight:600">
          {{ $isModuleEnabled ? 'Module Active' : 'Module Disabled' }}
        </span>
      </label>
    </div>
  </div>

  {{-- ── Module Disabled Banner ───────────────────────────────────────── --}}
  <div id="module-disabled-banner" style="{{ $isModuleEnabled ? 'display:none' : '' }}">
    <div class="card" style="border-left:4px solid var(--warning, #f59e0b)">
      <div class="card__body" style="display:flex;align-items:center;gap:1rem">
        <i data-lucide="alert-triangle" class="w-6 h-6" style="color:var(--warning,#f59e0b);flex-shrink:0"></i>
        <div>
          <strong>Multilingual module is disabled.</strong>
          <p class="text-sm text-muted" style="margin-top:.25rem">
            Enable the module to activate language detection, content translation, and the language switcher.
            All content will remain in the default language until enabled.
          </p>
        </div>
      </div>
    </div>
  </div>

  <div id="module-content" style="{{ $isModuleEnabled ? '' : 'opacity:.5;pointer-events:none' }}">

  {{-- ── Stats Row ────────────────────────────────────────────────────── --}}
  <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:1rem;margin-bottom:1.5rem">
    @php
      $enabledCount = count(array_filter($languages, fn($l) => $l->enabled));
      $totalCount = count($languages);
    @endphp
    <div class="card">
      <div class="card__body" style="text-align:center;padding:1.25rem">
        <div style="font-size:2rem;font-weight:700;color:var(--primary)">{{ $enabledCount }}</div>
        <div class="text-sm text-muted">Active Languages</div>
      </div>
    </div>
    <div class="card">
      <div class="card__body" style="text-align:center;padding:1.25rem">
        <div style="font-size:2rem;font-weight:700;color:var(--text)">{{ $totalCount }}</div>
        <div class="text-sm text-muted">Available Languages</div>
      </div>
    </div>
    <div class="card">
      <div class="card__body" style="text-align:center;padding:1.25rem">
        <div style="font-size:2rem;font-weight:700;color:var(--success,#22c55e)">{{ $defaultCode }}</div>
        <div class="text-sm text-muted">Default Language</div>
      </div>
    </div>
  </div>

  {{-- ── Languages Table ──────────────────────────────────────────────── --}}
  <div class="card">
    <div class="card__header" style="display:flex;align-items:center;justify-content:space-between">
      <h3 class="card__title"><i data-lucide="list" class="w-4 h-4"></i> Languages</h3>
      <div style="display:flex;gap:.5rem">
        <button class="btn btn--ghost btn--xs" onclick="showAll()" id="btn-show-all">Show All</button>
        <button class="btn btn--ghost btn--xs" onclick="showEnabled()" id="btn-show-enabled">Enabled Only</button>
      </div>
    </div>
    <div class="card__body p-0">
      <table class="table" id="lang-table">
        <thead>
          <tr>
            <th style="width:50px"></th>
            <th style="width:80px">Code</th>
            <th>Language</th>
            <th style="width:80px;text-align:center">Dir</th>
            <th style="width:100px;text-align:center">Status</th>
            <th style="width:160px;text-align:right">Actions</th>
          </tr>
        </thead>
        <tbody>
          @foreach($languages as $lang)
          <tr id="lang-{{ $lang->code }}" class="lang-row {{ $lang->enabled ? 'lang-enabled' : 'lang-disabled' }}"
              data-enabled="{{ $lang->enabled ? '1' : '0' }}">
            <td style="text-align:center;font-size:1.25rem">{{ $lang->flagEmoji }}</td>
            <td>
              <code style="font-weight:600">{{ $lang->code }}</code>
            </td>
            <td>
              <div>
                <span style="font-weight:500">{{ $lang->native }}</span>
                <span class="text-xs text-muted" style="margin-left:.5rem">{{ $lang->label }}</span>
              </div>
            </td>
            <td style="text-align:center">
              <span class="badge {{ $lang->isRtl ? 'badge--warning' : 'badge--muted' }}">
                {{ strtoupper($lang->direction) }}
              </span>
            </td>
            <td style="text-align:center">
              <span class="badge {{ $lang->statusBadge }}">{{ $lang->statusLabel }}</span>
            </td>
            <td style="text-align:right">
              <div style="display:flex;gap:.25rem;justify-content:flex-end">
                @if($lang->enabled && !$lang->is_default)
                  <button class="btn btn--ghost btn--xs" onclick="setDefault('{{ $lang->code }}')" title="Set as default">
                    <i data-lucide="star" class="w-3.5 h-3.5"></i>
                  </button>
                  <button class="btn btn--ghost btn--xs text-danger" onclick="disableLang('{{ $lang->code }}')" title="Disable">
                    <i data-lucide="x" class="w-3.5 h-3.5"></i>
                  </button>
                @elseif(!$lang->enabled)
                  <button class="btn btn--primary btn--xs" onclick="enableLang('{{ $lang->code }}')">
                    <i data-lucide="plus" class="w-3.5 h-3.5"></i> Enable
                  </button>
                @else
                  <span class="text-xs text-muted">Default</span>
                @endif
              </div>
            </td>
          </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  </div>

  {{-- ── Translation Coverage ─────────────────────────────────────────── --}}
  @if(!empty($coverage))
  <div class="card" style="margin-top:1.5rem">
    <div class="card__header">
      <h3 class="card__title"><i data-lucide="bar-chart-3" class="w-4 h-4"></i> Translation Coverage</h3>
    </div>
    <div class="card__body">
      <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:1.5rem">
        @foreach($coverage as $code => $data)
        <div style="padding:1rem;border:1px solid var(--border);border-radius:var(--radius-md,8px)">
          <div style="display:flex;align-items:center;gap:.5rem;margin-bottom:1rem">
            <span style="font-size:1.25rem">{{ $data['flag'] }}</span>
            <strong>{{ $data['label'] }}</strong>
            <code class="text-xs text-muted">{{ $code }}</code>
          </div>

          {{-- Content coverage --}}
          <div style="margin-bottom:.75rem">
            <div style="display:flex;justify-content:space-between;font-size:.8rem;margin-bottom:.25rem">
              <span>Content</span>
              <span>{{ $data['nodes']['translated'] }}/{{ $data['nodes']['total'] }} ({{ $data['nodes']['percent'] }}%)</span>
            </div>
            <div style="height:6px;background:var(--surface-alt,#e2e8f0);border-radius:3px;overflow:hidden">
              <div style="height:100%;width:{{ $data['nodes']['percent'] }}%;background:var(--primary);border-radius:3px;transition:width .3s"></div>
            </div>
          </div>

          {{-- Terms coverage --}}
          <div>
            <div style="display:flex;justify-content:space-between;font-size:.8rem;margin-bottom:.25rem">
              <span>Taxonomy</span>
              <span>{{ $data['terms']['translated'] }}/{{ $data['terms']['total'] }} ({{ $data['terms']['percent'] }}%)</span>
            </div>
            <div style="height:6px;background:var(--surface-alt,#e2e8f0);border-radius:3px;overflow:hidden">
              <div style="height:100%;width:{{ $data['terms']['percent'] }}%;background:var(--success,#22c55e);border-radius:3px;transition:width .3s"></div>
            </div>
          </div>
        </div>
        @endforeach
      </div>
    </div>
  </div>
  @endif

  </div>{{-- /module-content --}}

</div>
@endsection

@push('scripts')
<script>
// ── Module toggle ──────────────────────────────────────────────────────
async function toggleModule(enabled) {
  const resp = await CMS.fetch('/admin/languages/toggle-module', {
    method: 'POST',
    body: JSON.stringify({ enabled }),
  });
  const data = await resp.json();
  if (data.success) {
    document.getElementById('module-content').style.opacity = enabled ? '' : '.5';
    document.getElementById('module-content').style.pointerEvents = enabled ? '' : 'none';
    document.getElementById('module-disabled-banner').style.display = enabled ? 'none' : '';
    document.querySelector('#module-toggle + .form-toggle__label').textContent =
      enabled ? 'Module Active' : 'Module Disabled';
    CMS.toast?.('Multilingual module ' + (enabled ? 'enabled' : 'disabled'), 'success');
  }
}

// ── Language actions ───────────────────────────────────────────────────
async function enableLang(code) {
  await CMS.fetch(`/admin/languages/${code}/enable`, { method: 'POST' });
  location.reload();
}

async function disableLang(code) {
  if (!confirm('Disable this language?')) return;
  const resp = await CMS.fetch(`/admin/languages/${code}/disable`, { method: 'POST' });
  const data = await resp.json();
  if (!data.success) {
    alert(data.error || 'Cannot disable this language');
    return;
  }
  location.reload();
}

async function setDefault(code) {
  if (!confirm(`Set "${code}" as the default language?`)) return;
  await CMS.fetch(`/admin/languages/${code}/default`, { method: 'POST' });
  location.reload();
}

// ── Filter ─────────────────────────────────────────────────────────────
function showAll() {
  document.querySelectorAll('.lang-row').forEach(r => r.style.display = '');
  document.getElementById('btn-show-all').classList.add('btn--active');
  document.getElementById('btn-show-enabled').classList.remove('btn--active');
}

function showEnabled() {
  document.querySelectorAll('.lang-row').forEach(r => {
    r.style.display = r.dataset.enabled === '1' ? '' : 'none';
  });
  document.getElementById('btn-show-enabled').classList.add('btn--active');
  document.getElementById('btn-show-all').classList.remove('btn--active');
}
</script>
@endpush
