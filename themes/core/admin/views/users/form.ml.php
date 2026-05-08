@extends('layouts.admin')

@section('title', $isNew ? 'Create User' : 'Edit User')
@section('page_title', $title)

@section('breadcrumb')
<a href="/admin" class="breadcrumb__item">Dashboard</a>
<span class="breadcrumb__sep">›</span>
<a href="/admin/users" class="breadcrumb__item">Users</a>
<span class="breadcrumb__sep">›</span>
<span class="breadcrumb__item breadcrumb__item--active">{{ $isNew ? 'Create' : 'Edit' }}</span>
@endsection

@section('content')
<div class="user-form-page">

  @php
    $userData = is_array($user) ? $user : [];
    $action = $isNew ? '/admin/users' : '/admin/users/' . ($userData['id'] ?? '');
  @endphp

  <form method="POST" action="{{ $action }}" enctype="multipart/form-data" class="user-form" id="user-form">

    <div class="user-form__grid">

      {{-- Left Column: Main fields --}}
      <div class="user-form__main">
        <div class="card">
          <div class="card__header">
            <h3 class="card__title">
              <i data-lucide="user" class="w-4 h-4"></i>
              Profile
            </h3>
          </div>
          <div class="card__body">
            {{-- Name --}}
            <div class="form-group">
              <label for="name" class="form-label">Full Name <span class="required">*</span></label>
              <input type="text" id="name" name="name" class="form-input {{ isset($errors['name']) ? 'form-input--error' : '' }}"
                     value="{{ $userData['name'] ?? '' }}" required>
              @if(isset($errors['name']))
              <p class="form-error">{{ $errors['name'] }}</p>
              @endif
            </div>

            {{-- Email --}}
            <div class="form-group">
              <label for="email" class="form-label">Email Address <span class="required">*</span></label>
              <input type="email" id="email" name="email" class="form-input {{ isset($errors['email']) ? 'form-input--error' : '' }}"
                     value="{{ $userData['email'] ?? '' }}" required>
              @if(isset($errors['email']))
              <p class="form-error">{{ $errors['email'] }}</p>
              @endif
            </div>

            {{-- Password --}}
            <div class="form-group">
              <label for="password" class="form-label">
                Password
                @if($isNew)
                <span class="required">*</span>
                @else
                <span class="text-muted text-xs">(leave blank to keep current)</span>
                @endif
              </label>
              <div class="password-field">
                <input type="password" id="password" name="password"
                       class="form-input {{ isset($errors['password']) ? 'form-input--error' : '' }}"
                       {{ $isNew ? 'required' : '' }} minlength="8"
                       autocomplete="new-password">
                <button type="button" class="password-toggle" data-target="password" title="Show password">
                  <i data-lucide="eye" class="w-4 h-4"></i>
                </button>
              </div>
              @if(isset($errors['password']))
              <p class="form-error">{{ $errors['password'] }}</p>
              @endif
              <div class="password-strength" id="password-strength"></div>
            </div>

            {{-- Avatar --}}
            <div class="form-group">
              <label class="form-label">Avatar</label>
              <div class="avatar-upload">
                <div class="avatar-upload__preview">
                  @if(!$isNew && ($userData['avatar'] ?? ''))
                  <img src="/admin/users/{{ $userData['id'] }}/avatar" alt="Current avatar" id="avatar-preview-img">
                  @else
                  <div class="avatar-upload__placeholder" id="avatar-preview-placeholder">
                    <i data-lucide="camera" class="w-6 h-6"></i>
                  </div>
                  @endif
                </div>
                <div class="avatar-upload__controls">
                  <input type="file" id="avatar" name="avatar" accept="image/jpeg,image/png,image/webp,image/gif"
                         class="avatar-upload__input">
                  <label for="avatar" class="btn btn--sm btn--ghost">Choose Image</label>
                  <p class="text-muted text-xs mt-1">JPG, PNG, WebP or GIF. Max 2 MB.</p>
                </div>
              </div>
            </div>
          </div>
        </div>

        {{-- Settings Card --}}
        <div class="card mt-4">
          <div class="card__header">
            <h3 class="card__title">
              <i data-lucide="settings" class="w-4 h-4"></i>
              Settings
            </h3>
          </div>
          <div class="card__body">
            <div class="form-row">
              <div class="form-group form-group--half">
                <label for="locale" class="form-label">Language</label>
                <select id="locale" name="locale" class="form-select">
                  @php
                    $currentLocale = $userData['locale'] ?? 'en';
                    $locales = ['en' => 'English', 'es' => 'Español', 'fr' => 'Français', 'de' => 'Deutsch', 'pt' => 'Português'];
                  @endphp
                  @foreach($locales as $code => $label)
                  <?php $sel = $code === $currentLocale ? 'selected' : ''; ?>
                  <option value="{{ $code }}" {{ $sel }}>{{ $label }}</option>
                  @endforeach
                </select>
              </div>
              <div class="form-group form-group--half">
                <label for="timezone" class="form-label">Timezone</label>
                <select id="timezone" name="timezone" class="form-select">
                  <option value="">System default</option>
                  @php
                    $currentTz = $userData['timezone'] ?? '';
                    $tzList = ['America/New_York', 'America/Chicago', 'America/Denver', 'America/Los_Angeles', 'America/Mexico_City', 'Europe/London', 'Europe/Paris', 'Europe/Berlin', 'Asia/Tokyo', 'Asia/Shanghai', 'Australia/Sydney', 'Pacific/Auckland', 'UTC'];
                  @endphp
                  @foreach($tzList as $tz)
                  <?php $sel = $tz === $currentTz ? 'selected' : ''; ?>
                  <option value="{{ $tz }}" {{ $sel }}>{{ $tz }}</option>
                  @endforeach
                </select>
              </div>
            </div>
          </div>
        </div>
      </div>

      {{-- Right Column: Role & Status --}}
      <div class="user-form__sidebar">
        <div class="card">
          <div class="card__header">
            <h3 class="card__title">
              <i data-lucide="shield" class="w-4 h-4"></i>
              Role & Access
            </h3>
          </div>
          <div class="card__body">
            {{-- Role --}}
            <div class="form-group">
              <label for="role_id" class="form-label">Role <span class="required">*</span></label>
              <select id="role_id" name="role_id" class="form-select {{ isset($errors['role_id']) ? 'form-input--error' : '' }}" required>
                <option value="">— Select role —</option>
                @foreach($roles as $r)
                <?php $sel = ((string)($r['id'] ?? '') === (string)($userData['role_id'] ?? '')) ? 'selected' : ''; ?>
                <option value="{{ $r['id'] }}" {{ $sel }}>{{ $r['label'] }}</option>
                @endforeach
              </select>
              @if(isset($errors['role_id']))
              <p class="form-error">{{ $errors['role_id'] }}</p>
              @endif
              <a href="/admin/roles" class="text-xs text-link mt-1 inline-block">Manage roles →</a>
            </div>

            {{-- Active Toggle --}}
            <div class="form-group">
              <label class="toggle-label">
                <input type="checkbox" name="active" value="1"
                       {{ ($isNew || ($userData['active'] ?? 1)) ? 'checked' : '' }}
                       class="toggle-input">
                <span class="toggle-switch"></span>
                <span class="toggle-text">Account Active</span>
              </label>
            </div>
          </div>
        </div>

        {{-- Actions --}}
        <div class="card mt-4">
          <div class="card__body">
            <button type="submit" class="btn btn--primary btn--block">
              <i data-lucide="save" class="w-4 h-4"></i>
              {{ $isNew ? 'Create User' : 'Save Changes' }}
            </button>
            <a href="/admin/users" class="btn btn--ghost btn--block mt-2">Cancel</a>

            @if(!$isNew)
            <div class="user-form__meta mt-4 pt-4" style="border-top: 1px solid rgba(255,255,255,0.05)">
              <p class="text-muted text-xs">
                Created: {{ (new \DateTimeImmutable($userData['created_at'] ?? 'now'))->format('M j, Y H:i') }}
              </p>
              @if($userData['last_login_at'] ?? null)
              <p class="text-muted text-xs">
                Last login: {{ (new \DateTimeImmutable($userData['last_login_at']))->format('M j, Y H:i') }}
              </p>
              @endif
            </div>
            @endif
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
// Password visibility toggle
document.querySelectorAll('.password-toggle').forEach(btn => {
  btn.addEventListener('click', () => {
    const input = document.getElementById(btn.dataset.target);
    const icon = btn.querySelector('i');
    if (input.type === 'password') {
      input.type = 'text';
      icon.setAttribute('data-lucide', 'eye-off');
    } else {
      input.type = 'password';
      icon.setAttribute('data-lucide', 'eye');
    }
    if (window.lucide) lucide.createIcons();
  });
});

// Password strength indicator
const pwInput = document.getElementById('password');
const pwStrength = document.getElementById('password-strength');
if (pwInput && pwStrength) {
  pwInput.addEventListener('input', () => {
    const val = pwInput.value;
    let score = 0;
    if (val.length >= 8) score++;
    if (val.length >= 12) score++;
    if (/[A-Z]/.test(val)) score++;
    if (/[0-9]/.test(val)) score++;
    if (/[^A-Za-z0-9]/.test(val)) score++;

    const labels = ['', 'Weak', 'Fair', 'Good', 'Strong', 'Excellent'];
    const colors = ['', '#f87171', '#fb923c', '#facc15', '#4ade80', '#34d399'];
    if (val.length === 0) {
      pwStrength.innerHTML = '';
    } else {
      pwStrength.innerHTML = `<div class="pw-bar" style="width:${score*20}%;background:${colors[score]}"></div>
        <span class="pw-label" style="color:${colors[score]}">${labels[score]}</span>`;
    }
  });
}

// Avatar preview
const avatarInput = document.getElementById('avatar');
if (avatarInput) {
  avatarInput.addEventListener('change', (e) => {
    const file = e.target.files[0];
    if (!file) return;
    const reader = new FileReader();
    reader.onload = (ev) => {
      const placeholder = document.getElementById('avatar-preview-placeholder');
      const img = document.getElementById('avatar-preview-img');
      if (img) {
        img.src = ev.target.result;
      } else if (placeholder) {
        placeholder.outerHTML = `<img src="${ev.target.result}" alt="Preview" id="avatar-preview-img">`;
      }
    };
    reader.readAsDataURL(file);
  });
}
</script>
@endpush

@endsection
