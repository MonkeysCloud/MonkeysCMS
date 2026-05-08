@extends('layouts.front')

@section('title', ($node->meta_title ?? $node->title ?? 'Page') . ' | ' . ($site_name ?? 'MonkeysCMS'))
@section('meta_description', $node->meta_description ?? $node->summary ?? '')

@section('content')
{{-- Mosaic mode: full-width layout --}}
@if($node->mosaic_mode && !empty($mosaic_html))
<div class="container">
  <article class="article-single content-wrapper">
    <header class="article-single__header">
      <h1 class="article-single__title">{{ $node->title ?? 'Untitled' }}</h1>
      @if(!empty($node->summary))
      <p class="article-single__meta">{{ $node->summary }}</p>
      @endif
    </header>
  </article>
</div>

{!! $mosaic_html !!}

{{-- Body mode: contained width --}}
@else
<div class="container">
  <article class="article-single content-wrapper">
    <header class="article-single__header">
      <h1 class="article-single__title">{{ $node->title ?? 'Untitled' }}</h1>
      @if(!empty($node->summary))
      <p class="article-single__meta">{{ $node->summary }}</p>
      @endif
    </header>

    <div class="article-single__body">
      {!! $node->body ?? '' !!}
    </div>

    {{-- EAV fields --}}
    @if(!empty($node->fields))
    <div class="article-single__fields">
      @foreach($node->fields as $fieldName => $fieldValue)
        @if($fieldValue)
        <div class="field field--{{ $fieldName }}">
          {!! is_string($fieldValue) ? $fieldValue : '' !!}
        </div>
        @endif
      @endforeach
    </div>
    @endif
  </article>
</div>
@endif
@endsection
