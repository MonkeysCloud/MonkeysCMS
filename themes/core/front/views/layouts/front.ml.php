<!DOCTYPE html>
<html lang="{{ $language ?? 'en' }}">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>@yield('title', $site_name ?? 'MonkeysCMS')</title>
  <meta name="description" content="@yield('meta_description', $site_tagline ?? '')">
  @isset($meta_image)
  <meta property="og:image" content="{{ $meta_image }}">
  @endisset
  <meta property="og:title" content="@yield('title', $site_name ?? 'MonkeysCMS')">
  <meta property="og:type" content="website">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="/themes/core/front/css/front.css">
  <link rel="stylesheet" href="/build/assets/frontend-css.css">
  @stack('head')
</head>
<body>

  {{-- ═══ Header ═══ --}}
  <header class="site-header">
    <div class="container site-header__inner">
      <a href="/" class="site-header__logo">
        <span class="site-header__logo-icon">🐒</span>
        <span>{{ $site_name ?? 'MonkeysCMS' }}</span>
      </a>

      <button class="site-nav__mobile-toggle" $m-on:click="toggleMenu()" aria-label="Toggle menu">☰</button>

      <nav class="site-nav" :class="{ open: menuOpen }">
        @isset($main_menu)
          @foreach($main_menu as $item)
          <a href="{{ $item['url'] ?? '#' }}" class="site-nav__link">{{ $item['title'] }}</a>
          @endforeach
        @else
          <a href="/" class="site-nav__link">Home</a>
          <a href="/blog" class="site-nav__link">Blog</a>
          <a href="/about" class="site-nav__link">About</a>
          <a href="/contact" class="site-nav__link">Contact</a>
        @endisset
      </nav>
    </div>
  </header>

  {{-- ═══ Hero Region ═══ --}}
  @yield('hero')

  {{-- ═══ Main Content ═══ --}}
  <main>
    @yield('content')
  </main>

  {{-- ═══ Footer ═══ --}}
  <footer class="site-footer">
    <div class="container">
      <div class="site-footer__inner">
        <div>
          <div class="site-footer__brand">{{ $site_name ?? 'MonkeysCMS' }}</div>
          <p class="site-footer__desc">{{ $site_tagline ?? 'A modern CMS powered by MonkeysLegion.' }}</p>
        </div>
        @isset($footer_menu)
          @foreach($footer_menu as $group)
          <div>
            <h4 class="site-footer__heading">{{ $group['title'] }}</h4>
            <ul class="site-footer__links">
              @foreach($group['children'] ?? [] as $item)
              <li><a href="{{ $item['url'] ?? '#' }}">{{ $item['title'] }}</a></li>
              @endforeach
            </ul>
          </div>
          @endforeach
        @else
          <div>
            <h4 class="site-footer__heading">Navigation</h4>
            <ul class="site-footer__links">
              <li><a href="/">Home</a></li>
              <li><a href="/blog">Blog</a></li>
              <li><a href="/about">About</a></li>
            </ul>
          </div>
          <div>
            <h4 class="site-footer__heading">Legal</h4>
            <ul class="site-footer__links">
              <li><a href="/privacy">Privacy Policy</a></li>
              <li><a href="/terms">Terms of Service</a></li>
            </ul>
          </div>
        @endisset
      </div>
      <div class="site-footer__bottom">
        &copy; {{ date('Y') }} {{ $site_name ?? 'MonkeysCMS' }}. Powered by <a href="https://monkeyslegion.com">MonkeysLegion</a>.
      </div>
    </div>
  </footer>

  <script type="module" src="/build/assets/frontend.js"></script>
  @stack('scripts')
</body>
</html>
