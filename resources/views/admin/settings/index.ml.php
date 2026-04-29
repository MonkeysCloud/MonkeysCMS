@extends('layouts.admin')

@section('title', 'Settings')
@section('toolbar_title', 'Settings')

@section('content')
<div id="settings-app">

  <div style="display:grid; grid-template-columns:200px 1fr; gap:1.5rem;">

    {{-- Settings Groups Nav --}}
    <div>
      <nav style="position:sticky; top:calc(var(--cms-toolbar-height) + 1.5rem);">
        <template $m-for="group in groups">
          <button class="admin-sidebar__link" style="width:100%; text-align:left; text-transform:capitalize;"
                  :class="{ active: activeGroup === group }"
                  $m-on:click="activeGroup = group"
                  $m-text="group"></button>
        </template>
      </nav>
    </div>

    {{-- Settings Form --}}
    <div>
      <div class="card">
        <div class="card__header">
          <h3 class="card__title" style="text-transform:capitalize;" $m-text="activeGroup + ' Settings'"></h3>
          <button class="btn btn-primary btn-sm" $m-on:click="saveSettings()" :disabled="saving">
            <span $m-text="saving ? 'Saving...' : 'Save'"></span>
          </button>
        </div>
        <div class="card__body">
          <template $m-for="(value, key) in (settings[activeGroup] || {})">
            <div class="form-group" :key="key">
              <label class="form-label" style="text-transform:capitalize;" $m-text="key.replace(/_/g, ' ')"></label>

              {{-- Boolean --}}
              <template $m-if="typeof value === 'boolean'">
                <label style="display:flex; align-items:center; gap:0.5rem;">
                  <input type="checkbox" :checked="value" $m-on:change="settings[activeGroup][key] = $event.target.checked">
                  <span $m-text="value ? 'Enabled' : 'Disabled'"></span>
                </label>
              </template>

              {{-- Number --}}
              <template $m-if="typeof value === 'number'">
                <input type="number" class="form-input" :value="value"
                       $m-on:input="settings[activeGroup][key] = parseInt($event.target.value) || 0">
              </template>

              {{-- String (default) --}}
              <template $m-if="typeof value === 'string'">
                <input type="text" class="form-input" :value="value"
                       $m-on:input="settings[activeGroup][key] = $event.target.value">
              </template>
            </div>
          </template>

          <div $m-show="Object.keys(settings[activeGroup] || {}).length === 0"
               style="text-align:center; padding:2rem; color:var(--cms-text-muted);">
            No settings in this group.
          </div>
        </div>
      </div>

      <div $m-show="savedMessage" style="margin-top:0.75rem; text-align:center; font-size:0.85rem; color:var(--cms-success);"
           $m-text="savedMessage"></div>
    </div>
  </div>
</div>

@endsection

@push('scripts')
<script type="module">
import { createApp, createClient } from 'monkeysjs';

const api = createClient({ baseURL: '/admin/api/settings', headers: { 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });

const app = createApp({
  groups: [],
  settings: {},
  activeGroup: 'general',
  saving: false,
  savedMessage: '',

  async loadSettings() {
    try {
      const [groupsRes, settingsRes] = await Promise.all([
        api.get('/groups'),
        api.get('/'),
      ]);
      this.groups = groupsRes.data?.data || [];
      this.settings = settingsRes.data?.data || {};
      if (this.groups.length && !this.groups.includes(this.activeGroup)) {
        this.activeGroup = this.groups[0];
      }
    } catch (e) { console.error(e); }
  },

  async saveSettings() {
    this.saving = true;
    this.savedMessage = '';
    try {
      const payload = {};
      payload[this.activeGroup] = this.settings[this.activeGroup] || {};
      await api.put('/', JSON.stringify(payload));
      this.savedMessage = '✅ Settings saved';
      setTimeout(() => { this.savedMessage = ''; }, 3000);
    } catch (e) {
      this.savedMessage = '❌ Save failed';
      console.error(e);
    }
    this.saving = false;
  },
});

app.mount('#settings-app');
app.loadSettings();
</script>
@endpush
