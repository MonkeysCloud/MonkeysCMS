@extends('layouts.front')

@section('title', $site_name ?? 'MonkeysCMS')

@section('hero')
<section class="hero">
  <div class="container">
    <h1 class="hero__title">{{ $site_name ?? 'MonkeysCMS' }}</h1>
    <p class="hero__subtitle">{{ $site_tagline ?? 'A modern content management system built on MonkeysLegion.' }}</p>
    <div class="hero__actions">
      <a href="/blog" class="btn-front btn-front--primary">Read the Blog →</a>
      <a href="/about" class="btn-front btn-front--secondary">Learn More</a>
    </div>
  </div>
</section>
@endsection

@section('content')
<div class="container">
  {{-- Latest Articles --}}
  @if(!empty($latest_articles))
  <div style="padding:3rem 0 1rem;">
    <h2 style="font-size:1.5rem; font-weight:700; color:var(--front-heading);">Latest Articles</h2>
  </div>
  <div class="article-grid">
    @foreach($latest_articles as $article)
    <article class="article-card">
      @if($article->featured_image_id)
      <div class="article-card__image">
        <img src="/uploads/{{ $article->featured_image_id }}" alt="{{ $article->title }}" loading="lazy">
      </div>
      @endif
      <div class="article-card__body">
        <div class="article-card__meta">
          {{ $article->published_at?->format('M j, Y') }}
        </div>
        <h3 class="article-card__title">
          <a href="/article/{{ $article->slug }}">{{ $article->title }}</a>
        </h3>
        @if($article->summary)
        <p class="article-card__excerpt">{{ $article->summary }}</p>
        @endif
      </div>
    </article>
    @endforeach
  </div>
  @endif
</div>
@endsection
