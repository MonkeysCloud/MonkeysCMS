@extends('layouts.admin')

@section('title', 'Upload Plugin')

@section('breadcrumb')
<a href="/admin">Dashboard</a> › <a href="/admin/plugins">Extend</a> › <span>Upload</span>
@endsection

@section('page_title', 'Upload Plugin')

@section('content')
<div class="admin-page">

  <div class="card" style="max-width:600px">
    <div class="card__header">
      <h3 class="card__title"><i data-lucide="upload" class="w-4 h-4"></i> Upload Plugin Archive</h3>
    </div>

    <form action="/admin/plugins/upload" method="POST" enctype="multipart/form-data">
      <div class="card__body">

        <div class="form-group" style="margin-bottom:1.25rem">
          <label for="plugin_zip" class="form-label">Plugin ZIP File</label>
          <input
            type="file"
            name="plugin_zip"
            id="plugin_zip"
            accept=".zip"
            class="form-control"
            required
          >
          <p class="text-xs text-muted" style="margin-top:.5rem">
            Upload a <code>.zip</code> file containing the plugin. The archive must include a <code>*.plugin.mlc</code> file.
            Maximum file size: 50 MB.
          </p>
        </div>

        <div class="alert alert--info" style="margin-bottom:0">
          <i data-lucide="info" class="w-4 h-4"></i>
          <div>
            <strong>Plugin structure requirements:</strong>
            <ul style="margin:.5rem 0 0 1rem;font-size:.85rem">
              <li><code>plugin-name.plugin.mlc</code> — Plugin metadata file (required)</li>
              <li><code>src/</code> — PHP source files with the plugin provider class</li>
              <li><code>views/</code> — Template files (optional)</li>
              <li><code>assets/</code> — CSS/JS files (optional)</li>
              <li><code>migrations/</code> — Database migrations (optional)</li>
            </ul>
          </div>
        </div>

      </div>
      <div class="card__footer" style="display:flex;gap:.75rem;justify-content:flex-end">
        <a href="/admin/plugins" class="btn btn--ghost">Cancel</a>
        <button type="submit" class="btn btn--primary">
          <i data-lucide="upload" class="w-4 h-4"></i> Upload & Install
        </button>
      </div>
    </form>
  </div>

</div>
@endsection
