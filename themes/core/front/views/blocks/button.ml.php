{{-- Block: Button --}}
@php
  $text  = $data['text'] ?? 'Click here';
  $url   = $data['url'] ?? '#';
  $style = $data['style'] ?? 'primary';
  $target = !empty($data['new_tab']) ? ' target="_blank" rel="noopener"' : '';
@endphp
<div class="block-button block-button--{{ $style }}">
  <a href="{{ $url }}" class="block-button__link block-button__link--{{ $style }}"{{ $target }}>
    {{ $text }}
  </a>
</div>
