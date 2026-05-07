{{-- ═══ Comments Section ═══ --}}
{{-- Include this partial on any content template where comments are enabled --}}
{{-- Required vars: $comments_enabled, $comments, $comment_count, $comments_threaded, $node, $comment_form_html --}}

@if($comments_enabled ?? false)
<section class="comments-section" id="comments">
  <div class="container">
    <div class="comments-wrap">

      {{-- Header --}}
      <div class="comments-header">
        <h2 class="comments-header__title">
          <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M7.9 20A9 9 0 1 0 4 16.1L2 22Z"/></svg>
          @if($comment_count > 0)
            {{ $comment_count }} Comment{{ $comment_count !== 1 ? 's' : '' }}
          @else
            Leave a Comment
          @endif
        </h2>
      </div>

      {{-- Existing Comments --}}
      @if(!empty($comments))
      <div class="comments-list">
        @foreach($comments as $comment)
          @include('partials.comment-item', ['comment' => $comment, 'depth' => 0])
        @endforeach
      </div>
      @endif

      {{-- Comment Form --}}
      @if($comment_can_post ?? true)
      <div class="comment-form-wrap" id="comment-form">
        <h3 class="comment-form__title">Post a Comment</h3>
        <div class="comment-form__flash" id="comment-flash" style="display:none"></div>
        <div>
          {!! $comment_form_html !!}
        </div>
      </div>
      @else
      <div class="comment-login-prompt">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="18" height="11" x="3" y="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
        <div class="comment-login-prompt__text">
          <strong>Login required</strong>
          <span>You must be <a href="/admin/login">logged in</a> to post a comment.</span>
        </div>
      </div>
      @endif

    </div>
  </div>
</section>

@push('head')
<style>
/* ── Comments Section ────────────────────────────────────────────── */
.comments-section {
  margin-top: 3rem;
  padding: 3rem 0 4rem;
  border-top: 1px solid rgba(255,255,255,.06);
}

.comments-wrap {
  max-width: 720px;
  margin: 0 auto;
}

.comments-header {
  margin-bottom: 2rem;
}

.comments-header__title {
  display: flex;
  align-items: center;
  gap: .6rem;
  font-size: 1.3rem;
  font-weight: 700;
  color: #e2e8f0;
  margin: 0;
}

.comments-header__title svg {
  color: #818cf8;
  flex-shrink: 0;
}

/* ── Comment List ────────────────────────────────────────────────── */
.comments-list {
  margin-bottom: 2.5rem;
}

.comment-item {
  display: flex;
  gap: .85rem;
  padding: 1.25rem 0;
  border-bottom: 1px solid rgba(255,255,255,.04);
}

.comment-item:last-child {
  border-bottom: none;
}

.comment-item__avatar {
  width: 40px;
  height: 40px;
  border-radius: 10px;
  flex-shrink: 0;
  background: rgba(255,255,255,.05);
}

.comment-item__content {
  flex: 1;
  min-width: 0;
}

.comment-item__header {
  display: flex;
  align-items: center;
  gap: .5rem;
  margin-bottom: .35rem;
  flex-wrap: wrap;
}

.comment-item__name {
  font-weight: 600;
  font-size: .88rem;
  color: #e2e8f0;
}

.comment-item__time {
  font-size: .75rem;
  color: #64748b;
}

.comment-item__body {
  font-size: .9rem;
  color: #cbd5e1;
  line-height: 1.6;
}

.comment-item__body p {
  margin: 0 0 .5rem;
}

.comment-item__reply-btn {
  background: none;
  border: none;
  color: #818cf8;
  font-size: .75rem;
  font-weight: 600;
  cursor: pointer;
  display: inline-flex;
  align-items: center;
  gap: .3rem;
  margin-top: .4rem;
  padding: .2rem .4rem;
  border-radius: 4px;
  transition: background .15s;
}

.comment-item__reply-btn:hover {
  background: rgba(99,102,241,.08);
}

/* Threaded replies */
.comment-children {
  margin-left: 2.5rem;
  border-left: 2px solid rgba(99,102,241,.1);
  padding-left: 1rem;
}

/* ── Comment Form ────────────────────────────────────────────────── */
.comment-form-wrap {
  background: rgba(15,23,42,.5);
  border: 1px solid rgba(255,255,255,.06);
  border-radius: 16px;
  padding: 1.5rem 1.75rem;
}

.comment-form__title {
  font-size: 1.05rem;
  font-weight: 600;
  color: #e2e8f0;
  margin: 0 0 1.25rem;
}

.comment-form__flash {
  padding: .65rem 1rem;
  border-radius: 8px;
  font-size: .85rem;
  margin-bottom: 1rem;
}

.comment-form__flash--success {
  background: rgba(52,211,153,.1);
  color: #34d399;
  border: 1px solid rgba(52,211,153,.15);
}

.comment-form__flash--error {
  background: rgba(248,113,113,.1);
  color: #f87171;
  border: 1px solid rgba(248,113,113,.15);
}

/* ── FormRenderer overrides (scoped to comment form) ─────────────── */
.comment-form-wrap .form-group {
  margin-bottom: 1rem;
}

.comment-form-wrap .form-label {
  display: block;
  font-size: .8rem;
  font-weight: 600;
  color: #94a3b8;
  margin-bottom: .35rem;
  text-transform: uppercase;
  letter-spacing: .03em;
}

.comment-form-wrap .form-required {
  color: #f87171;
}

.comment-form-wrap .form-input,
.comment-form-wrap textarea {
  width: 100%;
  padding: .6rem .85rem;
  background: rgba(255,255,255,.04);
  border: 1px solid rgba(255,255,255,.08);
  border-radius: 10px;
  color: #e2e8f0;
  font-size: .88rem;
  font-family: inherit;
  transition: border-color .2s, box-shadow .2s;
  box-sizing: border-box;
}

.comment-form-wrap .form-input:focus,
.comment-form-wrap textarea:focus {
  outline: none;
  border-color: rgba(99,102,241,.5);
  box-shadow: 0 0 0 3px rgba(99,102,241,.1);
}

.comment-form-wrap .form-input::placeholder,
.comment-form-wrap textarea::placeholder {
  color: #475569;
}

.comment-form-wrap textarea {
  resize: vertical;
  min-height: 100px;
}

.comment-form-wrap .form-hint {
  display: block;
  font-size: .72rem;
  color: #64748b;
  margin-top: .25rem;
}

.comment-form-wrap .admin-form-actions {
  display: flex;
  justify-content: flex-end;
  margin-top: .5rem;
  padding: 0;
  border: none;
  background: none;
}

.comment-form-wrap .btn--primary {
  display: inline-flex;
  align-items: center;
  gap: .5rem;
  padding: .6rem 1.4rem;
  background: linear-gradient(135deg, #6366f1, #8b5cf6);
  color: #fff;
  border: none;
  border-radius: 10px;
  font-size: .88rem;
  font-weight: 600;
  cursor: pointer;
  transition: transform .15s, box-shadow .15s;
}

.comment-form-wrap .btn--primary:hover {
  transform: translateY(-1px);
  box-shadow: 0 4px 16px rgba(99,102,241,.3);
}

.comment-form-wrap .btn--primary:disabled {
  opacity: .6;
  cursor: not-allowed;
  transform: none;
}

.comment-form-wrap .btn--primary svg,
.comment-form-wrap .btn--primary i {
  width: 16px;
  height: 16px;
}

@media (max-width: 600px) {
  .comment-children { margin-left: 1rem; }
}

/* Reply indicator */
.comment-reply-to {
  display: flex;
  align-items: center;
  justify-content: space-between;
  background: rgba(99,102,241,.08);
  border: 1px solid rgba(99,102,241,.15);
  border-radius: 8px;
  padding: .5rem .85rem;
  margin-bottom: 1rem;
  font-size: .82rem;
  color: #a5b4fc;
}

.comment-reply-cancel {
  background: none;
  border: none;
  color: #94a3b8;
  font-size: .78rem;
  cursor: pointer;
  display: inline-flex;
  align-items: center;
  gap: .25rem;
}

.comment-reply-cancel:hover {
  color: #f87171;
}

/* ── Logged-in user badge ────────────────────────────────────────── */
.comment-user-badge {
  display: flex;
  align-items: center;
  gap: .75rem;
  padding: .65rem .85rem;
  background: rgba(99,102,241,.06);
  border: 1px solid rgba(99,102,241,.12);
  border-radius: 10px;
  margin-bottom: 1rem;
}

.comment-user-badge__avatar img {
  display: block;
}

.comment-user-badge__info {
  display: flex;
  flex-direction: column;
  gap: .1rem;
}

.comment-user-badge__name {
  font-size: .85rem;
  font-weight: 600;
  color: #e2e8f0;
}

.comment-user-badge__email {
  font-size: .72rem;
  color: #64748b;
}

/* ── Login prompt ────────────────────────────────────────────────── */
.comment-login-prompt {
  display: flex;
  align-items: center;
  gap: .85rem;
  padding: 1.25rem 1.5rem;
  background: rgba(15,23,42,.5);
  border: 1px solid rgba(255,255,255,.06);
  border-radius: 16px;
  color: #94a3b8;
}

.comment-login-prompt svg {
  color: #64748b;
  flex-shrink: 0;
}

.comment-login-prompt__text {
  display: flex;
  flex-direction: column;
  gap: .15rem;
}

.comment-login-prompt__text strong {
  font-size: .88rem;
  color: #e2e8f0;
}

.comment-login-prompt__text span {
  font-size: .8rem;
  color: #64748b;
}

.comment-login-prompt__text a {
  color: #818cf8;
  text-decoration: none;
  font-weight: 600;
}

.comment-login-prompt__text a:hover {
  text-decoration: underline;
}
</style>
@endpush

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
  const form = document.getElementById('comment-submit-form');
  if (!form) return;

  const flash = document.querySelector('.comment-form__flash');

  function showFlash(type, message) {
    if (!flash) return;
    flash.textContent = message;
    flash.className = 'comment-form__flash comment-form__flash--' + type;
    flash.style.display = 'block';
    setTimeout(() => { flash.style.display = 'none'; }, 6000);
  }

  // Form submission via MonkeysJS HTTP client
  form.addEventListener('submit', async (e) => {
    e.preventDefault();
    const btn = form.querySelector('button[type="submit"]');
    const originalLabel = btn.innerHTML;
    const data = new FormData(form);

    // Honeypot check
    if (data.get('website_url')) return;

    btn.disabled = true;
    btn.textContent = 'Posting…';

    try {
      const resp = await $m.http.post('/comments', data);
      const result = resp.data;

      if (result.success) {
        showFlash('success', result.message || 'Comment posted!');
        form.reset();
        cancelReply();
      } else {
        showFlash('error', result.error || 'Failed to post comment.');
      }
    } catch (err) {
      const msg = err?.response?.data?.error
                || err?.response?.data?.message
                || 'Server error. Please try again.';
      showFlash('error', msg);
    } finally {
      btn.disabled = false;
      btn.innerHTML = originalLabel;
    }
  });
});

// Reply functionality (global for onclick handlers)
function replyTo(commentId, authorName) {
  const form = document.getElementById('comment-submit-form');
  if (!form) return;
  form.querySelector('[name="parent_id"]').value = commentId;
  document.getElementById('reply-to-name').textContent = authorName;
  document.getElementById('comment-reply-to').style.display = 'flex';
  (form.querySelector('[name="body"]') || form.querySelector('textarea')).focus();
  document.getElementById('comment-form').scrollIntoView({ behavior: 'smooth', block: 'center' });
}

function cancelReply() {
  const form = document.getElementById('comment-submit-form');
  if (!form) return;
  form.querySelector('[name="parent_id"]').value = '';
  const el = document.getElementById('comment-reply-to');
  if (el) el.style.display = 'none';
}
</script>
@endpush
@endif
