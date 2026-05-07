@extends('layouts.admin')

@section('title', 'Webhooks')

@section('breadcrumb')
<a href="/admin">Dashboard</a> › <span>Webhooks</span>
@endsection

@section('page_title', 'Webhooks')

@section('page_actions')
<a href="/admin/webhooks/create" class="btn btn--primary btn--sm">
  <i data-lucide="plus" class="w-4 h-4"></i> New Webhook
</a>
@endsection

@section('content')
<div class="admin-page">

  {{-- ── Stats Row ──────────────────────────────────────────────────────── --}}
  @php
    $totalActive = count(array_filter($webhooks, fn($w) => $w->is_active));
    $totalInactive = count($webhooks) - $totalActive;
  @endphp
  <div class="stat-row" style="display:grid;grid-template-columns:repeat(3,1fr);gap:1rem;margin-bottom:1.5rem">
    <div class="card card--stat">
      <div class="card__body" style="text-align:center;padding:1rem">
        <div style="font-size:2rem;font-weight:700;color:var(--text)">{{ count($webhooks) }}</div>
        <div class="text-xs text-muted">TOTAL WEBHOOKS</div>
      </div>
    </div>
    <div class="card card--stat">
      <div class="card__body" style="text-align:center;padding:1rem">
        <div style="font-size:2rem;font-weight:700;color:var(--success)">{{ $totalActive }}</div>
        <div class="text-xs text-muted">ACTIVE</div>
      </div>
    </div>
    <div class="card card--stat">
      <div class="card__body" style="text-align:center;padding:1rem">
        <div style="font-size:2rem;font-weight:700;color:var(--text-muted)">{{ $totalInactive }}</div>
        <div class="text-xs text-muted">INACTIVE</div>
      </div>
    </div>
  </div>

  {{-- ── Table ──────────────────────────────────────────────────────────── --}}
  <div class="card">
    <div class="card__body p-0">
      @if(empty($webhooks))
        <div style="padding:3rem;text-align:center;color:var(--text-muted)">
          <i data-lucide="webhook" style="width:48px;height:48px;margin:0 auto 1rem;opacity:.3;display:block"></i>
          <p>No webhooks configured yet.</p>
          <a href="/admin/webhooks/create" class="btn btn--primary btn--sm" style="margin-top:1rem">
            <i data-lucide="plus" class="w-4 h-4"></i> Create Your First Webhook
          </a>
        </div>
      @else
        <table class="admin-table">
          <thead>
            <tr>
              <th>Name</th>
              <th>URL</th>
              <th>Events</th>
              <th>Status</th>
              <th>Deliveries</th>
              <th>Last Fired</th>
              <th style="width:120px">Actions</th>
            </tr>
          </thead>
          <tbody>
            @foreach($webhooks as $wh)
            <tr id="wh-row-{{ $wh->id }}">
              <td>
                <a href="/admin/webhooks/{{ $wh->id }}" class="text-link" style="font-weight:600">
                  {{ $wh->name }}
                </a>
              </td>
              <td>
                <code class="text-xs" style="word-break:break-all">{{ $wh->url }}</code>
              </td>
              <td>
                @foreach($wh->events as $ev)
                  <span class="badge badge--sm badge--outline" style="margin:.1rem">{{ $ev }}</span>
                @endforeach
              </td>
              <td>
                @if($wh->is_active)
                  <span class="badge badge--success">Active</span>
                @elseif($wh->isAutoDisabled)
                  <span class="badge badge--danger" title="Auto-disabled after {{ $wh->failure_count }} failures">
                    Disabled ({{ $wh->failure_count }} failures)
                  </span>
                @else
                  <span class="badge badge--muted">Inactive</span>
                @endif
              </td>
              <td>
                <span class="text-xs">
                  {{ $wh->_logCount }} total
                  @if($wh->_failedCount > 0)
                    · <span style="color:var(--danger)">{{ $wh->_failedCount }} failed</span>
                  @endif
                </span>
              </td>
              <td>
                <span class="text-xs text-muted">
                  {{ $wh->last_triggered_at ? $wh->last_triggered_at->format('M j, H:i') : '—' }}
                </span>
              </td>
              <td>
                <div style="display:flex;gap:.25rem">
                  <button class="btn btn--ghost btn--xs" onclick="toggleWebhook({{ $wh->id }})"
                          title="{{ $wh->is_active ? 'Disable' : 'Enable' }}">
                    <i data-lucide="{{ $wh->is_active ? 'pause' : 'play' }}" class="w-3.5 h-3.5"></i>
                  </button>
                  <button class="btn btn--ghost btn--xs" onclick="testWebhook({{ $wh->id }})" title="Send test">
                    <i data-lucide="send" class="w-3.5 h-3.5"></i>
                  </button>
                  <a href="/admin/webhooks/{{ $wh->id }}" class="btn btn--ghost btn--xs" title="Edit">
                    <i data-lucide="pencil" class="w-3.5 h-3.5"></i>
                  </a>
                  <button class="btn btn--ghost btn--xs text-danger" onclick="deleteWebhook({{ $wh->id }}, '{{ addslashes($wh->name) }}')" title="Delete">
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

async function toggleWebhook(id) {
  try {
    const resp = await CMS.fetch(`/admin/webhooks/${id}/toggle`, { method: 'POST', body: '{}' });
    const data = await resp.json();
    if (data.success) {
      window.location.reload();
    } else {
      alert(data.error || 'Failed to toggle webhook');
    }
  } catch (e) {
    alert('Error: ' + e.message);
  }
}

async function testWebhook(id) {
  const btn = event.currentTarget;
  const origHtml = btn.innerHTML;
  btn.disabled = true;
  btn.innerHTML = '<i data-lucide="loader" class="w-3.5 h-3.5 spin"></i>';
  if (window.lucide) lucide.createIcons({ nodes: [btn] });

  try {
    const resp = await CMS.fetch(`/admin/webhooks/${id}/test`, { method: 'POST', body: '{}' });
    const data = await resp.json();
    if (data.success) {
      alert(`✅ Test delivered!\nHTTP ${data.response_code} in ${data.duration_ms}ms (${data.attempts} attempt${data.attempts > 1 ? 's' : ''})`);
    } else {
      alert(`❌ Test failed.\nHTTP ${data.response_code || 'N/A'} after ${data.attempts} attempt(s)\nStatus: ${data.status}`);
    }
  } catch (e) {
    alert('Error: ' + e.message);
  } finally {
    btn.disabled = false;
    btn.innerHTML = origHtml;
    if (window.lucide) lucide.createIcons({ nodes: [btn] });
  }
}

async function deleteWebhook(id, name) {
  if (!confirm(`Delete webhook "${name}"? This cannot be undone.`)) return;

  try {
    const resp = await CMS.fetch(`/admin/webhooks/${id}`, { method: 'DELETE' });
    const data = await resp.json();
    if (data.success) {
      document.getElementById('wh-row-' + id)?.remove();
    } else {
      alert(data.error || 'Delete failed');
    }
  } catch (e) {
    alert('Error: ' + e.message);
  }
}

window.toggleWebhook = toggleWebhook;
window.testWebhook = testWebhook;
window.deleteWebhook = deleteWebhook;
</script>
@endpush

