@extends('layouts.admin')

@section('title', 'Media Library')
@section('page_title', 'Media Library')

@section('breadcrumb')
<a href="/admin" class="breadcrumb__item">Dashboard</a>
<span class="breadcrumb__sep">›</span>
<span class="breadcrumb__item breadcrumb__item--active">Media</span>
@endsection

@section('page_actions')
<button class="btn btn--primary btn--sm" $m-on:click="showUpload = true">📤 Upload Files</button>
@endsection

@section('content')
<div id="media-app">

  {{-- Upload Zone (toggle) --}}
  <div class="card mb-4" $m-show="showUpload">
    <div class="card__body">
      <div class="upload-zone" $m-on:dragover.prevent="dragOver = true" $m-on:dragleave="dragOver = false" $m-on:drop.prevent="handleDrop($event)" :class="{ 'upload-zone--active': dragOver }">
        <div class="upload-zone__content">
          <span class="upload-zone__icon">📂</span>
          <p>Drag & drop files here or <label class="upload-zone__browse">browse<input type="file" multiple hidden $m-on:change="handleFiles($event)"></label></p>
          <p class="text-xs text-muted mt-2">Max 10MB per file. Images, documents, videos.</p>
        </div>
      </div>
    </div>
  </div>

  {{-- Filters --}}
  <div class="card mb-4">
    <div class="card__body">
      <div class="flex gap-4 flex-wrap">
        <input type="text" class="form-input" style="flex:1; min-width:200px;" placeholder="Search media..." $m-model="search">
        <select class="form-select" style="width:160px;" $m-model="filterType">
          <option value="">All Types</option>
          <option value="image">Images</option>
          <option value="document">Documents</option>
          <option value="video">Videos</option>
          <option value="audio">Audio</option>
        </select>
        <div class="btn-group">
          <button class="btn btn--sm" :class="viewMode === 'grid' ? 'btn--primary' : 'btn--ghost'" $m-on:click="viewMode = 'grid'">⊞ Grid</button>
          <button class="btn btn--sm" :class="viewMode === 'list' ? 'btn--primary' : 'btn--ghost'" $m-on:click="viewMode = 'list'">☰ List</button>
        </div>
      </div>
    </div>
  </div>

  {{-- Media Grid --}}
  <div $m-show="viewMode === 'grid'">
    <div class="grid grid-auto">
      @foreach($media ?? [] as $item)
      <div class="media-card" $m-on:click="selectMedia({{ $item->id }})">
        @if(str_starts_with($item->mime_type ?? '', 'image/'))
        <img src="/uploads/{{ $item->filename }}" alt="{{ $item->alt_text ?? $item->filename }}" class="media-card__image">
        @else
        <div class="media-card__icon">
          {{ match(true) {
            str_contains($item->mime_type ?? '', 'pdf') => '📄',
            str_contains($item->mime_type ?? '', 'video') => '🎬',
            str_contains($item->mime_type ?? '', 'audio') => '🎵',
            default => '📎'
          } }}
        </div>
        @endif
        <div class="media-card__info">
          <div class="media-card__name truncate">{{ $item->filename }}</div>
          <div class="media-card__meta text-xs text-muted">{{ $item->file_size ?? '' }}</div>
        </div>
      </div>
      @endforeach
      @empty($media)
      <div class="empty-state" style="grid-column: 1 / -1;">
        <div class="empty-state__icon">🖼️</div>
        <div class="empty-state__title">No media files</div>
        <p class="text-muted">Upload your first media file to get started.</p>
      </div>
      @endempty
    </div>
  </div>

  {{-- Media List --}}
  <div $m-show="viewMode === 'list'">
    <div class="card">
      <div class="card__body p-0">
        <table class="table table--hover">
          <thead>
            <tr>
              <th style="width:50px;"></th>
              <th>Filename</th>
              <th>Type</th>
              <th>Size</th>
              <th>Uploaded</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            @foreach($media ?? [] as $item)
            <tr>
              <td>
                @if(str_starts_with($item->mime_type ?? '', 'image/'))
                <img src="/uploads/{{ $item->filename }}" alt="" style="width:40px; height:40px; object-fit:cover; border-radius:var(--radius-sm);">
                @else
                <span style="font-size:1.5rem;">📎</span>
                @endif
              </td>
              <td class="font-medium">{{ $item->filename }}</td>
              <td class="text-sm text-muted">{{ $item->mime_type ?? '' }}</td>
              <td class="text-sm text-muted">{{ $item->file_size ?? '' }}</td>
              <td class="text-sm text-muted">{{ $item->created_at ?? '' }}</td>
              <td>
                <button class="btn btn--xs btn--ghost" $m-on:click="editMedia({{ $item->id }})">Edit</button>
                <button class="btn btn--xs btn--ghost text-danger" $m-on:click="deleteMedia({{ $item->id }})">Delete</button>
              </td>
            </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>
@endsection
