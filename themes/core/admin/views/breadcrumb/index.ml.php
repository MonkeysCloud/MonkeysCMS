@extends('layouts.admin')

@section('title', 'Breadcrumbs')
@section('page_title', 'Breadcrumbs')

@section('breadcrumb')
<a href="/admin" class="breadcrumb__item">Dashboard</a>
<span class="breadcrumb__sep">›</span>
<span class="breadcrumb__item breadcrumb__item--active">Breadcrumbs</span>
@endsection

@section('content')
<div class="bc-page">

  {{-- Flash Messages --}}
  @if(!empty($flashSuccess))
  <div class="alert alert--success mb-4"><i data-lucide="check-circle" class="w-4 h-4"></i> {{ $flashSuccess }}</div>
  @endif
  @if(!empty($flashError))
  <div class="alert alert--danger mb-4"><i data-lucide="alert-circle" class="w-4 h-4"></i> {{ $flashError }}</div>
  @endif

  {{-- ═══ Global Defaults ═══ --}}
  @php $g = $global; @endphp
  <div class="card bc-card mb-6">
    <div class="card__header card__header--between">
      <h3 class="card__title">
        <i data-lucide="globe" class="w-5 h-5 card__title-icon"></i> Global Defaults
      </h3>
      <span class="badge badge--accent">Default for all pages</span>
    </div>
    <div class="card__body">
      <form action="/admin/breadcrumbs/save" method="POST" class="bc-form">
        <input type="hidden" name="entity_type" value="global">
        <input type="hidden" name="bundle" value="*">

        <div class="bc-grid">
          <label class="bc-toggle">
            <input type="checkbox" name="enabled" value="1" {{ $g->enabled ? 'checked' : '' }}>
            <span class="bc-toggle__label">
              <i data-lucide="toggle-right" class="w-4 h-4"></i> Enabled
            </span>
            <span class="bc-toggle__desc">Show breadcrumbs on pages</span>
          </label>

          <label class="bc-toggle">
            <input type="checkbox" name="show_home" value="1" {{ $g->show_home ? 'checked' : '' }}>
            <span class="bc-toggle__label">
              <i data-lucide="home" class="w-4 h-4"></i> Show Home
            </span>
            <span class="bc-toggle__desc">Include "Home" as the first crumb</span>
          </label>

          <label class="bc-toggle">
            <input type="checkbox" name="show_current" value="1" {{ $g->show_current ? 'checked' : '' }}>
            <span class="bc-toggle__label">
              <i data-lucide="map-pin" class="w-4 h-4"></i> Show Current
            </span>
            <span class="bc-toggle__desc">Show the current page title (non-linked)</span>
          </label>

          <label class="bc-toggle">
            <input type="checkbox" name="show_content_type" value="1" {{ $g->show_content_type ? 'checked' : '' }}>
            <span class="bc-toggle__label">
              <i data-lucide="file-text" class="w-4 h-4"></i> Content Type
            </span>
            <span class="bc-toggle__desc">Include content type name (e.g. Articles)</span>
          </label>

          <label class="bc-toggle">
            <input type="checkbox" name="show_taxonomy" value="1" {{ $g->show_taxonomy ? 'checked' : '' }}>
            <span class="bc-toggle__label">
              <i data-lucide="tags" class="w-4 h-4"></i> Taxonomy
            </span>
            <span class="bc-toggle__desc">Include the primary taxonomy term</span>
          </label>

          <label class="bc-toggle">
            <input type="checkbox" name="json_ld" value="1" {{ $g->json_ld ? 'checked' : '' }}>
            <span class="bc-toggle__label">
              <i data-lucide="code-2" class="w-4 h-4"></i> JSON-LD
            </span>
            <span class="bc-toggle__desc">Output structured data for SEO</span>
          </label>
        </div>

        <div class="bc-separator-row mt-4">
          <label class="bc-field-label">Separator</label>
          <div class="bc-separator-options">
            @foreach($separators as $sep)
            <label class="bc-sep-option">
              <input type="radio" name="separator" value="{{ $sep }}" {{ $g->separator === $sep ? 'checked' : '' }}>
              <span class="bc-sep-chip">{{ $sep }}</span>
            </label>
            @endforeach
          </div>
        </div>

        {{-- Preview --}}
        <div class="bc-preview mt-4">
          <span class="bc-preview__label">Preview</span>
          <div class="bc-preview__trail" id="global-preview">
            <span class="bc-crumb bc-crumb--link">Home</span>
            <span class="bc-crumb--sep">{{ $g->separator }}</span>
            <span class="bc-crumb bc-crumb--link">Articles</span>
            <span class="bc-crumb--sep">{{ $g->separator }}</span>
            <span class="bc-crumb bc-crumb--current">My Page Title</span>
          </div>
        </div>

        <div class="bc-actions mt-4">
          <button type="submit" class="btn btn--primary btn--sm">
            <i data-lucide="save" class="w-4 h-4"></i> Save Global Defaults
          </button>
        </div>
      </form>
    </div>
  </div>

  {{-- ═══ Per Content Type ═══ --}}
  <div class="card bc-card mb-6">
    <div class="card__header">
      <h3 class="card__title">
        <i data-lucide="file-text" class="w-5 h-5 card__title-icon"></i> Content Types
      </h3>
    </div>
    <div class="card__body">
      <p class="text-muted text-sm mb-4">Override breadcrumb settings for specific content types. Unconfigured types use the global defaults.</p>

      <div class="bc-types-list">
        @foreach($contentTypes as $ct)
        @php
          $key = 'node:' . $ct->type_id;
          $cfg = $configMap[$key] ?? null;
          $isEnabled = $cfg ? $cfg->enabled : $g->enabled;
          $showHome = $cfg ? $cfg->show_home : $g->show_home;
          $showCurrent = $cfg ? $cfg->show_current : $g->show_current;
          $showType = $cfg ? $cfg->show_content_type : $g->show_content_type;
          $showTax = $cfg ? $cfg->show_taxonomy : $g->show_taxonomy;
          $jsonLd = $cfg ? $cfg->json_ld : $g->json_ld;
          $sep = $cfg ? $cfg->separator : $g->separator;
        @endphp
        <div class="bc-type-row">
          <form action="/admin/breadcrumbs/save" method="POST" class="bc-type-form">
            <input type="hidden" name="entity_type" value="node">
            <input type="hidden" name="bundle" value="{{ $ct->type_id }}">

            <div class="bc-type-header">
              <div class="bc-type-info">
                <span class="bc-type-icon"><i data-lucide="file-text" class="w-5 h-5"></i></span>
                <div>
                  <span class="bc-type-name">{{ $ct->label }}</span>
                  <span class="bc-type-id">{{ $ct->type_id }}</span>
                </div>
              </div>
              <div class="bc-type-actions">
                <label class="bc-inline-toggle">
                  <input type="checkbox" name="enabled" value="1" {{ $isEnabled ? 'checked' : '' }}>
                  <span class="bc-inline-toggle__slider"></span>
                </label>
                @if($cfg)
                <span class="badge badge--sm badge--info">Custom</span>
                @else
                <span class="badge badge--sm badge--muted">Global</span>
                @endif
              </div>
            </div>

            <div class="bc-type-options">
              <label class="bc-mini-toggle"><input type="checkbox" name="show_home" value="1" {{ $showHome ? 'checked' : '' }}> Home</label>
              <label class="bc-mini-toggle"><input type="checkbox" name="show_current" value="1" {{ $showCurrent ? 'checked' : '' }}> Current</label>
              <label class="bc-mini-toggle"><input type="checkbox" name="show_content_type" value="1" {{ $showType ? 'checked' : '' }}> Type</label>
              <label class="bc-mini-toggle"><input type="checkbox" name="show_taxonomy" value="1" {{ $showTax ? 'checked' : '' }}> Taxonomy</label>
              <label class="bc-mini-toggle"><input type="checkbox" name="json_ld" value="1" {{ $jsonLd ? 'checked' : '' }}> JSON-LD</label>

              <select name="separator" class="form-select form-select--xs bc-sep-select">
                @foreach($separators as $s)
                <option value="{{ $s }}" {{ $sep === $s ? 'selected' : '' }}>{{ $s }}</option>
                @endforeach
              </select>

              <button type="submit" class="btn btn--xs btn--primary" title="Save">
                <i data-lucide="save" class="w-3.5 h-3.5"></i>
              </button>
              @if($cfg)
              <a href="javascript:void(0)" onclick="this.closest('.bc-type-row').querySelector('.bc-delete-form').requestSubmit()"
                 class="btn btn--xs btn--ghost btn--danger-ghost" title="Reset to global">
                <i data-lucide="rotate-ccw" class="w-3.5 h-3.5"></i>
              </a>
              @endif
            </div>
          </form>
          @if($cfg)
          <form action="/admin/breadcrumbs/{{ $cfg->id }}/delete" method="POST" class="bc-delete-form" style="display:none"
                data-confirm="Reset to global defaults?" data-confirm-title="Reset Breadcrumbs" data-confirm-label="Reset" data-confirm-class="btn btn--warning"></form>
          @endif
        </div>
        @endforeach
      </div>
    </div>
  </div>

  {{-- ═══ Taxonomy Vocabularies ═══ --}}
  @if(!empty($vocabularies))
  <div class="card bc-card bc-card--term">
    <div class="card__header">
      <h3 class="card__title" style="color:#fb923c;">
        <i data-lucide="tags" class="w-5 h-5 card__title-icon"></i> Taxonomy Vocabularies
      </h3>
    </div>
    <div class="card__body">
      <p class="text-muted text-sm mb-4">Configure breadcrumbs for taxonomy term pages.</p>

      <div class="bc-types-list">
        @foreach($vocabularies as $vocab)
        @php
          $key = 'term:' . $vocab->machine_name;
          $cfg = $configMap[$key] ?? null;
          $isEnabled = $cfg ? $cfg->enabled : $g->enabled;
          $showHome = $cfg ? $cfg->show_home : $g->show_home;
          $showCurrent = $cfg ? $cfg->show_current : $g->show_current;
          $jsonLd = $cfg ? $cfg->json_ld : $g->json_ld;
          $sep = $cfg ? $cfg->separator : $g->separator;
        @endphp
        <div class="bc-type-row bc-type-row--term">
          <form action="/admin/breadcrumbs/save" method="POST" class="bc-type-form">
            <input type="hidden" name="entity_type" value="term">
            <input type="hidden" name="bundle" value="{{ $vocab->machine_name }}">

            <div class="bc-type-header">
              <div class="bc-type-info">
                <span class="bc-type-icon bc-type-icon--term"><i data-lucide="tag" class="w-5 h-5"></i></span>
                <div>
                  <span class="bc-type-name">{{ $vocab->label }}</span>
                  <span class="bc-type-id">{{ $vocab->machine_name }}</span>
                </div>
              </div>
              <div class="bc-type-actions">
                <label class="bc-inline-toggle">
                  <input type="checkbox" name="enabled" value="1" {{ $isEnabled ? 'checked' : '' }}>
                  <span class="bc-inline-toggle__slider"></span>
                </label>
                @if($cfg)
                <span class="badge badge--sm badge--info">Custom</span>
                @else
                <span class="badge badge--sm badge--muted">Global</span>
                @endif
              </div>
            </div>

            <div class="bc-type-options">
              <label class="bc-mini-toggle"><input type="checkbox" name="show_home" value="1" {{ $showHome ? 'checked' : '' }}> Home</label>
              <label class="bc-mini-toggle"><input type="checkbox" name="show_current" value="1" {{ $showCurrent ? 'checked' : '' }}> Current</label>
              <label class="bc-mini-toggle"><input type="checkbox" name="json_ld" value="1" {{ $jsonLd ? 'checked' : '' }}> JSON-LD</label>

              <select name="separator" class="form-select form-select--xs bc-sep-select">
                @foreach($separators as $s)
                <option value="{{ $s }}" {{ $sep === $s ? 'selected' : '' }}>{{ $s }}</option>
                @endforeach
              </select>

              <button type="submit" class="btn btn--xs btn--primary" title="Save">
                <i data-lucide="save" class="w-3.5 h-3.5"></i>
              </button>
              @if($cfg)
              <a href="javascript:void(0)" onclick="this.closest('.bc-type-row').querySelector('.bc-delete-form').requestSubmit()"
                 class="btn btn--xs btn--ghost btn--danger-ghost" title="Reset to global">
                <i data-lucide="rotate-ccw" class="w-3.5 h-3.5"></i>
              </a>
              @endif
            </div>
          </form>
          @if($cfg)
          <form action="/admin/breadcrumbs/{{ $cfg->id }}/delete" method="POST" class="bc-delete-form" style="display:none"
                data-confirm="Reset to global defaults?" data-confirm-title="Reset Breadcrumbs" data-confirm-label="Reset" data-confirm-class="btn btn--warning"></form>
          @endif
        </div>
        @endforeach
      </div>
    </div>
  </div>
  @endif

</div>

@push('head')
<link rel="stylesheet" href="/themes/core/admin/css/breadcrumb.css?v={{ time() }}">
@endpush
@endsection
