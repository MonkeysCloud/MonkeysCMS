{{-- Breadcrumb Component --}}
{{-- Usage: @include('components.breadcrumb', ['items' => [['label' => 'Home', 'url' => '/'], ['label' => 'Blog']]]) --}}
<nav aria-label="Breadcrumb" style="padding:0.75rem 0; font-size:0.85rem;">
  <ol style="display:flex; gap:0.5rem; list-style:none; color:var(--front-text-muted, var(--cms-text-muted));">
    @foreach($items ?? [] as $i => $crumb)
    <li>
      @if($i > 0)<span style="margin-right:0.5rem;">/</span>@endif
      @if(isset($crumb['url']) && $i < count($items) - 1)
      <a href="{{ $crumb['url'] }}" style="color:var(--front-text-secondary, var(--cms-text)); text-decoration:none;">{{ $crumb['label'] }}</a>
      @else
      <span>{{ $crumb['label'] }}</span>
      @endif
    </li>
    @endforeach
  </ol>
</nav>
