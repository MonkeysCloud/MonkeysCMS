@extends('layouts.front')

@section('title', ($node->meta_title ?? $node->title ?? 'Page') . ' | ' . ($site_name ?? 'MonkeysCMS'))
@section('meta_description', $node->meta_description ?? $node->summary ?? '')

@section('content')
<div class="container">
  <article class="article-single content-wrapper">
    <header class="article-single__header">
      <h1 class="article-single__title">{{ $node->title ?? 'Untitled' }}</h1>
      @if(!empty($node->summary))
      <p class="article-single__meta">{{ $node->summary }}</p>
      @endif
    </header>

    {{-- Mosaic mode --}}
    @if($node->mosaic_mode && !empty($mosaic_html))
    </article>
    </div>
    {!! $mosaic_html !!}
    <div class="container"><article class="article-single content-wrapper">

    {{-- Body mode --}}
    @else
    <div class="article-single__body">
      {!! $node->body ?? '' !!}
    </div>
    @endif
  </article>
</div>
@endsection
