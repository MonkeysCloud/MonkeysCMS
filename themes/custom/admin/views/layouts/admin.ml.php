<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MonkeysCMS Admin</title>
    <meta name="csrf-token" content="{{ $csrf_token ?? '' }}">
    
    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Tailwind CSS (compiled) -->
    <link rel="stylesheet" href="/css/app.css">
    
    <!-- Dynamic Assets CSS -->
    <?= $assets ? $assets->renderCss() : '' ?>

    @stack('styles')
</head>
<body class="h-full bg-gray-50 text-gray-900 font-sans antialiased">

    <!-- Mobile Menu Overlay -->
    <div id="mobile-overlay" class="fixed inset-0 bg-black/50 z-40 hidden lg:hidden" onclick="toggleMobileMenu()"></div>

    <div class="h-full flex">
        <!-- Sidebar -->
        <aside id="sidebar" class="fixed lg:static inset-y-0 left-0 w-72 bg-slate-900 text-slate-300 flex flex-col flex-shrink-0 z-50 transform -translate-x-full lg:translate-x-0 transition-transform duration-300 ease-in-out border-r border-slate-800 shadow-2xl">
            <!-- Logo -->
            <div class="h-16 flex items-center justify-between px-6 bg-slate-950/30 border-b border-white/5 backdrop-blur-sm flex-shrink-0">
                <div class="flex items-center gap-3">
                     <img src="/themes/custom/admin/assets/images/icon-monkey.png" alt="MonkeysCMS" class="w-8 h-8 object-contain">
                    <span class="font-bold text-lg text-white tracking-tight">MonkeysCMS</span>
                </div>
                <!-- Close button for mobile -->
                <button onclick="toggleMobileMenu()" class="lg:hidden text-slate-400 hover:text-white p-1">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>

            <!-- Navigation -->
            <nav class="flex-1 overflow-y-auto py-6 px-4 space-y-1 custom-scrollbar">
                @if(!empty($admin_menu_tree))
                    @php
                        $currentUri = $_SERVER['REQUEST_URI'] ?? '/';
                        $isActive = function($url) use ($currentUri) {
                            $path = parse_url($url, PHP_URL_PATH);
                            $path = trim($path, '/');
                            $current = trim($currentUri, '/');
                            
                            // If the link is just the admin root, require exact match to avoid highlighting on all subpages
                            if ($path === 'admin') {
                                return $current === 'admin';
                            }

                            // For other links, allow exact match or sub-path match (e.g. /admin/media matches /admin/media/create)
                            return $path !== '' && ($current === $path || str_starts_with($current, $path . '/'));
                        };
                    @endphp

                    @foreach($admin_menu_tree as $item)
                        <div x-data="{ open: {{ !empty($item->children) ? 'true' : 'false' }} }">
                            <a href="{{ $item->getResolvedUrl() }}" 
                               class="group flex items-center px-3 py-2.5 text-sm font-medium rounded-xl transition-all duration-200 {{ $isActive($item->getResolvedUrl()) ? 'bg-indigo-600/10 text-white shadow-inner ring-1 ring-white/10' : 'hover:bg-white/5 hover:text-white' }}">
                               
                               @if($item->icon)
                                    <span class="mr-3 w-5 h-5 {{ $isActive($item->getResolvedUrl()) ? 'text-indigo-400' : 'text-slate-500 group-hover:text-indigo-400' }} transition-colors">{{ $item->icon }}</span>
                               @else
                                    <!-- Default Icon -->
                                    <svg class="mr-3 h-5 w-5 {{ $isActive($item->getResolvedUrl()) ? 'text-indigo-400' : 'text-slate-500 group-hover:text-indigo-400' }} transition-colors" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                                    </svg>
                               @endif

                                <span class="flex-1">{{ $item->title }}</span>
                            </a>

                            @if(!empty($item->children))
                                <div class="ml-4 mt-1 space-y-1 border-l border-white/10 pl-2">
                                    @foreach($item->children as $child)
                                        <a href="{{ $child->getResolvedUrl() }}" 
                                           class="group flex items-center px-3 py-2 text-sm font-medium rounded-lg transition-colors {{ $isActive($child->getResolvedUrl()) ? 'text-white' : 'text-slate-400 hover:text-white' }}">
                                            {{ $child->title }}
                                        </a>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    @endforeach
                @else
                    <!-- Fallback Navigation -->
                    <div class="space-y-1">
                        <p class="px-3 text-xs font-semibold text-slate-500 uppercase tracking-wider mb-2">Menu</p>
                        <a href="/admin/dashboard" class="flex items-center px-3 py-2.5 text-sm font-medium rounded-xl text-white bg-white/10">
                            <svg class="mr-3 h-5 w-5 text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
                            Dashboard
                        </a>
                        <a href="/admin/users" class="flex items-center px-3 py-2.5 text-sm font-medium rounded-xl text-slate-300 hover:bg-white/5 hover:text-white transition-colors">
                            <svg class="mr-3 h-5 w-5 text-slate-500 group-hover:text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
                            Users
                        </a>
                         <a href="/admin/settings" class="flex items-center px-3 py-2.5 text-sm font-medium rounded-xl text-slate-300 hover:bg-white/5 hover:text-white transition-colors">
                            <svg class="mr-3 h-5 w-5 text-slate-500 group-hover:text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                            Settings
                        </a>
                    </div>
                @endif
            </nav>
            
            <!-- User / Footer -->
            <div class="border-t border-white/5 p-4 bg-slate-950/20 flex-shrink-0">
                 <a href="/" target="_blank" class="flex items-center gap-2 text-sm text-slate-400 hover:text-white transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                    View Website
                </a>
            </div>
        </aside>

        <!-- Main Content Wrapper -->
        <div class="flex-1 flex flex-col min-w-0">
            <!-- Mobile Header -->
            <header class="lg:hidden bg-white border-b border-gray-200 px-4 py-3 flex items-center justify-between shadow-sm flex-shrink-0">
                <button onclick="toggleMobileMenu()" class="p-2 rounded-lg hover:bg-gray-100 text-gray-600">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
                    </svg>
                </button>
                <div class="flex items-center gap-2">
                    <img src="/themes/custom/admin/assets/images/icon-monkey.png" alt="MonkeysCMS" class="w-6 h-6 object-contain">
                    <span class="font-semibold text-gray-800">Admin</span>
                </div>
                @if(isset($current_user) && $current_user->avatar)
                    <img src="{{ $current_user->avatar }}" alt="{{ $current_user->display_name }}" 
                         class="h-8 w-8 rounded-full object-cover border-2 border-white shadow-sm">
                @else
                    <div class="h-8 w-8 rounded-full bg-gradient-to-br from-indigo-500 to-purple-600 flex items-center justify-center text-white font-bold text-sm">
                        {{ isset($current_user) ? strtoupper(substr($current_user->display_name, 0, 1)) : 'A' }}
                    </div>
                @endif
            </header>

            <!-- Main Content -->
            <main class="flex-1 overflow-y-auto bg-[#F9FAFB] p-4 sm:p-6 lg:p-8">
                <!-- Desktop Top Header -->
                <div class="hidden lg:flex max-w-7xl mx-auto mb-8 justify-between items-center">
                    <div><!-- Breadcrumbs potentially here --></div>
                    
                    <!-- User Dropdown -->
                    <div x-data="{ open: false }" class="relative">
                        <button @click="open = !open" @click.outside="open = false" 
                                class="flex items-center gap-3 px-3 py-2 rounded-xl hover:bg-gray-100 transition-colors focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">
                            <div class="text-sm text-gray-600">
                                Welcome, <span class="font-medium text-gray-900">{{ isset($current_user) ? $current_user->display_name : 'Admin' }}</span>
                            </div>
                            @if(isset($current_user) && $current_user->avatar)
                                <img src="{{ $current_user->avatar }}" alt="{{ $current_user->display_name }}" 
                                     class="rounded-full object-cover border-2 border-white shadow-sm"
                                     style="width: 36px; height: 36px; min-width: 36px; min-height: 36px;">
                            @else
                                <div class="rounded-full bg-gradient-to-br from-indigo-500 to-purple-600 flex items-center justify-center text-white font-bold text-sm border-2 border-white shadow-sm"
                                     style="width: 36px; height: 36px; min-width: 36px; min-height: 36px;">
                                    {{ isset($current_user) ? strtoupper(substr($current_user->display_name, 0, 1)) : 'A' }}
                                </div>
                            @endif
                            <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                            </svg>
                        </button>
                        
                        <!-- Dropdown Menu -->
                        <div x-show="open" 
                             x-transition:enter="transition ease-out duration-100"
                             x-transition:enter-start="transform opacity-0 scale-95"
                             x-transition:enter-end="transform opacity-100 scale-100"
                             x-transition:leave="transition ease-in duration-75"
                             x-transition:leave-start="transform opacity-100 scale-100"
                             x-transition:leave-end="transform opacity-0 scale-95"
                             class="absolute right-0 mt-2 w-56 origin-top-right rounded-xl bg-white shadow-lg ring-1 ring-black ring-opacity-5 focus:outline-none z-50 overflow-hidden">
                            
                            <!-- User Info Header -->
                            <div class="px-4 py-3 border-b border-gray-100 bg-gray-50">
                                <p class="text-sm font-medium text-gray-900">{{ isset($current_user) ? $current_user->display_name : 'Admin' }}</p>
                                <p class="text-xs text-gray-500 truncate">{{ isset($current_user) ? $current_user->email : '' }}</p>
                            </div>
                            
                            <div class="py-1">
                                <!-- View Profile -->
                                <a href="/admin/users/{{ isset($current_user) ? $current_user->id : 0 }}/view" 
                                   class="group flex items-center px-4 py-2.5 text-sm text-gray-700 hover:bg-indigo-50 hover:text-indigo-700 transition-colors">
                                    <svg class="mr-3 h-4 w-4 text-gray-400 group-hover:text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                                    </svg>
                                    View Profile
                                </a>
                                
                                <!-- Edit Profile -->
                                <a href="/admin/users/{{ isset($current_user) ? $current_user->id : 0 }}/edit" 
                                   class="group flex items-center px-4 py-2.5 text-sm text-gray-700 hover:bg-indigo-50 hover:text-indigo-700 transition-colors">
                                    <svg class="mr-3 h-4 w-4 text-gray-400 group-hover:text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                    </svg>
                                    Edit Profile
                                </a>
                                
                                <!-- User Settings -->
                                <a href="/admin/settings/users" 
                                   class="group flex items-center px-4 py-2.5 text-sm text-gray-700 hover:bg-indigo-50 hover:text-indigo-700 transition-colors">
                                    <svg class="mr-3 h-4 w-4 text-gray-400 group-hover:text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/>
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                    </svg>
                                    Settings
                                </a>
                            </div>
                            
                            <!-- Logout -->
                            <div class="border-t border-gray-100">
                                <form action="/logout" method="POST" class="m-0">
                                    <input type="hidden" name="_token" value="{{ $csrf_token }}">
                                    <button type="submit" 
                                            class="group flex w-full items-center px-4 py-2.5 text-sm text-red-600 hover:bg-red-50 transition-colors">
                                        <svg class="mr-3 h-4 w-4 text-red-400 group-hover:text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
                                        </svg>
                                        Sign Out
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="max-w-7xl mx-auto space-y-6 lg:space-y-8">
                    @yield('content')
                </div>
            </main>
        </div>
    </div>
    
    <!-- Dynamic Assets (HTMX, Alpine, CKEditor, etc) -->
    <?= $assets ? $assets->renderJs() : '' ?>

    @stack('scripts')
    
    <script>
        function toggleMobileMenu() {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('mobile-overlay');
            const isOpen = !sidebar.classList.contains('-translate-x-full');
            
            if (isOpen) {
                sidebar.classList.add('-translate-x-full');
                overlay.classList.add('hidden');
                document.body.classList.remove('overflow-hidden');
            } else {
                sidebar.classList.remove('-translate-x-full');
                overlay.classList.remove('hidden');
                document.body.classList.add('overflow-hidden');
            }
        }
    </script>
    
    <style>
        [x-cloak] { display: none !important; }
        
        /* HTMX Loading States */
        .htmx-request-active .htmx-indicator { opacity: 1; }
        .htmx-indicator { opacity: 0; transition: opacity 200ms ease-in; }
        
        /* Sortable Status */
        .sortable-status[data-status="saving"] { color: #6b7280; }
        .sortable-status[data-status="saved"] { color: #059669; }
        .sortable-status[data-status="error"] { color: #dc2626; }
        
        /* Custom scrollbar for sidebar */
        .custom-scrollbar::-webkit-scrollbar { width: 4px; }
        .custom-scrollbar::-webkit-scrollbar-track { background: transparent; }
        .custom-scrollbar::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.1); border-radius: 2px; }
        .custom-scrollbar::-webkit-scrollbar-thumb:hover { background: rgba(255,255,255,0.2); }
    </style>
    
    @include('partials.confirmation_modal')
</body>
</html>
