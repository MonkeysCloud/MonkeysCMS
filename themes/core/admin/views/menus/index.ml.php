@extends('layouts.admin')

@section('title', 'Menus')
@section('page_title', 'Menus')

@section('breadcrumb')
<a href="/admin" class="breadcrumb__item">Dashboard</a>
<span class="breadcrumb__sep">›</span>
<span class="breadcrumb__item breadcrumb__item--active">Menus</span>
@endsection

@section('content')
<div class="menus-page">

  {{-- Header --}}
  <div class="menus-header">
    <div class="menus-header__info">
      <p class="text-muted text-sm">
        Create and manage navigation menus. Drag items to reorder them.
      </p>
    </div>
    <a href="/admin/menus/create" class="btn btn--primary btn--sm">
      <i data-lucide="plus" class="w-4 h-4"></i>
      <span>Add Menu</span>
    </a>
  </div>

  {{-- Menu Cards Grid --}}
  @if(!empty($menusWithCounts))
  <div class="menus-grid">
    @foreach($menusWithCounts as $entry)
    @php
      $m = $entry['menu'];
      $count = $entry['item_count'];
    @endphp
    <div class="menu-card card">
      <div class="menu-card__header">
        <div class="menu-card__icon">
          <i data-lucide="menu" class="w-5 h-5"></i>
        </div>
        <div class="menu-card__title-wrap">
          <h3 class="menu-card__title">
            <a href="/admin/menus/{{ $m->id }}/edit">{{ $m->label }}</a>
          </h3>
          <code class="menu-card__machine">{{ $m->machine_name }}</code>
        </div>
        <div class="menu-card__stats">
          <div class="menu-card__stat">
            <span class="menu-card__stat-value">{{ $count }}</span>
            <span class="menu-card__stat-label">items</span>
          </div>
        </div>
      </div>

      @if($m->description)
      <p class="menu-card__desc">{{ $m->description }}</p>
      @endif

      <div class="menu-card__badges">
        @if($m->enabled)
        <span class="badge badge--sm badge--success">Active</span>
        @else
        <span class="badge badge--sm badge--muted">Disabled</span>
        @endif
      </div>

      <div class="menu-card__actions">
        <a href="/admin/menus/{{ $m->id }}/edit" class="btn btn--sm btn--primary menu-card__cta">
          <i data-lucide="pencil" class="w-4 h-4"></i>
          <span>Edit Items</span>
        </a>
        <div class="menu-card__secondary">
          <form action="/admin/menus/{{ $m->id }}/delete" method="POST" class="inline"
                data-confirm="Delete menu '{{ addslashes($m->label) }}'? All items will be removed." data-confirm-title="Delete Menu">
            <button type="submit" class="btn btn--xs btn--ghost btn--danger" title="Delete menu">
              <i data-lucide="trash-2" class="w-3.5 h-3.5"></i>
            </button>
          </form>
        </div>
      </div>
    </div>
    @endforeach
  </div>
  @else
  <div class="card">
    <div class="card__body">
      <div class="empty-state py-12">
        <div class="empty-state__icon"><i data-lucide="menu" class="w-12 h-12"></i></div>
        <div class="empty-state__title">No menus yet</div>
        <p class="text-muted text-sm mb-4">Create your first navigation menu to get started.</p>
        <a href="/admin/menus/create" class="btn btn--primary btn--sm">
          <i data-lucide="plus" class="w-4 h-4"></i>
          <span>Create Menu</span>
        </a>
      </div>
    </div>
  </div>
  @endif

</div>

@push('head')
<link rel="stylesheet" href="/themes/core/admin/css/menus.css?v={{ time() }}">
@endpush

@endsection
