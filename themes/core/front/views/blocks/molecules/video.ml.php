{{-- Molecule: Video — YouTube/Vimeo/native video with optional caption --}}
@php
  $url = $data['url'] ?? '';
  $embed = '';
  if (preg_match('/(?:youtube\.com\/watch\?v=|youtu\.be\/)([a-zA-Z0-9_-]+)/', $url, $m)) {
      $embed = 'https://www.youtube.com/embed/' . $m[1];
  } elseif (preg_match('/vimeo\.com\/(\d+)/', $url, $m)) {
      $embed = 'https://player.vimeo.com/video/' . $m[1];
  }
  $ratio = $data['ratio'] ?? '16:9';
  $paddingMap = ['16:9' => '56.25%', '4:3' => '75%', '1:1' => '100%', '21:9' => '42.86%'];
  $padding = $paddingMap[$ratio] ?? '56.25%';
  $cls = 'block-video' . (!empty($settings['css_class']) ? ' ' . htmlspecialchars($settings['css_class']) : '');
@endphp
<div class="{{ $cls }}">
  @if($embed)
  <div class="block-video__wrapper" style="padding-bottom: {{ $padding }}">
    <iframe src="{{ $embed }}"
            frameborder="0"
            allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
            allowfullscreen
            loading="lazy"
            title="{{ $data['title'] ?? 'Video' }}"></iframe>
  </div>
  @elseif($url)
  <video controls class="block-video__native" preload="metadata">
    <source src="{{ $url }}">
    Your browser does not support the video tag.
  </video>
  @endif
  @if(!empty($data['caption']))
  <p class="block-video__caption">{{ $data['caption'] }}</p>
  @endif
</div>
