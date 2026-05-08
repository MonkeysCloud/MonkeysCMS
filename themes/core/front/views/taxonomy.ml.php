@extends('layouts.front')

@section('title', ($term->name ?? 'Term') . ' — ' . ($vocabulary->label ?? 'Taxonomy') . ' | ' . ($site_name ?? 'MonkeysCMS'))

@section('content')
<div class="container">
  <div style="padding:3rem 0 1rem;">
    <nav style="font-size:0.85rem; color:var(--front-text-muted); margin-bottom:0.75rem;">
      <a href="/" style="color:var(--front-text-muted); text-decoration:none;">Home</a>
      <span style="margin:0 0.35rem;">›</span>
      <span style="color:var(--front-text-secondary);">{{ $vocabulary->label ?? 'Taxonomy' }}</span>
    </nav>
    <h1 style="font-size:2rem; font-weight:800; color:var(--front-heading);">{{ $term->name ?? 'Term' }}</h1>
    @if(!empty($term->description))
    <p style="color:var(--front-text-secondary); margin-top:0.5rem;">{{ $term->description }}</p>
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
          <a href="/{{ $node->slug }}">{{ $node->title }}</a>
        </h2>
        @if($node->summary)
        <p class="article-card__excerpt">{{ $node->summary }}</p>
        @endif
      </div>
    </article>
    @endforeach
  </div>
  @else
  <div style="text-align:center; padding:4rem; color:var(--front-text-muted);">
    <p>No content tagged with "{{ $term->name }}" yet.</p>
  </div>
  @endif
</div>
@endsection
