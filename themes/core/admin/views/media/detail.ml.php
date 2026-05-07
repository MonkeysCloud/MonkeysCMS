@extends('layouts.admin')

@section('title', $media->title ?? $media->original_name)

@section('breadcrumb')
<a href="/admin">Dashboard</a>
<span class="admin-breadcrumb__sep">/</span>
<a href="/admin/media">Media</a>
<span class="admin-breadcrumb__sep">/</span>
<span>{{ $media->title ?? $media->original_name }}</span>
@endsection

@section('toolbar_actions')
<form action="/admin/media/{{ $media->id }}/delete" method="POST" data-confirm="Delete this media permanently? This cannot be undone." data-confirm-title="Delete Media">
  <button type="submit" class="btn btn--sm btn--danger">
    <i data-lucide="trash-2" class="w-4 h-4"></i> Delete
  </button>
</form>
@endsection

@section('content')
<div class="admin-content">

  @php $saved = $_GET['saved'] ?? null; @endphp
  @if($saved)
  <div class="admin-alert admin-alert--success">
    <i data-lucide="check-circle" class="w-4 h-4"></i>
    Media updated successfully.
  </div>
  @endif

  <div class="media-detail">
    {{-- Preview Panel --}}
    <div class="media-detail__preview">
      @if($media->type === 'image')
        <img src="{{ $media->url ?? '/uploads/' . $media->path }}" alt="{{ $media->alt ?? '' }}" class="media-detail__image">
        @if($media->width && $media->height)
        <div class="media-detail__dimensions">{{ $media->width }} × {{ $media->height }}px</div>
        @endif
      @elseif($media->type === 'video')
        <div class="media-detail__file-icon">
          <i data-lucide="video" class="w-16 h-16"></i>
        </div>
      @elseif($media->type === 'audio')
        <div class="media-detail__file-icon">
          <i data-lucide="music" class="w-16 h-16"></i>
        </div>
      @else
        <div class="media-detail__file-icon">
          <i data-lucide="file-text" class="w-16 h-16"></i>
        </div>
      @endif

      {{-- File Info --}}
      <div class="media-detail__info-table">
        <div class="media-detail__info-row">
          <span class="media-detail__info-label">Filename</span>
          <span class="media-detail__info-value">{{ $media->original_name }}</span>
        </div>
        <div class="media-detail__info-row">
          <span class="media-detail__info-label">MIME Type</span>
          <span class="media-detail__info-value">{{ $media->mime_type }}</span>
        </div>
        <div class="media-detail__info-row">
          <span class="media-detail__info-label">Size</span>
          <span class="media-detail__info-value">{{ $media->formattedSize }}</span>
        </div>
        <div class="media-detail__info-row">
          <span class="media-detail__info-label">Uploaded</span>
          <span class="media-detail__info-value">{{ $media->created_at?->format('M j, Y g:ia') ?? '—' }}</span>
        </div>
        <div class="media-detail__info-row">
          <span class="media-detail__info-label">URL</span>
          <span class="media-detail__info-value">
            <a href="{{ $media->url ?? '/uploads/' . $media->path }}" target="_blank" class="media-detail__url-link">
              {{ $media->url ?? '/uploads/' . $media->path }}
              <i data-lucide="external-link" class="w-3 h-3"></i>
            </a>
          </span>
        </div>
      </div>

      {{-- Image Styles --}}
      @if($media->type === 'image' && !empty($styles))
      <div class="media-detail__styles">
        <h4 class="media-detail__section-title">Image Styles</h4>
        <div class="media-detail__styles-grid">
          <?php foreach($styles as $_sn => $_sd): ?>
          <div class="media-detail__style-item">
            <span class="media-detail__style-name"><?= htmlspecialchars($_sn) ?></span>
            <span class="media-detail__style-dims"><?= (int)$_sd['width'] ?>×<?= (int)$_sd['height'] ?> (<?= htmlspecialchars($_sd['fit']) ?>)</span>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
      @endif
    </div>

    {{-- Edit Form (from FormBuilder) --}}
    <div class="media-detail__edit">
      <div class="admin-card">
        <div class="admin-card__header">
          <h3 class="admin-card__title">Metadata</h3>
        </div>
        <div class="admin-card__body">
          {!! $editForm !!}

          {{-- Custom Metadata Fields --}}
          @if(!empty($media->metadata))
          <div class="form-group">
            <label class="form-label">Custom Fields</label>
            <?php foreach($media->metadata as $_mk => $_mv): ?>
            <?php if(is_string($_mv)): ?>
            <div class="media-detail__custom-field">
              <span class="media-detail__custom-key"><?= htmlspecialchars($_mk) ?></span>
              <span class="media-detail__custom-value"><?= htmlspecialchars($_mv) ?></span>
            </div>
            <?php endif; ?>
            <?php endforeach; ?>
          </div>
          @endif
        </div>
      </div>

      {{-- Usage --}}
      @if(!empty($usage))
      <div class="admin-card">
        <div class="admin-card__header">
          <h3 class="admin-card__title">Used In</h3>
        </div>
        <div class="admin-card__body">
          <div class="media-detail__usage-list">
            @foreach($usage as $node)
            <a href="/admin/content/{{ $node['id'] }}/edit" class="media-detail__usage-item">
              <i data-lucide="file-text" class="w-4 h-4"></i>
              <span>{{ $node['title'] }}</span>
              <span class="badge">{{ $node['content_type'] }}</span>
            </a>
            @endforeach
          </div>
        </div>
      </div>
      @endif
    </div>
  </div>

</div>
@endsection
