@extends('layouts.admin')

@section('title', $title ?? 'Webforms')

@section('content')
<div class="admin-page">

  {{-- ── Page Header ──────────────────────────────────────────────────── --}}
  <div class="admin-page__header" style="display:flex;align-items:center;justify-content:space-between">
    <div>
      <h1 class="admin-page__title">
        <i data-lucide="file-input" class="w-6 h-6"></i> Webforms
      </h1>
      <p class="admin-page__desc">Create and manage public forms — contact, surveys, applications, and more.</p>
    </div>
    <a href="/admin/webforms/create" class="btn btn--primary">
      <i data-lucide="plus" class="w-4 h-4"></i> New Form
    </a>
  </div>

  {{-- ── Forms Table ──────────────────────────────────────────────────── --}}
  <div class="card">
    <div class="card__body p-0">
      @if(empty($forms))
        <div style="padding:3rem;text-align:center;color:var(--text-muted)">
          <i data-lucide="inbox" style="width:48px;height:48px;margin-bottom:1rem;opacity:.4"></i>
          <p style="font-size:1.1rem;font-weight:500">No webforms yet</p>
          <p style="font-size:.875rem;margin-top:.25rem">Create your first form to start collecting responses.</p>
          <a href="/admin/webforms/create" class="btn btn--primary btn--sm" style="margin-top:1rem">
            <i data-lucide="plus" class="w-4 h-4"></i> Create Form
          </a>
        </div>
      @else
        <table class="table">
          <thead>
            <tr>
              <th>Form</th>
              <th style="width:100px;text-align:center">Status</th>
              <th style="width:130px;text-align:center">Submissions</th>
              <th style="width:100px;text-align:center">Fields</th>
              <th style="width:140px">Created</th>
              <th style="width:160px;text-align:right">Actions</th>
            </tr>
          </thead>
          <tbody>
            @foreach($forms as $form)
            <tr id="wf-row-{{ $form->id }}">
              <td>
                <div>
                  <a href="/admin/webforms/{{ $form->id }}" class="text-link" style="font-weight:600">
                    {{ $form->label }}
                  </a>
                  <div class="text-xs text-muted" style="margin-top:2px">
                    /form/{{ $form->machine_name }}
                    @if($form->isMultiPage)
                      · {{ $form->pageCount }} pages
                    @endif
                  </div>
                </div>
              </td>
              <td style="text-align:center">
                @php
                  $statusClass = match($form->status) {
                    'open' => 'badge--success',
                    'closed' => 'badge--danger',
                    'scheduled' => 'badge--warning',
                    default => 'badge--muted',
                  };
                @endphp
                <span class="badge {{ $statusClass }}">{{ ucfirst($form->status) }}</span>
              </td>
              <td style="text-align:center">
                <a href="/admin/webforms/{{ $form->id }}/submissions" class="text-link">
                  {{ $form->_submissionCount ?? 0 }}
                  @if(($form->_unreadCount ?? 0) > 0)
                    <span class="badge badge--primary badge--xs">{{ $form->_unreadCount }} new</span>
                  @endif
                </a>
              </td>
              <td style="text-align:center">
                <span class="text-muted">{{ $form->fieldCount }}</span>
              </td>
              <td>
                <span class="text-xs text-muted">{{ $form->created_at?->format('M j, Y') }}</span>
              </td>
              <td style="text-align:right">
                <div style="display:flex;gap:.25rem;justify-content:flex-end">
                  <a href="/admin/webforms/{{ $form->id }}" class="btn btn--ghost btn--xs" title="Edit">
                    <i data-lucide="pencil" class="w-3.5 h-3.5"></i>
                  </a>
                  <a href="/form/{{ $form->machine_name }}" target="_blank" class="btn btn--ghost btn--xs" title="View">
                    <i data-lucide="external-link" class="w-3.5 h-3.5"></i>
                  </a>
                  <a href="/admin/webforms/{{ $form->id }}/submissions" class="btn btn--ghost btn--xs" title="Submissions">
                    <i data-lucide="inbox" class="w-3.5 h-3.5"></i>
                  </a>
                  <button class="btn btn--ghost btn--xs" title="Duplicate"
                          onclick="duplicateForm({{ $form->id }})">
                    <i data-lucide="copy" class="w-3.5 h-3.5"></i>
                  </button>
                  <button class="btn btn--ghost btn--xs text-danger" title="Delete"
                          onclick="deleteForm({{ $form->id }}, '{{ addslashes($form->label) }}')">
                    <i data-lucide="trash-2" class="w-3.5 h-3.5"></i>
                  </button>
                </div>
              </td>
            </tr>
            @endforeach
          </tbody>
        </table>
      @endif
    </div>
  </div>

</div>
@endsection

@push('scripts')
<script>
async function duplicateForm(id) {
  if (!confirm('Duplicate this form?')) return;
  try {
    const resp = await CMS.fetch(`/admin/webforms/${id}/duplicate`, { method: 'POST' });
    const data = await resp.json();
    if (data.success) {
      location.reload();
    } else {
      alert(data.error || 'Duplication failed');
    }
  } catch (e) {
    alert('Error: ' + e.message);
  }
}

async function deleteForm(id, label) {
  if (!confirm(`Delete form "${label}" and all its submissions? This cannot be undone.`)) return;
  try {
    const resp = await CMS.fetch(`/admin/webforms/${id}`, { method: 'DELETE' });
    const data = await resp.json();
    if (data.success) {
      document.getElementById('wf-row-' + id)?.remove();
      CMS.toast?.('Form deleted', 'success');
    } else {
      alert(data.error || 'Delete failed');
    }
  } catch (e) {
    alert('Error: ' + e.message);
  }
}
</script>
@endpush
