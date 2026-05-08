{{-- ═══ Hreflang Alternate Links (SEO) ═══ --}}
{{-- Tells search engines about this page's translations --}}
@if(!empty($multilingualEnabled) && !empty($translationSiblings))
@php
  $appUrl = rtrim($_ENV['APP_URL'] ?? '', '/');
@endphp

{{-- hreflang for each translation sibling --}}
@foreach($translationSiblings as $siblingLang => $siblingUrl)
<link rel="alternate" hreflang="{{ $siblingLang }}" href="{{ $appUrl }}{{ $siblingUrl }}">
@endforeach

{{-- x-default points to the default language version --}}
@if(isset($translationSiblings[$defaultLang ?? 'en']))
<link rel="alternate" hreflang="x-default" href="{{ $appUrl }}{{ $translationSiblings[$defaultLang ?? 'en'] }}">
@endif

{{-- Open Graph locale tags --}}
<meta property="og:locale" content="{{ str_replace('-', '_', $locale ?? 'en') }}">
@foreach($translationSiblings as $siblingLang => $siblingUrl)
@if($siblingLang !== ($locale ?? 'en'))
<meta property="og:locale:alternate" content="{{ str_replace('-', '_', $siblingLang) }}">
@endif
@endforeach
@endif
