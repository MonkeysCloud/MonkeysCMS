@extends('layouts.front')

@section('title', ($contentType['label_plural'] ?? 'Articles') . ' | ' . ($site_name ?? 'MonkeysCMS'))

@section('content')
<div class="container">
  <div style="padding:3rem 0 1rem;">
    <h1 style="font-size:2rem; font-weight:800; color:var(--front-heading);">{{ $contentType['label_plural'] ?? 'Articles' }}</h1>
    @if(!empty($contentType['description']))
    <p style="color:var(--front-text-secondary); margin-top:0.5rem;">{{ $contentType['description'] }}</p>
    @endif
  </div>

  @if(!empty($nodes))
  <div class="article-grid">
    @foreach($nodes as $node)
    <article class="article-card">
      @if($node->featured_image_id)
      <div class="article-card__image">
        <img src="/uploads/{{ $node->featured_image_id }}" alt="{{ $node->title }}" loading="lazy">
      </div>
      @endif
      <div class="article-card__body">
        <div class="article-card__meta">
          {{ $node->published_at?->format('M j, Y') ?? $node->created_at?->format('M j, Y') }}
        </div>
        <h2 class="article-card__title">
          <a href="/{{ $node->content_type }}/{{ $node->slug }}">{{ $node->title }}</a>
        </h2>
        @if($node->summary)
        <p class="article-card__excerpt">{{ $node->summary }}</p>
        @endif
      </div>
    </article>
    @endforeach
  </div>

  {{-- Pagination --}}
  @if(($pagination['last_page'] ?? 1) > 1)
  <nav class="pagination">
    @if(($pagination['page'] ?? 1) > 1)
    <a href="?page={{ ($pagination['page'] ?? 1) - 1 }}" class="pagination__link">← Prev</a>
    @endif
    @for($i = 1; $i <= ($pagination['last_page'] ?? 1); $i++)
    <a href="?page={{ $i }}" class="pagination__link @if($i === ($pagination['page'] ?? 1)) active @endif">{{ $i }}</a>
    @endfor
    @if(($pagination['page'] ?? 1) < ($pagination['last_page'] ?? 1))
    <a href="?page={{ ($pagination['page'] ?? 1) + 1 }}" class="pagination__link">Next →</a>
    @endif
  </nav>
  @endif

  @else
  <div style="text-align:center; padding:4rem; color:var(--front-text-muted);">
    <p>No content published yet.</p>
  </div>
  @endif
</div>
@endsection
