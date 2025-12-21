{{-- Main Menu Partial --}}
@if($main_menu ?? false)
    <ul class="menu main-menu">
        @foreach($main_menu as $item)
            <li class="menu-item @if($item['active'] ?? false) is-active @endif @if($item['children'] ?? false) has-children @endif">
                <a href="{{ $item['url'] }}" @if($item['target'] ?? false) target="{{ $item['target'] }}" @endif>
                    @if($item['icon'] ?? false)
                        <span class="menu-icon">{{ $item['icon'] }}</span>
                    @endif
                    <span class="menu-label">{{ $item['label'] }}</span>
                </a>
                
                @if($item['children'] ?? false)
                    <ul class="submenu">
                        @foreach($item['children'] as $child)
                            <li class="menu-item @if($child['active'] ?? false) is-active @endif">
                                <a href="{{ $child['url'] }}">{{ $child['label'] }}</a>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </li>
        @endforeach
    </ul>
@endif
