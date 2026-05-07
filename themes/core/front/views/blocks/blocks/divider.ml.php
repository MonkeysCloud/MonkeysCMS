{{-- Block: Divider --}}
@php
  $style = $data['style'] ?? 'solid';
  $color = $data['color'] ?? '';
  $width = $data['width'] ?? '100%';
  $css   = 'border-style:' . htmlspecialchars($style) . ';';
  if ($color) $css .= 'border-color:' . htmlspecialchars($color) . ';';
  if ($width !== '100%') $css .= 'width:' . htmlspecialchars($width) . ';margin-inline:auto;';
@endphp
<hr class="block-divider block-divider--{{ $style }}" style="{{ $css }}">
