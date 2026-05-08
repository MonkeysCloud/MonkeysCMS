{{-- Dynamic Block: Hero --}}
@php
  $title = htmlspecialchars($data['title'] ?? 'Welcome');
  $subtitle = htmlspecialchars($data['subtitle'] ?? '');
  $mediaId = (int) ($data['bg_image'] ?? 0);
  $style = $data['style'] ?? 'dark';
  
  $bgStyle = '';
  if ($mediaId) {
      $bgStyle = 'background-image: url(/api/cms/media/' . $mediaId . '/thumb); background-size: cover; background-position: center;';
  }
  
  $textColor = $style === 'dark' ? '#fff' : '#1e293b';
  $overlay = $style === 'dark' ? 'rgba(0,0,0,0.6)' : 'rgba(255,255,255,0.8)';
@endphp

<div class="block-hero" style="{{ $bgStyle }} position: relative; border-radius: var(--block-radius); overflow: hidden;">
    <div style="position: absolute; inset: 0; background: {{ $overlay }}; z-index: 1;"></div>
    <div style="position: relative; z-index: 2; padding: 4rem 2rem; text-align: center; color: {{ $textColor }};">
        <h1 style="margin: 0 0 1rem; font-size: 2.5rem; font-weight: 800; line-height: 1.1;">{{ $title }}</h1>
        @if($subtitle)
            <p style="margin: 0; font-size: 1.25rem; opacity: 0.9;">{{ $subtitle }}</p>
        @endif
    </div>
</div>
