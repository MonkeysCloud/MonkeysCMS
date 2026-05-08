{{-- Block: Heading --}}
@php
  $level = in_array($data['level'] ?? 'h2', ['h2','h3','h4','h5','h6']) ? $data['level'] : 'h2';
  $text  = htmlspecialchars($data['text'] ?? '');
  $id    = !empty($data['id']) ? ' id="' . htmlspecialchars($data['id']) . '"' : '';
@endphp
<{{ $level }}{{ $id }} class="block-heading">{{ $data['text'] ?? '' }}</{{ $level }}>
