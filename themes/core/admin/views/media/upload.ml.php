@extends('layouts.admin')

@section('title', 'Upload Media')

@section('breadcrumb')
<a href="/admin">Dashboard</a>
<span class="admin-breadcrumb__sep">/</span>
<a href="/admin/media">Media</a>
<span class="admin-breadcrumb__sep">/</span>
<span>Upload</span>
@endsection

@section('content')
<div class="admin-content">
  <div class="admin-card">
    <div class="admin-card__header">
      <h2 class="admin-card__title">Upload Files</h2>
      <p class="admin-card__subtitle">
        Max file size: {{ $config->maxFileSizeFormatted }}.
        Drag and drop files or click to browse.
      </p>
    </div>
    <div class="admin-card__body">

      <form action="/admin/media/upload" method="POST" enctype="multipart/form-data" id="media-upload-form">
        {{-- Dropzone --}}
        <div class="media-dropzone" id="media-dropzone">
          <div class="media-dropzone__content">
            <i data-lucide="cloud-upload" class="w-12 h-12 media-dropzone__icon"></i>
            <p class="media-dropzone__title">Drop files here or click to upload</p>
            <p class="media-dropzone__hint">
              Supports: images, videos, audio, documents
            </p>
          </div>
          <input type="file" name="files[]" id="media-file-input" multiple
                 class="media-dropzone__input"
                 accept="{{ implode(',', $config->allowedMimeTypes) }}">
        </div>

        {{-- File Queue --}}
        <div class="media-upload-queue" id="media-upload-queue"></div>

        {{-- Submit --}}
        <div class="media-upload-actions" id="media-upload-actions">
          <button type="submit" class="btn btn--primary" id="media-upload-btn" disabled>
            <i data-lucide="upload" class="w-4 h-4"></i>
            <span id="media-upload-btn-text">Upload Files</span>
          </button>
          <a href="/admin/media" class="btn btn--ghost">Cancel</a>
        </div>
      </form>

    </div>
  </div>
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
  const dropzone = document.getElementById('media-dropzone');
  const fileInput = document.getElementById('media-file-input');
  const queue = document.getElementById('media-upload-queue');
  const uploadBtn = document.getElementById('media-upload-btn');
  const btnText = document.getElementById('media-upload-btn-text');

  // Click to open file dialog (ignore clicks from the input itself)
  dropzone.addEventListener('click', (e) => {
    if (e.target !== fileInput) fileInput.click();
  });

  // Drag & drop
  ['dragenter', 'dragover'].forEach(e =>
    dropzone.addEventListener(e, ev => { ev.preventDefault(); dropzone.classList.add('media-dropzone--active'); })
  );
  ['dragleave', 'drop'].forEach(e =>
    dropzone.addEventListener(e, ev => { ev.preventDefault(); dropzone.classList.remove('media-dropzone--active'); })
  );
  dropzone.addEventListener('drop', ev => {
    fileInput.files = ev.dataTransfer.files;
    updateQueue();
  });

  // File input change
  fileInput.addEventListener('change', updateQueue);

  function updateQueue() {
    const files = fileInput.files;
    queue.innerHTML = '';

    if (files.length === 0) {
      uploadBtn.disabled = true;
      return;
    }

    uploadBtn.disabled = false;
    btnText.textContent = 'Upload ' + files.length + ' File' + (files.length > 1 ? 's' : '');

    for (const file of files) {
      const item = document.createElement('div');
      item.className = 'media-queue-item';

      const isImage = file.type.startsWith('image/');
      const icon = isImage ? 'image' : file.type.startsWith('video/') ? 'video' : file.type.startsWith('audio/') ? 'music' : 'file-text';

      item.innerHTML =
        '<div class="media-queue-item__icon"><i data-lucide="' + icon + '" class="w-5 h-5"></i></div>' +
        '<div class="media-queue-item__info">' +
          '<span class="media-queue-item__name">' + file.name + '</span>' +
          '<span class="media-queue-item__size">' + formatSize(file.size) + '</span>' +
        '</div>';

      // Show thumbnail for images
      if (isImage) {
        const reader = new FileReader();
        reader.onload = (e) => {
          const img = document.createElement('img');
          img.src = e.target.result;
          img.className = 'media-queue-item__thumb';
          item.querySelector('.media-queue-item__icon').replaceWith(img);
        };
        reader.readAsDataURL(file);
      }

      queue.appendChild(item);
    }

    // Re-init lucide icons
    if (window.lucide) lucide.createIcons();
  }

  function formatSize(bytes) {
    if (bytes >= 1073741824) return (bytes / 1073741824).toFixed(1) + ' GB';
    if (bytes >= 1048576) return (bytes / 1048576).toFixed(1) + ' MB';
    if (bytes >= 1024) return (bytes / 1024).toFixed(1) + ' KB';
    return bytes + ' B';
  }
});
</script>
@endpush
@endsection
