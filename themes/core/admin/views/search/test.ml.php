@extends('layouts.admin')

@section('title', 'Search Test')
@section('page_title', 'Search Test')

@section('breadcrumb')
<a href="/admin" class="breadcrumb__item">Dashboard</a>
<span class="breadcrumb__sep">›</span>
<a href="/admin/search" class="breadcrumb__item">Search</a>
<span class="breadcrumb__sep">›</span>
<span class="breadcrumb__item breadcrumb__item--active">Test</span>
@endsection

@section('content')
<div class="search-test">

  {{-- Search Form --}}
  <form method="GET" action="/admin/search/test" class="search-test-form">
    <div style="display:flex;gap:.5rem;margin-bottom:1rem">
      <input type="text" name="q" value="{{ $queryText }}" placeholder="Enter search query..."
        class="form-input" style="flex:1;font-size:1rem" autofocus>
      <button type="submit" class="btn btn--primary btn--sm">
        <i data-lucide="search" class="w-4 h-4"></i> Search
      </button>
    </div>

    <div style="display:flex;gap:1rem;flex-wrap:wrap;margin-bottom:1.5rem">
      <div>
        <label style="font-size:.72rem;color:#64748b;display:block;margin-bottom:.25rem">Engine</label>
        <select name="engine" class="form-select" style="min-width:160px">
          <option value="">Active ({{ $selectedEngine ?? 'auto' }})</option>
          @foreach($engines as $eName => $eLabel)
          <option value="{{ $eName }}" {{ ($selectedEngine ?? '') === $eName ? 'selected' : '' }}>{{ $eLabel }}</option>
          @endforeach
        </select>
      </div>
      <div>
        <label style="font-size:.72rem;color:#64748b;display:block;margin-bottom:.25rem">Content Type</label>
        <select name="type" class="form-select" style="min-width:140px">
          <option value="">All types</option>
          <option value="article" {{ ($selectedType ?? '') === 'article' ? 'selected' : '' }}>Article</option>
          <option value="page" {{ ($selectedType ?? '') === 'page' ? 'selected' : '' }}>Page</option>
          <option value="news" {{ ($selectedType ?? '') === 'news' ? 'selected' : '' }}>News</option>
          <option value="event" {{ ($selectedType ?? '') === 'event' ? 'selected' : '' }}>Event</option>
        </select>
      </div>
      <div>
        <label style="font-size:.72rem;color:#64748b;display:block;margin-bottom:.25rem">Status</label>
        <select name="status" class="form-select" style="min-width:120px">
          <option value="published" {{ !($showAll ?? false) ? 'selected' : '' }}>Published</option>
          <option value="all" {{ ($showAll ?? false) ? 'selected' : '' }}>All</option>
        </select>
      </div>
    </div>
  </form>

  {{-- Results --}}
  @if($result !== null)
  <div class="search-test-meta" style="display:flex;gap:1.5rem;flex-wrap:wrap;margin-bottom:1.5rem;padding:.75rem 1rem;background:rgba(20,22,38,.6);border:1px solid rgba(255,255,255,.06);border-radius:12px">
    <div>
      <span style="color:#64748b;font-size:.72rem;text-transform:uppercase;letter-spacing:.04em">Total</span>
      <div style="color:#e2e8f0;font-weight:700">{{ $result->total }}</div>
    </div>
    <div>
      <span style="color:#64748b;font-size:.72rem;text-transform:uppercase;letter-spacing:.04em">Took</span>
      <div style="color:#e2e8f0;font-weight:700">{{ $result->took }}ms</div>
    </div>
    <div>
      <span style="color:#64748b;font-size:.72rem;text-transform:uppercase;letter-spacing:.04em">Engine</span>
      <div style="color:#818cf8;font-weight:700">{{ $result->engine }}</div>
    </div>
    <div>
      <span style="color:#64748b;font-size:.72rem;text-transform:uppercase;letter-spacing:.04em">Page</span>
      <div style="color:#e2e8f0;font-weight:700">{{ $result->currentPage() }} / {{ $result->totalPages() }}</div>
    </div>
  </div>

  {{-- Facets --}}
  @if($result->hasFacets())
  <div class="search-facets" style="display:flex;gap:1.5rem;margin-bottom:1.5rem;flex-wrap:wrap">
    @foreach($result->facets as $facetName => $facetValues)
    <div style="background:rgba(20,22,38,.4);border:1px solid rgba(255,255,255,.04);border-radius:10px;padding:.75rem 1rem;min-width:180px">
      <h4 style="font-size:.72rem;color:#64748b;text-transform:uppercase;letter-spacing:.04em;margin:0 0 .5rem">{{ ucfirst(str_replace('_', ' ', $facetName)) }}</h4>
      @foreach($facetValues as $fVal => $fCount)
      <div style="display:flex;justify-content:space-between;font-size:.8rem;padding:.15rem 0">
        <span style="color:#cbd5e1">{{ $fVal }}</span>
        <span style="color:#64748b">{{ $fCount }}</span>
      </div>
      @endforeach
    </div>
    @endforeach
  </div>
  @endif

  {{-- Hit list --}}
  <div class="search-hits">
    @foreach($result->hits as $hit)
    <div class="search-hit" style="padding:1rem 1.25rem;background:rgba(20,22,38,.5);border:1px solid rgba(255,255,255,.04);border-radius:12px;margin-bottom:.75rem">
      <div style="display:flex;justify-content:space-between;align-items:start;gap:1rem">
        <div style="flex:1">
          <div style="display:flex;align-items:center;gap:.5rem;margin-bottom:.35rem">
            <span class="badge badge--ghost" style="font-size:.6rem">{{ strtoupper($hit->type) }}</span>
            <span style="font-size:.7rem;color:#818cf8;font-family:monospace">score: {{ number_format($hit->score, 4) }}</span>
            <span style="font-size:.7rem;color:#475569">ID: {{ $hit->id }}</span>
          </div>
          <h3 style="font-size:1rem;font-weight:600;color:#e2e8f0;margin:0 0 .25rem">
            <a href="{{ $hit->url }}" target="_blank" style="color:#e2e8f0;text-decoration:none">
              {!! $hit->highlight('title', 100) ?: htmlspecialchars($hit->title) !!}
            </a>
          </h3>
          <p style="color:#94a3b8;font-size:.8rem;margin:0;line-height:1.5">{!! $hit->excerpt(300) !!}</p>
          <div style="margin-top:.5rem;font-size:.72rem;color:#475569;display:flex;gap:1rem">
            @if($hit->publishedAt)
            <span>📅 {{ $hit->publishedAt->format('M j, Y') }}</span>
            @endif
            @if($hit->author)
            <span>👤 {{ $hit->author }}</span>
            @endif
            <span>🔗 {{ $hit->url }}</span>
          </div>
        </div>
        <a href="/admin/content/{{ $hit->id }}/edit" class="btn btn--ghost btn--sm" style="white-space:nowrap">
          <i data-lucide="edit-3" class="w-3.5 h-3.5"></i> Edit
        </a>
      </div>
    </div>
    @endforeach

    @if($result->isEmpty())
    <div style="text-align:center;padding:3rem 0;color:#64748b">
      <i data-lucide="search-x" class="w-8 h-8" style="margin:0 auto .75rem;display:block;opacity:.4"></i>
      <p>No results found for "<strong style="color:#cbd5e1">{{ $queryText }}</strong>"</p>
    </div>
    @endif
  </div>

  {{-- Pagination --}}
  @if($result->totalPages() > 1)
  <div class="pagination mt-4">
    @for($p = 1; $p <= $result->totalPages(); $p++)
    <a href="/admin/search/test?q={{ urlencode($queryText) }}&page={{ $p }}&engine={{ $selectedEngine ?? '' }}&type={{ $selectedType ?? '' }}&status={{ ($showAll ?? false) ? 'all' : 'published' }}"
       class="pagination__item {{ $p === $result->currentPage() ? 'pagination__item--active' : '' }}">{{ $p }}</a>
    @endfor
  </div>
  @endif

  @endif
</div>
@endsection
