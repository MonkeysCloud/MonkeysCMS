{{-- Molecule: Image — Image with optional caption and link --}}
@php
  $mediaId = (int) ($data['media_id'] ?? 0);
  $alt = htmlspecialchars($data['alt'] ?? '');
  $caption = $data['caption'] ?? '';
  $link = $data['link'] ?? '';
  $target = ($data['target'] ?? '_self') === '_blank' ? ' target="_blank" rel="noopener"' : '';
  $cls = 'block-image' . (!empty($settings['css_class']) ? ' ' . htmlspecialchars($settings['css_class']) : '');
  // Use media API endpoint which resolves to the correct file path
  $src = $mediaId ? '/api/cms/media/' . $mediaId . '/thumb' : '';
@endphp
@if($mediaId)
<figure class="{{ $cls }}">
  @if($link)
  <a href="{{ $link }}"{!! $target !!}>
  @endif

  <img src="{{ $src }}"
       alt="{{ $alt }}"
       loading="lazy"
       class="block-image__img">

  @if($link)
  </a>
  @endif

  @if($caption)
  <figcaption class="block-image__caption">{{ $caption }}</figcaption>
  @endif
</figure>
@else
<div class="{{ $cls }} block-image--empty">
  <span class="block-image__placeholder">No image selected</span>
</div>
@endif
