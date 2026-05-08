{{-- Single comment item (recursive for threaded replies) --}}
{{-- Required vars: $comment, $depth, $comments_threaded --}}
<div class="comment-item" id="comment-{{ $comment->id }}">
  <img src="{{ $comment->gravatar }}" alt="" class="comment-item__avatar" width="40" height="40" loading="lazy">
  <div class="comment-item__content">
    <div class="comment-item__header">
      <span class="comment-item__name">{{ $comment->author_name }}</span>
      <span class="comment-item__time" title="{{ $comment->created_at?->format('Y-m-d H:i:s') }}">{{ $comment->timeAgo }}</span>
    </div>
    <div class="comment-item__body">
      {!! $comment->formattedBody !!}
    </div>
    @if($comments_threaded ?? false)
    <button class="comment-item__reply-btn" onclick="replyTo({{ $comment->id }}, '{{ addslashes($comment->author_name) }}')">
      <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 17 4 12 9 7"/><path d="M20 18v-2a4 4 0 0 0-4-4H4"/></svg>
      Reply
    </button>
    @endif

    {{-- Recursive children --}}
    @if(!empty($comment->children) && ($comments_threaded ?? false))
    <div class="comment-children">
      @foreach($comment->children as $child)
        @include('partials.comment-item', ['comment' => $child, 'depth' => ($depth ?? 0) + 1])
      @endforeach
    </div>
    @endif
  </div>
</div>
