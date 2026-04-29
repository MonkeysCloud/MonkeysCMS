@extends('layouts.admin')

@section('title', 'Media Library')
@section('toolbar_title', 'Media Library')
@section('toolbar_actions')
<label class="btn btn-primary btn-sm" for="media-upload" style="cursor:pointer;">📤 Upload</label>
<input type="file" id="media-upload" style="display:none;" multiple accept="image/*,video/*,audio/*,.pdf,.doc,.docx,.xls,.xlsx"
       $m-on:change="uploadFiles($event)">
@endsection

@section('content')
<div id="media-app">

  {{-- Filters --}}
  <div style="display:flex; gap:0.75rem; margin-bottom:1rem; align-items:center;">
    <select class="form-select" style="width:auto;" $m-model="typeFilter" $m-on:change="loadMedia()">
      <option value="">All Types</option>
      <option value="image">Images</option>
      <option value="video">Videos</option>
      <option value="audio">Audio</option>
      <option value="application">Documents</option>
    </select>
    <span style="margin-left:auto; font-size:0.85rem; color:var(--cms-text-muted);"
          $m-text="meta.total + ' files'"></span>
  </div>

  {{-- Upload Progress --}}
  <div $m-show="uploading" class="card" style="margin-bottom:1rem;">
    <div class="card__body" style="text-align:center; padding:1.5rem;">
      <div style="font-size:0.9rem; color:var(--cms-text-muted);">Uploading...</div>
      <div style="margin-top:0.5rem; background:var(--cms-bg-card); border-radius:9999px; height:6px; overflow:hidden;">
        <div style="background:var(--cms-primary); height:100%; transition:width 200ms;" :style="'width:' + uploadProgress + '%'"></div>
      </div>
    </div>
  </div>

  {{-- Media Grid --}}
  <div style="display:grid; grid-template-columns:repeat(auto-fill, minmax(180px, 1fr)); gap:1rem;">
    <template $m-for="item in items">
      <div class="card" style="cursor:pointer; transition:all 200ms;"
           :key="item.id"
           :class="{ 'ring': selectedId === item.id }"
           $m-on:click="selectItem(item)">
        <div style="aspect-ratio:1; overflow:hidden; background:var(--cms-bg-card); display:flex; align-items:center; justify-content:center;">
          {{-- Image preview --}}
          <img $m-show="item.attributes.media_type === 'image'"
               :src="item.attributes.url" :alt="item.attributes.alt || ''"
               style="width:100%; height:100%; object-fit:cover;">
          {{-- Non-image icon --}}
          <div $m-show="item.attributes.media_type !== 'image'"
               style="font-size:3rem; color:var(--cms-text-muted);"
               $m-text="item.attributes.media_type === 'video' ? '🎬' : item.attributes.media_type === 'audio' ? '🎵' : '📄'">
          </div>
        </div>
        <div style="padding:0.5rem;">
          <div style="font-size:0.8rem; font-weight:500; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;"
               $m-text="item.attributes.original_name"></div>
          <div style="font-size:0.7rem; color:var(--cms-text-muted);"
               $m-text="item.attributes.formatted_size"></div>
        </div>
      </div>
    </template>
  </div>

  {{-- Empty state --}}
  <div $m-show="!loading && items.length === 0" style="text-align:center; padding:4rem; color:var(--cms-text-muted);">
    <div style="font-size:3rem; margin-bottom:1rem;">🖼️</div>
    <div>No media files yet. Upload your first file above.</div>
  </div>

  {{-- Pagination --}}
  <div $m-show="meta.last_page > 1" style="display:flex; justify-content:center; gap:0.25rem; margin-top:1.5rem;">
    <button class="btn btn-secondary btn-sm" $m-on:click="goToPage(meta.page - 1)" :disabled="meta.page <= 1">← Prev</button>
    <span style="padding:0.5rem 0.75rem; font-size:0.85rem; color:var(--cms-text-muted);"
          $m-text="'Page ' + meta.page + ' of ' + meta.last_page"></span>
    <button class="btn btn-secondary btn-sm" $m-on:click="goToPage(meta.page + 1)" :disabled="meta.page >= meta.last_page">Next →</button>
  </div>

  {{-- Detail Sidebar (Modal) --}}
  <div $m-show="selectedItem" style="position:fixed; inset:0; background:rgba(0,0,0,0.5); z-index:200; display:flex; justify-content:flex-end;"
       $m-on:click.self="selectedItem = null; selectedId = null;">
    <div style="width:400px; background:var(--cms-bg-surface); height:100%; overflow-y:auto; padding:1.5rem; border-left:1px solid var(--cms-border);">
      <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1rem;">
        <h3 style="font-weight:600; color:var(--cms-text-heading);">Media Details</h3>
        <button class="btn btn-secondary btn-sm" $m-on:click="selectedItem = null; selectedId = null;">✕</button>
      </div>

      <template $m-if="selectedItem">
        {{-- Preview --}}
        <div style="margin-bottom:1rem; border-radius:var(--cms-radius); overflow:hidden; background:var(--cms-bg-card);">
          <img $m-show="selectedItem.attributes.media_type === 'image'"
               :src="selectedItem.attributes.url" :alt="selectedItem.attributes.alt || ''"
               style="width:100%; display:block;">
          <div $m-show="selectedItem.attributes.media_type !== 'image'"
               style="padding:3rem; text-align:center; font-size:4rem;"
               $m-text="selectedItem.attributes.media_type === 'video' ? '🎬' : '📄'"></div>
        </div>

        {{-- Info --}}
        <div style="font-size:0.85rem; color:var(--cms-text-muted); margin-bottom:1rem;">
          <div $m-text="selectedItem.attributes.original_name" style="font-weight:500; color:var(--cms-text);"></div>
          <div $m-text="selectedItem.attributes.mime_type"></div>
          <div $m-text="selectedItem.attributes.formatted_size"></div>
          <div $m-show="selectedItem.attributes.width" $m-text="selectedItem.attributes.width + ' × ' + selectedItem.attributes.height + ' px'"></div>
        </div>

        {{-- Editable fields --}}
        <div class="form-group">
          <label class="form-label">Alt Text</label>
          <input type="text" class="form-input" $m-model="editAlt">
        </div>
        <div class="form-group">
          <label class="form-label">Title</label>
          <input type="text" class="form-input" $m-model="editTitle">
        </div>
        <div class="form-group">
          <label class="form-label">Description</label>
          <textarea class="form-textarea" rows="3" $m-model="editDescription"></textarea>
        </div>

        {{-- URL for copy --}}
        <div class="form-group">
          <label class="form-label">URL</label>
          <input type="text" class="form-input" readonly :value="selectedItem.attributes.url" $m-on:click="$event.target.select()">
        </div>

        <div style="display:flex; gap:0.5rem; margin-top:1rem;">
          <button class="btn btn-primary" style="flex:1;" $m-on:click="updateMedia()">Save</button>
          <button class="btn btn-danger" $m-on:click="deleteMedia()">Delete</button>
        </div>
      </template>
    </div>
  </div>
</div>

@endsection

@push('scripts')
<script type="module">
import { createApp, createClient } from 'monkeysjs';

const api = createClient({ baseURL: '/admin/api/media', headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });

const app = createApp({
  items: [], meta: { total: 0, page: 1, per_page: 50, last_page: 1 },
  typeFilter: '', loading: false,
  uploading: false, uploadProgress: 0,
  selectedItem: null, selectedId: null,
  editAlt: '', editTitle: '', editDescription: '',

  async loadMedia() {
    this.loading = true;
    const params = new URLSearchParams({ page: String(this.meta.page), per_page: '50' });
    if (this.typeFilter) params.set('type', this.typeFilter);
    try {
      const res = await api.get('/?' + params.toString());
      this.items = res.data?.data || [];
      if (res.data?.meta) Object.assign(this.meta, res.data.meta);
    } catch (e) { console.error(e); }
    this.loading = false;
  },

  selectItem(item) {
    this.selectedItem = item;
    this.selectedId = item.id;
    this.editAlt = item.attributes.alt || '';
    this.editTitle = item.attributes.title || '';
    this.editDescription = item.attributes.description || '';
  },

  async uploadFiles(e) {
    const files = e.target.files;
    if (!files.length) return;
    this.uploading = true;
    this.uploadProgress = 0;
    const total = files.length;
    let done = 0;
    for (const file of files) {
      const fd = new FormData();
      fd.append('file', file);
      try {
        await fetch('/admin/api/media/upload', { method: 'POST', body: fd, headers: { 'X-Requested-With': 'XMLHttpRequest' } });
      } catch (err) { console.error('Upload failed:', err); }
      done++;
      this.uploadProgress = Math.round((done / total) * 100);
    }
    this.uploading = false;
    e.target.value = '';
    this.loadMedia();
  },

  async updateMedia() {
    if (!this.selectedItem) return;
    await api.put('/' + this.selectedItem.id, JSON.stringify({ alt: this.editAlt, title: this.editTitle, description: this.editDescription }));
    this.selectedItem.attributes.alt = this.editAlt;
    this.selectedItem.attributes.title = this.editTitle;
  },

  async deleteMedia() {
    if (!this.selectedItem || !confirm('Delete this file?')) return;
    await api.delete('/' + this.selectedItem.id);
    this.selectedItem = null;
    this.selectedId = null;
    this.loadMedia();
  },

  goToPage(p) { if (p >= 1 && p <= this.meta.last_page) { this.meta.page = p; this.loadMedia(); } },
});

app.mount('#media-app');
app.loadMedia();
</script>
@endpush
