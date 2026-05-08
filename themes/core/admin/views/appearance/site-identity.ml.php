@extends('layouts.admin')
@section('title', 'Site Identity')

@section('toolbar_actions')
<a href="/admin/appearance" class="btn btn--sm btn--ghost">
  <i data-lucide="arrow-left" class="w-4 h-4"></i> Back to Themes
</a>
@endsection

@section('content')
<div class="admin-content site-identity-page">

  @if(!empty($flashSuccess))
  <div class="alert alert--success mb-4">
    <i data-lucide="check-circle" class="w-4 h-4"></i>
    {{ $flashSuccess }}
  </div>
  @endif

  @if(!empty($flashError))
  <div class="alert alert--error mb-4">
    <i data-lucide="alert-circle" class="w-4 h-4"></i>
    {{ $flashError }}
  </div>
  @endif

  <form action="/admin/appearance/site-identity" method="POST" class="site-identity-form" enctype="multipart/form-data">

    {{-- Logo Section --}}
    <div class="card mb-4">
      <div class="card__header">
        <h3 class="card__title">
          <i data-lucide="image" class="w-4 h-4"></i> Logo
        </h3>
      </div>
      <div class="card__body">
        <div class="logo-upload-area">
          <div class="logo-preview" id="logo-preview">
            @if(!empty($settings['site_logo']))
            <img src="{{ $settings['site_logo'] }}" alt="Site Logo" id="logo-img">
            @else
            <div class="logo-placeholder">
              <i data-lucide="image-plus" class="w-8 h-8"></i>
              <span>No logo set</span>
            </div>
            @endif
          </div>
          <div class="logo-controls">
            <p class="text-muted text-sm mb-3">Upload your site logo. Recommended size: 200×60px. Supports PNG, SVG, JPG.</p>
            <div class="form-group">
              <label class="form-label">Logo URL</label>
              <input type="text" name="site_logo" value="{{ $settings['site_logo'] ?? '' }}"
                     class="form-input" id="logo-url-input"
                     placeholder="/uploads/logo.png or https://...">
            </div>
            <button type="button" class="btn btn--sm btn--ghost mt-2" onclick="document.getElementById('logo-url-input').value=''; document.getElementById('logo-img')?.remove(); document.querySelector('.logo-placeholder')?.style.removeProperty('display');">
              <i data-lucide="x" class="w-3.5 h-3.5"></i> Remove Logo
            </button>
          </div>
        </div>
      </div>
    </div>

    {{-- Site Info Section --}}
    <div class="card mb-4">
      <div class="card__header">
        <h3 class="card__title">
          <i data-lucide="type" class="w-4 h-4"></i> Site Information
        </h3>
      </div>
      <div class="card__body">
        <div class="form-group mb-4">
          <label class="form-label" for="site_name">Site Name</label>
          <input type="text" name="site_name" id="site_name"
                 value="{{ $settings['site_name'] ?? '' }}"
                 class="form-input" placeholder="My Website">
          <p class="form-help">The name of your website, displayed in the header, title bar, and meta tags.</p>
        </div>

        <div class="form-group mb-4">
          <label class="form-label" for="site_tagline">Tagline</label>
          <input type="text" name="site_tagline" id="site_tagline"
                 value="{{ $settings['site_tagline'] ?? '' }}"
                 class="form-input" placeholder="A brief description of your site">
          <p class="form-help">A short phrase describing what your site is about.</p>
        </div>
      </div>
    </div>

    {{-- Favicon Section --}}
    <div class="card mb-4">
      <div class="card__header">
        <h3 class="card__title">
          <i data-lucide="bookmark" class="w-4 h-4"></i> Favicon
        </h3>
      </div>
      <div class="card__body">
        <div class="favicon-area">
          <div class="favicon-preview">
            @if(!empty($settings['site_favicon']))
            <img src="{{ $settings['site_favicon'] }}" alt="Favicon" class="favicon-img">
            @else
            <div class="favicon-placeholder">
              <i data-lucide="bookmark" class="w-5 h-5"></i>
            </div>
            @endif
          </div>
          <div class="favicon-controls">
            <p class="text-muted text-sm mb-3">Upload a favicon (browser tab icon). Recommended: 32×32px ICO, PNG, or SVG.</p>
            <div class="form-group">
              <label class="form-label">Favicon URL</label>
              <input type="text" name="site_favicon" value="{{ $settings['site_favicon'] ?? '' }}"
                     class="form-input"
                     placeholder="/uploads/favicon.ico or /favicon.png">
            </div>
          </div>
        </div>
      </div>
    </div>

    {{-- Save --}}
    <div class="form-actions">
      <button type="submit" class="btn btn--primary">
        <i data-lucide="save" class="w-4 h-4"></i> Save Changes
      </button>
    </div>

  </form>

</div>

@push('head')
<style>
.site-identity-page { padding: 1.5rem 2rem; max-width: 900px; }
.site-identity-form .card__header {
  padding: 0.75rem 1.25rem;
  border-bottom: 1px solid rgba(255,255,255,0.04);
}
.site-identity-form .card__title {
  display: flex; align-items: center; gap: 0.5rem;
  font-size: 0.9rem; font-weight: 600; color: #e2e8f0;
}
.site-identity-form .card__body { padding: 1.25rem; }

/* Logo */
.logo-upload-area {
  display: flex; gap: 1.5rem; align-items: flex-start;
}
.logo-preview {
  flex-shrink: 0; width: 200px; height: 120px;
  background: rgba(15,17,28,0.6);
  border: 2px dashed rgba(255,255,255,0.08);
  border-radius: 12px;
  display: flex; align-items: center; justify-content: center;
  overflow: hidden;
}
.logo-preview img {
  max-width: 100%; max-height: 100%; object-fit: contain;
}
.logo-placeholder {
  display: flex; flex-direction: column;
  align-items: center; gap: 0.4rem;
  color: rgba(255,255,255,0.2);
  font-size: 0.75rem;
}
.logo-controls { flex: 1; }

/* Favicon */
.favicon-area {
  display: flex; gap: 1.5rem; align-items: flex-start;
}
.favicon-preview {
  flex-shrink: 0; width: 64px; height: 64px;
  background: rgba(15,17,28,0.6);
  border: 2px dashed rgba(255,255,255,0.08);
  border-radius: 10px;
  display: flex; align-items: center; justify-content: center;
  overflow: hidden;
}
.favicon-img { width: 32px; height: 32px; object-fit: contain; }
.favicon-placeholder { color: rgba(255,255,255,0.2); }
.favicon-controls { flex: 1; }

/* Form */
.form-group { margin-bottom: 0; }
.form-label {
  display: block; font-size: 0.78rem; font-weight: 600;
  color: #cbd5e1; margin-bottom: 0.35rem;
}
.form-input {
  width: 100%; padding: 0.55rem 0.85rem;
  background: rgba(15,17,28,0.5);
  border: 1px solid rgba(255,255,255,0.08);
  border-radius: 8px; color: #e2e8f0;
  font-size: 0.82rem;
  transition: border-color 0.2s;
}
.form-input:focus {
  outline: none;
  border-color: rgba(99,102,241,0.5);
  box-shadow: 0 0 0 3px rgba(99,102,241,0.1);
}
.form-help {
  font-size: 0.72rem; color: #64748b; margin-top: 0.35rem;
}
.form-actions {
  display: flex; justify-content: flex-end; padding-top: 0.5rem;
}

@media (max-width: 640px) {
  .logo-upload-area, .favicon-area { flex-direction: column; }
  .logo-preview { width: 100%; }
}
</style>
@endpush

@endsection
