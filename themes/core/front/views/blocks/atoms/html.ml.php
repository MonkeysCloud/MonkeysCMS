{{-- Atom: HTML — Raw HTML/embed block --}}
<div class="block-html{{ !empty($settings['css_class']) ? ' ' . $settings['css_class'] : '' }}">
  {!! $data['content'] ?? $data['code'] ?? '' !!}
</div>
