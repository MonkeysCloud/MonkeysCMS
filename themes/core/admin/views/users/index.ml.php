@extends('layouts.admin')

@section('title', 'Users')
@section('page_title', 'Users')

@section('breadcrumb')
<a href="/admin" class="breadcrumb__item">Dashboard</a>
<span class="breadcrumb__sep">›</span>
<span class="breadcrumb__item breadcrumb__item--active">Users</span>
@endsection

@section('content')
<div class="users-page">

  {{-- Header --}}
  <div class="users-header">
    <div class="users-header__info">
      <p class="text-muted text-sm">
        Manage CMS users, assign roles and control access.
        <strong>{{ $total }}</strong> user(s) found.
      </p>
    </div>
    <a href="/admin/users/create" class="btn btn--primary btn--sm">
      <i data-lucide="user-plus" class="w-4 h-4"></i>
      <span>Add User</span>
    </a>
  </div>

  {{-- Filters --}}
  <div class="users-filters card">
    <form method="GET" action="/admin/users" class="users-filters__form">
      <div class="users-filters__group">
        <input type="text" name="search" value="{{ $search }}"
               placeholder="Search by name or email…" class="form-input form-input--sm">
      </div>
      <div class="users-filters__group">
        <select name="role" class="form-select form-select--sm">
          <option value="">All Roles</option>
          @foreach($roles as $r)
          <?php $sel = ((string)($r['id'] ?? '') === (string)$roleFilter) ? 'selected' : ''; ?>
          <option value="{{ $r['id'] }}" {{ $sel }}>{{ $r['label'] }}</option>
          @endforeach
        </select>
      </div>
      <div class="users-filters__group">
        <select name="status" class="form-select form-select--sm">
          <option value="">All Status</option>
          <?php $selA = $statusFilter === 'active' ? 'selected' : ''; ?>
          <?php $selI = $statusFilter === 'inactive' ? 'selected' : ''; ?>
          <option value="active" {{ $selA }}>Active</option>
          <option value="inactive" {{ $selI }}>Inactive</option>
        </select>
      </div>
      <button type="submit" class="btn btn--sm btn--ghost">
        <i data-lucide="search" class="w-4 h-4"></i> Filter
      </button>
      @if($search || $roleFilter || $statusFilter)
      <a href="/admin/users" class="btn btn--sm btn--ghost">Clear</a>
      @endif
    </form>
  </div>

  {{-- Bulk Actions Bar --}}
  <div class="bulk-bar" data-bulk-toolbar style="display:none">
    <span class="bulk-bar__count"><span data-bulk-count>0</span> selected</span>
    <button class="btn btn--xs btn--ghost" style="color:#34d399" data-bulk-action="activate">
      <i data-lucide="check-circle" class="w-3.5 h-3.5"></i> Activate
    </button>
    <button class="btn btn--xs btn--ghost" style="color:#fbbf24" data-bulk-action="deactivate"
            data-bulk-confirm="Deactivate {count} user{s}?" data-bulk-severity="warning">
      <i data-lucide="x-circle" class="w-3.5 h-3.5"></i> Deactivate
    </button>
    <span class="bulk-bar__separator"></span>
    <select name="role_id" class="bulk-bar__role-select">
      <option value="">— Select role —</option>
      @foreach($roles as $r)
      <option value="{{ $r['id'] }}">{{ $r['label'] }}</option>
      @endforeach
    </select>
    <button class="btn btn--xs btn--ghost" data-bulk-action="change_role" data-bulk-extra="role_id">
      <i data-lucide="shield" class="w-3.5 h-3.5"></i> Assign Role
    </button>
    <span class="bulk-bar__separator"></span>
    <button class="btn btn--xs btn--ghost text-danger" data-bulk-action="delete"
            data-bulk-confirm="Permanently delete {count} user{s}? This cannot be undone."
            data-bulk-severity="danger">
      <i data-lucide="trash-2" class="w-3.5 h-3.5"></i> Delete
    </button>
  </div>

  {{-- Users Table --}}
  @if(!empty($users))
  <div class="card">
    <div class="table-wrap">
      <table class="data-table" id="users-table" data-bulk-actions data-bulk-url="/admin/users/bulk">
        <thead>
          <tr>
            <th class="table__check"><input type="checkbox" data-bulk-select-all></th>
            <th class="w-12"></th>
            <th>Name</th>
            <th>Email</th>
            <th>Role</th>
            <th class="text-center">Status</th>
            <th>Last Login</th>
            <th class="text-right">Actions</th>
          </tr>
        </thead>
        <tbody>
          @foreach($users as $u)
          <tr class="data-table__row">
            <td class="table__check">
              <input type="checkbox" value="{{ $u['id'] }}" data-bulk-item>
            </td>
            <td>
              <div class="user-avatar user-avatar--sm">
                @if($u['avatar'])
                <img src="/admin/users/{{ $u['id'] }}/avatar" alt="{{ $u['name'] }}">
                @else
                <span class="user-avatar__initials">
                  @php echo strtoupper(mb_substr($u['name'], 0, 2)); @endphp
                </span>
                @endif
              </div>
            </td>
            <td>
              <a href="/admin/users/{{ $u['id'] }}/edit" class="user-name-link">{{ $u['name'] }}</a>
            </td>
            <td class="text-muted text-sm">{{ $u['email'] }}</td>
            <td>
              <span class="role-badge role-badge--{{ $u['role_machine_name'] ?? 'default' }}">
                {{ $u['role_label'] ?? 'No role' }}
              </span>
            </td>
            <td class="text-center">
              <button class="status-toggle" data-user-id="{{ $u['id'] }}" data-active="{{ $u['active'] }}"
                      title="Click to toggle">
                @if($u['active'])
                <span class="status-pill status-pill--active">Active</span>
                @else
                <span class="status-pill status-pill--inactive">Inactive</span>
                @endif
              </button>
            </td>
            <td class="text-muted text-sm">
              @if($u['last_login_at'])
              @php
                $dt = new \DateTimeImmutable($u['last_login_at']);
                echo $dt->format('M j, Y H:i');
              @endphp
              @else
              <span class="text-muted">Never</span>
              @endif
            </td>
            <td class="text-right">
              <div class="action-group">
                <a href="/admin/users/{{ $u['id'] }}/edit" class="btn btn--xs btn--ghost" title="Edit">
                  <i data-lucide="pencil" class="w-3.5 h-3.5"></i>
                </a>
                <form action="/admin/users/{{ $u['id'] }}/delete" method="POST" class="inline"
                      data-confirm="Delete user '{{ addslashes($u['name']) }}'? This cannot be undone." data-confirm-title="Delete User">
                  <button type="submit" class="btn btn--xs btn--ghost btn--danger" title="Delete">
                    <i data-lucide="trash-2" class="w-3.5 h-3.5"></i>
                  </button>
                </form>
              </div>
            </td>
          </tr>
          @endforeach
        </tbody>
      </table>
    </div>

    {{-- Pagination --}}
    @if($totalPages > 1)
    <div class="pagination-bar">
      <div class="pagination-bar__info text-muted text-sm">
        Page {{ $page }} of {{ $totalPages }}
      </div>
      <div class="pagination-bar__nav">
        @if($page > 1)
        @php
          $prevParams = array_filter(['search' => $search, 'role' => $roleFilter, 'status' => $statusFilter, 'page' => $page - 1]);
        @endphp
        <a href="/admin/users?{{ http_build_query($prevParams) }}" class="btn btn--xs btn--ghost">← Prev</a>
        @endif
        @if($page < $totalPages)
        @php
          $nextParams = array_filter(['search' => $search, 'role' => $roleFilter, 'status' => $statusFilter, 'page' => $page + 1]);
        @endphp
        <a href="/admin/users?{{ http_build_query($nextParams) }}" class="btn btn--xs btn--ghost">Next →</a>
        @endif
      </div>
    </div>
    @endif
  </div>
  @else
  <div class="card">
    <div class="card__body">
      <div class="empty-state py-12">
        <div class="empty-state__icon"><i data-lucide="users" class="w-12 h-12"></i></div>
        <div class="empty-state__title">No users found</div>
        <p class="text-muted text-sm mb-4">No users match your current filters.</p>
        <a href="/admin/users/create" class="btn btn--primary btn--sm">
          <i data-lucide="user-plus" class="w-4 h-4"></i>
          <span>Add User</span>
        </a>
      </div>
    </div>
  </div>
  @endif

</div>

@push('head')
<link rel="stylesheet" href="/themes/core/admin/css/bulk.css?v={{ time() }}">
<link rel="stylesheet" href="/themes/core/admin/css/users.css?v={{ time() }}">
@endpush

@push('scripts')
<script>
document.querySelectorAll('.status-toggle').forEach(btn => {
  btn.addEventListener('click', async () => {
    const userId = btn.dataset.userId;
    try {
      const res = await fetch(`/admin/users/${userId}/toggle-active`, { method: 'POST' });
      const data = await res.json();
      if (data.success) {
        const pill = btn.querySelector('.status-pill');
        if (data.active) {
          pill.className = 'status-pill status-pill--active';
          pill.textContent = 'Active';
          btn.dataset.active = '1';
        } else {
          pill.className = 'status-pill status-pill--inactive';
          pill.textContent = 'Inactive';
          btn.dataset.active = '0';
        }
      } else if (data.error) {
        alert(data.error);
      }
    } catch (e) { console.error(e); }
  });
});
</script>
<script src="/themes/core/admin/js/bulk-actions.js?v={{ time() }}"></script>
@endpush

@endsection
