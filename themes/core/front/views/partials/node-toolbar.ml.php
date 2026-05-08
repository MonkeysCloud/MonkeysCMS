{{-- Node Management Toolbar — shown on content detail pages for authenticated users --}}
@if(($cms['user']['authenticated'] ?? false) && isset($node))
<div class="cms-node-toolbar" id="cms-node-toolbar">
  <div class="cms-node-toolbar__inner">
    <div class="cms-node-toolbar__left">
      {{-- Status badge --}}
      @php $status = $node->status ?? 'draft'; @endphp
      <span class="cms-node-toolbar__status cms-node-toolbar__status--{{ $status }}">
        {{ ucfirst($status) }}
      </span>

      {{-- Node info --}}
      <span class="cms-node-toolbar__info">
        <strong>{{ $node->title ?? 'Untitled' }}</strong>
        <span class="cms-node-toolbar__type">{{ ucfirst($node->content_type ?? 'content') }}</span>
        @if($node->updated_at ?? false)
          · Updated {{ $node->updated_at->format('M j, Y g:ia') }}
        @endif
      </span>
    </div>

    <div class="cms-node-toolbar__right">
      {{-- Edit button --}}
      <a href="/admin/content/{{ $node->id }}/edit" class="cms-node-toolbar__btn cms-node-toolbar__btn--edit">
        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 3a2.85 2.85 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5Z"/></svg>
        Edit
      </a>

      {{-- Quick Publish/Unpublish --}}
      @if($status !== 'published')
      <form method="POST" action="/admin/content/{{ $node->id }}/quick-publish" style="display:inline;">
        <button type="submit" class="cms-node-toolbar__btn cms-node-toolbar__btn--publish">
          <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m5 12 5 5L20 7"/></svg>
          Publish
        </button>
      </form>
      @else
      <form method="POST" action="/admin/content/{{ $node->id }}/quick-unpublish" style="display:inline;">
        <button type="submit" class="cms-node-toolbar__btn cms-node-toolbar__btn--unpublish">
          <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>
          Unpublish
        </button>
      </form>
      @endif

      {{-- View in admin --}}
      <a href="/admin/content" class="cms-node-toolbar__btn">Content List</a>
    </div>
  </div>
</div>
@endif
