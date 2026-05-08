<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="robots" content="noindex, nofollow">
  <title>@yield('title', 'MonkeysCMS')</title>

  {{-- Admin Theme CSS (Vite-built or raw fallback) --}}
  <link rel="stylesheet" href="{{ vite_asset('themes/core/admin/css/admin.css') }}">

  {{-- Global CSS + preconnect (injected by ThemeResolverMiddleware) --}}
  {!! $cms_head ?? '' !!}

  @stack('head')
</head>
<body class="auth-body">

  <div class="auth-wrapper">
    <div class="auth-card">
      <div class="auth-logo">
        <img
          src="/assets/images/monkeyscmslogo.svg"
          alt="MonkeysCMS"
          class="auth-logo__image"
        >
        <div class="auth-logo__divider"></div>
        <p>@yield('subtitle', '')</p>
      </div>

      @yield('content')

      <div class="auth-footer">
        &copy; {{ date('Y') }} MonkeysCMS &middot; Powered by <a href="https://monkeyslegion.com" target="_blank" rel="noopener">MonkeysLegion</a>
      </div>
    </div>
  </div>

  {{-- Global JS libraries (injected by ThemeResolverMiddleware) --}}
  {!! $cms_scripts ?? '' !!}

  @stack('scripts')
</body>
</html>
