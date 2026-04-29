@extends('layouts.front')

@section('title', ($node->meta_title ?? $node->title ?? 'Article') . ' | ' . ($site_name ?? 'MonkeysCMS'))
@section('meta_description', $node->meta_description ?? $node->summary ?? '')

@section('content')
<div class="container">
  <article class="article-single content-wrapper">
    <header class="article-single__header">
      <h1 class="article-single__title">{{ $node->title }}</h1>
      <div class="article-single__meta">
        @if($node->published_at)
        <time datetime="{{ $node->published_at->format('c') }}">{{ $node->published_at->format('F j, Y') }}</time>
        @endif
        @isset($author)
        <span> · {{ $author->name }}</span>
        @endisset
      </div>
    </header>

    @if($node->featured_image_id)
    <div style="margin-bottom:2rem;">
      <img src="/uploads/{{ $node->featured_image_id }}" alt="{{ $node->title }}"
           style="border-radius:var(--front-radius); width:100%;" loading="lazy">
    </div>
    @endif

    {{-- Mosaic mode --}}
    @if($node->mosaic_mode && !empty($mosaic_html))
    </article>
    </div>
    {!! $mosaic_html !!}
    <div class="container"><article class="article-single content-wrapper">
    @else
    <div class="article-single__body">
      {!! $node->body ?? '' !!}
    </div>
    @endif

    {{-- Taxonomy terms --}}
    @if(!empty($terms))
    <div style="margin-top:2rem; padding-top:1.5rem; border-top:1px solid var(--front-border);">
      <div style="display:flex; flex-wrap:wrap; gap:0.5rem;">
        @foreach($terms as $term)
        <a href="/tag/{{ $term->slug }}" style="display:inline-block; padding:0.25rem 0.75rem; background:var(--front-bg-alt); border:1px solid var(--front-border); border-radius:9999px; font-size:0.8rem; color:var(--front-text-secondary); text-decoration:none;">
          {{ $term->name }}
        </a>
        @endforeach
      </div>
    </div>
    @endif
  </article>
</div>
@endsection
