@extends('layouts.admin')

@section('title', 'AI Assistant')
@section('page_title', 'AI Assistant')

@section('breadcrumb')
<a href="/admin" class="breadcrumb__item">Dashboard</a>
<span class="breadcrumb__sep">›</span>
<span class="breadcrumb__item breadcrumb__item--active">AI Assistant</span>
@endsection

@section('content')
<div class="apex-settings">

  @if(isset($_GET['saved']))
  <div class="alert alert--success mb-4">
    <i data-lucide="check-circle" class="w-4 h-4"></i>
    AI settings saved successfully.
  </div>
  @endif

  <form action="/admin/ai/settings" method="POST" id="apex-settings-form">

    {{-- ═══ Master Toggle ═══ --}}
    @php $isEnabled = $config->enabled; @endphp
    <div class="apex-master-toggle {{ $isEnabled ? 'apex-master-toggle--on' : '' }}">
      <div class="apex-master-toggle__content">
        <div class="apex-master-toggle__icon">
          <i data-lucide="{{ $isEnabled ? 'brain' : 'brain-cog' }}" class="w-8 h-8"></i>
        </div>
        <div class="apex-master-toggle__info">
          <h2 class="apex-master-toggle__title">AI Assistant</h2>
          <p class="apex-master-toggle__desc" id="master-toggle-desc">
            {{ $isEnabled ? 'AI-powered tools are active across the CMS.' : 'Enable to unlock AI-powered content creation, SEO, and more.' }}
          </p>
        </div>
      </div>
      <label class="apex-master-toggle__switch" for="apex-enabled">
        <input type="checkbox" name="enabled" id="apex-enabled" value="1"
               {{ $isEnabled ? 'checked' : '' }}
               onchange="toggleMaster(this.checked)">
        <span class="toggle-switch toggle-switch--lg"></span>
      </label>
    </div>

    {{-- ═══ Tab Navigation ═══ --}}
    <div class="apex-tabs" id="apex-tabs-nav">
      <button type="button" class="apex-tab apex-tab--active" data-tab="provider" onclick="switchTab('provider', this)">
        <i data-lucide="cpu" class="w-4 h-4"></i> Provider
      </button>
      <button type="button" class="apex-tab" data-tab="features" onclick="switchTab('features', this)">
        <i data-lucide="toggle-right" class="w-4 h-4"></i> Features
      </button>
      <button type="button" class="apex-tab" data-tab="images" onclick="switchTab('images', this)">
        <i data-lucide="image" class="w-4 h-4"></i> Images
      </button>
      <button type="button" class="apex-tab" data-tab="costs" onclick="switchTab('costs', this)">
        <i data-lucide="bar-chart-3" class="w-4 h-4"></i> Costs
      </button>
      <button type="button" class="apex-tab" data-tab="advanced" onclick="switchTab('advanced', this)">
        <i data-lucide="settings-2" class="w-4 h-4"></i> Advanced
      </button>
    </div>

    {{-- ═══ Tab 1: Provider Configuration ═══ --}}
    <div class="apex-panel" id="panel-provider">
      <div class="card">
        <div class="card__header card__header--between">
          <h3 class="card__title">
            <i data-lucide="cloud" class="w-4 h-4 card__title-icon"></i> Provider Configuration
          </h3>
          <button type="button" class="btn btn--sm btn--ghost" id="test-connection-btn" onclick="testConnection()">
            <i data-lucide="wifi" class="w-4 h-4"></i> Test Connection
          </button>
        </div>
        <div class="card__body">
          <div id="connection-result" class="mb-4" hidden></div>

          <div class="form-grid form-grid--2">
            {{-- Provider Selector --}}
            <div class="form-group">
              <label class="form-label" for="apex-provider">AI Provider</label>
              <select class="form-select" name="provider" id="apex-provider" onchange="onProviderChange(this.value)">
                @foreach($providers as $key => $label)
                @php $provSelected = ($config->provider === $key) ? 'selected' : ''; @endphp
                <option value="{{ $key }}" {{ $provSelected }}>{{ $label }}</option>
                @endforeach
              </select>
            </div>

            {{-- Model Selector --}}
            <div class="form-group">
              <label class="form-label" for="apex-model">Model</label>
              <select class="form-select" name="model" id="apex-model">
                {{-- Populated by JS based on provider --}}
              </select>
            </div>

            {{-- API Key --}}
            <div class="form-group" id="api-key-group">
              <label class="form-label" for="apex-api-key">API Key</label>
              <div class="input-group">
                <input type="password" class="form-input" name="api_key" id="apex-api-key"
                       value="{{ $config->apiKey }}" placeholder="Enter your API key">
                <button type="button" class="btn btn--sm btn--ghost" onclick="toggleApiKey()" title="Show/Hide">
                  <i data-lucide="eye" class="w-4 h-4"></i>
                </button>
              </div>
              @php $providerForHelp = $config->provider; @endphp
              <small class="form-hint" id="api-key-hint">
                @if($providerForHelp === 'ollama')
                No API key needed for local Ollama.
                @else
                Get your API key from the provider dashboard.
                @endif
              </small>
            </div>

            {{-- Base URL --}}
            <div class="form-group">
              <label class="form-label" for="apex-base-url">Base URL <span class="text-muted">(optional)</span></label>
              <input type="url" class="form-input" name="base_url" id="apex-base-url"
                     value="{{ $config->baseUrl }}" placeholder="Custom endpoint URL">
              <small class="form-hint">Only needed for custom endpoints, proxies, or Ollama.</small>
            </div>
          </div>

          <div class="form-grid form-grid--2 mt-4">
            {{-- Temperature --}}
            <div class="form-group">
              <label class="form-label" for="apex-temperature">
                Temperature: <span id="temp-value">{{ $config->temperature }}</span>
              </label>
              <input type="range" class="form-range" name="temperature" id="apex-temperature"
                     min="0" max="2" step="0.1" value="{{ $config->temperature }}"
                     oninput="document.getElementById('temp-value').textContent = this.value">
              <small class="form-hint">0 = deterministic, 1 = creative, 2 = very random</small>
            </div>

            {{-- Max Tokens --}}
            <div class="form-group">
              <label class="form-label" for="apex-max-tokens">Max Output Tokens</label>
              <input type="number" class="form-input" name="max_tokens" id="apex-max-tokens"
                     value="{{ $config->maxTokens }}" min="100" max="128000" step="1">
              <small class="form-hint">Maximum tokens in AI response (4096 = ~3000 words)</small>
            </div>
          </div>
        </div>
      </div>
    </div>

    {{-- ═══ Tab 2: Feature Toggles ═══ --}}
    <div class="apex-panel" id="panel-features" hidden>
      <div class="card">
        <div class="card__header">
          <h3 class="card__title">
            <i data-lucide="toggle-right" class="w-4 h-4 card__title-icon"></i> AI Features
          </h3>
        </div>
        <div class="card__body">
          <div class="apex-features">
            @foreach($features as $key => $description)
            @php
              $featureEnabled = $config->isFeatureEnabled($key) ? 'checked' : '';
              $featureId = str_replace('_', '-', $key);
            @endphp
            <label class="apex-feature" for="feature-{{ $featureId }}">
              <div class="apex-feature__toggle">
                <input type="checkbox" name="feature_{{ $key }}" id="feature-{{ $featureId }}" {{ $featureEnabled }}>
                <span class="toggle-switch"></span>
              </div>
              <div class="apex-feature__info">
                <span class="apex-feature__name">{{ $description }}</span>
              </div>
            </label>
            @endforeach
          </div>

          <div class="form-group mt-6">
            <label class="form-label" for="apex-system-prompt">Default System Prompt</label>
            <textarea class="form-textarea" name="system_prompt" id="apex-system-prompt" rows="4"
                      placeholder="Custom system prompt for all AI operations">{{ $config->systemPrompt }}</textarea>
            <small class="form-hint">Applied to all AI operations. Each feature also has its own specialized prompt.</small>
          </div>
        </div>
      </div>
    </div>
    {{-- ═══ Tab: Image Generation ═══ --}}
    <div class="apex-panel" id="panel-images" hidden>
      <div class="card">
        <div class="card__header">
          <h3 class="card__title">
            <i data-lucide="image" class="w-4 h-4 card__title-icon"></i> Image Generation
          </h3>
        </div>
        <div class="card__body">
          <div class="form-grid form-grid--2">
            <div class="form-group">
              <label class="form-label" for="apex-image-provider">Image Provider</label>
              <select class="form-select" name="image_provider" id="apex-image-provider" onchange="onImageProviderChange(this.value)">
                @foreach($imageProviders as $key => $label)
                @php $imgProvSelected = ($config->imageProvider === $key) ? 'selected' : ''; @endphp
                <option value="{{ $key }}" {{ $imgProvSelected }}>{{ $label }}</option>
                @endforeach
              </select>
              <small class="form-hint">Can be different from the text AI provider.</small>
            </div>
            <div class="form-group">
              <label class="form-label" for="apex-image-model">Image Model</label>
              <select class="form-select" name="image_model" id="apex-image-model">
                {{-- Populated by JS --}}
              </select>
            </div>
            <div class="form-group">
              <label class="form-label" for="apex-image-api-key">Image API Key</label>
              <div class="input-group">
                <input type="password" class="form-input" name="image_api_key" id="apex-image-api-key"
                       value="{{ $config->imageApiKey }}" placeholder="API key for image provider">
                <button type="button" class="btn btn--sm btn--ghost" onclick="toggleField('apex-image-api-key')" title="Show/Hide">
                  <i data-lucide="eye" class="w-4 h-4"></i>
                </button>
              </div>
              <small class="form-hint">Uses text provider key if same provider; otherwise enter separately.</small>
            </div>
            <div class="form-group">
              <label class="form-label" for="apex-image-size">Default Size</label>
              <select class="form-select" name="image_size" id="apex-image-size">
                @php $imgSize = $config->imageSettings['size'] ?? '1024x1024'; @endphp
                <option value="1024x1024" {{ $imgSize === '1024x1024' ? 'selected' : '' }}>1024×1024 (Square)</option>
                <option value="1024x1792" {{ $imgSize === '1024x1792' ? 'selected' : '' }}>1024×1792 (Portrait)</option>
                <option value="1792x1024" {{ $imgSize === '1792x1024' ? 'selected' : '' }}>1792×1024 (Landscape)</option>
                <option value="1024x1536" {{ $imgSize === '1024x1536' ? 'selected' : '' }}>1024×1536 (Portrait HD)</option>
                <option value="1536x1024" {{ $imgSize === '1536x1024' ? 'selected' : '' }}>1536×1024 (Landscape HD)</option>
              </select>
            </div>
            <div class="form-group">
              <label class="form-label" for="apex-image-quality">Quality</label>
              <select class="form-select" name="image_quality" id="apex-image-quality">
                @php $imgQuality = $config->imageSettings['quality'] ?? 'standard'; @endphp
                <option value="standard" {{ $imgQuality === 'standard' ? 'selected' : '' }}>Standard</option>
                <option value="hd" {{ $imgQuality === 'hd' ? 'selected' : '' }}>HD (Higher detail)</option>
              </select>
            </div>
            <div class="form-group">
              <label class="form-label" for="apex-image-style">Style</label>
              <select class="form-select" name="image_style" id="apex-image-style">
                @php $imgStyle = $config->imageSettings['style'] ?? 'natural'; @endphp
                <option value="natural" {{ $imgStyle === 'natural' ? 'selected' : '' }}>Natural</option>
                <option value="vivid" {{ $imgStyle === 'vivid' ? 'selected' : '' }}>Vivid</option>
              </select>
            </div>
          </div>
          <div class="alert alert--info mt-4">
            <i data-lucide="info" class="w-4 h-4"></i>
            Image generation costs are tracked separately and included in the total budget.
            DALL-E 3: ~$0.04-$0.08/image · GPT Image 1: ~$0.04-$0.06/image · Imagen 3: ~$0.04/image
          </div>
        </div>
      </div>
    </div>

    {{-- ═══ Tab: Costs ═══ --}}
    <div class="apex-panel" id="panel-costs" hidden>
      <div class="card">
        <div class="card__header">
          <h3 class="card__title">
            <i data-lucide="bar-chart-3" class="w-4 h-4 card__title-icon"></i> Usage & Costs
          </h3>
        </div>
        <div class="card__body">
          <div class="apex-cost-grid">
            <div class="apex-cost-card">
              <div class="apex-cost-card__value">
                @php $totalCost = number_format($usage['total_cost'] ?? 0, 4); @endphp
                ${{ $totalCost }}
              </div>
              <div class="apex-cost-card__label">Monthly Spend</div>
            </div>
            <div class="apex-cost-card">
              <div class="apex-cost-card__value">{{ $usage['total_requests'] ?? 0 }}</div>
              <div class="apex-cost-card__label">Total Requests</div>
            </div>
            <div class="apex-cost-card">
              @php $inputTokens = number_format($usage['total_input_tokens'] ?? 0); @endphp
              <div class="apex-cost-card__value">{{ $inputTokens }}</div>
              <div class="apex-cost-card__label">Input Tokens</div>
            </div>
            <div class="apex-cost-card">
              @php $outputTokens = number_format($usage['total_output_tokens'] ?? 0); @endphp
              <div class="apex-cost-card__value">{{ $outputTokens }}</div>
              <div class="apex-cost-card__label">Output Tokens</div>
            </div>
          </div>

          <div class="form-grid form-grid--2 mt-6">
            <div class="form-group">
              <label class="form-label" for="apex-budget">Monthly Budget Limit (USD)</label>
              <input type="number" class="form-input" name="budget_limit" id="apex-budget"
                     value="{{ $config->budgetLimit }}" min="0" max="10000" step="1">
            </div>
            <div class="form-group">
              <label class="form-label" for="apex-alert">Alert Threshold (%)</label>
              @php $alertPct = (int)($config->alertThreshold * 100); @endphp
              <input type="number" class="form-input" name="alert_threshold" id="apex-alert"
                     value="0.{{ $alertPct }}" min="0" max="1" step="0.05">
              <small class="form-hint">Alert when this percentage of budget is reached</small>
            </div>
          </div>

          @if(!empty($usage['by_operation']))
          <h4 class="mt-6 mb-3 text-sm text-muted">Usage by Operation</h4>
          <table class="data-table">
            <thead>
              <tr>
                <th>Operation</th>
                <th class="text-right">Requests</th>
                <th class="text-right">Cost</th>
              </tr>
            </thead>
            <tbody>
              @foreach($usage['by_operation'] as $op)
              @php $opCost = number_format((float)$op['cost'], 4); @endphp
              <tr>
                <td><span class="badge badge--sm">{{ $op['operation'] }}</span></td>
                <td class="text-right">{{ $op['count'] }}</td>
                <td class="text-right">${{ $opCost }}</td>
              </tr>
              @endforeach
            </tbody>
          </table>
          @endif
        </div>
      </div>
    </div>

    {{-- ═══ Tab 4: Advanced ═══ --}}
    <div class="apex-panel" id="panel-advanced" hidden>
      <div class="card">
        <div class="card__header">
          <h3 class="card__title">
            <i data-lucide="shield" class="w-4 h-4 card__title-icon"></i> Guardrails
          </h3>
        </div>
        <div class="card__body">
          <div class="apex-features">
            @php $guardPii = ($config->guardrails['pii_detection'] ?? false) ? 'checked' : ''; @endphp
            <label class="apex-feature" for="guard-pii">
              <div class="apex-feature__toggle">
                <input type="checkbox" name="guard_pii_detection" id="guard-pii" {{ $guardPii }}>
                <span class="toggle-switch"></span>
              </div>
              <div class="apex-feature__info">
                <span class="apex-feature__name">PII Detection — Redact personal information from AI output</span>
              </div>
            </label>

            @php $guardInjection = ($config->guardrails['prompt_injection'] ?? false) ? 'checked' : ''; @endphp
            <label class="apex-feature" for="guard-injection">
              <div class="apex-feature__toggle">
                <input type="checkbox" name="guard_prompt_injection" id="guard-injection" {{ $guardInjection }}>
                <span class="toggle-switch"></span>
              </div>
              <div class="apex-feature__info">
                <span class="apex-feature__name">Prompt Injection Protection — Block malicious prompt attempts</span>
              </div>
            </label>

            @php $guardToxicity = ($config->guardrails['toxicity_filter'] ?? false) ? 'checked' : ''; @endphp
            <label class="apex-feature" for="guard-toxicity">
              <div class="apex-feature__toggle">
                <input type="checkbox" name="guard_toxicity_filter" id="guard-toxicity" {{ $guardToxicity }}>
                <span class="toggle-switch"></span>
              </div>
              <div class="apex-feature__info">
                <span class="apex-feature__name">Toxicity Filter — Block toxic or harmful content generation</span>
              </div>
            </label>
          </div>

          <div class="form-group mt-4">
            <label class="form-label" for="guard-max-words">Maximum Output Words</label>
            <input type="number" class="form-input" name="guard_max_output_words" id="guard-max-words"
                   value="{{ $config->guardrails['max_output_words'] ?? 5000 }}" min="100" max="50000">
            <small class="form-hint">Auto-truncate AI output beyond this word count</small>
          </div>
        </div>
      </div>
    </div>

    {{-- ═══ Save Bar ═══ --}}
    <div class="apex-save-bar">
      <button type="submit" class="btn btn--primary btn--lg">
        <i data-lucide="save" class="w-4 h-4"></i> Save Settings
      </button>
    </div>
  </form>
</div>

@push('scripts')
<script>
// ── Data ──
@php
  $modelsJson = json_encode($models);
  $imageModelsJson = json_encode($imageModels);
  $currentModel = $config->model;
  $currentImageModel = $config->imageModel;
@endphp
const PROVIDER_MODELS = {!! $modelsJson !!};
const IMAGE_MODELS = {!! $imageModelsJson !!};
const CURRENT_MODEL = '{{ $currentModel }}';
const CURRENT_IMAGE_MODEL = '{{ $currentImageModel }}';

// ── Master toggle ──
function toggleMaster(enabled) {
  const toggle = document.querySelector('.apex-master-toggle');
  const tabs = document.getElementById('apex-tabs-nav');
  const panels = document.querySelectorAll('.apex-panel');
  const desc = document.getElementById('master-toggle-desc');

  toggle.classList.toggle('apex-master-toggle--on', enabled);
  tabs.style.opacity = enabled ? '1' : '0.4';
  tabs.style.pointerEvents = enabled ? 'auto' : 'none';
  panels.forEach(p => { if (!p.hidden) p.style.opacity = enabled ? '1' : '0.4'; });
  desc.textContent = enabled
    ? 'AI-powered tools are active across the CMS.'
    : 'Enable to unlock AI-powered content creation, SEO, and more.';
}

// ── Tab switching ──
function switchTab(tabName, btn) {
  document.querySelectorAll('.apex-panel').forEach(p => p.hidden = true);
  document.querySelectorAll('.apex-tab').forEach(t => t.classList.remove('apex-tab--active'));
  const panel = document.getElementById('panel-' + tabName);
  panel.hidden = false;
  panel.style.opacity = document.getElementById('apex-enabled').checked ? '1' : '0.4';
  btn.classList.add('apex-tab--active');
}

// ── Provider change → update model list ──
function onProviderChange(provider) {
  const select = document.getElementById('apex-model');
  const models = PROVIDER_MODELS[provider] || {};
  select.innerHTML = '';
  for (const [value, label] of Object.entries(models)) {
    const opt = document.createElement('option');
    opt.value = value;
    opt.textContent = label;
    if (value === CURRENT_MODEL) opt.selected = true;
    select.appendChild(opt);
  }
  const hint = document.getElementById('api-key-hint');
  hint.textContent = provider === 'ollama'
    ? 'No API key needed for local Ollama.'
    : 'Get your API key from the provider dashboard.';
}

// ── Image provider change → update image model list ──
function onImageProviderChange(provider) {
  const select = document.getElementById('apex-image-model');
  const models = IMAGE_MODELS[provider] || {};
  select.innerHTML = '';
  for (const [value, label] of Object.entries(models)) {
    const opt = document.createElement('option');
    opt.value = value;
    opt.textContent = label;
    if (value === CURRENT_IMAGE_MODEL) opt.selected = true;
    select.appendChild(opt);
  }
}

// ── Toggle any password field visibility ──
function toggleApiKey() { toggleField('apex-api-key'); }
function toggleField(id) {
  const el = document.getElementById(id);
  el.type = el.type === 'password' ? 'text' : 'password';
}

// ── Test connection ──
async function testConnection() {
  const btn = document.getElementById('test-connection-btn');
  const result = document.getElementById('connection-result');
  btn.disabled = true;
  btn.innerHTML = '<i data-lucide="loader" class="w-4 h-4 spin"></i> Testing...';
  result.hidden = false;
  result.className = 'alert alert--info mb-4';
  result.innerHTML = '<i data-lucide="loader" class="w-4 h-4 spin"></i> Testing connection...';

  try {
    const form = document.getElementById('apex-settings-form');
    const formData = new FormData(form);
    await fetch('/admin/ai/settings', { method: 'POST', body: formData });

    const res = await fetch('/api/cms/apex/test', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
    });
    const data = await res.json();
    if (data.success) {
      result.className = 'alert alert--success mb-4';
      result.innerHTML = '<i data-lucide="check-circle" class="w-4 h-4"></i> ' + data.message +
        ' <small class="text-muted">(' + data.latency_ms + 'ms)</small>';
    } else {
      result.className = 'alert alert--danger mb-4';
      result.innerHTML = '<i data-lucide="x-circle" class="w-4 h-4"></i> ' + data.message;
    }
  } catch (err) {
    result.className = 'alert alert--danger mb-4';
    result.innerHTML = '<i data-lucide="x-circle" class="w-4 h-4"></i> ' + err.message;
  }
  btn.disabled = false;
  btn.innerHTML = '<i data-lucide="wifi" class="w-4 h-4"></i> Test Connection';
  if (typeof lucide !== 'undefined') lucide.createIcons();
}

// ── Init ──
document.addEventListener('DOMContentLoaded', () => {
  onProviderChange(document.getElementById('apex-provider').value);
  onImageProviderChange(document.getElementById('apex-image-provider').value);
  toggleMaster(document.getElementById('apex-enabled').checked);
});
</script>
@endpush
@endsection
