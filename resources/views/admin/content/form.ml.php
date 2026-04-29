@extends('layouts.admin')

@section('title', $title ?? 'Edit Content')
@section('toolbar_title', $isNew ? 'Create ' . ($contentType['label'] ?? 'Content') : 'Edit: ' . ($node->title ?? ''))

@section('content')
<div id="content-form-app">
  <div style="display:grid; grid-template-columns:1fr 320px; gap:1.5rem;">

    {{-- Main Form --}}
    <div>
      <div class="card" style="margin-bottom:1rem;">
        <div class="card__body">
          <div class="form-group">
            <label class="form-label">Title</label>
            <input type="text" class="form-input" $m-model="form.title" $m-on:input="generateSlug()" style="font-size:1.25rem; font-weight:600;" placeholder="Enter title...">
          </div>
          <div class="form-group">
            <label class="form-label">URL Slug</label>
            <div style="display:flex; align-items:center; gap:0.25rem;">
              <span style="color:var(--cms-text-muted); font-size:0.85rem;">/{{ $contentType['type_id'] ?? '' }}/</span>
              <input type="text" class="form-input" $m-model="form.slug" style="flex:1;">
            </div>
          </div>
        </div>
      </div>

      {{-- Body Editor --}}
      <div class="card" style="margin-bottom:1rem;">
        <div class="card__header">
          <span class="card__title">Body</span>
          @if(($contentType['mosaic_enabled'] ?? false))
          <a :href="'/admin/mosaic/' + (form.id || 'new')" class="btn btn-secondary btn-sm" $m-show="form.id">🧩 Mosaic Editor</a>
          @endif
        </div>
        <div class="card__body">
          <textarea class="form-textarea" $m-model="form.body" rows="12" placeholder="Content body..."></textarea>
        </div>
      </div>

      {{-- Dynamic Fields --}}
      @if(!empty($fields))
      <div class="card" style="margin-bottom:1rem;">
        <div class="card__header"><span class="card__title">Fields</span></div>
        <div class="card__body">
          @foreach($fields as $field)
          <div class="form-group">
            <label class="form-label">{{ $field['name'] }} @if($field['required'])<span style="color:var(--cms-danger);">*</span>@endif</label>
            @if($field['help_text'])<div style="font-size:0.75rem; color:var(--cms-text-muted); margin-bottom:0.25rem;">{{ $field['help_text'] }}</div>@endif

            @if(in_array($field['field_type'], ['string', 'email', 'url', 'phone', 'slug']))
            <input type="text" class="form-input" $m-model="form.fields.{{ $field['machine_name'] }}">
            @elseif(in_array($field['field_type'], ['text', 'html', 'markdown', 'code']))
            <textarea class="form-textarea" rows="4" $m-model="form.fields.{{ $field['machine_name'] }}"></textarea>
            @elseif($field['field_type'] === 'boolean')
            <label style="display:flex; align-items:center; gap:0.5rem;">
              <input type="checkbox" $m-model="form.fields.{{ $field['machine_name'] }}">
              <span>{{ $field['name'] }}</span>
            </label>
            @elseif(in_array($field['field_type'], ['integer', 'float', 'decimal']))
            <input type="number" class="form-input" $m-model="form.fields.{{ $field['machine_name'] }}">
            @else
            <input type="text" class="form-input" $m-model="form.fields.{{ $field['machine_name'] }}">
            @endif
          </div>
          @endforeach
        </div>
      </div>
      @endif
    </div>

    {{-- Sidebar --}}
    <div>
      {{-- Publish Box --}}
      <div class="card" style="margin-bottom:1rem;">
        <div class="card__header"><span class="card__title">Publish</span></div>
        <div class="card__body">
          <div class="form-group">
            <label class="form-label">Status</label>
            <select class="form-select" $m-model="form.status">
              <option value="draft">Draft</option>
              <option value="published">Published</option>
            </select>
          </div>
          <div class="form-group" $m-show="form.status === 'published'">
            <label class="form-label">Publish Date</label>
            <input type="datetime-local" class="form-input" $m-model="form.published_at">
          </div>
          <div style="display:flex; gap:0.5rem; margin-top:1rem;">
            <button class="btn btn-primary" style="flex:1;" $m-on:click="saveContent()" :disabled="saving">
              <span $m-text="saving ? 'Saving...' : (isNew ? 'Create' : 'Update')"></span>
            </button>
          </div>
          <div $m-show="savedMessage" style="margin-top:0.5rem; font-size:0.85rem; color:var(--cms-success); text-align:center;"
               $m-text="savedMessage"></div>
        </div>
      </div>

      {{-- Summary / SEO --}}
      <div class="card" style="margin-bottom:1rem;">
        <div class="card__header"><span class="card__title">SEO & Summary</span></div>
        <div class="card__body">
          <div class="form-group">
            <label class="form-label">Summary</label>
            <textarea class="form-textarea" rows="3" $m-model="form.summary" placeholder="Brief summary..."></textarea>
          </div>
          <div class="form-group">
            <label class="form-label">Meta Title</label>
            <input type="text" class="form-input" $m-model="form.meta_title" placeholder="SEO title...">
            <span style="font-size:0.75rem; color:var(--cms-text-muted);" $m-text="(form.meta_title || '').length + '/60'"></span>
          </div>
          <div class="form-group">
            <label class="form-label">Meta Description</label>
            <textarea class="form-textarea" rows="2" $m-model="form.meta_description" placeholder="SEO description..."></textarea>
            <span style="font-size:0.75rem; color:var(--cms-text-muted);" $m-text="(form.meta_description || '').length + '/160'"></span>
          </div>
        </div>
      </div>

      {{-- Language --}}
      <div class="card">
        <div class="card__header"><span class="card__title">Options</span></div>
        <div class="card__body">
          <div class="form-group">
            <label class="form-label">Language</label>
            <select class="form-select" $m-model="form.language">
              <option value="en">English</option>
              <option value="es">Español</option>
              <option value="fr">Français</option>
              <option value="de">Deutsch</option>
            </select>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

@endsection

@push('scripts')
<script type="module">
import { createApp, createClient } from 'monkeysjs';

const api = createClient({ baseURL: '/admin/api/content', headers: { 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });

const app = createApp({
  isNew: {{ $isNew ? 'true' : 'false' }},
  saving: false,
  savedMessage: '',
  form: {
    id: {{ $node->id ?? 'null' }},
    content_type: '{{ $contentType['type_id'] ?? 'page' }}',
    title: {!! json_encode($node->title ?? '') !!},
    slug: {!! json_encode($node->slug ?? '') !!},
    body: {!! json_encode($node->body ?? '') !!},
    summary: {!! json_encode($node->summary ?? '') !!},
    status: {!! json_encode($node->status ?? 'draft') !!},
    published_at: {!! json_encode($node->published_at?->format('Y-m-d\\TH:i') ?? '') !!},
    meta_title: {!! json_encode($node->meta_title ?? '') !!},
    meta_description: {!! json_encode($node->meta_description ?? '') !!},
    language: {!! json_encode($node->language ?? 'en') !!},
    fields: {!! json_encode($node->fields ?? (object)[]) !!},
  },

  generateSlug() {
    if (this.isNew || !this.form.slug) {
      this.form.slug = this.form.title.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '');
    }
  },

  async saveContent() {
    this.saving = true;
    this.savedMessage = '';
    try {
      let res;
      if (this.isNew) {
        res = await api.post('/', this.form);
        const newId = res.data?.data?.id;
        if (newId) {
          this.form.id = newId;
          this.isNew = false;
          history.replaceState(null, '', '/admin/content/' + newId + '/edit');
        }
      } else {
        res = await api.put('/' + this.form.id, this.form);
      }
      this.savedMessage = '✅ Saved successfully';
      setTimeout(() => { this.savedMessage = ''; }, 3000);
    } catch (e) {
      this.savedMessage = '❌ Save failed';
      console.error(e);
    }
    this.saving = false;
  },
});

app.mount('#content-form-app');
</script>
@endpush
