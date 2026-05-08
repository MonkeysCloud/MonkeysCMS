@extends('layouts.admin')

@section('title', 'Appearance')
@section('page_title', 'Appearance')

@section('breadcrumb')
<a href="/admin" class="breadcrumb__item">Dashboard</a>
<span class="breadcrumb__sep">›</span>
<span class="breadcrumb__item breadcrumb__item--active">Appearance</span>
@endsection

@section('content')
<div id="appearance-app">

  {{-- Frontend Themes --}}
  <div class="card mb-6">
    <div class="card__header">
      <h3 class="card__title">Frontend Theme</h3>
      <span class="badge badge--info text-xs">Active: {{ $activeFrontend ?? 'front' }}</span>
    </div>
    <div class="card__body">
      <div class="grid grid-3">
        @foreach($frontendThemes ?? [] as $theme)
        <div class="theme-card {{ ($activeFrontend ?? 'front') === $theme->name ? 'theme-card--active' : '' }}">
          <div class="theme-card__preview">
            <div class="theme-card__preview-icon">🎨</div>
          </div>
          <div class="theme-card__info">
            <div class="theme-card__name">{{ $theme->label }}</div>
            <div class="theme-card__meta text-xs text-muted">
              v{{ $theme->version }} · {{ $theme->tier }}
              @if($theme->parent)
              · extends {{ $theme->parent }}
              @endif
            </div>
            <p class="theme-card__desc text-sm text-muted mt-1">{{ $theme->description }}</p>
          </div>
          <div class="theme-card__actions">
            @if(($activeFrontend ?? 'front') === $theme->name)
            <span class="badge badge--success">Active</span>
            @else
            <form method="POST" action="/admin/appearance/set-frontend">
              <input type="hidden" name="theme" value="{{ $theme->name }}">
              <button type="submit" class="btn btn--sm btn--primary">Activate</button>
            </form>
            @endif
          </div>
        </div>
        @endforeach
      </div>
    </div>
  </div>

  {{-- Admin Themes --}}
  <div class="card mb-6">
    <div class="card__header">
      <h3 class="card__title">Admin Theme</h3>
      <span class="badge badge--info text-xs">Active: {{ $activeAdmin ?? 'admin' }}</span>
    </div>
    <div class="card__body">
      <div class="grid grid-3">
        @foreach($adminThemes ?? [] as $theme)
        <div class="theme-card {{ ($activeAdmin ?? 'admin') === $theme->name ? 'theme-card--active' : '' }}">
          <div class="theme-card__preview theme-card__preview--admin">
            <div class="theme-card__preview-icon">🛠️</div>
          </div>
          <div class="theme-card__info">
            <div class="theme-card__name">{{ $theme->label }}</div>
            <div class="theme-card__meta text-xs text-muted">
              v{{ $theme->version }} · {{ $theme->tier }}
              @if($theme->parent)
              · extends {{ $theme->parent }}
              @endif
            </div>
            <p class="theme-card__desc text-sm text-muted mt-1">{{ $theme->description }}</p>
          </div>
          <div class="theme-card__actions">
            @if(($activeAdmin ?? 'admin') === $theme->name)
            <span class="badge badge--success">Active</span>
            @else
            <form method="POST" action="/admin/appearance/set-admin">
              <input type="hidden" name="theme" value="{{ $theme->name }}">
              <button type="submit" class="btn btn--sm btn--primary">Activate</button>
            </form>
            @endif
          </div>
        </div>
        @endforeach
      </div>
    </div>
  </div>

  {{-- Global Libraries --}}
  <div class="card">
    <div class="card__header">
      <h3 class="card__title">Global Libraries</h3>
    </div>
    <div class="card__body p-0">
      <table class="table table--striped">
        <thead>
          <tr>
            <th>Library</th>
            <th>Description</th>
            <th>CSS</th>
            <th>JS</th>
            <th>Weight</th>
            <th>Status</th>
          </tr>
        </thead>
        <tbody>
          @foreach($libraries ?? [] as $lib)
          <tr>
            <td class="font-medium">{{ $lib->id }}</td>
            <td class="text-sm text-muted">{{ $lib->description }}</td>
            <td class="text-sm">{{ count($lib->css) }} files</td>
            <td class="text-sm">{{ count($lib->js) }} files</td>
            <td class="text-sm text-muted">{{ $lib->weight }}</td>
            <td>
              @if($lib->required)
              <span class="badge badge--success">Required</span>
              @else
              <span class="badge badge--default">Optional</span>
              @endif
            </td>
          </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  </div>
</div>
@endsection
