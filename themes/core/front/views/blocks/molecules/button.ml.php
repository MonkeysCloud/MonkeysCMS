{{-- Molecule: Button — Link styled as a CTA button --}}
@php
  $text   = $data['text'] ?? 'Click here';
  $url    = $data['url'] ?? '#';
  $style  = $data['style'] ?? 'primary';
  $target = ($data['target'] ?? '_self') === '_blank' ? ' target="_blank" rel="noopener"' : '';
  $cls    = 'block-button block-button--' . htmlspecialchars($style)
          . (!empty($settings['css_class']) ? ' ' . htmlspecialchars($settings['css_class']) : '');
@endphp
<div class="{{ $cls }}">
  <a href="{{ $url }}" class="block-button__link block-button__link--{{ $style }}"{{ $target }}>
    {{ $text }}
  </a>
</div>
