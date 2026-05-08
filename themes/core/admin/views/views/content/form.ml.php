@extends('layouts.admin')

@section('title', $isNew ? 'Create ' . ($contentType->label ?? 'Content') : 'Edit: ' . $node->title)
@section('page_title', $isNew ? 'Create ' . ($contentType->label ?? 'Content') : 'Edit Content')

@section('breadcrumb')
<a href="/admin" class="breadcrumb__item">Dashboard</a>
<span class="breadcrumb__sep">›</span>
<a href="/admin/content" class="breadcrumb__item">Content</a>
@if($contentType)
<span class="breadcrumb__sep">›</span>
<a href="/admin/content?type={{ $contentType->type_id }}" class="breadcrumb__item">{{ $contentType->label_plural ?: $contentType->label }}</a>
@endif
<span class="breadcrumb__sep">›</span>
<span class="breadcrumb__item breadcrumb__item--active">{{ $isNew ? 'Create' : ($node->title ?? 'Edit') }}</span>
@endsection

@section('toolbar_actions')
<div class="toolbar-actions">
  @if(!$isNew)
  <a href="{{ $node->viewUrl }}" target="_blank" class="btn btn--ghost btn--sm" title="View">
    <i data-lucide="external-link" class="w-4 h-4"></i>
  </a>
  @endif
  <button type="submit" form="content-form" class="btn btn--primary btn--sm">
    <i data-lucide="save" class="w-4 h-4"></i>
    {{ $isNew ? 'Create' : 'Update' }}
  </button>
</div>
@endsection

@section('content')
<form id="content-form" method="POST"
      action="{{ $isNew ? '/admin/content' : '/admin/content/' . $node->id }}"
      class="content-form">

  @if($isNew && $contentType)
  <input type="hidden" name="content_type" value="{{ $contentType->type_id }}">
  @endif

  <div class="grid" style="grid-template-columns: 1fr 320px; gap: 1.5rem;">

    {{-- Main Column --}}
    <div>
      {{-- Title + Slug --}}
      <div class="card mb-4">
        <div class="card__body">
          <div class="form-group">
            <label class="form-label" for="edit-title">Title <span class="text-danger">*</span></label>
            <input type="text" name="title" id="edit-title" class="form-input form-input--lg"
                   value="{{ $node->title ?? '' }}" placeholder="Enter title..." required>
          </div>
          <div class="form-group">
            <label class="form-label" for="edit-slug">Slug</label>
            <div class="slug-input">
              <span class="slug-input__prefix">/{{ $contentType->type_id ?? '' }}/</span>
              <input type="text" name="slug" id="edit-slug" class="form-input"
                     value="{{ $node->slug ?? '' }}" placeholder="auto-generated">
              <button type="button" class="btn btn--sm btn--ghost" id="regenerate-slug" title="Regenerate from title">
                <i data-lucide="refresh-cw" class="w-4 h-4"></i>
              </button>
            </div>
          </div>
        </div>
      </div>

      {{-- Body --}}
      <div class="card mb-4">
        <div class="card__header">
          <h3 class="card__title"><i data-lucide="text" class="w-4 h-4" style="display:inline-block;vertical-align:-2px;margin-right:0.3rem;color:#818cf8;"></i> Body</h3>
        </div>
        <div class="card__body">
          <textarea name="body" class="form-textarea" rows="16" id="edit-body"
                    placeholder="Write your content here...">{{ $node->body ?? '' }}</textarea>
        </div>
      </div>

      {{-- Dynamic Fields --}}
      @if(!empty($fields))
      <div class="card mb-4">
        <div class="card__header">
          <h3 class="card__title">
            <i data-lucide="list" class="w-4 h-4" style="display:inline-block;vertical-align:-2px;margin-right:0.3rem;color:#818cf8;"></i>
            {{ $contentType->label ?? '' }} Fields
          </h3>
        </div>
        <div class="card__body">
          {!! $widgetRegistry->renderAll($fields, $fieldValues ?? []) !!}
        </div>
      </div>
      @endif

      {{-- Mosaic Link --}}
      @if(!$isNew && $contentType && $contentType->hasMosaic)
      <div class="card mb-4 mosaic-link-card">
        <div class="card__body" style="padding:1.25rem;">
          <div class="mosaic-link">
            <div class="mosaic-link__icon">
              <i data-lucide="layout-grid" class="w-6 h-6"></i>
            </div>
            <div class="mosaic-link__text">
              <h4 style="margin:0;font-size:0.95rem;color:#e2e8f0;">Mosaic Page Builder</h4>
              <p class="text-sm text-muted" style="margin:0.25rem 0 0;">Use the visual drag-and-drop builder for rich layouts.</p>
            </div>
            <a href="/admin/mosaic/{{ $node->id }}" class="btn btn--sm btn--primary">
              Open Editor <i data-lucide="arrow-right" class="w-4 h-4"></i>
            </a>
          </div>
        </div>
      </div>
      @endif
    </div>

    {{-- Sidebar Column --}}
    <div>
      {{-- Publishing --}}
      <div class="card mb-4">
        <div class="card__header">
          <h3 class="card__title"><i data-lucide="settings-2" class="w-4 h-4" style="display:inline-block;vertical-align:-2px;margin-right:0.3rem;color:#818cf8;"></i> Publishing</h3>
        </div>
        <div class="card__body">
          <div class="form-group">
            <label class="form-label">Status</label>
            <select name="status" class="form-select">
              @foreach($statuses ?? [] as $status)
              <option value="{{ $status->value }}" {{ ($node->status ?? 'draft') === $status->value ? 'selected' : '' }}>
                {{ $status->label() }}
              </option>
              @endforeach
            </select>
          </div>

          @if(!$isNew)
          <div class="form-group">
            <label class="form-label">Content Type</label>
            <select name="content_type" class="form-select">
              @foreach($allTypes ?? [] as $ct)
              <option value="{{ $ct->type_id }}" {{ ($node->content_type ?? '') === $ct->type_id ? 'selected' : '' }}>
                {{ $ct->label }}
              </option>
              @endforeach
            </select>
          </div>
          @endif

          <div class="form-group">
            <label class="form-label">Publish Date</label>
            <input type="datetime-local" name="published_at" class="form-input"
                   value="{{ $node && $node->published_at ? $node->published_at->format('Y-m-d\TH:i') : '' }}">
          </div>

          @if(!$isNew && $node)
          <div class="publish-meta">
            <div class="publish-meta__item">
              <span class="text-muted">Created</span>
              <span>{{ $node->created_at?->format('M j, Y g:i A') ?? '—' }}</span>
            </div>
            <div class="publish-meta__item">
              <span class="text-muted">Updated</span>
              <span>{{ $node->updatedAgo }}</span>
            </div>
            <div class="publish-meta__item">
              <span class="text-muted">Revision</span>
              <span>#{{ $node->revision }}</span>
            </div>
          </div>
          @endif
        </div>
      </div>

      {{-- Summary --}}
      <div class="card mb-4">
        <div class="card__header">
          <h3 class="card__title">Summary</h3>
        </div>
        <div class="card__body">
          <div class="form-group">
            <textarea name="summary" class="form-textarea" rows="3"
                      placeholder="Brief summary...">{{ $node->summary ?? '' }}</textarea>
          </div>
        </div>
      </div>

      {{-- Featured Image --}}
      <div class="card mb-4">
        <div class="card__header">
          <h3 class="card__title"><i data-lucide="image" class="w-4 h-4" style="display:inline-block;vertical-align:-2px;margin-right:0.3rem;color:#818cf8;"></i> Featured Image</h3>
        </div>
        <div class="card__body">
          <div class="media-picker" id="featured-image-picker">
            <div class="media-picker__placeholder">
              <i data-lucide="image-plus" class="w-6 h-6"></i>
              <span class="text-sm text-muted">Click to select image</span>
            </div>
            <input type="hidden" name="featured_image_id" value="{{ $node->featured_image_id ?? '' }}">
          </div>
        </div>
      </div>

      {{-- SEO --}}
      <div class="card mb-4">
        <div class="card__header">
          <h3 class="card__title"><i data-lucide="search" class="w-4 h-4" style="display:inline-block;vertical-align:-2px;margin-right:0.3rem;color:#818cf8;"></i> SEO</h3>
        </div>
        <div class="card__body">
          <div class="form-group">
            <label class="form-label">Meta Title</label>
            <input type="text" name="meta_title" class="form-input"
                   value="{{ $node->meta_title ?? '' }}" maxlength="60">
            <p class="form-help form-help--counter"><span id="meta-title-count">0</span>/60</p>
          </div>
          <div class="form-group">
            <label class="form-label">Meta Description</label>
            <textarea name="meta_description" class="form-textarea" rows="3"
                      maxlength="160">{{ $node->meta_description ?? '' }}</textarea>
            <p class="form-help form-help--counter"><span id="meta-desc-count">0</span>/160</p>
          </div>
        </div>
      </div>

      {{-- Danger Zone --}}
      @if(!$isNew)
      <div class="card mb-4 card--danger">
        <div class="card__body">
          <form action="/admin/content/{{ $node->id }}/delete" method="POST"
                onsubmit="return confirm('Are you sure you want to delete this content? This action cannot be undone.')">
            <button type="submit" class="btn btn--sm btn--danger btn--block">
              <i data-lucide="trash-2" class="w-4 h-4"></i> Delete Content
            </button>
          </form>
        </div>
      </div>
      @endif
    </div>
  </div>
</form>

@push('head')
<style>
.toolbar-actions { display: flex; gap: 0.5rem; align-items: center; }
.slug-input {
  display: flex; align-items: center; border: 1px solid rgba(255,255,255,0.1);
  border-radius: 10px; overflow: hidden; background: rgba(0,0,0,0.15);
}
.slug-input__prefix {
  padding: 0 0.5rem 0 0.75rem; font-size: 0.8rem; color: #64748b; white-space: nowrap;
}
.slug-input .form-input { border: none; background: transparent; border-radius: 0; }
.slug-input .btn { border-radius: 0; }
.mosaic-link {
  display: flex; align-items: center; gap: 1rem;
}
.mosaic-link__icon {
  width: 48px; height: 48px;
  display: flex; align-items: center; justify-content: center;
  background: linear-gradient(135deg, rgba(99,102,241,0.15), rgba(139,92,246,0.1));
  border-radius: 12px; color: #818cf8; flex-shrink: 0;
}
.mosaic-link__text { flex: 1; }
.publish-meta { margin-top: 0.75rem; padding-top: 0.75rem; border-top: 1px solid rgba(255,255,255,0.04); }
.publish-meta__item {
  display: flex; justify-content: space-between; font-size: 0.75rem;
  padding: 0.2rem 0; color: #94a3b8;
}
.card--danger { border-color: rgba(239,68,68,0.15); }
.btn--danger {
  background: rgba(239,68,68,0.15); color: #f87171; border: 1px solid rgba(239,68,68,0.2);
}
.btn--danger:hover { background: rgba(239,68,68,0.25); }
.media-picker {
  border: 2px dashed rgba(255,255,255,0.08); border-radius: 12px;
  padding: 2rem; text-align: center; cursor: pointer; transition: border-color 0.2s;
}
.media-picker:hover { border-color: rgba(99,102,241,0.3); }
.media-picker__placeholder {
  display: flex; flex-direction: column; align-items: center; gap: 0.5rem; color: #64748b;
}
.form-help--counter { text-align: right; font-size: 0.7rem; }
</style>
@endpush

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
  // Auto-generate slug
  const titleInput = document.getElementById('edit-title');
  const slugInput = document.getElementById('edit-slug');
  const regenBtn = document.getElementById('regenerate-slug');
  let slugEdited = {{ $isNew ? 'false' : 'true' }};

  function generateSlug(text) {
    return text.toLowerCase()
      .replace(/[^a-z0-9\s-]/g, '')
      .replace(/\s+/g, '-')
      .replace(/-+/g, '-')
      .replace(/^-|-$/g, '');
  }

  if (titleInput && slugInput) {
    slugInput.addEventListener('input', () => { slugEdited = true; });
    titleInput.addEventListener('input', () => {
      if (!slugEdited) {
        slugInput.value = generateSlug(titleInput.value);
      }
    });
  }

  if (regenBtn && titleInput && slugInput) {
    regenBtn.addEventListener('click', () => {
      slugInput.value = generateSlug(titleInput.value);
      slugEdited = false;
    });
  }

  // SEO character counters
  const metaTitleInput = document.querySelector('input[name="meta_title"]');
  const metaDescInput = document.querySelector('textarea[name="meta_description"]');
  const metaTitleCount = document.getElementById('meta-title-count');
  const metaDescCount = document.getElementById('meta-desc-count');

  if (metaTitleInput && metaTitleCount) {
    metaTitleCount.textContent = metaTitleInput.value.length;
    metaTitleInput.addEventListener('input', () => { metaTitleCount.textContent = metaTitleInput.value.length; });
  }
  if (metaDescInput && metaDescCount) {
    metaDescCount.textContent = metaDescInput.value.length;
    metaDescInput.addEventListener('input', () => { metaDescCount.textContent = metaDescInput.value.length; });
  }
});
</script>
@endpush
@endsection
