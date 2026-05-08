@extends('layouts.front')

@section('title', ($query ? "Search: {$query}" : 'Search') . ' | ' . ($site_name ?? 'MonkeysCMS'))
@section('meta_description', "Search results for: {$query}")

@section('content')
<div class="container" style="padding: 3rem 0 4rem;">

  {{-- Search Form --}}
  <div class="search-header">
    <h1 class="search-header__title">Search</h1>
    <form method="GET" action="/search" class="search-form">
      <div class="search-form__inner">
        <input type="text"
               name="q"
               value="{{ $query ?? '' }}"
               placeholder="Search articles, pages, and more..."
               class="search-form__input"
               autofocus>
        <button type="submit" class="search-form__btn">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/>
          </svg>
        </button>
      </div>
    </form>
  </div>

  {{-- Results --}}
  @if(!empty($query))
  <div class="search-results">
    <p class="search-results__count">
      @if($total > 0)
        Found <strong>{{ $total }}</strong> result{{ $total !== 1 ? 's' : '' }} for "<em>{{ $query }}</em>"
      @else
        No results found for "<em>{{ $query }}</em>"
      @endif
    </p>

    @if(!empty($results))
    <div class="search-results__list">
      @foreach($results as $hit)
      <article class="search-result">
        <div class="search-result__type">
          <span>{{ ucfirst($hit->type ?: 'page') }}</span>
        </div>
        <h2 class="search-result__title">
          <a href="{{ $hit->url }}">{!! $hit->highlight('title', 100) ?: htmlspecialchars($hit->title) !!}</a>
        </h2>
        <p class="search-result__excerpt">{!! $hit->excerpt(250) !!}</p>
        <div class="search-result__meta">
          @if($hit->publishedAt)
          <time datetime="{{ $hit->publishedAt->format('c') }}">{{ $hit->publishedAt->format('M j, Y') }}</time>
          @endif
          @if($hit->author)
          <span>{{ $hit->author }}</span>
          @endif
          <a href="{{ $hit->url }}" class="search-result__link">Read more →</a>
        </div>
      </article>
      @endforeach
    </div>
    @endif

    {{-- Pagination --}}
    @if($totalPages > 1)
    <nav class="search-pagination">
      @for($p = 1; $p <= $totalPages; $p++)
      <a href="/search?q={{ urlencode($query) }}&page={{ $p }}"
         class="search-pagination__item {{ $p === $page ? 'search-pagination__item--active' : '' }}">{{ $p }}</a>
      @endfor
    </nav>
    @endif

    {{-- Empty State --}}
    @if($total === 0)
    <div class="search-empty">
      <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" style="opacity:.3;margin-bottom:1rem">
        <circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/>
      </svg>
      <p>Try different keywords or check your spelling.</p>
    </div>
    @endif
  </div>
  @endif
</div>
@endsection

@push('head')
<style>
.search-header { text-align: center; margin-bottom: 2.5rem; }
.search-header__title { font-size: 2rem; font-weight: 800; color: var(--front-heading); margin-bottom: 1.25rem; }

.search-form__inner {
  display: flex; max-width: 600px; margin: 0 auto;
  border: 2px solid var(--front-border); border-radius: 12px;
  overflow: hidden; transition: border-color .2s;
}
.search-form__inner:focus-within { border-color: var(--front-accent); }
.search-form__input {
  flex: 1; padding: .85rem 1.25rem; border: none; background: transparent;
  font-size: 1rem; color: var(--front-text); outline: none;
}
.search-form__input::placeholder { color: var(--front-muted); }
.search-form__btn {
  padding: .85rem 1.25rem; background: var(--front-accent); border: none;
  color: white; cursor: pointer; transition: background .2s;
}
.search-form__btn:hover { filter: brightness(1.1); }

.search-results__count { color: var(--front-muted); margin-bottom: 1.5rem; font-size: .9rem; }
.search-results__count strong { color: var(--front-heading); }

.search-result {
  padding: 1.5rem 0; border-bottom: 1px solid var(--front-border);
}
.search-result:first-child { border-top: 1px solid var(--front-border); }
.search-result__type span {
  display: inline-block; font-size: .7rem; text-transform: uppercase; letter-spacing: .06em;
  color: var(--front-accent); font-weight: 600; margin-bottom: .35rem;
}
.search-result__title { font-size: 1.2rem; font-weight: 700; margin: 0 0 .5rem; }
.search-result__title a { color: var(--front-heading); text-decoration: none; }
.search-result__title a:hover { color: var(--front-accent); }
.search-result__excerpt { color: var(--front-text); font-size: .9rem; line-height: 1.6; margin: 0 0 .5rem; }
.search-result__excerpt mark { background: rgba(99,102,241,.2); color: var(--front-heading); border-radius: 2px; padding: 0 2px; }
.search-result__meta { display: flex; align-items: center; gap: 1rem; font-size: .8rem; color: var(--front-muted); }
.search-result__link { color: var(--front-accent); text-decoration: none; font-weight: 500; }
.search-result__link:hover { text-decoration: underline; }

.search-pagination { display: flex; justify-content: center; gap: .35rem; margin-top: 2rem; }
.search-pagination__item {
  display: inline-flex; align-items: center; justify-content: center;
  width: 36px; height: 36px; border-radius: 8px; font-size: .85rem; font-weight: 500;
  color: var(--front-text); text-decoration: none; border: 1px solid var(--front-border);
  transition: all .2s;
}
.search-pagination__item:hover { border-color: var(--front-accent); color: var(--front-accent); }
.search-pagination__item--active { background: var(--front-accent); color: white; border-color: var(--front-accent); }

.search-empty { text-align: center; padding: 3rem 0; color: var(--front-muted); }
</style>
@endpush
