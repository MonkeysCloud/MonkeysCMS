@extends('layouts.public')

@section('title', $title ?? 'Form')

@section('content')
<div class="webform-page" style="max-width:720px;margin:2rem auto;padding:0 1rem">

  <h1 style="margin-bottom:.5rem">{{ $webform->label }}</h1>
  @if($webform->description)
    <p style="color:var(--text-muted);margin-bottom:1.5rem">{{ $webform->description }}</p>
  @endif

  {{-- Multi-page progress --}}
  @if($webform->isMultiPage)
  <div style="display:flex;gap:.5rem;margin-bottom:1.5rem">
    @foreach($webform->pages as $i => $pg)
    <div style="flex:1;text-align:center;padding:.5rem;border-radius:var(--radius-md,6px);font-size:.8rem;
      {{ $i === $currentPage ? 'background:var(--primary);color:#fff;font-weight:600' : 'background:var(--surface-alt,#f1f5f9);color:var(--text-muted)' }}">
      {{ $pg['title'] ?? 'Page ' . ($i + 1) }}
    </div>
    @endforeach
  </div>
  @endif

  {{-- Form rendered by FormRenderer --}}
  <div id="webform-container">
    {!! $formHtml !!}
  </div>

  {{-- Success message (hidden by default) --}}
  <div id="webform-success" hidden style="padding:2rem;text-align:center;background:var(--surface-alt,#f0fdf4);border-radius:var(--radius-md,8px)">
    <p style="font-size:1.25rem;font-weight:600;color:var(--success,#22c55e)">✓ Submitted!</p>
    <p id="webform-success-msg" style="margin-top:.5rem;color:var(--text-muted)"></p>
  </div>

</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
  const form = document.querySelector('#webform-container form');
  if (!form) return;

  form.addEventListener('submit', async (e) => {
    e.preventDefault();

    const btn = form.querySelector('[type="submit"]');
    if (btn) { btn.disabled = true; btn.textContent = 'Submitting…'; }

    const formData = new FormData(form);
    const data = {};
    for (const [k, v] of formData.entries()) {
      if (data[k]) {
        if (!Array.isArray(data[k])) data[k] = [data[k]];
        data[k].push(v);
      } else {
        data[k] = v;
      }
    }

    try {
      const slug = '{{ $webform->machine_name }}';
      const resp = await fetch(`/form/${slug}/submit`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(data),
      });
      const result = await resp.json();

      if (result.success) {
        if (result.redirect) {
          window.location.href = result.redirect;
          return;
        }
        document.getElementById('webform-container').hidden = true;
        document.getElementById('webform-success').hidden = false;
        document.getElementById('webform-success-msg').textContent = result.message || 'Thank you!';
      } else if (result.errors) {
        // Show field errors
        Object.entries(result.errors).forEach(([field, msg]) => {
          const input = form.querySelector(`[name="${field}"]`);
          if (input) {
            input.style.borderColor = 'var(--danger,red)';
            let hint = input.parentElement?.querySelector('.form-error');
            if (!hint) {
              hint = document.createElement('span');
              hint.className = 'form-error';
              hint.style.cssText = 'color:var(--danger,red);font-size:.8rem;display:block;margin-top:.25rem';
              input.parentElement?.appendChild(hint);
            }
            hint.textContent = msg;
          }
        });
        if (btn) { btn.disabled = false; btn.textContent = '{{ $webform->submit_label }}'; }
      } else {
        alert(result.error || 'Submission failed');
        if (btn) { btn.disabled = false; btn.textContent = '{{ $webform->submit_label }}'; }
      }
    } catch (err) {
      alert('Network error: ' + err.message);
      if (btn) { btn.disabled = false; btn.textContent = '{{ $webform->submit_label }}'; }
    }
  });
});
</script>
@endpush
