@extends('layouts.admin')

@section('title', $title ?? 'Content')
@section('toolbar_title', 'Content')
@section('toolbar_actions')
<div style="display:flex; gap:0.5rem;">
  @foreach($contentTypes as $ct)
  <a href="/admin/content/create/{{ $ct['type_id'] }}" class="btn btn-primary btn-sm">+ {{ $ct['label'] }}</a>
  @endforeach
</div>
@endsection

@section('content')
<div id="content-list-app">

  {{-- Content Type Tabs --}}
  <div style="display:flex; gap:0.25rem; margin-bottom:1.5rem; border-bottom:1px solid var(--cms-border); padding-bottom:0;">
    @foreach($contentTypes as $ct)
    <button class="btn btn-sm"
            style="border-radius:var(--cms-radius-sm) var(--cms-radius-sm) 0 0; border-bottom:none;"
            :class="activeType === '{{ $ct['type_id'] }}' ? 'btn-primary' : 'btn-secondary'"
            $m-on:click="switchType('{{ $ct['type_id'] }}')">
      {{ $ct['icon'] ?? '📄' }} {{ $ct['label_plural'] ?? $ct['label'] }}
    </button>
    @endforeach
  </div>

  {{-- Search & Filters --}}
  <div style="display:flex; gap:0.75rem; margin-bottom:1rem; align-items:center;">
    <input type="text" class="form-input" placeholder="Search content..."
           style="max-width:300px;"
           $m-model="searchQuery"
           $m-on:input="debouncedSearch()">
    <select class="form-select" style="width:auto;" $m-model="statusFilter" $m-on:change="loadContent()">
      <option value="all">All Status</option>
      <option value="published">Published</option>
      <option value="draft">Draft</option>
    </select>
    <span style="margin-left:auto; font-size:0.85rem; color:var(--cms-text-muted);"
          $m-text="'Showing ' + items.length + ' of ' + meta.total + ' items'"></span>
  </div>

  {{-- Content Table --}}
  <div class="card">
    <div class="card__body" style="padding:0;">
      <table class="table">
        <thead>
          <tr>
            <th style="width:40%;">Title</th>
            <th>Status</th>
            <th>Author</th>
            <th>Updated</th>
            <th style="width:120px;">Actions</th>
          </tr>
        </thead>
        <tbody>
          <tr $m-show="loading">
            <td colspan="5" style="text-align:center; padding:2rem; color:var(--cms-text-muted);">Loading...</td>
          </tr>
          <tr $m-show="!loading && items.length === 0">
            <td colspan="5" style="text-align:center; padding:2rem; color:var(--cms-text-muted);">No content found</td>
          </tr>
          <template $m-for="item in items">
            <tr :key="item.id">
              <td>
                <a :href="'/admin/content/' + item.id + '/edit'" style="color:var(--cms-text); text-decoration:none; font-weight:500;"
                   $m-text="item.attributes.title"></a>
                <div style="font-size:0.75rem; color:var(--cms-text-muted);" $m-text="'/' + item.attributes.slug"></div>
              </td>
              <td>
                <span class="badge" :class="item.attributes.status === 'published' ? 'badge--published' : 'badge--draft'"
                      $m-text="item.attributes.status"></span>
              </td>
              <td style="font-size:0.85rem; color:var(--cms-text-muted);">—</td>
              <td style="font-size:0.85rem; color:var(--cms-text-muted);"
                  $m-text="item.attributes.updated_at ? new Date(item.attributes.updated_at).toLocaleDateString() : '—'"></td>
              <td>
                <div style="display:flex; gap:0.25rem;">
                  <a :href="'/admin/content/' + item.id + '/edit'" class="btn btn-secondary btn-sm">✏️</a>
                  <button class="btn btn-secondary btn-sm" $m-show="item.attributes.status !== 'published'"
                          $m-on:click="publish(item.id)" title="Publish">✅</button>
                  <button class="btn btn-secondary btn-sm" $m-show="item.attributes.status === 'published'"
                          $m-on:click="unpublish(item.id)" title="Unpublish">⏸️</button>
                  <button class="btn btn-danger btn-sm" $m-on:click="deleteItem(item.id)" title="Delete">🗑️</button>
                </div>
              </td>
            </tr>
          </template>
        </tbody>
      </table>
    </div>
  </div>

  {{-- Pagination --}}
  <div $m-show="meta.last_page > 1" style="display:flex; justify-content:center; gap:0.25rem; margin-top:1rem;">
    <button class="btn btn-secondary btn-sm" $m-on:click="goToPage(meta.page - 1)" :disabled="meta.page <= 1">← Prev</button>
    <span style="padding:0.5rem 0.75rem; font-size:0.85rem; color:var(--cms-text-muted);"
          $m-text="'Page ' + meta.page + ' of ' + meta.last_page"></span>
    <button class="btn btn-secondary btn-sm" $m-on:click="goToPage(meta.page + 1)" :disabled="meta.page >= meta.last_page">Next →</button>
  </div>
</div>

@endsection

@push('scripts')
<script type="module">
import { createApp, reactive, createClient, debounce } from 'monkeysjs';

const api = createClient({ baseURL: '/admin/api/content', headers: { 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });

const app = createApp({
  activeType: '{{ $activeType }}',
  items: [],
  meta: { total: 0, page: 1, per_page: 25, last_page: 1 },
  searchQuery: '',
  statusFilter: 'all',
  loading: false,

  async loadContent() {
    this.loading = true;
    try {
      const params = new URLSearchParams({ type: this.activeType, status: this.statusFilter, page: String(this.meta.page), per_page: '25' });
      if (this.searchQuery) params.set('q', this.searchQuery);
      const res = await api.get('/?' + params.toString());
      this.items = res.data?.data || [];
      if (res.data?.meta) Object.assign(this.meta, res.data.meta);
    } catch (e) { console.error(e); }
    this.loading = false;
  },

  switchType(type) {
    this.activeType = type;
    this.meta.page = 1;
    this.loadContent();
    history.replaceState(null, '', '/admin/content?type=' + type);
  },

  debouncedSearch: debounce(function() { this.meta.page = 1; this.loadContent(); }, 300),

  goToPage(page) { if (page >= 1 && page <= this.meta.last_page) { this.meta.page = page; this.loadContent(); } },

  async publish(id) { await api.post('/' + id + '/publish'); this.loadContent(); },
  async unpublish(id) { await api.post('/' + id + '/unpublish'); this.loadContent(); },
  async deleteItem(id) { if (confirm('Delete this content?')) { await api.delete('/' + id); this.loadContent(); } },
});

app.mount('#content-list-app');
app.loadContent();
</script>
@endpush
