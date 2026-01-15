@php
    $currentUri = $_SERVER['REQUEST_URI'] ?? '/';
    $isActive = function($url) use ($currentUri) {
        $path = parse_url($url, PHP_URL_PATH);
        $path = trim($path, '/');
        $current = trim($currentUri, '/');
        if ($path === 'admin') {
            return $current === 'admin';
        }
        return $path !== '' && ($current === $path || str_starts_with($current, $path . '/'));
    };
    
    $isActiveSection = function($urls) use ($currentUri) {
        $current = trim($currentUri, '/');
        foreach ($urls as $url) {
            $path = trim(parse_url($url, PHP_URL_PATH), '/');
            if ($path !== '' && ($current === $path || str_starts_with($current, $path . '/'))) {
                return true;
            }
        }
        return false;
    };
@endphp

<style>
    /* Base Dropdown Styles */
    .sidebar-dropdown { position: relative; }
    .sidebar-dropdown .dropdown-menu {
        opacity: 0;
        visibility: hidden;
        transition: opacity 0.2s ease, visibility 0.2s;
        z-index: 50;
    }
    .sidebar-dropdown:hover .dropdown-menu {
        opacity: 1;
        visibility: visible;
    }
    .sidebar-dropdown .dropdown-arrow {
        transition: transform 0.2s ease;
    }
    
    /* Desktop: flyout to the right */
    @media (min-width: 1025px) {
        .sidebar-dropdown { overflow: visible; }
        .sidebar-nav, nav.flex-1, aside nav { overflow: visible !important; }
        aside.fixed, .admin-sidebar { overflow: visible !important; }
        .sidebar-dropdown .dropdown-menu {
            position: absolute;
            left: 100%;
            top: 0;
            min-width: 200px;
            background: rgb(30 41 59);
            border-radius: 0.5rem;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.3);
            padding: 0.5rem;
            margin-left: 0.5rem;
            transform: translateX(-10px);
        }
        .sidebar-dropdown:hover .dropdown-menu {
            transform: translateX(0);
        }
        .sidebar-dropdown:hover .dropdown-arrow {
            transform: rotate(-90deg);
        }
        .sidebar-dropdown .dropdown-menu a {
            border-left: none !important;
            margin-left: 0 !important;
            padding-left: 0.75rem !important;
        }
    }
    
    /* Mobile: expand below */
    @media (max-width: 1024px) {
        .sidebar-dropdown .dropdown-menu {
            position: static;
            max-height: 0;
            overflow: hidden;
            background: transparent;
            padding: 0;
            margin-left: 0.75rem;
            border-left: 2px solid rgb(51 65 85);
            padding-left: 0.75rem;
            transition: max-height 0.2s ease, opacity 0.2s ease, visibility 0.2s;
        }
        .sidebar-dropdown:hover .dropdown-menu {
            max-height: 300px;
            margin-top: 0.25rem;
        }
        .sidebar-dropdown:hover .dropdown-arrow {
            transform: rotate(180deg);
        }
    }
</style>

<div class="space-y-1">
    {{-- Dashboard --}}
    <a href="/admin" class="group flex items-center px-3 py-2.5 text-sm font-medium rounded-xl transition-all duration-200 {{ $isActive('/admin') ? 'bg-indigo-600/10 text-white shadow-inner ring-1 ring-white/10' : 'text-slate-400 hover:bg-white/5 hover:text-white' }}">
        <span class="mr-3 w-5 h-5 {{ $isActive('/admin') ? 'text-indigo-400' : 'text-slate-500 group-hover:text-indigo-400' }} transition-colors">📊</span>
        <span class="flex-1">Dashboard</span>
    </a>
    
    {{-- Content Section with Dropdown --}}
    <div class="sidebar-dropdown">
        <button class="w-full group flex items-center px-3 py-2.5 text-sm font-medium rounded-xl transition-all duration-200 {{ $isActiveSection(['/admin/content', '/admin/media']) ? 'bg-indigo-600/10 text-white shadow-inner ring-1 ring-white/10' : 'text-slate-400 hover:bg-white/5 hover:text-white' }}">
            <span class="mr-3 w-5 h-5 {{ $isActiveSection(['/admin/content', '/admin/media']) ? 'text-indigo-400' : 'text-slate-500 group-hover:text-indigo-400' }} transition-colors">📑</span>
            <span class="flex-1 text-left">Content</span>
            <svg class="w-4 h-4 text-slate-500 dropdown-arrow" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
            </svg>
        </button>
        <div class="dropdown-menu space-y-1">
            <a href="/admin/content" class="block px-3 py-2 text-sm rounded-lg transition-colors {{ $isActive('/admin/content') ? 'bg-indigo-600/20 text-white' : 'text-slate-400 hover:bg-white/5 hover:text-white' }}">
                <span class="mr-2">📄</span> All Content
            </a>
            @if($content_types ?? false)
                @foreach($content_types as $type => $info)
                    <a href="/admin/content/{{ $type }}" class="block px-3 py-2 text-sm rounded-lg transition-colors {{ $isActive('/admin/content/'.$type) ? 'bg-indigo-600/20 text-white' : 'text-slate-400 hover:bg-white/5 hover:text-white' }}">
                        <span class="mr-2">{{ $info['icon'] ?? '📄' }}</span> {{ $info['label'] ?? ucfirst($type) }}
                    </a>
                @endforeach
            @endif
            <a href="/admin/blocks" class="block px-3 py-2 text-sm rounded-lg transition-colors {{ $isActive('/admin/blocks') ? 'bg-indigo-600/20 text-white' : 'text-slate-400 hover:bg-white/5 hover:text-white' }}">
                <span class="mr-2">🧱</span> Blocks
            </a>
            <a href="/admin/media" class="block px-3 py-2 text-sm rounded-lg transition-colors {{ $isActive('/admin/media') ? 'bg-indigo-600/20 text-white' : 'text-slate-400 hover:bg-white/5 hover:text-white' }}">
                <span class="mr-2">🖼️</span> Media
            </a>
        </div>
    </div>
    
    {{-- Structure Section with Dropdown --}}
    <div class="sidebar-dropdown">
        <button class="w-full group flex items-center px-3 py-2.5 text-sm font-medium rounded-xl transition-all duration-200 {{ $isActiveSection(['/admin/structure', '/admin/menus', '/admin/blocks']) ? 'bg-indigo-600/10 text-white shadow-inner ring-1 ring-white/10' : 'text-slate-400 hover:bg-white/5 hover:text-white' }}">
            <span class="mr-3 w-5 h-5 {{ $isActiveSection(['/admin/structure', '/admin/menus', '/admin/blocks']) ? 'text-indigo-400' : 'text-slate-500 group-hover:text-indigo-400' }} transition-colors">🏗️</span>
            <span class="flex-1 text-left">Structure</span>
            <svg class="w-4 h-4 text-slate-500 dropdown-arrow" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
            </svg>
        </button>
        <div class="dropdown-menu space-y-1">
            <a href="/admin/structure/content-types" class="block px-3 py-2 text-sm rounded-lg transition-colors {{ $isActive('/admin/structure/content-types') ? 'bg-indigo-600/20 text-white' : 'text-slate-400 hover:bg-white/5 hover:text-white' }}">
                <span class="mr-2">📝</span> Content Types
            </a>
            <a href="/admin/menus" class="block px-3 py-2 text-sm rounded-lg transition-colors {{ $isActive('/admin/menus') ? 'bg-indigo-600/20 text-white' : 'text-slate-400 hover:bg-white/5 hover:text-white' }}">
                <span class="mr-2">📋</span> Menus
            </a>
            <a href="/admin/structure/block-types" class="block px-3 py-2 text-sm rounded-lg transition-colors {{ $isActive('/admin/structure/block-types') ? 'bg-indigo-600/20 text-white' : 'text-slate-400 hover:bg-white/5 hover:text-white' }}">
                <span class="mr-2">🧱</span> Block Types
            </a>
        </div>
    </div>
    
    {{-- Appearance - Direct Link --}}
    <a href="/admin/themes" class="group flex items-center px-3 py-2.5 text-sm font-medium rounded-xl transition-all duration-200 {{ $isActive('/admin/themes') ? 'bg-indigo-600/10 text-white shadow-inner ring-1 ring-white/10' : 'text-slate-400 hover:bg-white/5 hover:text-white' }}">
        <span class="mr-3 w-5 h-5 {{ $isActive('/admin/themes') ? 'text-indigo-400' : 'text-slate-500 group-hover:text-indigo-400' }} transition-colors">🎨</span>
        <span class="flex-1">Appearance</span>
    </a>
    
    {{-- Extend Section with Dropdown --}}
    <div class="sidebar-dropdown">
        <button class="w-full group flex items-center px-3 py-2.5 text-sm font-medium rounded-xl transition-all duration-200 {{ $isActiveSection(['/admin/modules']) ? 'bg-indigo-600/10 text-white shadow-inner ring-1 ring-white/10' : 'text-slate-400 hover:bg-white/5 hover:text-white' }}">
            <span class="mr-3 w-5 h-5 {{ $isActiveSection(['/admin/modules']) ? 'text-indigo-400' : 'text-slate-500 group-hover:text-indigo-400' }} transition-colors">🧩</span>
            <span class="flex-1 text-left">Extend</span>
            <svg class="w-4 h-4 text-slate-500 dropdown-arrow" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
            </svg>
        </button>
        <div class="dropdown-menu space-y-1">
            <a href="/admin/modules" class="block px-3 py-2 text-sm rounded-lg transition-colors {{ $isActive('/admin/modules') ? 'bg-indigo-600/20 text-white' : 'text-slate-400 hover:bg-white/5 hover:text-white' }}">
                <span class="mr-2">🧩</span> Modules
            </a>
        </div>
    </div>
    
    {{-- Configuration Section with Dropdown --}}
    <div class="sidebar-dropdown">
        <button class="w-full group flex items-center px-3 py-2.5 text-sm font-medium rounded-xl transition-all duration-200 {{ $isActiveSection(['/admin/users', '/admin/settings', '/admin/roles']) ? 'bg-indigo-600/10 text-white shadow-inner ring-1 ring-white/10' : 'text-slate-400 hover:bg-white/5 hover:text-white' }}">
            <span class="mr-3 w-5 h-5 {{ $isActiveSection(['/admin/users', '/admin/settings', '/admin/roles']) ? 'text-indigo-400' : 'text-slate-500 group-hover:text-indigo-400' }} transition-colors">⚙️</span>
            <span class="flex-1 text-left">Configuration</span>
            <svg class="w-4 h-4 text-slate-500 dropdown-arrow" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
            </svg>
        </button>
        <div class="dropdown-menu space-y-1">
            <a href="/admin/users" class="block px-3 py-2 text-sm rounded-lg transition-colors {{ $isActive('/admin/users') ? 'bg-indigo-600/20 text-white' : 'text-slate-400 hover:bg-white/5 hover:text-white' }}">
                <span class="mr-2">👥</span> Users
            </a>
            <a href="/admin/roles" class="block px-3 py-2 text-sm rounded-lg transition-colors {{ $isActive('/admin/roles') ? 'bg-indigo-600/20 text-white' : 'text-slate-400 hover:bg-white/5 hover:text-white' }}">
                <span class="mr-2">🔐</span> Roles & Permissions
            </a>
            <a href="/admin/settings" class="block px-3 py-2 text-sm rounded-lg transition-colors {{ $isActive('/admin/settings') ? 'bg-indigo-600/20 text-white' : 'text-slate-400 hover:bg-white/5 hover:text-white' }}">
                <span class="mr-2">⚙️</span> Settings
            </a>
        </div>
    </div>
</div>
