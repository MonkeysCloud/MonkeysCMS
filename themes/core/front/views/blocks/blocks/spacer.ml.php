{{-- Block: Spacer --}}
@php
  $height = (int) ($data['height'] ?? 40);
@endphp
<div class="block-spacer" style="height: {{ $height }}px" aria-hidden="true"></div>
