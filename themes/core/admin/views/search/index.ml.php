@extends('layouts.admin')

@section('title', 'Search')
@section('page_title', 'Search Engine')

@section('breadcrumb')
<a href="/admin" class="breadcrumb__item">Dashboard</a>
<span class="breadcrumb__sep">›</span>
<span class="breadcrumb__item breadcrumb__item--active">Search</span>
@endsection

@section('toolbar_actions')
<div class="toolbar-actions" style="display:flex;gap:.5rem">
  <a href="/admin/search/test" class="btn btn--ghost btn--sm">
    <i data-lucide="search" class="w-4 h-4"></i> Test Query
  </a>
  <a href="/admin/search/sources" class="btn btn--ghost btn--sm">
    <i data-lucide="database" class="w-4 h-4"></i> Sources
  </a>
  <a href="/admin/search/settings" class="btn btn--ghost btn--sm">
    <i data-lucide="settings" class="w-4 h-4"></i> Settings
  </a>
  <form method="POST" action="/admin/search/reindex" style="display:inline"
    data-confirm="Rebuild the entire search index? This may take a moment."
    data-confirm-title="Rebuild Index"
    data-confirm-label="Rebuild"
    data-confirm-class="btn btn--primary">
    <button type="submit" class="btn btn--primary btn--sm">
      <i data-lucide="refresh-cw" class="w-4 h-4"></i> Rebuild Index
    </button>
  </form>
</div>
@endsection

@section('content')
<div class="search-admin" style="padding:0 1.5rem 2rem">

  {{-- Flash message --}}
  @if(!empty($flash ?? ''))
  <div class="alert alert--success" style="margin-bottom:1.5rem">{{ $flash }}</div>
  @endif

  {{-- Stats Overview --}}
  <div class="se-stats">
    <div class="se-stat-card">
      <div class="se-stat-card__icon" style="background:rgba(129,140,248,.12)">
        <i data-lucide="file-text" style="color:#818cf8;width:20px;height:20px"></i>
      </div>
      <div class="se-stat-card__body">
        <span class="se-stat-card__value">{{ $searchStats['total_documents'] ?? 0 }}</span>
        <span class="se-stat-card__label">Total Documents</span>
      </div>
    </div>
    <div class="se-stat-card">
      <div class="se-stat-card__icon" style="background:rgba(52,211,153,.12)">
        <i data-lucide="check-circle" style="color:#34d399;width:20px;height:20px"></i>
      </div>
      <div class="se-stat-card__body">
        <span class="se-stat-card__value">{{ $searchStats['published'] ?? 0 }}</span>
        <span class="se-stat-card__label">Published</span>
      </div>
    </div>
    <div class="se-stat-card">
      <div class="se-stat-card__icon" style="background:rgba(251,191,36,.12)">
        <i data-lucide="cpu" style="color:#fbbf24;width:20px;height:20px"></i>
      </div>
      <div class="se-stat-card__body">
        <span class="se-stat-card__value">{{ count($availableEngines) }}</span>
        <span class="se-stat-card__label">Engines</span>
      </div>
    </div>
    <div class="se-stat-card">
      <div class="se-stat-card__icon" style="background:rgba(52,211,153,.12)">
        <i data-lucide="zap" style="color:#34d399;width:20px;height:20px"></i>
      </div>
      <div class="se-stat-card__body">
        <span class="se-stat-card__value se-stat-card__value--accent">{{ $activeEngine }}</span>
        <span class="se-stat-card__label">Active Engine</span>
      </div>
    </div>
  </div>

  {{-- Main Grid: Engine + Distribution --}}
  <div class="se-main-grid">

    {{-- Engine Status --}}
    <div class="se-section">
      <div class="se-section__header">
        <h2 class="se-section__title">
          <i data-lucide="server" class="w-4 h-4"></i>
          Search Engines
        </h2>
      </div>
      <div class="se-engines">
        @foreach($engineStatuses as $name => $status)
        <div class="se-engine {{ ($status['active'] ?? false) ? 'se-engine--active' : '' }}">
          <div class="se-engine__top">
            <div class="se-engine__name-row">
              <span class="se-engine__indicator {{ ($status['available'] ?? false) ? 'se-engine__indicator--online' : 'se-engine__indicator--offline' }}"></span>
              <h3 class="se-engine__name">{{ $status['engine'] ?? $name }}</h3>
            </div>
            @if($status['active'] ?? false)
            <span class="se-engine__badge">ACTIVE</span>
            @endif
          </div>

          <div class="se-engine__details">
            @if(isset($status['total_documents']))
            <div class="se-engine__detail">
              <span class="se-engine__detail-label">Documents</span>
              <span class="se-engine__detail-value">{{ number_format($status['total_documents']) }}</span>
            </div>
            @endif
            @if(isset($status['driver']))
            <div class="se-engine__detail">
              <span class="se-engine__detail-label">Driver</span>
              <span class="se-engine__detail-value">
                <code class="se-engine__code">{{ $status['driver'] }}</code>
              </span>
            </div>
            @endif
            @if(isset($status['server_version']))
            <div class="se-engine__detail">
              <span class="se-engine__detail-label">Version</span>
              <span class="se-engine__detail-value">{{ $status['server_version'] }}</span>
            </div>
            @endif
            @if(isset($status['fulltext_support']))
            <div class="se-engine__detail">
              <span class="se-engine__detail-label">Full-Text</span>
              <span class="se-engine__detail-value" style="color:{{ $status['fulltext_support'] ? '#34d399' : '#fbbf24' }}">
                {{ $status['fulltext_support'] ? '✓ Enabled' : '⚠ Unavailable' }}
              </span>
            </div>
            @endif
            @if(isset($status['enabled_sources']))
            <div class="se-engine__detail">
              <span class="se-engine__detail-label">Active Sources</span>
              <span class="se-engine__detail-value">{{ $status['enabled_sources'] }} / {{ $status['total_sources'] }}</span>
            </div>
            @endif
            @if(isset($status['cluster_status']))
            <div class="se-engine__detail">
              <span class="se-engine__detail-label">Cluster</span>
              <span class="se-engine__detail-value">{{ $status['cluster_status'] }}</span>
            </div>
            @endif
            @if(isset($status['error']))
            <div class="se-engine__detail" style="color:#f87171">
              <span class="se-engine__detail-label">Error</span>
              <span class="se-engine__detail-value" style="font-size:.72rem">{{ $status['error'] }}</span>
            </div>
            @endif
          </div>

          {{-- Source breakdown --}}
          @if(!empty($status['sources']))
          <div class="se-engine__sources">
            <span class="se-engine__sources-label">Indexed Sources</span>
            @foreach($status['sources'] as $srcKey => $srcInfo)
            <div class="se-engine__source-row">
              <span class="se-engine__source-name">{{ $srcInfo['label'] }}</span>
              <span class="se-engine__source-meta">
                {{ implode(', ', $srcInfo['fields'] ?? []) }}
              </span>
              <span class="se-engine__source-count">{{ number_format($srcInfo['count']) }}</span>
            </div>
            @endforeach
          </div>
          @endif
        </div>
        @endforeach
      </div>
    </div>

    {{-- Content Distribution --}}
    @if(!empty($searchStats['by_type']))
    <div class="se-section">
      <div class="se-section__header">
        <h2 class="se-section__title">
          <i data-lucide="pie-chart" class="w-4 h-4"></i>
          Content Distribution
        </h2>
      </div>
      <div class="se-distribution">
        @php $maxCnt = max(array_values($searchStats['by_type'])); @endphp
        @foreach($searchStats['by_type'] as $typeName => $cnt)
        <div class="se-dist-row">
          <div class="se-dist-row__label">
            <span class="se-dist-row__dot"></span>
            <span class="se-dist-row__name">{{ ucfirst($typeName) }}</span>
          </div>
          <div class="se-dist-row__bar-wrap">
            <div class="se-dist-row__bar" style="width:{{ $maxCnt > 0 ? round($cnt / $maxCnt * 100) : 0 }}%"></div>
          </div>
          <span class="se-dist-row__count">{{ $cnt }}</span>
        </div>
        @endforeach
      </div>
    </div>
    @endif

  </div>

</div>
@endsection

@push('head')
<style>
/* ── Stats Grid ─────────────────────────────────────────────── */
.se-stats {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
  gap: 1rem;
  margin-bottom: 2rem;
}

.se-stat-card {
  display: flex;
  align-items: center;
  gap: 1rem;
  padding: 1.25rem 1.5rem;
  background: rgba(20, 22, 38, .5);
  border: 1px solid rgba(255,255,255,.06);
  border-radius: 16px;
  transition: border-color .2s, transform .15s;
}

.se-stat-card:hover {
  border-color: rgba(129,140,248,.15);
  transform: translateY(-1px);
}

.se-stat-card__icon {
  width: 44px;
  height: 44px;
  border-radius: 12px;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

.se-stat-card__body {
  display: flex;
  flex-direction: column;
}

.se-stat-card__value {
  font-size: 1.6rem;
  font-weight: 800;
  color: #e2e8f0;
  line-height: 1.1;
}

.se-stat-card__value--accent {
  background: linear-gradient(135deg, #34d399, #22d3ee);
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
  background-clip: text;
  font-size: 1.15rem;
}

.se-stat-card__label {
  font-size: .7rem;
  color: #64748b;
  text-transform: uppercase;
  letter-spacing: .04em;
  margin-top: .2rem;
}

/* ── Main Grid ──────────────────────────────────────────────── */
.se-main-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 1.5rem;
}

@media (max-width: 900px) {
  .se-main-grid { grid-template-columns: 1fr; }
}

/* ── Section ────────────────────────────────────────────────── */
.se-section {
  background: rgba(20, 22, 38, .5);
  border: 1px solid rgba(255,255,255,.06);
  border-radius: 16px;
  overflow: hidden;
}

.se-section__header {
  padding: 1rem 1.25rem;
  border-bottom: 1px solid rgba(255,255,255,.04);
  background: rgba(255,255,255,.015);
}

.se-section__title {
  display: flex;
  align-items: center;
  gap: .5rem;
  font-size: .9rem;
  font-weight: 700;
  color: #e2e8f0;
  margin: 0;
}

/* ── Engine Card ────────────────────────────────────────────── */
.se-engines { padding: .5rem 0; }

.se-engine {
  padding: 1rem 1.25rem;
  border-bottom: 1px solid rgba(255,255,255,.03);
}

.se-engine:last-child { border-bottom: none; }

.se-engine__top {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: .75rem;
}

.se-engine__name-row {
  display: flex;
  align-items: center;
  gap: .5rem;
}

.se-engine__indicator {
  width: 8px;
  height: 8px;
  border-radius: 50%;
  flex-shrink: 0;
}

.se-engine__indicator--online {
  background: #34d399;
  box-shadow: 0 0 8px rgba(52,211,153,.4);
}

.se-engine__indicator--offline {
  background: #f87171;
  box-shadow: 0 0 8px rgba(248,113,113,.3);
}

.se-engine__name {
  font-size: .9rem;
  font-weight: 700;
  color: #e2e8f0;
  margin: 0;
}

.se-engine__badge {
  padding: .15rem .5rem;
  border-radius: 6px;
  background: linear-gradient(135deg, rgba(52,211,153,.15), rgba(129,140,248,.15));
  border: 1px solid rgba(52,211,153,.25);
  color: #34d399;
  font-size: .58rem;
  font-weight: 700;
  letter-spacing: .06em;
}

/* ── Engine Details ──────────────────────────────────────────── */
.se-engine__details {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: .1rem .75rem;
}

.se-engine__detail {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: .35rem .5rem;
  border-radius: 6px;
  transition: background .1s;
}

.se-engine__detail:hover {
  background: rgba(255,255,255,.02);
}

.se-engine__detail-label {
  font-size: .85rem;
  color: #64748b;
}

.se-engine__detail-value {
  font-size: .88rem;
  color: #cbd5e1;
  font-weight: 500;
}

.se-engine__code {
  padding: .15rem .4rem;
  border-radius: 4px;
  background: rgba(129,140,248,.08);
  color: #a5b4fc;
  font-family: 'JetBrains Mono', monospace;
  font-size: .8rem;
}

/* ── Engine Sources ──────────────────────────────────────────── */
.se-engine__sources {
  margin-top: .75rem;
  padding-top: .75rem;
  border-top: 1px solid rgba(255,255,255,.04);
}

.se-engine__sources-label {
  display: block;
  font-size: .72rem;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: .04em;
  color: #475569;
  margin-bottom: .5rem;
}

.se-engine__source-row {
  display: flex;
  align-items: center;
  gap: .5rem;
  padding: .35rem 0;
  font-size: .85rem;
}

.se-engine__source-name {
  color: #94a3b8;
  font-weight: 600;
  min-width: 100px;
}

.se-engine__source-meta {
  flex: 1;
  color: #64748b;
  font-family: 'JetBrains Mono', monospace;
  font-size: .78rem;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.se-engine__source-count {
  color: #818cf8;
  font-weight: 700;
  font-size: .88rem;
  min-width: 30px;
  text-align: right;
}

/* ── Distribution ───────────────────────────────────────────── */
.se-distribution { padding: .75rem 1.25rem 1rem; }

.se-dist-row {
  display: flex;
  align-items: center;
  gap: .75rem;
  padding: .6rem 0;
  border-bottom: 1px solid rgba(255,255,255,.025);
}

.se-dist-row:last-child { border-bottom: none; }

.se-dist-row__label {
  display: flex;
  align-items: center;
  gap: .5rem;
  min-width: 120px;
}

.se-dist-row__dot {
  width: 8px;
  height: 8px;
  border-radius: 2px;
  background: #818cf8;
  flex-shrink: 0;
}

.se-dist-row__name {
  font-size: .82rem;
  font-weight: 500;
  color: #cbd5e1;
}

.se-dist-row__bar-wrap {
  flex: 1;
  height: 8px;
  background: rgba(255,255,255,.04);
  border-radius: 4px;
  overflow: hidden;
}

.se-dist-row__bar {
  height: 100%;
  background: linear-gradient(90deg, #818cf8, #6366f1);
  border-radius: 4px;
  transition: width .4s ease;
}

.se-dist-row__count {
  color: #94a3b8;
  font-size: .8rem;
  font-weight: 600;
  min-width: 40px;
  text-align: right;
}
</style>
@endpush
