@extends('layouts.admin')

@section('title', 'Settings')
@section('page_title', 'Settings')

@section('breadcrumb')
<a href="/admin" class="breadcrumb__item">Dashboard</a>
<span class="breadcrumb__sep">›</span>
<span class="breadcrumb__item breadcrumb__item--active">Settings</span>
@endsection

@section('toolbar_actions')
<button type="submit" form="settings-form" class="btn btn--primary btn--sm" data-save-btn>💾 Save Settings</button>
@endsection

@section('content')
<form id="settings-form" method="POST" action="/admin/settings">

@if(!empty($flashSuccess))
<div class="alert alert--success mb-4">
  <i data-lucide="check-circle" class="w-4 h-4"></i>
  {{ $flashSuccess }}
</div>
@endif

@if(!empty($flashError))
<div class="alert alert--danger mb-4">
  <i data-lucide="alert-circle" class="w-4 h-4"></i>
  {{ $flashError }}
</div>
@endif

  {{-- Settings Tabs --}}
  <div class="tabs mb-4">
    <button class="tabs__item" :class="{ active: activeTab === 'general' }" $m-on:click="activeTab = 'general'">General</button>
    <button class="tabs__item" :class="{ active: activeTab === 'content' }" $m-on:click="activeTab = 'content'">Content</button>
    <button class="tabs__item" :class="{ active: activeTab === 'media' }" $m-on:click="activeTab = 'media'">Media</button>
    <button class="tabs__item" :class="{ active: activeTab === 'api' }" $m-on:click="activeTab = 'api'">API</button>
    <button class="tabs__item" :class="{ active: activeTab === 'advanced' }" $m-on:click="activeTab = 'advanced'">Advanced</button>
  </div>

  {{-- General Settings --}}
  <div $m-show="activeTab === 'general'">
    <div class="card mb-4">
      <div class="card__header"><h3 class="card__title">Site Information</h3></div>
      <div class="card__body">
        <div class="form-group">
          <label class="form-label">Site Name</label>
          <input type="text" name="site_name" class="form-input" value="{{ $settings['site_name'] ?? '' }}">
        </div>
        <div class="form-group">
          <label class="form-label">Tagline</label>
          <input type="text" name="site_tagline" class="form-input" value="{{ $settings['site_tagline'] ?? '' }}">
        </div>
        <div class="form-group">
          <label class="form-label">Site URL</label>
          <input type="url" name="site_url" class="form-input" value="{{ $settings['site_url'] ?? '' }}">
        </div>
        <div class="form-group">
          <label class="form-label">Contact Email</label>
          <input type="email" name="contact_email" class="form-input" value="{{ $settings['contact_email'] ?? '' }}">
        </div>
        <div class="form-group">
          <label class="form-label">Timezone</label>
          <select name="timezone" class="form-select">
            <option value="UTC" {{ ($settings['timezone'] ?? 'UTC') === 'UTC' ? 'selected' : '' }}>UTC</option>
            <option value="America/Mexico_City" {{ ($settings['timezone'] ?? '') === 'America/Mexico_City' ? 'selected' : '' }}>Mexico City</option>
            <option value="America/New_York" {{ ($settings['timezone'] ?? '') === 'America/New_York' ? 'selected' : '' }}>Eastern (US)</option>
            <option value="America/Los_Angeles" {{ ($settings['timezone'] ?? '') === 'America/Los_Angeles' ? 'selected' : '' }}>Pacific (US)</option>
            <option value="Europe/London" {{ ($settings['timezone'] ?? '') === 'Europe/London' ? 'selected' : '' }}>London</option>
            <option value="Europe/Madrid" {{ ($settings['timezone'] ?? '') === 'Europe/Madrid' ? 'selected' : '' }}>Madrid</option>
          </select>
        </div>
      </div>
    </div>
  </div>

  {{-- Content Settings --}}
  <div $m-show="activeTab === 'content'">
    <div class="card mb-4">
      <div class="card__header"><h3 class="card__title">Content Settings</h3></div>
      <div class="card__body">
        <div class="form-group">
          <label class="form-label">Default Content Status</label>
          <select name="default_status" class="form-select">
            <option value="draft" {{ ($settings['default_status'] ?? 'draft') === 'draft' ? 'selected' : '' }}>Draft</option>
            <option value="published" {{ ($settings['default_status'] ?? '') === 'published' ? 'selected' : '' }}>Published</option>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">Posts Per Page</label>
          <input type="number" name="posts_per_page" class="form-input" value="{{ $settings['posts_per_page'] ?? 10 }}" min="1" max="100">
        </div>
        <div class="form-group">
          <label class="form-check">
            <input type="checkbox" name="enable_revisions" value="1" {{ !empty($settings['enable_revisions']) ? 'checked' : '' }}>
            <span>Enable content revisions</span>
          </label>
        </div>
      </div>
    </div>

    {{-- Comments Global Toggle --}}
    <div class="card mb-4">
      <div class="card__header">
        <h3 class="card__title"><i data-lucide="message-circle" class="w-4 h-4" style="color:#818cf8;vertical-align:-2px;margin-right:.35rem"></i>Comments</h3>
      </div>
      <div class="card__body">
        <div class="settings-toggle-row">
          <div class="settings-toggle-info">
            <div class="settings-toggle-label">Enable Comments Globally</div>
            <div class="settings-toggle-desc">Allow visitors to submit comments on content. Individual content types can still be toggled separately in their settings.</div>
          </div>
          <label class="form-toggle" style="margin:0">
            <input type="hidden" name="enable_comments" value="0">
            <input type="checkbox" name="enable_comments" value="1" {{ !empty($settings['enable_comments']) ? 'checked' : '' }}>
            <span class="form-toggle__slider"></span>
          </label>
        </div>

        <div class="settings-divider"></div>

        <div class="settings-toggle-row">
          <div class="settings-toggle-info">
            <div class="settings-toggle-label">Require Moderation</div>
            <div class="settings-toggle-desc">New comments must be approved by an admin before appearing publicly.</div>
          </div>
          <label class="form-toggle" style="margin:0">
            <input type="hidden" name="comments_moderation" value="0">
            <input type="checkbox" name="comments_moderation" value="1" {{ !empty($settings['comments_moderation']) ? 'checked' : '' }}>
            <span class="form-toggle__slider"></span>
          </label>
        </div>

        <div class="settings-divider"></div>

        <div class="settings-toggle-row">
          <div class="settings-toggle-info">
            <div class="settings-toggle-label">Allow Threaded Replies</div>
            <div class="settings-toggle-desc">Let visitors reply to existing comments to create conversation threads.</div>
          </div>
          <label class="form-toggle" style="margin:0">
            <input type="hidden" name="comments_threaded" value="0">
            <input type="checkbox" name="comments_threaded" value="1" {{ !empty($settings['comments_threaded']) ? 'checked' : '' }}>
            <span class="form-toggle__slider"></span>
          </label>
        </div>

        <div class="settings-divider"></div>

        <div class="settings-toggle-row">
          <div class="settings-toggle-info">
            <div class="settings-toggle-label">Require Login to Comment</div>
            <div class="settings-toggle-desc">Only authenticated users can post comments. Anonymous visitors will see a login prompt.</div>
          </div>
          <label class="form-toggle" style="margin:0">
            <input type="hidden" name="comments_require_login" value="0">
            <input type="checkbox" name="comments_require_login" value="1" {{ !empty($settings['comments_require_login']) ? 'checked' : '' }}>
            <span class="form-toggle__slider"></span>
          </label>
        </div>
      </div>
    </div>
  </div>

  {{-- Media Settings --}}
  <div $m-show="activeTab === 'media'">
    <div class="card mb-4">
      <div class="card__header"><h3 class="card__title">Media Settings</h3></div>
      <div class="card__body">
        <div class="form-group">
          <label class="form-label">Max Upload Size (MB)</label>
          <input type="number" name="max_upload_size" class="form-input" value="{{ $settings['max_upload_size'] ?? 10 }}">
        </div>
        <div class="form-group">
          <label class="form-label">Allowed File Types</label>
          <input type="text" name="allowed_types" class="form-input" value="{{ $settings['allowed_types'] ?? 'jpg,jpeg,png,gif,webp,svg,pdf,mp4,mp3' }}" placeholder="jpg,png,pdf,...">
          <small class="text-muted">Comma-separated file extensions</small>
        </div>
        <div class="form-group">
          <label class="form-label">Thumbnail Size</label>
          <div class="flex gap-2">
            <input type="number" name="thumb_width" class="form-input" value="{{ $settings['thumb_width'] ?? 300 }}" placeholder="Width">
            <span class="flex-center">×</span>
            <input type="number" name="thumb_height" class="form-input" value="{{ $settings['thumb_height'] ?? 200 }}" placeholder="Height">
          </div>
        </div>
      </div>
    </div>
  </div>

  {{-- API Settings --}}
  <div $m-show="activeTab === 'api'">
    <div class="card mb-4">
      <div class="card__header"><h3 class="card__title">API Settings</h3></div>
      <div class="card__body">
        <div class="form-group">
          <label class="form-check">
            <input type="checkbox" name="api_enabled" value="1" {{ !empty($settings['api_enabled']) ? 'checked' : '' }}>
            <span>Enable JSON:API endpoint</span>
          </label>
        </div>
        <div class="form-group">
          <label class="form-label">CORS Origins</label>
          <input type="text" name="cors_origins" class="form-input" value="{{ $settings['cors_origins'] ?? '*' }}" placeholder="https://example.com,https://app.example.com">
          <small class="text-muted">Comma-separated origins. Use * for all.</small>
        </div>
        <div class="form-group">
          <label class="form-label">API Rate Limit (requests/minute)</label>
          <input type="number" name="api_rate_limit" class="form-input" value="{{ $settings['api_rate_limit'] ?? 60 }}">
        </div>
      </div>
    </div>
  </div>

  {{-- Advanced Settings --}}
  <div $m-show="activeTab === 'advanced'">
    <div class="card mb-4">
      <div class="card__header"><h3 class="card__title">Cache & Performance</h3></div>
      <div class="card__body">
        <div class="form-group">
          <label class="form-check">
            <input type="checkbox" name="cache_enabled" value="1" {{ !empty($settings['cache_enabled']) ? 'checked' : '' }}>
            <span>Enable page caching</span>
          </label>
        </div>
        <div class="form-group">
          <label class="form-label">Cache TTL (seconds)</label>
          <input type="number" name="cache_ttl" class="form-input" value="{{ $settings['cache_ttl'] ?? 3600 }}">
        </div>
        <button type="button" class="btn btn--danger btn--sm" $m-on:click="clearCache()">🗑️ Clear All Caches</button>
      </div>
    </div>
  </div>
</form>
@endsection

@push('head')
<style>
/* Settings toggle rows */
.settings-toggle-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 1rem;
}

.settings-toggle-info { flex: 1; }

.settings-toggle-label {
  font-size: .85rem;
  color: #e2e8f0;
  font-weight: 500;
  margin-bottom: .15rem;
}

.settings-toggle-desc {
  font-size: .75rem;
  color: #64748b;
  line-height: 1.4;
}

.settings-divider {
  height: 1px;
  background: rgba(255,255,255,.06);
  margin: .75rem 0;
}

/* Toggle switch */
.form-toggle {
  display: flex; align-items: center; gap: 0.75rem;
  cursor: pointer; margin-bottom: 0.75rem; user-select: none;
}
.form-toggle__slider {
  width: 40px; height: 22px;
  background: rgba(255,255,255,0.1); border-radius: 11px;
  position: relative; transition: background 0.2s; flex-shrink: 0;
}
.form-toggle__slider::after {
  content: ''; position: absolute;
  width: 18px; height: 18px; border-radius: 50%;
  background: #64748b; top: 2px; left: 2px; transition: all 0.2s;
}
.form-toggle input:checked + .form-toggle__slider {
  background: rgba(99,102,241,0.4);
}
.form-toggle input:checked + .form-toggle__slider::after {
  background: #818cf8; transform: translateX(18px);
}
.form-toggle input { display: none; }
.form-toggle input[type="hidden"] + input { display: none; }
.form-toggle__label {
  font-size: 0.85rem; color: #cbd5e1;
}
</style>
@endpush

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
  window.settingsState = { activeTab: 'general' };
});
</script>
@endpush
