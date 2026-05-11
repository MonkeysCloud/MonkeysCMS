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
  @php
    $appKey = $_ENV['APP_KEY'] ?? 'monkeyscms-default-key';
    $previewToken = hash_hmac('sha256', "preview:{$node->id}:{$node->revision}", $appKey);
    $previewUrl = "/preview/{$node->id}?token={$previewToken}";
  @endphp
  <a href="{{ $previewUrl }}" target="_blank" class="btn btn--ghost btn--sm" title="Preview (shareable link)">
    <i data-lucide="eye" class="w-4 h-4"></i> Preview
  </a>
  @endif
  <button type="submit" form="content-form" id="content-save-btn" class="btn btn--primary btn--sm" {{ (!$isNew && !empty($lockInfo) && empty($lockAcquired)) ? 'disabled' : '' }}>
    <i data-lucide="save" class="w-4 h-4"></i>
    {{ $isNew ? 'Create' : 'Update' }}
  </button>
</div>
@endsection

@section('content')

{{-- ── Content Lock Banner ────────────────────────────────────────────── --}}
@if(!$isNew && !empty($lockInfo) && empty($lockAcquired))
<div class="lock-banner" id="lock-banner">
  <div class="lock-banner__icon">
    <i data-lucide="lock" class="w-5 h-5"></i>
  </div>
  <div class="lock-banner__text">
    <strong>This content is currently being edited by {{ $lockInfo->userName }}</strong>
    <span class="lock-banner__expires">Expires in {{ $lockInfo->minutesRemaining() }} min</span>
  </div>
  <button type="button" class="btn btn--sm btn--warning" id="break-lock-btn"
          onclick="breakContentLock()">
    <i data-lucide="unlock" class="w-4 h-4"></i> Break Lock
  </button>
</div>
@endif

<form id="content-form" method="POST"
      action="{{ $isNew ? '/admin/content' : '/admin/content/' . $node->id }}"
      class="content-form {{ (!$isNew && !empty($lockInfo) && empty($lockAcquired)) ? 'content-form--locked' : '' }}"
      data-is-new="{{ $isNew ? 'true' : 'false' }}"
      data-node-id="{{ $node->id ?? '' }}">


  @if($isNew && $contentType)
  <input type="hidden" name="content_type" value="{{ $contentType->type_id }}">
  @endif
  @php $translateFrom = $_GET['translate_from'] ?? ''; @endphp
  @if($translateFrom)
  <input type="hidden" name="translate_from" value="{{ $translateFrom }}">
  @endif

  <div class="content-form__grid">

    {{-- Main Column --}}
    <div>
      {{-- Title --}}
      <div class="card mb-4">
        <div class="card__body">
          <div class="form-group">
            <label class="form-label" for="edit-title">Title <span class="text-danger">*</span></label>
            <input type="text" name="title" id="edit-title" class="form-input form-input--lg"
                   value="{{ $node->title ?? '' }}" placeholder="Enter title..." required>
          </div>
        </div>
      </div>

      {{-- Body --}}
      <div class="card mb-4">
        <div class="card__header card__header--between">
          <h3 class="card__title"><i data-lucide="text" class="w-4 h-4 card__title-icon"></i> Body</h3>
          <div class="body-format-tabs" id="body-format-tabs">
            <button type="button" class="body-format-tab is-active" data-format="wysiwyg">Visual</button>
            <button type="button" class="body-format-tab" data-format="markdown">Markdown</button>
            <button type="button" class="body-format-tab" data-format="plain">Plain</button>
          </div>
        </div>
        <div class="card__body card__body--flush">
          <input type="hidden" name="body_format" id="body-format-input" value="{{ $node->body_format ?? 'wysiwyg' }}">

          {{-- WYSIWYG toolbar --}}
          <div class="wysiwyg-toolbar" id="wysiwyg-toolbar">
            <button type="button" class="wysiwyg-toolbar__btn" data-cmd="bold" title="Bold (Ctrl+B)">
              <i data-lucide="bold" class="w-4 h-4"></i>
            </button>
            <button type="button" class="wysiwyg-toolbar__btn" data-cmd="italic" title="Italic (Ctrl+I)">
              <i data-lucide="italic" class="w-4 h-4"></i>
            </button>
            <button type="button" class="wysiwyg-toolbar__btn" data-cmd="underline" title="Underline">
              <i data-lucide="underline" class="w-4 h-4"></i>
            </button>
            <button type="button" class="wysiwyg-toolbar__btn" data-cmd="strikethrough" title="Strikethrough">
              <i data-lucide="strikethrough" class="w-4 h-4"></i>
            </button>
            <span class="wysiwyg-toolbar__sep"></span>
            <button type="button" class="wysiwyg-toolbar__btn" data-cmd="formatBlock" data-value="H2" title="Heading 2">
              <i data-lucide="heading-2" class="w-4 h-4"></i>
            </button>
            <button type="button" class="wysiwyg-toolbar__btn" data-cmd="formatBlock" data-value="H3" title="Heading 3">
              <i data-lucide="heading-3" class="w-4 h-4"></i>
            </button>
            <button type="button" class="wysiwyg-toolbar__btn" data-cmd="formatBlock" data-value="P" title="Paragraph">
              <i data-lucide="pilcrow" class="w-4 h-4"></i>
            </button>
            <span class="wysiwyg-toolbar__sep"></span>
            <button type="button" class="wysiwyg-toolbar__btn" data-cmd="insertUnorderedList" title="Bullet List">
              <i data-lucide="list" class="w-4 h-4"></i>
            </button>
            <button type="button" class="wysiwyg-toolbar__btn" data-cmd="insertOrderedList" title="Numbered List">
              <i data-lucide="list-ordered" class="w-4 h-4"></i>
            </button>
            <button type="button" class="wysiwyg-toolbar__btn" data-cmd="formatBlock" data-value="BLOCKQUOTE" title="Blockquote">
              <i data-lucide="quote" class="w-4 h-4"></i>
            </button>
            <span class="wysiwyg-toolbar__sep"></span>
            <button type="button" class="wysiwyg-toolbar__btn" id="btn-link" title="Insert Link">
              <i data-lucide="link" class="w-4 h-4"></i>
            </button>
            <button type="button" class="wysiwyg-toolbar__btn" data-cmd="removeFormat" title="Clear Formatting">
              <i data-lucide="eraser" class="w-4 h-4"></i>
            </button>
            <button type="button" class="wysiwyg-toolbar__btn" id="btn-source" title="View Source">
              <i data-lucide="code" class="w-4 h-4"></i>
            </button>
          </div>

          {{-- Markdown toolbar --}}
          <div class="wysiwyg-toolbar" id="markdown-toolbar" hidden>
            <button type="button" class="wysiwyg-toolbar__btn" data-md="bold" title="Bold">
              <i data-lucide="bold" class="w-4 h-4"></i>
            </button>
            <button type="button" class="wysiwyg-toolbar__btn" data-md="italic" title="Italic">
              <i data-lucide="italic" class="w-4 h-4"></i>
            </button>
            <span class="wysiwyg-toolbar__sep"></span>
            <button type="button" class="wysiwyg-toolbar__btn" data-md="h2" title="Heading 2">
              <i data-lucide="heading-2" class="w-4 h-4"></i>
            </button>
            <button type="button" class="wysiwyg-toolbar__btn" data-md="h3" title="Heading 3">
              <i data-lucide="heading-3" class="w-4 h-4"></i>
            </button>
            <span class="wysiwyg-toolbar__sep"></span>
            <button type="button" class="wysiwyg-toolbar__btn" data-md="ul" title="Bullet List">
              <i data-lucide="list" class="w-4 h-4"></i>
            </button>
            <button type="button" class="wysiwyg-toolbar__btn" data-md="ol" title="Numbered List">
              <i data-lucide="list-ordered" class="w-4 h-4"></i>
            </button>
            <button type="button" class="wysiwyg-toolbar__btn" data-md="link" title="Insert Link">
              <i data-lucide="link" class="w-4 h-4"></i>
            </button>
            <button type="button" class="wysiwyg-toolbar__btn" data-md="code" title="Code Block">
              <i data-lucide="code" class="w-4 h-4"></i>
            </button>
          </div>

          {{-- WYSIWYG contenteditable area --}}
          <div class="wysiwyg-editor" contenteditable="true" id="wysiwyg-editor"
               data-empty="{{ empty($node->body ?? '') ? 'true' : 'false' }}">{!! $node->body ?? '' !!}</div>

          {{-- Raw textarea (Markdown / Plain / Source) --}}
          <textarea name="body" class="form-textarea body-source-editor" rows="16" id="edit-body"
                    placeholder="Write your content here..." hidden>{{ $node->body ?? '' }}</textarea>
        </div>
      </div>

      {{-- Dynamic Fields --}}
      @if(!empty($fields))
      <div class="card mb-4">
        <div class="card__header">
          <h3 class="card__title">
            <i data-lucide="list" class="w-4 h-4 card__title-icon"></i>
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
        <div class="card__body card__body--compact">
          <div class="mosaic-link">
            <div class="mosaic-link__icon">
              <i data-lucide="layout-grid" class="w-6 h-6"></i>
            </div>
            <div class="mosaic-link__text">
              <h4 class="mosaic-link__title">Mosaic Page Builder</h4>
              <p class="text-sm text-muted mosaic-link__desc">Use the visual drag-and-drop builder for rich layouts.</p>
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
          <h3 class="card__title"><i data-lucide="settings-2" class="w-4 h-4 card__title-icon"></i> Publishing</h3>
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
            <label class="form-label">Author</label>
            <div class="author-autocomplete" id="author-autocomplete">
              <input type="hidden" name="author_id" id="author-id" value="{{ $authorId ?? '' }}">
              <input type="text"
                     class="form-input"
                     id="author-search"
                     value="{{ $authorName ?? '' }}"
                     placeholder="Search users…"
                     autocomplete="off">
              <div class="author-autocomplete__dropdown" id="author-dropdown"></div>
            </div>
          </div>

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
          <h3 class="card__title"><i data-lucide="image" class="w-4 h-4 card__title-icon"></i> Featured Image</h3>
        </div>
        <div class="card__body">
          <div class="media-picker {{ ($node->featured_image_id ?? null) ? 'has-image' : '' }}" id="featured-image-picker">
            @if($node->featured_image_id ?? null)
            <div class="media-picker__preview" id="fi-preview">
              <img src="{{ $featuredImageUrl ?? '' }}" alt="{{ $featuredImageAlt ?? '' }}" id="fi-img">
              <div class="media-picker__preview-info">
                <span id="fi-name">{{ $featuredImageName ?? 'Image' }}</span>
                <button type="button" class="media-picker__remove" id="fi-remove">Remove</button>
              </div>
            </div>
            @else
            <div class="media-picker__placeholder" id="fi-placeholder">
              <i data-lucide="image-plus" class="w-6 h-6"></i>
              <span class="text-sm text-muted">Click to select image</span>
            </div>
            <div class="media-picker__preview" id="fi-preview" hidden>
              <img src="" alt="" id="fi-img">
              <div class="media-picker__preview-info">
                <span id="fi-name"></span>
                <button type="button" class="media-picker__remove" id="fi-remove">Remove</button>
              </div>
            </div>
            @endif
            <input type="hidden" name="featured_image_id" id="featured-image-id" value="{{ $node->featured_image_id ?? '' }}">
          </div>
        </div>
      </div>

      {{-- URL Alias --}}
      <div class="card mb-4">
        <div class="card__header">
          <h3 class="card__title"><i data-lucide="link" class="w-4 h-4 card__title-icon"></i> URL Alias</h3>
        </div>
        <div class="card__body">
          <div class="form-group">
            <div class="slug-input">
              <span class="slug-input__prefix">/</span>
              <input type="text" name="slug" id="edit-slug" class="form-input"
                     value="{{ $node->slug ?? '' }}" placeholder="auto-generated"
                     style="font-family:'JetBrains Mono',monospace;font-size:0.85rem;">
              <button type="button" class="btn btn--sm btn--ghost" id="regenerate-slug" title="Regenerate from title">
                <i data-lucide="refresh-cw" class="w-4 h-4"></i>
              </button>
            </div>
            @if(!$isNew && $node && $node->slug)
            <div class="slug-preview-url" style="margin-top:0.5rem;display:flex;align-items:center;gap:0.35rem;">
              <i data-lucide="external-link" class="w-3 h-3" style="color:#64748b;"></i>
              <a href="/{{ $node->slug }}" target="_blank"
                 style="font-size:0.75rem;color:#64748b;font-family:monospace;text-decoration:none;"
                 class="slug-url-link">/{{ $node->slug }}</a>
            </div>
            @endif
            <p class="form-help">Leave blank to auto-generate from the URL pattern. Modifying this will change the content URL.</p>
          </div>
        </div>
      </div>

      {{-- SEO --}}
      <div class="card mb-4">
        <div class="card__header">
          <h3 class="card__title"><i data-lucide="search" class="w-4 h-4 card__title-icon"></i> SEO</h3>
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

      {{-- Translations (only when multilingual module is enabled) --}}
      @if(!empty($multilingualEnabled))
      <div class="card mb-4" id="translations-panel">
        <div class="card__header card__header--between">
          <h3 class="card__title"><i data-lucide="globe" class="w-4 h-4 card__title-icon"></i> Translations</h3>
          <span class="badge {{ $node->language === ($defaultLang ?? 'en') ? 'badge--primary' : 'badge--info' }}">
            {{ strtoupper($node->language ?? 'en') }}
          </span>
        </div>
        <div class="card__body">
          {{-- Language selector for this content --}}
          <div class="form-group">
            <label class="form-label">Language</label>
            <select name="language" class="form-select">
              @foreach($enabledLanguages ?? [] as $lang)
              <option value="{{ $lang->code }}" {{ ($node->language ?? 'en') === $lang->code ? 'selected' : '' }}>
                {{ $lang->flagEmoji }} {{ $lang->native }} ({{ $lang->code }})
              </option>
              @endforeach
            </select>
          </div>

          {{-- Existing translations --}}
          @if(!$isNew && !empty($translations))
          <div style="margin-top:.75rem">
            <label class="form-label" style="margin-bottom:.5rem">Linked Translations</label>
            @foreach($translations as $langCode => $transId)
            <a href="/admin/content/{{ $transId }}/edit"
               class="btn btn--ghost btn--xs btn--block" style="justify-content:space-between;margin-bottom:.25rem">
              <span>
                @php $tLang = null; foreach($enabledLanguages ?? [] as $l) { if($l->code === $langCode) { $tLang = $l; break; } } @endphp
                {{ $tLang?->flagEmoji ?? '🌐' }} {{ $tLang?->native ?? $langCode }}
              </span>
              <i data-lucide="external-link" class="w-3 h-3"></i>
            </a>
            @endforeach
          </div>
          @endif

          {{-- Add translation button --}}
          @if(!$isNew)
          <div style="margin-top:.75rem">
            @php
              $missingLangs = [];
              foreach($enabledLanguages ?? [] as $l) {
                if ($l->code !== ($node->language ?? 'en') && !isset($translations[$l->code])) {
                  $missingLangs[] = $l;
                }
              }
            @endphp
            @if(!empty($missingLangs))
            <div class="form-group" style="margin-bottom:0">
              <label class="form-label" style="margin-bottom:.5rem">Add Translation</label>
              <div style="display:flex;gap:.25rem;flex-wrap:wrap">
                @foreach($missingLangs as $ml)
                <a href="/admin/content/create?type={{ $node->content_type }}&translate_from={{ $node->id }}&lang={{ $ml->code }}"
                   class="btn btn--ghost btn--xs" title="Create {{ $ml->native }} translation">
                  {{ $ml->flagEmoji }} {{ strtoupper($ml->code) }}
                </a>
                @endforeach
              </div>
            </div>
            @else
            <p class="text-xs text-muted" style="text-align:center">✓ All languages covered</p>
            @endif
          </div>
          @endif
        </div>
      </div>
      @endif
      <input type="hidden" name="_lock_status" id="lock-status-input" value="{{ (empty($lockAcquired) && empty($lockInfo)) ? 'released' : 'held' }}">

      {{-- Content Lock Status --}}
      @if(!$isNew)
      <div class="card mb-4" id="lock-status-card">
        <div class="card__body card__body--compact">
          @if(!empty($lockAcquired))
          <div class="lock-status lock-status--owned">
            <i data-lucide="lock" class="w-5 h-5"></i>
            <div>
              <strong>Locked by you</strong>
              <div class="text-xs text-muted">Auto-renews while editing</div>
            </div>
            <button type="button" class="btn btn--ghost btn--xs" title="Release lock" onclick="releaseOwnLock()">
              <i data-lucide="unlock" class="w-3.5 h-3.5"></i>
            </button>
          </div>
          @elseif(!empty($lockInfo))
          <div class="lock-status lock-status--other">
            <i data-lucide="lock" class="w-5 h-5"></i>
            <div>
              <strong>Locked by {{ $lockInfo->userName ?? 'another user' }}</strong>
              <div class="text-xs text-muted">Expires in {{ $lockInfo?->minutesRemaining() ?? '?' }} min</div>
            </div>
          </div>
          @else
          <div class="lock-status lock-status--released">
            <i data-lucide="unlock" class="w-5 h-5"></i>
            <div>
              <strong>Lock released</strong>
              <div class="text-xs text-muted">Other users can now edit</div>
            </div>
            <button type="button" class="btn btn--ghost btn--xs" title="Re-acquire lock" onclick="reacquireLock()">
              <i data-lucide="lock" class="w-3.5 h-3.5"></i>
            </button>
          </div>
          @endif
        </div>
      </div>
      @endif

      {{-- Revisions --}}
      @if(!$isNew)
      <div class="card mb-4">
        <div class="card__body card__body--compact">
          <a href="/admin/content/{{ $node->id }}/revisions" class="rev-link">
            <i data-lucide="history" class="w-5 h-5"></i>
            <div>
              <strong>Revision History</strong>
              <div class="text-xs text-muted">Compare versions and revert changes</div>
            </div>
            <i data-lucide="chevron-right" class="w-4 h-4" style="margin-left:auto;color:#64748b"></i>
          </a>
        </div>
      </div>
      @endif

      {{-- Danger Zone --}}
      @if(!$isNew)
      <div class="card mb-4 card--danger">
        <div class="card__body">
          <form action="/admin/content/{{ $node->id }}/delete" method="POST"
                data-confirm="Are you sure you want to delete this content? This action cannot be undone." data-confirm-title="Delete Content">
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
<link rel="stylesheet" href="/themes/core/admin/css/media-picker.css?v={{ time() }}">
@endpush

@push('scripts')
<script src="/themes/core/admin/js/media-picker.js?v={{ time() }}"></script>
<script src="/themes/core/admin/js/content-editor.js?v={{ time() }}"></script>
<script>
(function() {
  const picker = document.getElementById('featured-image-picker');
  const input = document.getElementById('featured-image-id');
  const placeholder = document.getElementById('fi-placeholder');
  const preview = document.getElementById('fi-preview');
  const img = document.getElementById('fi-img');
  const nameEl = document.getElementById('fi-name');
  const removeBtn = document.getElementById('fi-remove');

  function openPicker() {
    CMS.mediaPicker({
      type: 'image',
      onSelect(media) {
        input.value = media.id;
        img.src = media.url;
        img.alt = media.alt || media.name;
        nameEl.textContent = media.name;
        if (placeholder) placeholder.hidden = true;
        preview.hidden = false;
        picker.classList.add('has-image');
      },
    });
  }

  // Click on picker area (but not on remove button)
  picker.addEventListener('click', (e) => {
    if (e.target.closest('.media-picker__remove')) return;
    openPicker();
  });

  // Remove button
  if (removeBtn) {
    removeBtn.addEventListener('click', (e) => {
      e.stopPropagation();
      input.value = '';
      img.src = '';
      nameEl.textContent = '';
      preview.hidden = true;
      if (placeholder) placeholder.hidden = false;
      picker.classList.remove('has-image');
    });
  }
})();
</script>
@if(!$isNew)
<script>
(function() {
  const NODE_ID = {{ $node->id ?? 0 }};
  const LOCK_ACQUIRED = {{ !empty($lockAcquired) ? 'true' : 'false' }};
  let heartbeatTimer = null;
  let isSubmitting = false;

  if (LOCK_ACQUIRED && NODE_ID) {
    // ── Heartbeat: renew lock every 60 seconds ──────────────────────
    heartbeatTimer = setInterval(async () => {
      try {
        const resp = await CMS.fetch(`/admin/content/${NODE_ID}/lock/renew`, {
          method: 'POST',
          body: '{}',
        });
        const data = await resp.json();
        if (!data.success) {
          clearInterval(heartbeatTimer);
          console.warn('Lock renewal failed — lock may have expired.');
        }
      } catch (e) {
        console.warn('Lock heartbeat error:', e.message);
      }
    }, 60_000);

    // ── Release lock on page unload (only when NOT saving) ──────────
    window.addEventListener('beforeunload', () => {
      clearInterval(heartbeatTimer);
      if (!isSubmitting) {
        navigator.sendBeacon(`/admin/content/${NODE_ID}/lock/release`);
      }
    });

    // ── On form submit: keep the lock, just stop heartbeat ──────────
    const form = document.getElementById('content-form');
    if (form) {
      form.addEventListener('submit', () => {
        isSubmitting = true;
        clearInterval(heartbeatTimer);
      });
    }
  }

  // ── Break Lock ──────────────────────────────────────────────────────
  window.breakContentLock = async function() {
    const confirmed = await CMS.confirm({
      title: 'Break Content Lock',
      message: 'The other editor may lose unsaved changes. Are you sure?',
      confirmLabel: 'Break Lock',
      confirmClass: 'btn btn--warning',
    });
    if (!confirmed) return;

    const btn = document.getElementById('break-lock-btn');
    btn.disabled = true;
    btn.innerHTML = '<i data-lucide="loader" class="w-4 h-4 spin"></i> Breaking...';
    if (window.lucide) lucide.createIcons({ nodes: [btn] });

    try {
      const resp = await CMS.fetch(`/admin/content/${NODE_ID}/lock/break`, {
        method: 'POST',
        body: '{}',
      });
      const data = await resp.json();
      if (data.success) {
        window.location.reload();
      } else {
        CMS.modal({ title: 'Error', body: `<p>${data.error || 'Failed to break lock'}</p>`, size: 'sm' });
        btn.disabled = false;
        btn.innerHTML = '<i data-lucide="unlock" class="w-4 h-4"></i> Break Lock';
        if (window.lucide) lucide.createIcons({ nodes: [btn] });
      }
    } catch (e) {
      CMS.modal({ title: 'Error', body: `<p>${e.message}</p>`, size: 'sm' });
      btn.disabled = false;
    }
  };

  // ── Release Own Lock ───────────────────────────────────────────────────
  window.releaseOwnLock = async function() {
    const confirmed = await CMS.confirm({
      title: 'Release Lock',
      message: 'Another user will be able to edit this content. Continue?',
      confirmLabel: 'Release',
      confirmClass: 'btn btn--primary',
    });
    if (!confirmed) return;

    try {
      await CMS.fetch(`/admin/content/${NODE_ID}/lock/release`, { method: 'POST', body: '{}' });
      clearInterval(heartbeatTimer);
      heartbeatTimer = null;

      // Update sidebar card to "Released" state
      const card = document.getElementById('lock-status-card');
      if (card) {
        card.innerHTML = `
          <div class="card__body card__body--compact">
            <div class="lock-status lock-status--released">
              <i data-lucide="unlock" class="w-5 h-5"></i>
              <div>
                <strong>Lock released</strong>
                <div class="text-xs text-muted">Other users can now edit</div>
              </div>
              <button type="button" class="btn btn--ghost btn--xs" title="Re-acquire lock"
                      onclick="reacquireLock()">
                <i data-lucide="lock" class="w-3.5 h-3.5"></i>
              </button>
            </div>
          </div>`;
        if (window.lucide) lucide.createIcons({ nodes: [card] });
      }

      // Update hidden input to preserve released state on save
      const lockInput = document.getElementById('lock-status-input');
      if (lockInput) lockInput.value = 'released';

    } catch (e) {
      CMS.modal({ title: 'Error', body: `<p>${e.message}</p>`, size: 'sm' });
    }
  };

  // ── Re-acquire Lock ───────────────────────────────────────────────────
  window.reacquireLock = async function() {
    try {
      const resp = await CMS.fetch(`/admin/content/${NODE_ID}/lock/renew`, { method: 'POST', body: '{}' });
      // Renew won't work if lock was deleted — reload to re-acquire
      window.location.reload();
    } catch (e) {
      CMS.modal({ title: 'Error', body: `<p>${e.message}</p>`, size: 'sm' });
    }
  };
})();
</script>
@endif
@endpush
@endsection
