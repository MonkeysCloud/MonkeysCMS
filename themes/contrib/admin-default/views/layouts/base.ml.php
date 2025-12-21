<!DOCTYPE html>
<html lang="{{ $locale ?? 'en' }}" class="@if($dark_mode ?? false) dark @endif">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    
    <title>@yield('title', 'Admin') - {{ $site_name ?? 'MonkeysCMS' }}</title>
    
    {{-- Admin Styles --}}
    <link rel="stylesheet" href="{{ $theme_asset('css/admin.css') }}">
    
    @stack('head')
    @stack('styles')
</head>
<body class="admin-body @yield('body_class')">
    <div class="admin-wrapper">
        {{-- Sidebar --}}
        <aside class="admin-sidebar @if($sidebar_collapsed ?? false) is-collapsed @endif" id="admin-sidebar">
            <div class="sidebar-header">
                <a href="/admin" class="sidebar-logo">
                    <span class="logo-icon">🐵</span>
                    <span class="logo-text">MonkeysCMS</span>
                </a>
                <button class="sidebar-toggle" id="sidebar-toggle" aria-label="Toggle sidebar">
                    <span></span>
                </button>
            </div>
            
            <nav class="sidebar-nav">
                @include('partials.admin-menu')
            </nav>
            
            <div class="sidebar-footer">
                @if($current_user ?? false)
                    <div class="user-info">
                        <span class="user-avatar">{{ substr($current_user['name'] ?? 'A', 0, 1) }}</span>
                        <span class="user-name">{{ $current_user['name'] ?? 'Admin' }}</span>
                    </div>
                @endif
            </div>
        </aside>
        
        {{-- Main Content --}}
        <div class="admin-main">
            {{-- Header --}}
            <header class="admin-header">
                <div class="header-left">
                    <button class="mobile-menu-toggle" id="mobile-menu-toggle" aria-label="Toggle menu">
                        <span></span>
                        <span></span>
                        <span></span>
                    </button>
                    
                    @hasSection('breadcrumbs')
                        <nav class="admin-breadcrumbs" aria-label="Breadcrumb">
                            @yield('breadcrumbs')
                        </nav>
                    @endif
                </div>
                
                <div class="header-right">
                    @yield('header_actions')
                    
                    <a href="/" class="header-action" target="_blank" title="View Site">
                        <span class="icon">🌐</span>
                    </a>
                    
                    <a href="/admin/logout" class="header-action" title="Logout">
                        <span class="icon">🚪</span>
                    </a>
                </div>
            </header>
            
            {{-- Page Content --}}
            <main class="admin-content">
                {{-- Flash Messages --}}
                @if($flash_messages ?? false)
                    <div class="admin-alerts">
                        @foreach($flash_messages as $type => $message)
                            <div class="alert alert-{{ $type }}">
                                <span class="alert-message">{{ $message }}</span>
                                <button class="alert-close" aria-label="Close">&times;</button>
                            </div>
                        @endforeach
                    </div>
                @endif
                
                {{-- Page Header --}}
                @hasSection('page_header')
                    <div class="page-header">
                        @yield('page_header')
                    </div>
                @else
                    @hasSection('page_title')
                        <div class="page-header">
                            <h1 class="page-title">@yield('page_title')</h1>
                            @hasSection('page_actions')
                                <div class="page-actions">
                                    @yield('page_actions')
                                </div>
                            @endif
                        </div>
                    @endif
                @endif
                
                {{-- Main Content --}}
                <div class="page-content">
                    @yield('content')
                    {{ $slot ?? '' }}
                </div>
            </main>
            
            {{-- Footer --}}
            <footer class="admin-footer">
                <p>MonkeysCMS v{{ $cms_version ?? '1.0.0' }} &bull; &copy; {{ date('Y') }}</p>
            </footer>
        </div>
    </div>
    
    {{-- Admin Scripts --}}
    <script src="{{ $theme_asset('js/admin.js') }}"></script>
    
    @stack('scripts')
</body>
</html>
