@extends('layouts.admin')

@section('title', $isNew ? 'Create Content Type' : 'Edit: ' . $contentType->label)
@section('page_title', $isNew ? 'Create Content Type' : 'Edit Content Type')

@section('breadcrumb')
<a href="/admin" class="breadcrumb__item">Dashboard</a>
<span class="breadcrumb__sep">›</span>
<a href="/admin/content-types" class="breadcrumb__item">Content Types</a>
<span class="breadcrumb__sep">›</span>
<span class="breadcrumb__item breadcrumb__item--active">{{ $isNew ? 'Create' : 'Edit' }}</span>
@endsection

@section('toolbar_actions')
<button type="submit" form="ct-form" class="btn btn--primary btn--sm">
  <i data-lucide="save" class="w-4 h-4"></i>
  {{ $isNew ? 'Create' : 'Update' }}
</button>
@endsection

@section('content')
<form id="ct-form" method="POST"
      action="{{ $isNew ? '/admin/content-types' : '/admin/content-types/' . $contentType->type_id }}"
      class="content-form">


  <div class="grid" style="grid-template-columns: 1fr 340px; gap: 1.5rem;">

    {{-- Main Column --}}
    <div>
      {{-- Basic Info --}}
      <div class="card mb-4">
        <div class="card__header">
          <h3 class="card__title">Basic Information</h3>
        </div>
        <div class="card__body">
          <div class="form-group">
            <label class="form-label">Machine Name <span class="text-danger">*</span></label>
            <input type="text" name="type_id" class="form-input"
                   value="{{ $contentType->type_id ?? '' }}"
                   placeholder="e.g. article, product, event"
                   pattern="[a-z][a-z0-9_]*" maxlength="64" required
                   {{ !$isNew ? 'readonly' : '' }}>
            <p class="form-help">Lowercase letters, numbers, underscores. Cannot be changed after creation.</p>
          </div>

          <div class="grid" style="grid-template-columns: 1fr 1fr; gap: 1rem;">
            <div class="form-group">
              <label class="form-label">Label <span class="text-danger">*</span></label>
              <input type="text" name="label" class="form-input"
                     value="{{ $contentType->label ?? '' }}" placeholder="Article" required>
            </div>
            <div class="form-group">
              <label class="form-label">Label (plural)</label>
              <input type="text" name="label_plural" class="form-input"
                     value="{{ $contentType->label_plural ?? '' }}" placeholder="Articles">
            </div>
          </div>

          <div class="form-group">
            <label class="form-label">Description</label>
            <textarea name="description" class="form-textarea" rows="2"
                      placeholder="What is this content type used for?">{{ $contentType->description ?? '' }}</textarea>
          </div>

          <div class="form-group">
            <label class="form-label">Icon (Lucide name)</label>
            <div class="icon-input">
              <span class="icon-input__preview"><i data-lucide="{{ $contentType->icon ?? 'file-text' }}" class="w-5 h-5" id="icon-preview"></i></span>
              <input type="text" name="icon" class="form-input"
                     value="{{ $contentType->icon ?? 'file-text' }}" placeholder="file-text"
                     id="icon-name-input">
            </div>
            <p class="form-help">Use any <a href="https://lucide.dev/icons" target="_blank" class="text-indigo-400">Lucide icon name</a></p>
          </div>

          <div class="form-group">
            <label class="form-label">URL Pattern</label>
            <input type="text" name="url_pattern" class="form-input"
                   value="{{ $contentType->url_pattern ?? '' }}" placeholder="/{slug}">
            <p class="form-help">Use <code>{slug}</code> as placeholder. Leave empty for default.</p>
          </div>
        </div>
      </div>
    </div>

    {{-- Sidebar --}}
    <div>
      {{-- Features --}}
      <div class="card mb-4">
        <div class="card__header">
          <h3 class="card__title">Features</h3>
        </div>
        <div class="card__body">
          <label class="form-toggle">
            <input type="hidden" name="publishable" value="0">
            <input type="checkbox" name="publishable" value="1" {{ ($contentType->publishable ?? true) ? 'checked' : '' }}>
            <span class="form-toggle__slider"></span>
            <span class="form-toggle__label">Publishable</span>
          </label>

          <label class="form-toggle">
            <input type="hidden" name="revisionable" value="0">
            <input type="checkbox" name="revisionable" value="1" {{ ($contentType->revisionable ?? false) ? 'checked' : '' }}>
            <span class="form-toggle__slider"></span>
            <span class="form-toggle__label">Revisions</span>
          </label>

          <label class="form-toggle">
            <input type="hidden" name="translatable" value="0">
            <input type="checkbox" name="translatable" value="1" {{ ($contentType->translatable ?? false) ? 'checked' : '' }}>
            <span class="form-toggle__slider"></span>
            <span class="form-toggle__label">Translatable</span>
          </label>

          <label class="form-toggle">
            <input type="hidden" name="has_author" value="0">
            <input type="checkbox" name="has_author" value="1" {{ ($contentType->has_author ?? true) ? 'checked' : '' }}>
            <span class="form-toggle__slider"></span>
            <span class="form-toggle__label">Track Author</span>
          </label>

          <label class="form-toggle">
            <input type="hidden" name="has_taxonomy" value="0">
            <input type="checkbox" name="has_taxonomy" value="1" {{ ($contentType->has_taxonomy ?? true) ? 'checked' : '' }}>
            <span class="form-toggle__slider"></span>
            <span class="form-toggle__label">Taxonomy</span>
          </label>

          <label class="form-toggle">
            <input type="hidden" name="has_media" value="0">
            <input type="checkbox" name="has_media" value="1" {{ ($contentType->has_media ?? true) ? 'checked' : '' }}>
            <span class="form-toggle__slider"></span>
            <span class="form-toggle__label">Media</span>
          </label>
        </div>
      </div>

      {{-- Mosaic --}}
      <div class="card mb-4">
        <div class="card__header">
          <h3 class="card__title">Mosaic Builder</h3>
        </div>
        <div class="card__body">
          <label class="form-toggle">
            <input type="hidden" name="mosaic_enabled" value="0">
            <input type="checkbox" name="mosaic_enabled" value="1" {{ ($contentType->mosaic_enabled ?? false) ? 'checked' : '' }}>
            <span class="form-toggle__slider"></span>
            <span class="form-toggle__label">Enable Mosaic</span>
          </label>

          <label class="form-toggle">
            <input type="hidden" name="mosaic_default" value="0">
            <input type="checkbox" name="mosaic_default" value="1" {{ ($contentType->mosaic_default ?? false) ? 'checked' : '' }}>
            <span class="form-toggle__slider"></span>
            <span class="form-toggle__label">Default to Mosaic editing</span>
          </label>
        </div>
      </div>

      {{-- Comments --}}
      <div class="card mb-4">
        <div class="card__header">
          <h3 class="card__title"><i data-lucide="message-circle" class="w-4 h-4" style="color:#818cf8;vertical-align:-2px;margin-right:.35rem"></i>Comments</h3>
        </div>
        <div class="card__body">
          <label class="form-toggle">
            <input type="hidden" name="comments_enabled" value="0">
            <input type="checkbox" name="comments_enabled" value="1" {{ ($contentType->comments_enabled ?? false) ? 'checked' : '' }}>
            <span class="form-toggle__slider"></span>
            <span class="form-toggle__label">Enable Comments</span>
          </label>
          <p class="form-help" style="margin:0;font-size:.75rem;color:#64748b;line-height:1.4;">
            Allow visitors to post comments on content of this type. The global comments setting in <a href="/admin/settings" style="color:#818cf8;">Settings</a> must also be enabled.
          </p>
        </div>
      </div>
    </div>
  </div>
</form>

@push('head')
<style>
.icon-input {
  display: flex; align-items: center; gap: 0.75rem;
}
.icon-input__preview {
  width: 44px; height: 44px;
  display: flex; align-items: center; justify-content: center;
  background: rgba(99,102,241,0.1); border-radius: 10px; color: #818cf8;
  flex-shrink: 0;
}
.icon-input .form-input { flex: 1; }
.form-toggle {
  display: flex; align-items: center; gap: 0.75rem;
  cursor: pointer; margin-bottom: 0.75rem;
  user-select: none;
}
.form-toggle__slider {
  width: 40px; height: 22px;
  background: rgba(255,255,255,0.1); border-radius: 11px;
  position: relative; transition: background 0.2s; flex-shrink: 0;
}
.form-toggle__slider::after {
  content: ''; position: absolute;
  width: 18px; height: 18px; border-radius: 50%;
  background: #64748b; top: 2px; left: 2px; transition: all 0.2s;
}
.form-toggle input:checked + .form-toggle__slider {
  background: rgba(99,102,241,0.4);
}
.form-toggle input:checked + .form-toggle__slider::after {
  background: #818cf8; transform: translateX(18px);
}
.form-toggle input { display: none; }
.form-toggle input[type="hidden"] + input { display: none; }
.form-toggle__label {
  font-size: 0.85rem; color: #cbd5e1;
}
</style>
@endpush
@endsection
