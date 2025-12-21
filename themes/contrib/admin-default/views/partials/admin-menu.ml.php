{{-- Admin Menu Partial --}}
<ul class="admin-menu">
    {{-- Dashboard --}}
    <li class="menu-item @if($current_route == 'admin.dashboard') is-active @endif">
        <a href="/admin">
            <span class="menu-icon">📊</span>
            <span class="menu-label">Dashboard</span>
        </a>
    </li>
    
    {{-- Content Section --}}
    <li class="menu-section">
        <span class="section-label">Content</span>
    </li>
    
    @if($content_types ?? false)
        @foreach($content_types as $type => $info)
            <li class="menu-item @if($current_content_type ?? '' == $type) is-active @endif">
                <a href="/admin/content/{{ $type }}">
                    <span class="menu-icon">{{ $info['icon'] ?? '📄' }}</span>
                    <span class="menu-label">{{ $info['label'] ?? ucfirst($type) }}</span>
                </a>
            </li>
        @endforeach
    @endif
    
    {{-- Media --}}
    <li class="menu-item @if($current_route == 'admin.media') is-active @endif">
        <a href="/admin/media">
            <span class="menu-icon">🖼️</span>
            <span class="menu-label">Media</span>
        </a>
    </li>
    
    {{-- Structure Section --}}
    <li class="menu-section">
        <span class="section-label">Structure</span>
    </li>
    
    <li class="menu-item @if($current_route == 'admin.menus') is-active @endif">
        <a href="/admin/menus">
            <span class="menu-icon">📋</span>
            <span class="menu-label">Menus</span>
        </a>
    </li>
    
    <li class="menu-item @if($current_route == 'admin.blocks') is-active @endif">
        <a href="/admin/blocks">
            <span class="menu-icon">🧱</span>
            <span class="menu-label">Blocks</span>
        </a>
    </li>
    
    {{-- Appearance Section --}}
    <li class="menu-section">
        <span class="section-label">Appearance</span>
    </li>
    
    <li class="menu-item @if($current_route == 'admin.themes') is-active @endif">
        <a href="/admin/themes">
            <span class="menu-icon">🎨</span>
            <span class="menu-label">Themes</span>
        </a>
    </li>
    
    {{-- Extend Section --}}
    <li class="menu-section">
        <span class="section-label">Extend</span>
    </li>
    
    <li class="menu-item @if($current_route == 'admin.modules') is-active @endif">
        <a href="/admin/modules">
            <span class="menu-icon">🧩</span>
            <span class="menu-label">Modules</span>
        </a>
    </li>
    
    {{-- Configuration Section --}}
    <li class="menu-section">
        <span class="section-label">Configuration</span>
    </li>
    
    <li class="menu-item @if($current_route == 'admin.users') is-active @endif">
        <a href="/admin/users">
            <span class="menu-icon">👥</span>
            <span class="menu-label">Users</span>
        </a>
    </li>
    
    <li class="menu-item @if($current_route == 'admin.settings') is-active @endif">
        <a href="/admin/settings">
            <span class="menu-icon">⚙️</span>
            <span class="menu-label">Settings</span>
        </a>
    </li>
</ul>
