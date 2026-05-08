@extends('layouts.admin')

@section('title', $plugin->name . ' Settings')

@section('breadcrumb')
<a href="/admin">Dashboard</a> › <a href="/admin/plugins">Extend</a> › <span>{{ $plugin->name }} Settings</span>
@endsection

@section('page_title')
<i data-lucide="settings" class="w-5 h-5"></i> {{ $plugin->name }} Settings
@endsection

@section('content')
<div class="admin-page">

  <div class="card" style="max-width:700px">
    <div class="card__header">
      <h3 class="card__title">
        {{ $plugin->name }}
        <span class="badge badge--neutral" style="margin-left:.5rem">v{{ $plugin->version }}</span>
      </h3>
      <p class="text-sm text-muted" style="margin-top:.25rem">{{ $plugin->description }}</p>
    </div>

    @if(!empty($settings_def))
    <form action="/admin/plugins/{{ $plugin->vendor }}/{{ $plugin->name }}/settings" method="POST">
      <div class="card__body">

        @foreach($settings_def as $key => $def)
        @php
          $type     = $def['type'] ?? 'string';
          $label    = $def['label'] ?? ucfirst(str_replace('_', ' ', $key));
          $default  = $def['default'] ?? '';
          $required = $def['required'] ?? false;
          $current  = $values[$key] ?? $default;
        @endphp

        <div class="form-group" style="margin-bottom:1.25rem">
          <label for="setting-{{ $key }}" class="form-label">
            {{ $label }}
            @if($required)
              <span style="color:var(--danger)">*</span>
            @endif
          </label>

          @if($type === 'boolean')
            <label class="toggle-label" style="display:flex;align-items:center;gap:.5rem;cursor:pointer">
              <input
                type="checkbox"
                name="{{ $key }}"
                id="setting-{{ $key }}"
                value="1"
                class="form-checkbox"
                {{ ($current === '1' || $current === 'true' || $current === true) ? 'checked' : '' }}
              >
              <span class="text-sm text-muted">{{ $label }}</span>
            </label>

          @elseif($type === 'int' || $type === 'integer' || $type === 'number')
            <input
              type="number"
              name="{{ $key }}"
              id="setting-{{ $key }}"
              value="{{ $current }}"
              class="form-control"
              {{ $required ? 'required' : '' }}
            >

          @elseif($type === 'text' || $type === 'textarea')
            <textarea
              name="{{ $key }}"
              id="setting-{{ $key }}"
              class="form-control"
              rows="4"
              {{ $required ? 'required' : '' }}
            >{{ $current }}</textarea>

          @elseif($type === 'select')
            {{-- select type would need 'options' in def — future enhancement --}}
            <input
              type="text"
              name="{{ $key }}"
              id="setting-{{ $key }}"
              value="{{ $current }}"
              class="form-control"
              {{ $required ? 'required' : '' }}
            >

          @else
            {{-- Default: string input --}}
            <input
              type="text"
              name="{{ $key }}"
              id="setting-{{ $key }}"
              value="{{ $current }}"
              class="form-control"
              {{ $required ? 'required' : '' }}
            >
          @endif
        </div>
        @endforeach

      </div>
      <div class="card__footer" style="display:flex;gap:.75rem;justify-content:flex-end">
        <a href="/admin/plugins" class="btn btn--ghost">Cancel</a>
        <button type="submit" class="btn btn--primary">
          <i data-lucide="save" class="w-4 h-4"></i> Save Settings
        </button>
      </div>
    </form>
    @else
    <div class="card__body" style="text-align:center;padding:2rem">
      <i data-lucide="settings-2" class="w-10 h-10" style="color:var(--text-muted);margin-bottom:1rem"></i>
      <p class="text-muted">This plugin has no configurable settings.</p>
      <a href="/admin/plugins" class="btn btn--ghost" style="margin-top:1rem">← Back to Plugins</a>
    </div>
    @endif
  </div>

</div>
@endsection
