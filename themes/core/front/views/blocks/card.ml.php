{{-- Feature Card Block Template --}}
@php
$title = $data['title'] ?? '';
$subtitle = $data['subtitle'] ?? '';
$body = $data['body'] ?? '';
$mediaId = $data['image'] ?? 0;
$cta = $data['cta'] ?? [];
$featured = !empty($data['featured']);
$bgColor = $data['bg_color'] ?? '#1a1b2e';
$features = $data['features'] ?? [];
$cssClass = $settings['css_class'] ?? '';
$cardClass = 'block-card' . ($featured ? ' block-card--featured' : '') . ($cssClass ? ' ' . $cssClass : '');
@endphp

<article class="{{ $cardClass }}" style="background-color: {{ $bgColor }}">
    @if($mediaId)
    <div class="block-card__image">
        {!! cms_image((int)$mediaId, 'medium', $title, '', 'lazy') !!}
    </div>
    @endif

    <div class="block-card__content">
        @if($title)
        <h3 class="block-card__title">{{ $title }}</h3>
        @endif

        @if($subtitle)
        <p class="block-card__subtitle">{{ $subtitle }}</p>
        @endif

        @if($body)
        <div class="block-card__body">{!! $body !!}</div>
        @endif

        @if(!empty($features))
        <ul class="block-card__features">
            @foreach($features as $feature)
            <li class="block-card__feature">
                @if(!empty($feature['icon']))
                <span class="block-card__feature-icon">{{ $feature['icon'] }}</span>
                @endif
                <span>{{ $feature['text'] ?? '' }}</span>
            </li>
            @endforeach
        </ul>
        @endif

        @if(!empty($cta['url']))
        <a href="{{ $cta['url'] }}" class="block-card__cta" @if(($cta['target'] ?? '_self' )==='_blank' )
            target="_blank" rel="noopener" @endif>
            {{ $cta['label'] ?? 'Learn More' }}
        </a>
        @endif
    </div>

    {{-- Footer slot: rendered nested blocks --}}
    @if(!empty($data['footer_slot']))
    <div class="block-card__footer">
        @foreach($data['footer_slot'] as $slotBlock)
        {!! $slotBlock['_rendered'] ?? '' !!}
        @endforeach
    </div>
    @endif
</article>