@extends('layouts.admin')

@section('title', $title ?? 'Submissions')

@section('content')
<div class="admin-page">

  {{-- Header --}}
  <div class="admin-page__header" style="display:flex;align-items:center;justify-content:space-between">
    <div>
      <h1 class="admin-page__title">
        <i data-lucide="inbox" class="w-6 h-6"></i> {{ $webform->label }} — Submissions
      </h1>
      <div style="display:flex;gap:1rem;margin-top:.5rem">
        <span class="badge badge--primary">{{ $stats['total'] }} total</span>
        <span class="badge badge--warning">{{ $stats['unread'] }} unread</span>
        <span class="badge badge--success">{{ $stats['today'] }} today</span>
      </div>
    </div>
    <div style="display:flex;gap:.5rem">
      <a href="/admin/webforms/{{ $webform->id }}/export/csv" class="btn btn--ghost btn--sm">
        <i data-lucide="download" class="w-4 h-4"></i> CSV
      </a>
      <a href="/admin/webforms/{{ $webform->id }}/export/json" class="btn btn--ghost btn--sm">
        <i data-lucide="download" class="w-4 h-4"></i> JSON
      </a>
      <a href="/admin/webforms/{{ $webform->id }}" class="btn btn--ghost btn--sm">
        <i data-lucide="pencil" class="w-4 h-4"></i> Edit Form
      </a>
    </div>
  </div>

  {{-- Table --}}
  <div class="card">
    <div class="card__body p-0">
      @if(empty($submissions->items))
        <div style="padding:3rem;text-align:center;color:var(--text-muted)">
          <i data-lucide="inbox" style="width:48px;height:48px;opacity:.3"></i>
          <p style="margin-top:1rem">No submissions yet.</p>
        </div>
      @else
        <table class="table">
          <thead>
            <tr>
              <th style="width:40px"></th>
              <th style="width:60px">#</th>
              <th>Summary</th>
              <th style="width:120px">IP</th>
              <th style="width:150px">Date</th>
              <th style="width:120px;text-align:right">Actions</th>
            </tr>
          </thead>
          <tbody>
            @foreach($submissions->items as $sub)
            @php
              $isUnread = empty($sub['is_read']);
              $isStarred = !empty($sub['is_starred']);
              $summary = [];
              foreach(array_slice($webform->fields, 0, 3) as $f) {
                $val = $sub['data'][$f['name'] ?? ''] ?? '';
                if ($val) $summary[] = mb_substr((string)$val, 0, 40);
              }
            @endphp
            <tr id="sub-{{ $sub['id'] }}" style="{{ $isUnread ? 'font-weight:600' : '' }}">
              <td>
                <button class="btn btn--ghost btn--xs" onclick="toggleStar({{ $webform->id }}, {{ $sub['id'] }}, this)"
                        title="Star">
                  <i data-lucide="{{ $isStarred ? 'star' : 'star' }}" class="w-4 h-4"
                     style="color:{{ $isStarred ? '#f59e0b' : 'var(--text-muted)' }};{{ $isStarred ? 'fill:#f59e0b' : '' }}"></i>
                </button>
              </td>
              <td>{{ $sub['id'] }}</td>
              <td>
                <a href="/admin/webforms/{{ $webform->id }}/submissions/{{ $sub['id'] }}" class="text-link">
                  {{ implode(' · ', $summary) ?: '(empty)' }}
                </a>
              </td>
              <td class="text-xs text-muted">{{ $sub['ip_address'] ?? '' }}</td>
              <td class="text-xs text-muted">{{ isset($sub['created_at']) ? (new DateTimeImmutable($sub['created_at']))->format('M j, g:ia') : '' }}</td>
              <td style="text-align:right">
                <a href="/admin/webforms/{{ $webform->id }}/submissions/{{ $sub['id'] }}" class="btn btn--ghost btn--xs">
                  <i data-lucide="eye" class="w-3.5 h-3.5"></i>
                </a>
                <button class="btn btn--ghost btn--xs text-danger" onclick="deleteSub({{ $webform->id }}, {{ $sub['id'] }})">
                  <i data-lucide="trash-2" class="w-3.5 h-3.5"></i>
                </button>
              </td>
            </tr>
            @endforeach
          </tbody>
        </table>

        {{-- Pagination --}}
        @if($submissions->totalPages > 1)
        <div style="padding:.75rem 1rem;display:flex;justify-content:center;gap:.25rem">
          @for($p = 1; $p <= $submissions->totalPages; $p++)
          <a href="?page={{ $p }}"
             class="btn btn--ghost btn--xs {{ $p === $submissions->currentPage ? 'btn--active' : '' }}">{{ $p }}</a>
          @endfor
        </div>
        @endif
      @endif
    </div>
  </div>

</div>
@endsection

@push('scripts')
<script>
async function toggleStar(formId, subId, btn) {
  await CMS.fetch(`/admin/webforms/${formId}/submissions/${subId}/star`, { method: 'POST' });
  location.reload();
}

async function deleteSub(formId, subId) {
  if (!confirm('Delete this submission?')) return;
  const resp = await CMS.fetch(`/admin/webforms/${formId}/submissions/${subId}`, { method: 'DELETE' });
  const data = await resp.json();
  if (data.success) {
    document.getElementById('sub-' + subId)?.remove();
  }
}
</script>
@endpush
