<!DOCTYPE html>
<html lang="{{ $locale ?? $language ?? 'en' }}" dir="{{ $textDirection ?? 'ltr' }}">

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

  {{-- Front Theme CSS (Vite-built or raw fallback) --}}
  <link rel="stylesheet" href="{{ vite_asset('themes/core/front/css/front.css') }}">
  <link rel="stylesheet" href="/assets/css/search.css">

  @if(!empty($breadcrumb_jsonld))
  {!! $breadcrumb_jsonld !!}
  @endif

  @stack('head')

  {{-- Hreflang alternate links (SEO) --}}
  @include('partials.hreflang')

  {{-- RTL stylesheet (only for right-to-left languages) --}}
  @if(($textDirection ?? 'ltr') === 'rtl')
  <link rel="stylesheet" href="{{ vite_asset('themes/core/front/css/rtl.css') }}">
  @endif

  {{-- Global CSS + preconnect + fonts (injected by ThemeResolverMiddleware) --}}
  {!! $cms_head ?? '' !!}
</head>

<body>

  {{-- ═══ Global Admin Toolbar (rendered by middleware) ═══ --}}
  {!! $cms_admin_bar ?? '' !!}

  {{-- ═══ Node Management Toolbar (on content pages for admin users) ═══ --}}
  @include('partials.node-toolbar')

  {{-- ═══ Header ═══ --}}
  <header class="site-header">
    <div class="container site-header__inner">
      <a href="/" class="site-header__logo">
        <img src="/assets/images/monkeyscmslogo.svg" alt="MonkeysCMS" style="height:32px;width:auto;">
        <span>{{ $site_name ?? 'MonkeysCMS' }}</span>
      </a>

      <button class="site-nav__mobile-toggle" onclick="document.querySelector('.site-nav').classList.toggle('open')"
        aria-label="Toggle menu">☰</button>

      <nav class="site-nav" id="site-nav">
        @isset($menus['main'])
        @foreach($menus['main'] as $item)
        <a href="{{ $item['url'] ?? '#' }}" class="site-nav__link">{{ $item['title'] }}</a>
        @endforeach
        @else
        <a href="/" class="site-nav__link">Home</a>
        <a href="/blog" class="site-nav__link">Blog</a>
        <a href="/about" class="site-nav__link">About</a>
        <a href="/contact" class="site-nav__link">Contact</a>
        @endisset
        <button class="site-search-btn" data-mks-search aria-label="Search">
          <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
            <circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/>
          </svg>
          Search
          <kbd>⌘K</kbd>
        </button>

        {{-- Language Switcher --}}
        @include('partials.language-switcher')
      </nav>
    </div>
  </header>

  {{-- ═══ Hero Region ═══ --}}
  @yield('hero')

  {{-- ═══ Breadcrumbs ═══ --}}
  @if(isset($breadcrumbs) && !$breadcrumbs->isEmpty())
  <div class="container">
    <nav class="site-breadcrumb" aria-label="Breadcrumb">
      @foreach($breadcrumbs->getItems() as $i => $crumb)
      @if($i > 0)
      <span class="site-breadcrumb__sep">{{ $breadcrumbs->getSeparator() }}</span>
      @endif
      @if($crumb->isLink())
      <a href="{{ $crumb->url }}" class="site-breadcrumb__link">{{ $crumb->label }}</a>
      @else
      <span class="site-breadcrumb__current" aria-current="page">{{ $crumb->label }}</span>
      @endif
      @endforeach
    </nav>
  </div>
  @endif

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
        @isset($menus['footer'])
        @foreach($menus['footer'] as $group)
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
        &copy; {{ date('Y') }} {{ $site_name ?? 'MonkeysCMS' }}. Powered by <a
          href="https://monkeyslegion.com">MonkeysLegion</a>.
      </div>
    </div>
  </footer>

  {{-- Global JS libraries (injected by ThemeResolverMiddleware) --}}
  {!! $cms_scripts ?? '' !!}

  {{-- Global Search Widget --}}
  <script src="/assets/js/search.js" defer></script>

  @stack('scripts')
</body>

</html>