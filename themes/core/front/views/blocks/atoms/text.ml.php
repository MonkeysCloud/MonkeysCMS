{{-- Atom: Text — Rich text content block --}}
<div class="block-text{{ !empty($settings['css_class']) ? ' ' . $settings['css_class'] : '' }}">
  {!! $data['body'] ?? '' !!}
</div>
