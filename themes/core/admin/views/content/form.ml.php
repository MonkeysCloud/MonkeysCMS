@extends('layouts.admin')

@section('title', isset($node) ? 'Edit: ' . $node->title : 'Create Content')
@section('page_title', isset($node) ? 'Edit Content' : 'Create Content')

@section('breadcrumb')
<a href="/admin" class="breadcrumb__item">Dashboard</a>
<span class="breadcrumb__sep">›</span>
<a href="/admin/content" class="breadcrumb__item">Content</a>
<span class="breadcrumb__sep">›</span>
<span class="breadcrumb__item breadcrumb__item--active">{{ isset($node) ? 'Edit' : 'Create' }}</span>
@endsection

@section('toolbar_actions')
<button type="submit" form="content-form" class="btn btn--primary btn--sm" data-save-btn>
  💾 {{ isset($node) ? 'Update' : 'Create' }}
</button>
@endsection

@section('content')
<form id="content-form" method="POST" action="{{ isset($node) ? '/admin/content/' . $node->id : '/admin/content' }}" class="content-form">

  <div class="grid" style="grid-template-columns: 1fr 320px;">

    {{-- Main Column --}}
    <div>
      {{-- Title --}}
      <div class="card mb-4">
        <div class="card__body">
          <div class="form-group">
            <label class="form-label">Title</label>
            <input type="text" name="title" class="form-input form-input--lg"
                   value="{{ $node->title ?? '' }}" placeholder="Enter title..." required
                   $m-model="title" $m-on:input="generateSlug()">
          </div>
          <div class="form-group">
            <label class="form-label">Slug</label>
            <div class="flex gap-2">
              <input type="text" name="slug" class="form-input" value="{{ $node->slug ?? '' }}" $m-model="slug">
              <button type="button" class="btn btn--sm btn--ghost" $m-on:click="generateSlug()">🔄</button>
            </div>
          </div>
        </div>
      </div>

      {{-- Body --}}
      <div class="card mb-4">
        <div class="card__header">
          <h3 class="card__title">Body</h3>
        </div>
        <div class="card__body">
          <textarea name="body" class="form-textarea" rows="15" placeholder="Write your content here...">{{ $node->body ?? '' }}</textarea>
        </div>
      </div>

      {{-- Dynamic Fields --}}
      @isset($fields)
      <div class="card mb-4">
        <div class="card__header">
          <h3 class="card__title">Fields</h3>
        </div>
        <div class="card__body">
          @foreach($fields as $field)
          <div class="form-group">
            <label class="form-label">{{ $field->label }}</label>
            @if($field->widget === 'textarea')
            <textarea name="fields[{{ $field->machine_name }}]" class="form-textarea" rows="4">{{ $fieldValues[$field->machine_name] ?? '' }}</textarea>
            @elseif($field->widget === 'select')
            <select name="fields[{{ $field->machine_name }}]" class="form-select">
              @foreach($field->options ?? [] as $opt)
              <option value="{{ $opt }}" {{ ($fieldValues[$field->machine_name] ?? '') === $opt ? 'selected' : '' }}>{{ $opt }}</option>
              @endforeach
            </select>
            @elseif($field->widget === 'boolean')
            <label class="form-check">
              <input type="checkbox" name="fields[{{ $field->machine_name }}]" value="1" {{ !empty($fieldValues[$field->machine_name]) ? 'checked' : '' }}>
              <span>{{ $field->description ?? '' }}</span>
            </label>
            @else
            <input type="text" name="fields[{{ $field->machine_name }}]" class="form-input" value="{{ $fieldValues[$field->machine_name] ?? '' }}">
            @endif
          </div>
          @endforeach
        </div>
      </div>
      @endisset

      {{-- Mosaic Link --}}
      @isset($node)
      <div class="card mb-4">
        <div class="card__header flex-between">
          <h3 class="card__title">Mosaic Page Builder</h3>
          <a href="/admin/mosaic/{{ $node->id }}" class="btn btn--sm btn--primary">Open Editor →</a>
        </div>
        <div class="card__body">
          <p class="text-muted text-sm">Use the Mosaic visual builder to create rich page layouts with blocks.</p>
        </div>
      </div>
      @endisset
    </div>

    {{-- Sidebar Column --}}
    <div>
      {{-- Publishing --}}
      <div class="card mb-4">
        <div class="card__header">
          <h3 class="card__title">Publishing</h3>
        </div>
        <div class="card__body">
          <div class="form-group">
            <label class="form-label">Status</label>
            <select name="status" class="form-select">
              <option value="draft" {{ ($node->status ?? 'draft') === 'draft' ? 'selected' : '' }}>Draft</option>
              <option value="published" {{ ($node->status ?? '') === 'published' ? 'selected' : '' }}>Published</option>
              <option value="archived" {{ ($node->status ?? '') === 'archived' ? 'selected' : '' }}>Archived</option>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label">Content Type</label>
            <select name="content_type" class="form-select">
              @foreach($contentTypes ?? [] as $ct)
              <option value="{{ $ct->machine_name ?? $ct }}" {{ ($node->content_type ?? $contentType ?? '') === ($ct->machine_name ?? $ct) ? 'selected' : '' }}>
                {{ $ct->label ?? ucfirst($ct) }}
              </option>
              @endforeach
            </select>
          </div>
        </div>
      </div>

      {{-- Featured Image --}}
      <div class="card mb-4">
        <div class="card__header">
          <h3 class="card__title">Featured Image</h3>
        </div>
        <div class="card__body">
          <div class="media-picker" $m-on:click="openMediaBrowser()">
            <div class="media-picker__placeholder" $m-show="!featuredImage">
              <span>🖼️</span>
              <span class="text-sm text-muted">Click to select image</span>
            </div>
            <img $m-show="featuredImage" :src="featuredImage" class="media-picker__preview">
            <input type="hidden" name="featured_image" :value="featuredImageId">
          </div>
        </div>
      </div>

      {{-- Taxonomy --}}
      @isset($vocabularies)
      <div class="card mb-4">
        <div class="card__header">
          <h3 class="card__title">Taxonomy</h3>
        </div>
        <div class="card__body">
          @foreach($vocabularies as $vocab)
          <div class="form-group">
            <label class="form-label">{{ $vocab->label }}</label>
            <select name="terms[{{ $vocab->machine_name }}][]" class="form-select" multiple>
              @foreach($vocab->terms ?? [] as $term)
              <option value="{{ $term->id }}">{{ $term->name }}</option>
              @endforeach
            </select>
          </div>
          @endforeach
        </div>
      </div>
      @endisset

      {{-- SEO --}}
      <div class="card mb-4">
        <div class="card__header">
          <h3 class="card__title">SEO</h3>
        </div>
        <div class="card__body">
          <div class="form-group">
            <label class="form-label">Meta Title</label>
            <input type="text" name="meta_title" class="form-input" value="{{ $node->meta_title ?? '' }}" maxlength="60">
          </div>
          <div class="form-group">
            <label class="form-label">Meta Description</label>
            <textarea name="meta_description" class="form-textarea" rows="3" maxlength="160">{{ $node->meta_description ?? '' }}</textarea>
          </div>
        </div>
      </div>
    </div>
  </div>
</form>
@endsection
