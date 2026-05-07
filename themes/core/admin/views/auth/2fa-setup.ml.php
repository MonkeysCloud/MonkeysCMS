@extends('layouts.admin')

@section('title', 'Two-Factor Authentication')

@section('breadcrumb')
<a href="/admin">Dashboard</a> › <span>Two-Factor Authentication</span>
@endsection

@section('page_title')
<i data-lucide="shield-check" class="w-5 h-5"></i> Two-Factor Authentication
@endsection

@section('content')
<div class="admin-page">

  @php
    $recoveryCodes = $recovery_codes ?? [];
  @endphp

  {{-- ── Recovery Codes Display (one-time after enable) ───────────── --}}
  @if($recoveryCodes)
  <div class="card" style="max-width:600px;margin-bottom:1.5rem;border-color:var(--warning)">
    <div class="card__header" style="background:rgba(255,165,0,0.1)">
      <h3 class="card__title" style="color:var(--warning)">
        <i data-lucide="alert-triangle" class="w-4 h-4"></i> Save Your Recovery Codes
      </h3>
    </div>
    <div class="card__body">
      <p class="text-sm text-muted" style="margin-bottom:1rem">
        Store these codes in a safe place. Each code can only be used <strong>once</strong>.
        If you lose access to your authenticator app, use one of these codes to sign in.
      </p>
      <div style="background:var(--bg-alt);border-radius:var(--radius);padding:1rem;font-family:monospace;display:grid;grid-template-columns:1fr 1fr;gap:.5rem">
        @foreach($recoveryCodes as $code)
        <div style="padding:.25rem .5rem;background:var(--bg);border-radius:4px;text-align:center;font-weight:600">{{ $code }}</div>
        @endforeach
      </div>
      <p class="text-xs text-muted" style="margin-top:.75rem">
        <i data-lucide="info" class="w-3 h-3"></i>
        These codes will not be shown again. Copy or print them now.
      </p>
    </div>
  </div>
  @endif

  {{-- ── Success Messages ─────────────────────────────────────────── --}}
  @if($success === 'enabled')
  <div class="alert alert--success" style="max-width:600px;margin-bottom:1rem">
    <i data-lucide="check-circle" class="w-4 h-4"></i>
    Two-Factor Authentication has been <strong>enabled</strong> for your account.
  </div>
  @endif

  @if($success === 'disabled')
  <div class="alert alert--info" style="max-width:600px;margin-bottom:1rem">
    <i data-lucide="info" class="w-4 h-4"></i>
    Two-Factor Authentication has been <strong>disabled</strong>.
  </div>
  @endif

  {{-- ── Error Messages ───────────────────────────────────────────── --}}
  @if($error === 'invalid_code')
  <div class="alert alert--danger" style="max-width:600px;margin-bottom:1rem">
    <i data-lucide="alert-circle" class="w-4 h-4"></i>
    Invalid verification code. Make sure your authenticator app shows the correct code and try again.
  </div>
  @endif

  @if($error === 'invalid_password')
  <div class="alert alert--danger" style="max-width:600px;margin-bottom:1rem">
    <i data-lucide="alert-circle" class="w-4 h-4"></i>
    Incorrect password. Please try again.
  </div>
  @endif

  <div class="card" style="max-width:600px">
    <div class="card__header">
      <div style="display:flex;align-items:center;justify-content:space-between">
        <h3 class="card__title">TOTP Authentication</h3>
        @if($is_enabled)
          <span class="badge badge--success">Enabled</span>
        @else
          <span class="badge badge--neutral">Disabled</span>
        @endif
      </div>
      <p class="text-sm text-muted" style="margin-top:.25rem">
        Add an extra layer of security using a time-based one-time password (TOTP).
      </p>
    </div>

    @if(!$is_enabled)
    {{-- ── Setup Form (enable 2FA) ──────────────────────────────── --}}
    <form action="/admin/2fa/enable" method="POST">
      <div class="card__body">

        <div style="text-align:center;margin-bottom:1.5rem">
          <p class="text-sm" style="margin-bottom:1rem">
            Scan this QR code with your authenticator app:
          </p>
          <img
            src="{{ $qr_url }}"
            alt="Scan this QR code"
            style="border-radius:var(--radius);border:2px solid var(--border);padding:.5rem;background:white"
            width="200"
            height="200"
          >
        </div>

        <div class="form-group" style="margin-bottom:1rem">
          <label class="form-label">Manual Entry Key</label>
          <div style="background:var(--bg-alt);padding:.75rem;border-radius:var(--radius);font-family:monospace;font-size:.85rem;word-break:break-all;text-align:center;font-weight:600;letter-spacing:.1rem">
            {{ $secret }}
          </div>
        </div>

        <input type="hidden" name="secret" value="{{ $secret }}">

        <div class="form-group" style="margin-bottom:1rem">
          <label for="verify-code" class="form-label">Verification Code</label>
          <input
            type="text"
            name="code"
            id="verify-code"
            class="form-control"
            placeholder="Enter the 6-digit code"
            maxlength="6"
            pattern="[0-9]{6}"
            inputmode="numeric"
            autocomplete="one-time-code"
            required
            style="text-align:center;font-size:1.25rem;letter-spacing:.3rem;font-weight:600"
          >
          <p class="text-xs text-muted" style="margin-top:.25rem">
            Enter the code from your authenticator app to confirm setup.
          </p>
        </div>

      </div>
      <div class="card__footer" style="display:flex;gap:.75rem;justify-content:flex-end">
        <a href="/admin" class="btn btn--ghost">Cancel</a>
        <button type="submit" class="btn btn--primary">
          <i data-lucide="shield-check" class="w-4 h-4"></i> Enable 2FA
        </button>
      </div>
    </form>

    @else
    {{-- ── Disable Form ─────────────────────────────────────────── --}}
    <form action="/admin/2fa/disable" method="POST">
      <div class="card__body">
        <div class="alert alert--info" style="margin-bottom:1rem">
          <i data-lucide="info" class="w-4 h-4"></i>
          <div>
            Two-Factor Authentication is currently <strong>active</strong> on your account.
            To disable it, enter your password below.
          </div>
        </div>

        <div class="form-group">
          <label for="disable-password" class="form-label">Current Password <span style="color:var(--danger)">*</span></label>
          <input
            type="password"
            name="password"
            id="disable-password"
            class="form-control"
            required
            autocomplete="current-password"
          >
        </div>
      </div>
      <div class="card__footer" style="display:flex;gap:.75rem;justify-content:flex-end">
        <a href="/admin" class="btn btn--ghost">Cancel</a>
        <button type="submit" class="btn btn--danger">
          <i data-lucide="shield-off" class="w-4 h-4"></i> Disable 2FA
        </button>
      </div>
    </form>
    @endif
  </div>

</div>
@endsection
