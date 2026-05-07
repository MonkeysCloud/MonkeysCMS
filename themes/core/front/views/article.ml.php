@extends('layouts.front')

@section('title', ($node->meta_title ?? $node->title ?? 'Article') . ' | ' . ($site_name ?? 'MonkeysCMS'))
@section('meta_description', $node->meta_description ?? $node->summary ?? '')

@section('content')

{{-- ═══ Hero Header ═══ --}}
<header class="article-hero">
  <div class="container">
    <div class="article-hero__inner">
      {{-- Category / Content type badge --}}
      <div class="article-hero__badges">
        <span class="article-hero__type">{{ ucfirst($node->content_type ?? 'article') }}</span>
        @if(!($node->isPublished ?? true))
          <span class="article-hero__draft">Draft</span>
        @endif
      </div>

      <h1 class="article-hero__title">{{ $node->title }}</h1>

      @if($node->summary)
      <p class="article-hero__summary">{{ $node->summary }}</p>
      @endif

      <div class="article-hero__meta">
        @if($node->published_at)
        <div class="article-hero__meta-item">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="18" height="18" x="3" y="4" rx="2" ry="2"/><line x1="16" x2="16" y1="2" y2="6"/><line x1="8" x2="8" y1="2" y2="6"/><line x1="3" x2="21" y1="10" y2="10"/></svg>
          <time datetime="{{ $node->published_at->format('c') }}">{{ $node->published_at->format('F j, Y') }}</time>
        </div>
        @endif

        @isset($author)
        <div class="article-hero__meta-item">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
          {{ $author->name }}
        </div>
        @endisset

        {{-- Estimated reading time --}}
        @php
          $wordCount = str_word_count(strip_tags($node->body ?? ''));
          $readTime = max(1, (int) ceil($wordCount / 230));
        @endphp
        <div class="article-hero__meta-item">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
          {{ $readTime }} min read
        </div>
      </div>
    </div>
  </div>
</header>

{{-- ═══ Featured Image ═══ --}}
@if($node->featured_image_id)
<div class="container">
  <div class="article-featured-img">
    <img src="/uploads/{{ $node->featured_image_id }}" alt="{{ $node->title }}" loading="lazy">
  </div>
</div>
@endif

{{-- ═══ Content Body ═══ --}}
@if($node->mosaic_mode && !empty($mosaic_html))
  {{-- Mosaic mode: full-width layout --}}
  {!! $mosaic_html !!}
@else
  {{-- Standard body: optimized reading width --}}
  <div class="container">
    <div class="article-content">
      <div class="article-content__body prose">
        {!! $node->body ?? '' !!}
      </div>

      {{-- EAV fields --}}
      @if(!empty($node->fields))
      <div class="article-content__fields">
        @foreach($node->fields as $fieldName => $fieldValue)
          @if($fieldValue && is_string($fieldValue))
          <div class="field field--{{ $fieldName }}">
            {!! $fieldValue !!}
          </div>
          @endif
        @endforeach
      </div>
      @endif
    </div>
  </div>
@endif

{{-- ═══ Tags / Taxonomy ═══ --}}
@if(!empty($terms))
<div class="container">
  <div class="article-content">
    <footer class="article-footer">
      <div class="article-tags">
        <span class="article-tags__label">Tags:</span>
        @foreach($terms as $term)
        <a href="/tag/{{ $term->slug }}" class="article-tag">{{ $term->name }}</a>
        @endforeach
      </div>
    </footer>
  </div>
</div>
@endif

{{-- ═══ Comments ═══ --}}
@include('partials.comments')

@endsection
