@extends('layouts.auth')

@section('title', 'Two-Factor Authentication')
@section('subtitle', 'Verify your identity')

@section('content')
<div class="auth-form" style="text-align:center">

  <div style="margin-bottom:1.5rem">
    <i data-lucide="shield-check" style="width:48px;height:48px;color:var(--accent);margin:0 auto"></i>
    <h2 style="margin-top:.75rem;font-size:1.25rem;font-weight:600;color:var(--text)">Two-Factor Authentication</h2>
    <p class="text-sm text-muted" style="margin-top:.25rem">Enter the 6-digit code from your authenticator app</p>
  </div>

  @if($error === 'invalid_code')
  <div class="alert alert--danger" style="margin-bottom:1rem;text-align:left">
    <i data-lucide="alert-circle" class="w-4 h-4"></i>
    Invalid verification code. Please try again.
  </div>
  @endif

  @if($error === 'invalid_recovery')
  <div class="alert alert--danger" style="margin-bottom:1rem;text-align:left">
    <i data-lucide="alert-circle" class="w-4 h-4"></i>
    Invalid recovery code. Please try again.
  </div>
  @endif

  {{-- TOTP Code Form --}}
  <form action="/admin/2fa/verify" method="POST" id="2fa-form">
    <div class="form-group" style="margin-bottom:1rem">
      <input
        type="text"
        name="code"
        id="2fa-code"
        class="form-control"
        placeholder="000000"
        maxlength="6"
        pattern="[0-9]{6}"
        inputmode="numeric"
        autocomplete="one-time-code"
        autofocus
        required
        style="text-align:center;font-size:1.5rem;letter-spacing:.5rem;font-weight:700"
      >
    </div>

    <label style="display:flex;align-items:center;gap:.5rem;justify-content:center;margin-bottom:1.25rem;cursor:pointer">
      <input type="checkbox" name="remember_device" value="1" class="form-checkbox">
      <span class="text-sm text-muted">Remember this device for 30 days</span>
    </label>

    <button type="submit" class="btn btn--primary" style="width:100%">
      <i data-lucide="check-circle" class="w-4 h-4"></i> Verify
    </button>
  </form>

  <div style="margin-top:1.5rem;padding-top:1rem;border-top:1px solid var(--border)">
    <p class="text-sm text-muted" style="margin-bottom:.75rem">Lost your authenticator?</p>
    <form action="/admin/2fa/recovery" method="POST" style="display:flex;gap:.5rem">
      <input
        type="text"
        name="recovery_code"
        class="form-control"
        placeholder="Recovery code"
        style="flex:1;font-size:.85rem"
        required
      >
      <button type="submit" class="btn btn--ghost btn--sm">Use Code</button>
    </form>
  </div>

  <div style="margin-top:1rem">
    <a href="/admin/logout" class="text-sm text-muted" style="text-decoration:underline">Cancel and sign out</a>
  </div>

</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
  const input = document.getElementById('2fa-code');
  if (input) {
    // Auto-submit when 6 digits entered
    input.addEventListener('input', () => {
      input.value = input.value.replace(/\D/g, '').slice(0, 6);
      if (input.value.length === 6) {
        document.getElementById('2fa-form').submit();
      }
    });
  }
});
</script>
@endpush
