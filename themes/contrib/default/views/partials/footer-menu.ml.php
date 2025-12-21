{{-- Footer Menu Partial --}}
@if($footer_menu ?? false)
    <nav class="footer-navigation" aria-label="Footer navigation">
        <ul class="menu footer-menu">
            @foreach($footer_menu as $item)
                <li class="menu-item">
                    <a href="{{ $item['url'] }}" @if($item['target'] ?? false) target="{{ $item['target'] }}" @endif>
                        {{ $item['label'] }}
                    </a>
                </li>
            @endforeach
        </ul>
    </nav>
@endif
