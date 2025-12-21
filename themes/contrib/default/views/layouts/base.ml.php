<!DOCTYPE html>
<html lang="{{ $locale ?? 'en' }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="@yield('meta_description', $site_description ?? '')">
    
    <title>@yield('title', $site_name ?? 'MonkeysCMS')</title>
    
    {{-- Theme Styles --}}
    <link rel="stylesheet" href="{{ $theme_asset('css/theme.css') }}">
    
    {{-- Additional head content --}}
    @stack('head')
    @stack('styles')
</head>
<body class="theme-default @yield('body_class')">
    {{-- Skip to content for accessibility --}}
    <a href="#main-content" class="skip-link">Skip to content</a>
    
    {{-- Header Region --}}
    <header class="site-header" role="banner">
        <div class="container">
            @yield('header')
            
            {{-- Site branding --}}
            <div class="site-branding">
                @if($site_logo ?? false)
                    <a href="/" class="site-logo">
                        <img src="{{ $site_logo }}" alt="{{ $site_name ?? 'Home' }}">
                    </a>
                @else
                    <a href="/" class="site-title">{{ $site_name ?? 'MonkeysCMS' }}</a>
                @endif
            </div>
            
            {{-- Navigation Region --}}
            <nav class="main-navigation" role="navigation" aria-label="Main navigation">
                @yield('navigation')
                @include('partials.main-menu')
            </nav>
        </div>
    </header>
    
    {{-- Hero Region (optional) --}}
    @hasSection('hero')
        <section class="site-hero">
            @yield('hero')
        </section>
    @endif
    
    {{-- Main Content Area --}}
    <div class="site-main">
        <div class="container">
            <div class="content-wrapper @if($show_sidebar ?? true) has-sidebar @endif">
                
                {{-- Main Content Region --}}
                <main id="main-content" class="site-content" role="main">
                    {{-- Breadcrumbs --}}
                    @hasSection('breadcrumbs')
                        <nav class="breadcrumbs" aria-label="Breadcrumb">
                            @yield('breadcrumbs')
                        </nav>
                    @endif
                    
                    {{-- Flash Messages --}}
                    @if($flash_messages ?? false)
                        <div class="flash-messages">
                            @foreach($flash_messages as $type => $message)
                                <div class="alert alert-{{ $type }}">{{ $message }}</div>
                            @endforeach
                        </div>
                    @endif
                    
                    {{-- Page Title --}}
                    @hasSection('page_title')
                        <h1 class="page-title">@yield('page_title')</h1>
                    @endif
                    
                    {{-- Main Content --}}
                    @yield('content')
                    {{ $slot ?? '' }}
                </main>
                
                {{-- Sidebar Region --}}
                @if($show_sidebar ?? true)
                    <aside class="site-sidebar" role="complementary">
                        @yield('sidebar')
                        @include('partials.sidebar-widgets')
                    </aside>
                @endif
                
            </div>
        </div>
    </div>
    
    {{-- Footer Widgets Region --}}
    @hasSection('footer_widgets')
        <section class="footer-widgets">
            <div class="container">
                @yield('footer_widgets')
            </div>
        </section>
    @endif
    
    {{-- Footer Region --}}
    <footer class="site-footer" role="contentinfo">
        <div class="container">
            @yield('footer')
            
            <div class="footer-content">
                <p class="copyright">
                    &copy; {{ date('Y') }} {{ $site_name ?? 'MonkeysCMS' }}. All rights reserved.
                </p>
                
                @include('partials.footer-menu')
            </div>
        </div>
    </footer>
    
    {{-- Theme Scripts --}}
    <script src="{{ $theme_asset('js/theme.js') }}"></script>
    
    {{-- Additional scripts --}}
    @stack('scripts')
</body>
</html>
