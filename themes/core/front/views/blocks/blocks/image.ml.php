{{-- Block: Image --}}
<figure class="block-image">
  @if(!empty($data['link']))
  <a href="{{ $data['link'] }}">
  @endif

  <img src="/uploads/{{ (int) ($data['media_id'] ?? 0) }}"
       alt="{{ $data['alt'] ?? '' }}"
       loading="lazy"
       class="block-image__img">

  @if(!empty($data['link']))
  </a>
  @endif

  @if(!empty($data['caption']))
  <figcaption class="block-image__caption">{{ $data['caption'] }}</figcaption>
  @endif
</figure>
