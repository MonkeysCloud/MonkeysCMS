{{-- Tags Component --}}
{{-- Usage: @include('components.tags', ['terms' => $terms]) --}}
@if(!empty($terms))
<div style="display:flex; flex-wrap:wrap; gap:0.5rem;">
  @foreach($terms as $term)
  <a href="/tag/{{ $term->slug }}"
     style="display:inline-flex; align-items:center; gap:0.25rem; padding:0.2rem 0.6rem; background:var(--front-bg-alt, var(--cms-bg-card)); border:1px solid var(--front-border, var(--cms-border)); border-radius:9999px; font-size:0.8rem; color:var(--front-text-secondary, var(--cms-text-muted)); text-decoration:none; transition:all 150ms;">
    🏷️ {{ $term->name }}
  </a>
  @endforeach
</div>
@endif
