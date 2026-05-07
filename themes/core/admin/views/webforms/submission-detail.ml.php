@extends('layouts.admin')

@section('title', $title ?? 'Submission Detail')

@section('content')
<div class="admin-page">

  <div class="admin-page__header" style="display:flex;align-items:center;justify-content:space-between">
    <div>
      <h1 class="admin-page__title">
        <i data-lucide="inbox" class="w-6 h-6"></i> Submission #{{ $submission['id'] }}
      </h1>
      <p class="admin-page__desc">{{ $webform->label }}</p>
    </div>
    <a href="/admin/webforms/{{ $webform->id }}/submissions" class="btn btn--ghost btn--sm">
      <i data-lucide="arrow-left" class="w-4 h-4"></i> Back to Submissions
    </a>
  </div>

  <div style="display:grid;grid-template-columns:1fr 300px;gap:1.5rem;align-items:start">

    {{-- Submission Data --}}
    <div class="card">
      <div class="card__header"><h3 class="card__title">Response Data</h3></div>
      <div class="card__body p-0">
        <table class="table">
          <tbody>
            @foreach($webform->fields as $field)
            @php
              $name = $field['name'] ?? '';
              $label = $field['label'] ?? $name;
              $val = $submission['data'][$name] ?? '';
              if (is_array($val)) $val = implode(', ', $val);
            @endphp
            <tr>
              <td style="width:200px;font-weight:600;color:var(--text-muted)">{{ $label }}</td>
              <td>{{ $val ?: '—' }}</td>
            </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    </div>

    {{-- Metadata --}}
    <div class="card">
      <div class="card__header"><h3 class="card__title">Metadata</h3></div>
      <div class="card__body">
        <div style="display:flex;flex-direction:column;gap:.75rem;font-size:.875rem">
          <div>
            <span class="text-muted">Submitted:</span><br>
            {{ isset($submission['created_at']) ? (new DateTimeImmutable($submission['created_at']))->format('M j, Y g:i A') : '—' }}
          </div>
          <div>
            <span class="text-muted">IP Address:</span><br>
            {{ $submission['ip_address'] ?? '—' }}
          </div>
          <div>
            <span class="text-muted">User Agent:</span><br>
            <span class="text-xs">{{ mb_substr($submission['user_agent'] ?? '—', 0, 80) }}</span>
          </div>
          @if(!empty($submission['files']))
          <div>
            <span class="text-muted">Files:</span><br>
            @foreach($submission['files'] as $fname => $fpath)
              <span class="text-xs">{{ $fname }}: {{ $fpath }}</span><br>
            @endforeach
          </div>
          @endif
        </div>
      </div>
    </div>

  </div>

</div>
@endsection
