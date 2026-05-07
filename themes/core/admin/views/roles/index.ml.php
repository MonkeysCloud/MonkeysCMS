@extends('layouts.admin')

@section('title', 'Roles & Permissions')
@section('page_title', 'Roles & Permissions')

@section('breadcrumb')
<a href="/admin" class="breadcrumb__item">Dashboard</a>
<span class="breadcrumb__sep">›</span>
<span class="breadcrumb__item breadcrumb__item--active">Roles</span>
@endsection

@section('content')
<div class="roles-page">

  {{-- Header --}}
  <div class="roles-header">
    <div class="roles-header__info">
      <p class="text-muted text-sm">
        Define roles and assign permissions to control what each user group can do.
      </p>
    </div>
    <a href="/admin/roles/create" class="btn btn--primary btn--sm">
      <i data-lucide="shield-plus" class="w-4 h-4"></i>
      <span>Add Role</span>
    </a>
  </div>

  {{-- Roles Grid --}}
  @if(!empty($roles))
  <div class="roles-grid">
    @foreach($roles as $role)
    <div class="role-card card">
      <div class="role-card__header">
        <div class="role-card__icon {{ $role['is_super_admin'] ? 'role-card__icon--super' : '' }}">
          @if($role['is_super_admin'])
          <i data-lucide="crown" class="w-5 h-5"></i>
          @else
          <i data-lucide="shield" class="w-5 h-5"></i>
          @endif
        </div>
        <div class="role-card__title-wrap">
          <h3 class="role-card__title">
            <a href="/admin/roles/{{ $role['id'] }}/edit">{{ $role['label'] }}</a>
          </h3>
          <span class="role-card__machine">{{ $role['machine_name'] }}</span>
        </div>
        <div class="role-card__stats">
          <div class="role-card__stat">
            <span class="role-card__stat-value">{{ $role['user_count'] }}</span>
            <span class="role-card__stat-label">users</span>
          </div>
          <div class="role-card__stat">
            <span class="role-card__stat-value">
              {{ $role['is_super_admin'] ? '∞' : $role['permission_count'] }}
            </span>
            <span class="role-card__stat-label">perms</span>
          </div>
        </div>
      </div>

      @if($role['description'])
      <p class="role-card__desc">{{ $role['description'] }}</p>
      @endif

      <div class="role-card__badges">
        @if($role['is_super_admin'])
        <span class="badge badge--sm badge--warning">Super Admin</span>
        @endif
        @if($role['is_system'])
        <span class="badge badge--sm badge--info">System</span>
        @endif
        @if(!$role['is_super_admin'] && $role['permission_count'] > 0)
          @php
            $preview = array_slice($role['permissions_list'], 0, 3);
          @endphp
          @foreach($preview as $p)
          <span class="badge badge--sm badge--muted">{{ $p }}</span>
          @endforeach
          @if($role['permission_count'] > 3)
          <span class="badge badge--sm badge--muted">+{{ $role['permission_count'] - 3 }} more</span>
          @endif
        @endif
      </div>

      <div class="role-card__actions">
        <a href="/admin/roles/{{ $role['id'] }}/edit" class="btn btn--sm btn--primary role-card__cta">
          <i data-lucide="pencil" class="w-4 h-4"></i>
          <span>Edit Permissions</span>
        </a>
        <div class="role-card__secondary">
          @if(!$role['is_system'])
          <form action="/admin/roles/{{ $role['id'] }}/delete" method="POST" class="inline"
                data-confirm="Delete role '{{ addslashes($role['label']) }}'? Users with this role will lose access." data-confirm-title="Delete Role">
            <button type="submit" class="btn btn--xs btn--ghost btn--danger" title="Delete role"
                    {{ $role['user_count'] > 0 ? 'disabled' : '' }}>
              <i data-lucide="trash-2" class="w-3.5 h-3.5"></i>
            </button>
          </form>
          @else
          <span class="btn btn--xs btn--ghost" style="opacity:0.3;cursor:default" title="System role — cannot delete">
            <i data-lucide="lock" class="w-3.5 h-3.5"></i>
          </span>
          @endif
        </div>
      </div>
    </div>
    @endforeach
  </div>
  @else
  <div class="card">
    <div class="card__body">
      <div class="empty-state py-12">
        <div class="empty-state__icon"><i data-lucide="shield" class="w-12 h-12"></i></div>
        <div class="empty-state__title">No roles defined</div>
        <p class="text-muted text-sm mb-4">Create roles to organize user access and permissions.</p>
        <a href="/admin/roles/create" class="btn btn--primary btn--sm">
          <i data-lucide="shield-plus" class="w-4 h-4"></i>
          <span>Create Role</span>
        </a>
      </div>
    </div>
  </div>
  @endif

</div>

@push('head')
<link rel="stylesheet" href="/themes/core/admin/css/users.css?v={{ time() }}">
@endpush

@endsection
