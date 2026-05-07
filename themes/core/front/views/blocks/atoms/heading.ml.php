{{-- Atom: Heading --}}
@php
  $level = in_array($data['level'] ?? 'h2', ['h1','h2','h3','h4','h5','h6']) ? $data['level'] : 'h2';
  $text  = $data['text'] ?? '';
  $id    = !empty($data['id']) ? ' id="' . htmlspecialchars($data['id']) . '"' : '';
  $cls   = 'block-heading' . (!empty($settings['css_class']) ? ' ' . htmlspecialchars($settings['css_class']) : '');
@endphp
<{{ $level }}{{ $id }} class="{{ $cls }}">{{ $text }}</{{ $level }}>
