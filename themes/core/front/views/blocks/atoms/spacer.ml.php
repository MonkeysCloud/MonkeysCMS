{{-- Atom: Spacer --}}
@php
  $height = $data['height'] ?? '2rem';
@endphp
<div class="block-spacer{{ !empty($settings['css_class']) ? ' ' . $settings['css_class'] : '' }}" style="height: {{ htmlspecialchars($height) }}" aria-hidden="true"></div>
