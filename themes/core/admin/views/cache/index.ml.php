@extends('layouts.admin')

@section('title', 'Cache Management')
@section('page_title', 'Cache Management')

@section('breadcrumb')
<a href="/admin" class="breadcrumb__item">Dashboard</a>
<span class="breadcrumb__sep">/</span>
<span class="breadcrumb__item breadcrumb__item--active">Cache</span>
@endsection

@section('content')
<div id="cache-app">

  {{-- ═══ Flash messages ═══ --}}
  @php
    $saved   = ($_GET['saved'] ?? '') === '1';
    $cleared = $_GET['cleared'] ?? '';
  @endphp

  @if($saved)
  <div class="mb-4 flex items-center gap-2 px-4 py-3 rounded-xl bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 text-sm">
    <i data-lucide="check-circle" class="w-4 h-4"></i>
    Cache settings saved successfully.
  </div>
  @endif

  @if($cleared)
  <div class="mb-4 flex items-center gap-2 px-4 py-3 rounded-xl bg-blue-500/10 border border-blue-500/20 text-blue-400 text-sm">
    <i data-lucide="trash-2" class="w-4 h-4"></i>
    Cache cleared: <strong>{{ $cleared }}</strong>
  </div>
  @endif

  {{-- ═══ Status Overview ═══ --}}
  <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4 mb-6">
    {{-- Cache Status --}}
    <div class="relative overflow-hidden rounded-2xl border border-white/5 bg-white/[0.03] backdrop-blur-xl p-5">
      <div class="relative z-10">
        <div class="flex items-center gap-2 mb-3">
          <i data-lucide="database" class="w-4 h-4 text-slate-500"></i>
          <span class="text-[0.65rem] uppercase tracking-widest font-semibold text-slate-400">Status</span>
        </div>
        <div class="text-2xl font-extrabold leading-none mb-1 {{ $settings['enabled'] === '1' ? 'text-emerald-400' : 'text-slate-500' }}">
          {{ $settings['enabled'] === '1' ? 'Enabled' : 'Disabled' }}
        </div>
        <div class="text-xs text-slate-500">Master cache toggle</div>
      </div>
    </div>

    {{-- Driver --}}
    <div class="relative overflow-hidden rounded-2xl border border-white/5 bg-white/[0.03] backdrop-blur-xl p-5">
      <div class="relative z-10">
        <div class="flex items-center gap-2 mb-3">
          <i data-lucide="hard-drive" class="w-4 h-4 text-slate-500"></i>
          <span class="text-[0.65rem] uppercase tracking-widest font-semibold text-slate-400">Driver</span>
        </div>
        <div class="text-2xl font-extrabold leading-none mb-1 text-indigo-400 capitalize">{{ $settings['driver'] }}</div>
        <div class="text-xs text-slate-500">Cache storage backend</div>
      </div>
    </div>

    {{-- Total Files --}}
    <div class="relative overflow-hidden rounded-2xl border border-white/5 bg-white/[0.03] backdrop-blur-xl p-5">
      <div class="relative z-10">
        <div class="flex items-center gap-2 mb-3">
          <i data-lucide="files" class="w-4 h-4 text-slate-500"></i>
          <span class="text-[0.65rem] uppercase tracking-widest font-semibold text-slate-400">Cached Files</span>
        </div>
        <div class="text-2xl font-extrabold leading-none mb-1 text-violet-400">{{ $stats['total_files'] }}</div>
        <div class="text-xs text-slate-500">Across all cache stores</div>
      </div>
    </div>

    {{-- Total Size --}}
    <div class="relative overflow-hidden rounded-2xl border border-white/5 bg-white/[0.03] backdrop-blur-xl p-5">
      <div class="relative z-10">
        <div class="flex items-center gap-2 mb-3">
          <i data-lucide="gauge" class="w-4 h-4 text-slate-500"></i>
          <span class="text-[0.65rem] uppercase tracking-widest font-semibold text-slate-400">Disk Usage</span>
        </div>
        @php
          $sizeKb = round($stats['total_size'] / 1024, 1);
          $sizeMb = round($stats['total_size'] / 1048576, 2);
          $sizeDisplay = $stats['total_size'] > 1048576 ? $sizeMb . ' MB' : $sizeKb . ' KB';
        @endphp
        <div class="text-2xl font-extrabold leading-none mb-1 text-blue-400">{{ $sizeDisplay }}</div>
        <div class="text-xs text-slate-500">Total cache footprint</div>
      </div>
    </div>
  </div>

  {{-- ═══ Main Grid: Settings + Actions ═══ --}}
  <div class="grid grid-cols-1 lg:grid-cols-[1.6fr_1fr] gap-4">

    {{-- ─── Settings Form ─────────────────────────────────────────────── --}}
    <form action="/admin/cache/settings" method="POST">

      {{-- Master Toggle & Driver --}}
      <div class="rounded-2xl border border-white/5 bg-white/[0.03] backdrop-blur-xl overflow-hidden mb-4">
        <div class="flex items-center justify-between px-5 py-4 border-b border-white/5">
          <h3 class="text-sm font-semibold text-slate-300 flex items-center gap-2">
            <i data-lucide="settings" class="w-4 h-4 text-slate-500"></i>
            General
          </h3>
        </div>
        <div class="p-5 space-y-5">

          {{-- Enable/Disable --}}
          <div class="flex items-center justify-between">
            <div>
              <div class="text-sm font-medium text-slate-200">Enable Cache</div>
              <div class="text-xs text-slate-500 mt-0.5">Master toggle for all caching features</div>
            </div>
            <label class="cache-toggle">
              <input type="checkbox" name="enabled" value="1" {{ $settings['enabled'] === '1' ? 'checked' : '' }}>
              <span class="cache-toggle__slider"></span>
            </label>
          </div>

          {{-- Driver --}}
          <div>
            <div class="text-sm font-medium text-slate-200 mb-2">Cache Driver</div>
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-2">
              @php $drivers = ['file' => 'file', 'database' => 'database', 'redis' => 'server', 'array' => 'cpu']; @endphp
              @foreach($drivers as $driver => $icon)
              <label class="cache-driver-option {{ $settings['driver'] === $driver ? 'cache-driver-option--active' : '' }}">
                <input type="radio" name="driver" value="{{ $driver }}" {{ $settings['driver'] === $driver ? 'checked' : '' }} class="hidden" onchange="this.closest('form').querySelectorAll('.cache-driver-option').forEach(el => el.classList.remove('cache-driver-option--active')); this.closest('.cache-driver-option').classList.add('cache-driver-option--active'); document.getElementById('redis-config').style.display = this.value === 'redis' ? 'block' : 'none';">
                <i data-lucide="{{ $icon }}" class="w-5 h-5 mb-1"></i>
                <span class="text-xs font-medium capitalize">{{ $driver }}</span>
              </label>
              @endforeach
            </div>
          </div>

          {{-- TTL --}}
          <div>
            <div class="text-sm font-medium text-slate-200 mb-1">Default TTL</div>
            <div class="text-xs text-slate-500 mb-2">Time-to-live for cached entries (seconds)</div>
            <div class="flex items-center gap-3">
              <input type="number" name="ttl" value="{{ $settings['ttl'] }}" min="60" max="86400" step="60"
                class="w-32 px-3 py-2 rounded-lg bg-white/5 border border-white/10 text-slate-200 text-sm focus:outline-none focus:border-indigo-500/50 transition-colors">
              <span class="text-xs text-slate-500">
                @php
                  $ttlVal = (int)$settings['ttl'];
                  $hours = floor($ttlVal / 3600);
                  $mins = floor(($ttlVal % 3600) / 60);
                  $ttlLabel = $hours > 0 ? $hours . 'h' : '';
                  $ttlLabel .= $mins > 0 ? ' ' . $mins . 'm' : '';
                @endphp
                = {{ trim($ttlLabel) ?: '0m' }}
              </span>
            </div>
          </div>

        </div>
      </div>

      {{-- Cache Types --}}
      <div class="rounded-2xl border border-white/5 bg-white/[0.03] backdrop-blur-xl overflow-hidden mb-4">
        <div class="flex items-center justify-between px-5 py-4 border-b border-white/5">
          <h3 class="text-sm font-semibold text-slate-300 flex items-center gap-2">
            <i data-lucide="layers" class="w-4 h-4 text-slate-500"></i>
            Cache Layers
          </h3>
        </div>
        <div class="divide-y divide-white/5">

          @php
            $layers = [
              ['key' => 'page_cache',  'label' => 'Page Cache',  'desc' => 'Cache full HTML responses for anonymous visitors', 'icon' => 'globe'],
              ['key' => 'query_cache', 'label' => 'Query Cache', 'desc' => 'Cache frequent database query results', 'icon' => 'database'],
              ['key' => 'view_cache',  'label' => 'View Cache',  'desc' => 'Cache compiled template files (.ml.php)', 'icon' => 'layout-template'],
              ['key' => 'asset_cache', 'label' => 'Asset Cache', 'desc' => 'Cache CSS/JS build fingerprinting', 'icon' => 'package'],
            ];
          @endphp

          @foreach($layers as $layer)
          <div class="flex items-center justify-between px-5 py-4">
            <div class="flex items-center gap-3">
              <div class="w-9 h-9 rounded-lg flex items-center justify-center bg-white/5 text-slate-400">
                <i data-lucide="{{ $layer['icon'] }}" class="w-4 h-4"></i>
              </div>
              <div>
                <div class="text-sm font-medium text-slate-200">{{ $layer['label'] }}</div>
                <div class="text-xs text-slate-500">{{ $layer['desc'] }}</div>
              </div>
            </div>
            <label class="cache-toggle">
              <input type="checkbox" name="{{ $layer['key'] }}" value="1" {{ $settings[$layer['key']] === '1' ? 'checked' : '' }}>
              <span class="cache-toggle__slider"></span>
            </label>
          </div>
          @endforeach

        </div>
      </div>

      {{-- Redis Config (conditional) --}}
      <div id="redis-config" class="rounded-2xl border border-white/5 bg-white/[0.03] backdrop-blur-xl overflow-hidden mb-4" style="display: {{ $settings['driver'] === 'redis' ? 'block' : 'none' }}">
        <div class="flex items-center justify-between px-5 py-4 border-b border-white/5">
          <h3 class="text-sm font-semibold text-slate-300 flex items-center gap-2">
            <i data-lucide="server" class="w-4 h-4 text-red-400"></i>
            Redis Configuration
          </h3>
        </div>
        <div class="p-5 space-y-4">
          <div class="grid grid-cols-2 gap-4">
            <div>
              <label class="block text-xs font-medium text-slate-400 mb-1">Host</label>
              <input type="text" name="redis_host" value="{{ $settings['redis_host'] }}"
                class="w-full px-3 py-2 rounded-lg bg-white/5 border border-white/10 text-slate-200 text-sm focus:outline-none focus:border-indigo-500/50 transition-colors">
            </div>
            <div>
              <label class="block text-xs font-medium text-slate-400 mb-1">Port</label>
              <input type="number" name="redis_port" value="{{ $settings['redis_port'] }}" min="1" max="65535"
                class="w-full px-3 py-2 rounded-lg bg-white/5 border border-white/10 text-slate-200 text-sm focus:outline-none focus:border-indigo-500/50 transition-colors">
            </div>
          </div>
          <div class="grid grid-cols-2 gap-4">
            <div>
              <label class="block text-xs font-medium text-slate-400 mb-1">Password</label>
              <input type="password" name="redis_password" value="{{ $settings['redis_password'] ?? '' }}" placeholder="Leave empty if none"
                class="w-full px-3 py-2 rounded-lg bg-white/5 border border-white/10 text-slate-200 text-sm focus:outline-none focus:border-indigo-500/50 transition-colors">
            </div>
            <div>
              <label class="block text-xs font-medium text-slate-400 mb-1">Database</label>
              <input type="number" name="redis_database" value="{{ $settings['redis_database'] ?? '0' }}" min="0" max="15"
                class="w-full px-3 py-2 rounded-lg bg-white/5 border border-white/10 text-slate-200 text-sm focus:outline-none focus:border-indigo-500/50 transition-colors">
            </div>
          </div>
        </div>
      </div>

      {{-- Save Button --}}
      <button type="submit" class="btn btn--primary inline-flex items-center gap-2">
        <i data-lucide="save" class="w-4 h-4"></i>
        Save Settings
      </button>

    </form>

    {{-- ─── Right Column: Clear Cache + Stats ─────────────────────────── --}}
    <div>

      {{-- Clear Cache --}}
      <div class="rounded-2xl border border-white/5 bg-white/[0.03] backdrop-blur-xl overflow-hidden mb-4">
        <div class="flex items-center justify-between px-5 py-4 border-b border-white/5">
          <h3 class="text-sm font-semibold text-slate-300 flex items-center gap-2">
            <i data-lucide="eraser" class="w-4 h-4 text-slate-500"></i>
            Clear Cache
          </h3>
        </div>
        <div class="p-4 space-y-2">

          @php
            $clearActions = [
              ['target' => 'views',  'label' => 'View Cache',   'desc' => 'Compiled templates',   'icon' => 'layout-template', 'color' => 'indigo'],
              ['target' => 'config', 'label' => 'Config Cache',  'desc' => 'Configuration files',  'icon' => 'settings',        'color' => 'violet'],
              ['target' => 'data',   'label' => 'Data Cache',    'desc' => 'Query & data caches',  'icon' => 'database',        'color' => 'emerald'],
              ['target' => 'all',    'label' => 'All Caches',    'desc' => 'Flush everything',      'icon' => 'flame',           'color' => 'red'],
            ];
          @endphp

          @foreach($clearActions as $action)
          <form action="/admin/cache/clear" method="POST" class="block">
            <input type="hidden" name="target" value="{{ $action['target'] }}">
            <button type="submit" class="w-full flex items-center gap-3 px-4 py-3 rounded-xl border border-white/5 bg-white/[0.02] hover:bg-{{ $action['color'] }}-500/[0.06] hover:border-{{ $action['color'] }}-500/20 transition-all duration-200 text-left group">
              <div class="w-9 h-9 rounded-lg flex items-center justify-center bg-{{ $action['color'] }}-500/10 text-{{ $action['color'] }}-400">
                <i data-lucide="{{ $action['icon'] }}" class="w-4 h-4"></i>
              </div>
              <div class="flex-1">
                <div class="text-sm font-medium text-slate-200 group-hover:text-{{ $action['color'] }}-300 transition-colors">{{ $action['label'] }}</div>
                <div class="text-xs text-slate-500">{{ $action['desc'] }}</div>
              </div>
              <i data-lucide="trash-2" class="w-4 h-4 text-slate-600 group-hover:text-{{ $action['color'] }}-400 transition-colors"></i>
            </button>
          </form>
          @endforeach

        </div>
      </div>

      {{-- Cache Stats Breakdown --}}
      <div class="rounded-2xl border border-white/5 bg-white/[0.03] backdrop-blur-xl overflow-hidden">
        <div class="flex items-center justify-between px-5 py-4 border-b border-white/5">
          <h3 class="text-sm font-semibold text-slate-300 flex items-center gap-2">
            <i data-lucide="bar-chart-3" class="w-4 h-4 text-slate-500"></i>
            Storage Breakdown
          </h3>
        </div>
        <div class="divide-y divide-white/5">
          @foreach($stats['dirs'] as $dirName => $info)
          <div class="flex items-center justify-between px-5 py-3">
            <div class="flex items-center gap-2">
              <div class="w-2 h-2 rounded-full {{ $info['exists'] && $info['files'] > 0 ? 'bg-emerald-400' : 'bg-slate-600' }}"></div>
              <span class="text-sm text-slate-300 capitalize">{{ $dirName }}</span>
            </div>
            <div class="text-right">
              <span class="text-sm font-medium text-slate-200">{{ $info['files'] }}</span>
              <span class="text-xs text-slate-500 ml-1">files</span>
              <span class="text-xs text-slate-600 ml-2">
                @php $kb = round($info['size'] / 1024, 1); @endphp
                ({{ $kb }} KB)
              </span>
            </div>
          </div>
          @endforeach
        </div>
      </div>

    </div>
  </div>
</div>

@push('head')
<style>
  /* Toggle switch */
  .cache-toggle { position: relative; display: inline-block; width: 44px; height: 24px; flex-shrink: 0; }
  .cache-toggle input { opacity: 0; width: 0; height: 0; }
  .cache-toggle__slider {
    position: absolute; cursor: pointer; inset: 0;
    background: rgba(255, 255, 255, 0.08); border-radius: 999px;
    transition: all 0.25s ease;
  }
  .cache-toggle__slider::before {
    content: ''; position: absolute; left: 2px; bottom: 2px;
    width: 20px; height: 20px; border-radius: 50%;
    background: #64748b; transition: all 0.25s ease;
  }
  .cache-toggle input:checked + .cache-toggle__slider { background: rgba(99, 102, 241, 0.25); }
  .cache-toggle input:checked + .cache-toggle__slider::before { transform: translateX(20px); background: #818cf8; }

  /* Driver option cards */
  .cache-driver-option {
    display: flex; flex-direction: column; align-items: center; justify-content: center;
    padding: 12px 8px; border-radius: 12px; cursor: pointer;
    border: 1px solid rgba(255, 255, 255, 0.06); background: rgba(255, 255, 255, 0.02);
    color: #94a3b8; transition: all 0.2s ease; gap: 2px;
  }
  .cache-driver-option:hover { border-color: rgba(99, 102, 241, 0.2); color: #cbd5e1; }
  .cache-driver-option--active {
    border-color: rgba(99, 102, 241, 0.4); background: rgba(99, 102, 241, 0.08);
    color: #818cf8; box-shadow: 0 0 20px rgba(99, 102, 241, 0.1);
  }
</style>
@endpush
@endsection
