@extends('layouts.admin')

@section('title', 'Media Library')

@section('breadcrumb')
<a href="/admin">Dashboard</a>
<span class="admin-breadcrumb__sep">/</span>
<span>Media Library</span>
@endsection

@section('toolbar_actions')
<a href="/admin/media/upload" class="btn btn--sm btn--primary">
  <i data-lucide="upload" class="w-4 h-4"></i> Upload
</a>
<a href="/admin/media/settings" class="btn btn--sm btn--ghost">
  <i data-lucide="settings" class="w-4 h-4"></i> Settings
</a>
@endsection

@push('head')
<link rel="stylesheet" href="/themes/core/admin/css/bulk.css?v={{ time() }}">
@endpush

@section('content')
<div class="admin-content">

  {{-- Notifications --}}
  @php $uploaded = $_GET['uploaded'] ?? null; $deleted = $_GET['deleted'] ?? null; $error = $_GET['error'] ?? null; @endphp
  @if($uploaded)
  <div class="admin-alert admin-alert--success">
    <i data-lucide="check-circle" class="w-4 h-4"></i>
    {{ $uploaded }} file(s) uploaded successfully.
  </div>
  @endif
  @if($deleted)
  <div class="admin-alert admin-alert--success">
    <i data-lucide="check-circle" class="w-4 h-4"></i>
    Media deleted successfully.
  </div>
  @endif
  @if($error)
  <div class="admin-alert admin-alert--error">
    <i data-lucide="alert-circle" class="w-4 h-4"></i>
    {{ $error }}
  </div>
  @endif

  {{-- Filter Tabs --}}
  <div class="media-filters">
    <a href="/admin/media" class="media-filter-tab {{ !$type ? 'active' : '' }}">
      <i data-lucide="grid-3x3" class="w-4 h-4"></i> All
      <span class="media-filter-tab__count">{{ $diskUsage['count'] ?? 0 }}</span>
    </a>
    <a href="/admin/media?type=image" class="media-filter-tab {{ $type === 'image' ? 'active' : '' }}">
      <i data-lucide="image" class="w-4 h-4"></i> Images
      <span class="media-filter-tab__count">{{ $diskUsage['by_type']['images'] ?? 0 }}</span>
    </a>
    <a href="/admin/media?type=video" class="media-filter-tab {{ $type === 'video' ? 'active' : '' }}">
      <i data-lucide="video" class="w-4 h-4"></i> Videos
      <span class="media-filter-tab__count">{{ $diskUsage['by_type']['videos'] ?? 0 }}</span>
    </a>
    <a href="/admin/media?type=audio" class="media-filter-tab {{ $type === 'audio' ? 'active' : '' }}">
      <i data-lucide="music" class="w-4 h-4"></i> Audio
      <span class="media-filter-tab__count">{{ $diskUsage['by_type']['audio'] ?? 0 }}</span>
    </a>
    <a href="/admin/media?type=application" class="media-filter-tab {{ $type === 'application' ? 'active' : '' }}">
      <i data-lucide="file-text" class="w-4 h-4"></i> Documents
      <span class="media-filter-tab__count">{{ $diskUsage['by_type']['documents'] ?? 0 }}</span>
    </a>

    <div class="media-filters__spacer"></div>

    <div class="media-filters__info">
      <span class="text-muted">{{ $diskUsage['formatted_size'] ?? '0 B' }} used</span>
    </div>
  </div>

  {{-- Grid --}}
  @if(empty($items))
  <div class="admin-empty">
    <i data-lucide="image-off" class="w-12 h-12"></i>
    <h3>No media found</h3>
    <p>Upload files to get started.</p>
    <a href="/admin/media/upload" class="btn btn--primary">
      <i data-lucide="upload" class="w-4 h-4"></i> Upload Files
    </a>
  </div>
  @else

  {{-- Bulk Actions Bar --}}
  <div class="bulk-bar mb-3" data-bulk-toolbar style="display:none">
    <span class="bulk-bar__count"><span data-bulk-count>0</span> selected</span>
    <button class="btn btn--xs btn--ghost text-danger" data-bulk-action="delete"
            data-bulk-confirm="Permanently delete {count} file{s}? This cannot be undone."
            data-bulk-severity="danger">
      <i data-lucide="trash-2" class="w-3.5 h-3.5"></i> Delete
    </button>
  </div>

  <div class="media-grid" id="media-grid" data-bulk-actions data-bulk-url="/admin/media/bulk" data-bulk-grid>
    @foreach($items as $item)
    <a href="/admin/media/{{ $item->id }}" class="media-card" data-media-id="{{ $item->id }}">
      <input type="checkbox" value="{{ $item->id }}" data-bulk-item class="media-card__check"
             onclick="event.stopPropagation(); event.preventDefault(); this.checked = !this.checked; this.dispatchEvent(new Event('change', {bubbles:true}));">
      <div class="media-card__preview">
        @if($item->type === 'image')
          <img src="{{ $item->url ?? '/uploads/' . $item->path }}" alt="{{ $item->alt ?? $item->title ?? '' }}" loading="lazy">
        @elseif($item->type === 'video')
          <div class="media-card__icon media-card__icon--video">
            <i data-lucide="video" class="w-8 h-8"></i>
          </div>
        @elseif($item->type === 'audio')
          <div class="media-card__icon media-card__icon--audio">
            <i data-lucide="music" class="w-8 h-8"></i>
          </div>
        @elseif($item->type === 'document')
          <div class="media-card__icon media-card__icon--document">
            <i data-lucide="file-text" class="w-8 h-8"></i>
          </div>
        @else
          <div class="media-card__icon media-card__icon--file">
            <i data-lucide="file" class="w-8 h-8"></i>
          </div>
        @endif
        <div class="media-card__overlay">
          <i data-lucide="eye" class="w-5 h-5"></i>
        </div>
      </div>
      <div class="media-card__info">
        <span class="media-card__name" title="{{ $item->original_name }}">{{ $item->title ?? $item->original_name }}</span>
        <span class="media-card__meta">{{ $item->formattedSize }} · {{ strtoupper(pathinfo($item->filename, PATHINFO_EXTENSION)) }}</span>
      </div>
    </a>
    @endforeach
  </div>

  {{-- Pagination --}}
  @if($totalPages > 1)
  <div class="admin-pagination">
    @if($page > 1)
    <a href="/admin/media?page={{ $page - 1 }}{{ $type ? '&type=' . $type : '' }}" class="btn btn--sm btn--ghost">← Previous</a>
    @endif
    <span class="admin-pagination__info">Page {{ $page }} of {{ $totalPages }}</span>
    @if($page < $totalPages)
    <a href="/admin/media?page={{ $page + 1 }}{{ $type ? '&type=' . $type : '' }}" class="btn btn--sm btn--ghost">Next →</a>
    @endif
  </div>
  @endif
  @endif

</div>

@push('scripts')
<script src="/themes/core/admin/js/bulk-actions.js?v={{ time() }}"></script>
@endpush
@endsection
