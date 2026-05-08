@extends('layouts.admin')

@section('title', $isNew ? 'Create Role' : 'Edit Role')
@section('page_title', $title)

@section('breadcrumb')
<a href="/admin" class="breadcrumb__item">Dashboard</a>
<span class="breadcrumb__sep">›</span>
<a href="/admin/roles" class="breadcrumb__item">Roles</a>
<span class="breadcrumb__sep">›</span>
<span class="breadcrumb__item breadcrumb__item--active">{{ $isNew ? 'Create' : 'Edit' }}</span>
@endsection

@section('content')
<div class="role-form-page">

  @php
    $roleData = is_array($role) ? $role : [];
    $action = $isNew ? '/admin/roles' : '/admin/roles/' . ($roleData['id'] ?? '');
    $isSuperAdmin = (bool) ($roleData['is_super_admin'] ?? false);
    $isSystem = (bool) ($roleData['is_system'] ?? false);
    $hasWildcard = in_array('*', $grantedPermissions, true);
  @endphp

  <form method="POST" action="{{ $action }}" class="role-form" id="role-form">

    <div class="role-form__grid">

      {{-- Left Column: Main fields --}}
      <div class="role-form__main">
        <div class="card">
          <div class="card__header">
            <h3 class="card__title">
              <i data-lucide="shield" class="w-4 h-4"></i>
              Role Details
            </h3>
          </div>
          <div class="card__body">
            {{-- Label --}}
            <div class="form-group">
              <label for="label" class="form-label">Role Name <span class="required">*</span></label>
              <input type="text" id="label" name="label" class="form-input {{ isset($errors['label']) ? 'form-input--error' : '' }}"
                     value="{{ $roleData['label'] ?? '' }}" required>
              @if(isset($errors['label']))
              <p class="form-error">{{ $errors['label'] }}</p>
              @endif
            </div>

            {{-- Machine Name --}}
            <div class="form-group">
              <label for="machine_name" class="form-label">Machine Name</label>
              <input type="text" id="machine_name" name="machine_name"
                     class="form-input form-input--mono {{ isset($errors['machine_name']) ? 'form-input--error' : '' }}"
                     value="{{ $roleData['machine_name'] ?? '' }}"
                     {{ $isSystem ? 'readonly' : '' }}
                     pattern="[a-z0-9_]+">
              @if(isset($errors['machine_name']))
              <p class="form-error">{{ $errors['machine_name'] }}</p>
              @endif
              <p class="text-muted text-xs mt-1">Lowercase letters, numbers and underscores only. Auto-generated from name if empty.</p>
            </div>

            {{-- Description --}}
            <div class="form-group">
              <label for="description" class="form-label">Description</label>
              <textarea id="description" name="description" class="form-input" rows="2">{{ $roleData['description'] ?? '' }}</textarea>
            </div>

            {{-- Weight --}}
            <div class="form-group">
              <label for="weight" class="form-label">Weight (sort order)</label>
              <input type="number" id="weight" name="weight" class="form-input" style="max-width: 120px"
                     value="{{ $roleData['weight'] ?? 0 }}" min="0">
              <p class="text-muted text-xs mt-1">Lower weight = higher priority.</p>
            </div>
          </div>
        </div>

        {{-- Permission Matrix --}}
        <div class="card mt-4">
          <div class="card__header">
            <h3 class="card__title">
              <i data-lucide="key" class="w-4 h-4"></i>
              Permissions
            </h3>
            @if(!$isSuperAdmin)
            <button type="button" class="btn btn--xs btn--ghost" id="toggle-all-perms">Select All</button>
            @endif
          </div>
          <div class="card__body" id="permission-matrix">

            @if($isSuperAdmin || $hasWildcard)
            <div class="perm-notice perm-notice--super">
              <i data-lucide="crown" class="w-4 h-4"></i>
              <span>Super Admin — has all permissions. Toggle off below to configure specific permissions instead.</span>
            </div>
            @endif

            @foreach($permissionGroups as $group => $permissions)
            <div class="perm-group">
              <div class="perm-group__header">
                <label class="perm-group__toggle">
                  <input type="checkbox" class="perm-group-toggle" data-group="{{ $group }}">
                  <span class="perm-group__label">{{ $group }}</span>
                </label>
                <span class="perm-group__count text-muted text-xs">
                  @php
                    $groupGranted = 0;
                    foreach ($permissions as $key => $lbl) {
                      if ($hasWildcard || in_array($key, $grantedPermissions, true)) $groupGranted++;
                    }
                  @endphp
                  {{ $groupGranted }}/{{ count($permissions) }}
                </span>
              </div>
              <div class="perm-group__items">
                @foreach($permissions as $permKey => $permLabel)
                @php
                  $checked = $hasWildcard || in_array($permKey, $grantedPermissions, true);
                @endphp
                <label class="perm-item">
                  <input type="checkbox" name="permissions[]" value="{{ $permKey }}"
                         {{ $checked ? 'checked' : '' }}
                         class="perm-checkbox" data-group="{{ $group }}">
                  <span class="perm-item__label">{{ $permLabel }}</span>
                  <code class="perm-item__key">{{ $permKey }}</code>
                </label>
                @endforeach
              </div>
            </div>
            @endforeach

          </div>
        </div>
      </div>

      {{-- Right Column: Options --}}
      <div class="role-form__sidebar">
        <div class="card">
          <div class="card__header">
            <h3 class="card__title">
              <i data-lucide="settings" class="w-4 h-4"></i>
              Options
            </h3>
          </div>
          <div class="card__body">
            {{-- Super Admin Toggle --}}
            <div class="form-group">
              <label class="toggle-label">
                <input type="checkbox" name="is_super_admin" value="1"
                       {{ $isSuperAdmin ? 'checked' : '' }}
                       class="toggle-input" id="super-admin-toggle">
                <span class="toggle-switch"></span>
                <span class="toggle-text">Super Admin</span>
              </label>
              <p class="text-muted text-xs mt-1">Grants all permissions. Bypasses all checks.</p>
            </div>

            @if($isSystem)
            <div class="form-group mt-3">
              <div class="perm-notice perm-notice--info">
                <i data-lucide="lock" class="w-3.5 h-3.5"></i>
                <span class="text-xs">System role — cannot be deleted or renamed.</span>
              </div>
            </div>
            @endif
          </div>
        </div>

        {{-- Actions --}}
        <div class="card mt-4">
          <div class="card__body">
            <button type="submit" class="btn btn--primary btn--block">
              <i data-lucide="save" class="w-4 h-4"></i>
              {{ $isNew ? 'Create Role' : 'Save Changes' }}
            </button>
            <a href="/admin/roles" class="btn btn--ghost btn--block mt-2">Cancel</a>
          </div>
        </div>
      </div>
    </div>
  </form>
</div>

@push('head')
<link rel="stylesheet" href="/themes/core/admin/css/users.css?v={{ time() }}">
@endpush

@push('scripts')
<script>
// Auto-generate machine name from label
const labelInput = document.getElementById('label');
const machineInput = document.getElementById('machine_name');
if (labelInput && machineInput && !machineInput.readOnly) {
  let userEdited = machineInput.value !== '';
  machineInput.addEventListener('input', () => { userEdited = true; });
  labelInput.addEventListener('input', () => {
    if (!userEdited) {
      machineInput.value = labelInput.value
        .toLowerCase().trim()
        .replace(/[^a-z0-9]+/g, '_')
        .replace(/^_|_$/g, '');
    }
  });
}

// Permission group toggles
document.querySelectorAll('.perm-group-toggle').forEach(toggle => {
  const group = toggle.dataset.group;
  const checkboxes = document.querySelectorAll(`.perm-checkbox[data-group="${group}"]`);

  // Initialize group toggle state
  const allChecked = [...checkboxes].every(cb => cb.checked);
  toggle.checked = allChecked;

  toggle.addEventListener('change', () => {
    checkboxes.forEach(cb => { cb.checked = toggle.checked; });
    updateGroupCounts();
  });

  checkboxes.forEach(cb => {
    cb.addEventListener('change', () => {
      toggle.checked = [...checkboxes].every(c => c.checked);
      updateGroupCounts();
    });
  });
});

// Toggle all permissions
const toggleAllBtn = document.getElementById('toggle-all-perms');
if (toggleAllBtn) {
  toggleAllBtn.addEventListener('click', () => {
    const all = document.querySelectorAll('.perm-checkbox');
    const allChecked = [...all].every(cb => cb.checked);
    all.forEach(cb => { cb.checked = !allChecked; });
    document.querySelectorAll('.perm-group-toggle').forEach(t => { t.checked = !allChecked; });
    toggleAllBtn.textContent = allChecked ? 'Select All' : 'Deselect All';
    updateGroupCounts();
  });
}

// Super admin toggle: disable/enable individual checkboxes
const superToggle = document.getElementById('super-admin-toggle');
if (superToggle) {
  function handleSuperToggle() {
    const matrix = document.getElementById('permission-matrix');
    if (superToggle.checked) {
      matrix.style.opacity = '0.5';
      matrix.style.pointerEvents = 'none';
    } else {
      matrix.style.opacity = '1';
      matrix.style.pointerEvents = 'auto';
    }
  }
  superToggle.addEventListener('change', handleSuperToggle);
  handleSuperToggle();
}

function updateGroupCounts() {
  document.querySelectorAll('.perm-group').forEach(group => {
    const checkboxes = group.querySelectorAll('.perm-checkbox');
    const checked = [...checkboxes].filter(cb => cb.checked).length;
    const countEl = group.querySelector('.perm-group__count');
    if (countEl) countEl.textContent = `${checked}/${checkboxes.length}`;
  });
}
</script>
@endpush

@endsection
