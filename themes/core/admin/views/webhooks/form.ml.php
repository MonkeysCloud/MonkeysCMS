@extends('layouts.admin')

@section('title', $title ?? 'Webhook')

@section('content')
<div class="admin-page">

  {{-- ── Header ───────────────────────────────────────────────────────── --}}
  <div class="admin-page__header" style="display:flex;align-items:center;justify-content:space-between">
    <div>
      <h1 class="admin-page__title">
        <i data-lucide="webhook" class="w-6 h-6"></i>
        {{ $isNew ? 'Create Webhook' : 'Edit: ' . $webhook->name }}
      </h1>
    </div>
    <div style="display:flex;gap:.5rem">
      <a href="/admin/webhooks" class="btn btn--ghost btn--sm">
        <i data-lucide="arrow-left" class="w-4 h-4"></i> Back
      </a>
      @if(!$isNew)
        <button class="btn btn--outline btn--sm" id="test-btn" onclick="testWebhook()">
          <i data-lucide="send" class="w-4 h-4"></i> Test
        </button>
      @endif
      <button class="btn btn--primary btn--sm" id="save-btn" onclick="saveWebhook()">
        <i data-lucide="save" class="w-4 h-4"></i> Save
      </button>
    </div>
  </div>

  {{-- ── Layout: 2 columns ─────────────────────────────────────────────── --}}
  <div style="display:grid;grid-template-columns:1fr 340px;gap:1.5rem;align-items:start">

    {{-- Left: Config --}}
    <div style="display:flex;flex-direction:column;gap:1.5rem">

      {{-- Basic Info --}}
      <div class="card">
        <div class="card__header"><h3 class="card__title"><i data-lucide="info" class="w-4 h-4"></i> Endpoint</h3></div>
        <div class="card__body">
          <div class="form-group">
            <label class="form-label">Name *</label>
            <input type="text" class="form-input" id="wh-name"
                   value="{{ $webhook->name }}" placeholder="My Integration">
          </div>
          <div class="form-group">
            <label class="form-label">Payload URL *</label>
            <input type="url" class="form-input" id="wh-url"
                   value="{{ $webhook->url }}" placeholder="https://example.com/webhook">
            <span class="form-hint">POST requests will be sent to this URL</span>
          </div>
          <div class="form-group">
            <label class="form-label">Secret</label>
            <div style="display:flex;gap:.5rem">
              <input type="text" class="form-input" id="wh-secret"
                     value="{{ $webhook->secret }}" placeholder="Auto-generated if empty"
                     style="font-family:monospace;font-size:.8125rem">
              <button class="btn btn--ghost btn--sm" onclick="generateSecret()" title="Generate">
                <i data-lucide="refresh-cw" class="w-4 h-4"></i>
              </button>
            </div>
            <span class="form-hint">Used to sign payloads via HMAC-SHA256 (X-Webhook-Signature header)</span>
          </div>
        </div>
      </div>

      {{-- Events --}}
      <div class="card">
        <div class="card__header">
          <h3 class="card__title"><i data-lucide="radio" class="w-4 h-4"></i> Events</h3>
        </div>
        <div class="card__body">
          <p class="text-xs text-muted" style="margin-bottom:.75rem">Select which events should trigger this webhook:</p>
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:.5rem">
            @foreach($events as $eventKey => $eventLabel)
              @php
                $checked = in_array($eventKey, $webhook->events ?? []);
              @endphp
              <label class="form-toggle" style="padding:.375rem .5rem;border:1px solid var(--border);border-radius:var(--radius-md,6px)">
                <input type="checkbox" class="wh-event-cb" value="{{ $eventKey }}" {{ $checked ? 'checked' : '' }}>
                <span class="form-toggle__label" style="font-size:.8125rem">
                  <span style="font-weight:500">{{ $eventLabel }}</span>
                  <span class="text-xs text-muted" style="display:block">{{ $eventKey }}</span>
                </span>
              </label>
            @endforeach
          </div>
          <div style="margin-top:.75rem;display:flex;gap:.5rem">
            <button class="btn btn--ghost btn--xs" onclick="selectAllEvents()">Select All</button>
            <button class="btn btn--ghost btn--xs" onclick="deselectAllEvents()">None</button>
          </div>
        </div>
      </div>

      {{-- Delivery Log (edit only) --}}
      @if(!$isNew && isset($logs))
      <div class="card">
        <div class="card__header" style="display:flex;align-items:center;justify-content:space-between">
          <h3 class="card__title"><i data-lucide="scroll-text" class="w-4 h-4"></i> Delivery Log</h3>
          @if(isset($stats))
            <span class="text-xs text-muted">
              {{ $stats['delivered'] }}/{{ $stats['total'] }} delivered · avg {{ $stats['avg_duration_ms'] }}ms
            </span>
          @endif
        </div>
        <div class="card__body p-0">
          @if(empty($logs->items))
            <div style="padding:2rem;text-align:center;color:var(--text-muted)">
              <p>No deliveries yet.</p>
            </div>
          @else
            <table class="admin-table admin-table--compact">
              <thead>
                <tr>
                  <th>Time</th>
                  <th>Event</th>
                  <th>Status</th>
                  <th>HTTP</th>
                  <th>Duration</th>
                  <th>Attempts</th>
                </tr>
              </thead>
              <tbody>
                @foreach($logs->items as $log)
                <tr>
                  <td class="text-xs text-muted">{{ date('M j H:i:s', strtotime($log['created_at'])) }}</td>
                  <td><span class="badge badge--sm badge--outline">{{ $log['event'] }}</span></td>
                  <td>
                    @if($log['status'] === 'delivered')
                      <span class="badge badge--sm badge--success">OK</span>
                    @elseif($log['status'] === 'failed')
                      <span class="badge badge--sm badge--danger">Failed</span>
                    @else
                      <span class="badge badge--sm badge--warning">{{ $log['status'] }}</span>
                    @endif
                  </td>
                  <td class="text-xs">{{ $log['response_code'] ?? '—' }}</td>
                  <td class="text-xs">{{ $log['duration_ms'] ?? 0 }}ms</td>
                  <td class="text-xs">{{ $log['attempts'] }}</td>
                </tr>
                @endforeach
              </tbody>
            </table>

            {{-- Pagination --}}
            @if($logs->pages > 1)
            <div style="padding:.75rem 1rem;display:flex;justify-content:center;gap:.5rem;border-top:1px solid var(--border)">
              @for($p = 1; $p <= $logs->pages; $p++)
                <a href="?page={{ $p }}" class="btn btn--ghost btn--xs {{ $p === $logs->page ? 'btn--active' : '' }}">{{ $p }}</a>
              @endfor
            </div>
            @endif
          @endif
        </div>
      </div>
      @endif

    </div>

    {{-- Right: Status Panel --}}
    <div style="display:flex;flex-direction:column;gap:1.5rem">

      {{-- Status --}}
      <div class="card">
        <div class="card__header"><h3 class="card__title"><i data-lucide="toggle-right" class="w-4 h-4"></i> Status</h3></div>
        <div class="card__body">
          <label class="form-toggle">
            <input type="checkbox" id="wh-active" {{ $webhook->is_active ? 'checked' : '' }}>
            <span class="form-toggle__label">Active</span>
          </label>
          @if($webhook->isAutoDisabled ?? false)
            <div class="alert alert--danger" style="margin-top:.75rem;font-size:.8125rem">
              <i data-lucide="alert-triangle" class="w-4 h-4"></i>
              Auto-disabled after {{ $webhook->failure_count }} consecutive failures.
              Re-enable to reset failure count.
            </div>
          @endif
        </div>
      </div>

      {{-- Info (edit only) --}}
      @if(!$isNew)
      <div class="card">
        <div class="card__header"><h3 class="card__title"><i data-lucide="bar-chart-3" class="w-4 h-4"></i> Info</h3></div>
        <div class="card__body" style="font-size:.8125rem">
          <div style="display:flex;justify-content:space-between;margin-bottom:.5rem">
            <span class="text-muted">Failures</span>
            <span style="font-weight:600;color:{{ $webhook->failure_count > 0 ? 'var(--danger)' : 'var(--text)' }}">{{ $webhook->failure_count }}</span>
          </div>
          <div style="display:flex;justify-content:space-between;margin-bottom:.5rem">
            <span class="text-muted">Last Fired</span>
            <span>{{ $webhook->last_triggered_at ? $webhook->last_triggered_at->format('M j, H:i') : '—' }}</span>
          </div>
          <div style="display:flex;justify-content:space-between;margin-bottom:.5rem">
            <span class="text-muted">Created</span>
            <span>{{ $webhook->created_at?->format('M j, Y') ?? '—' }}</span>
          </div>
          @if(isset($stats))
          <hr style="border-color:var(--border);margin:.75rem 0">
          <div style="display:flex;justify-content:space-between;margin-bottom:.5rem">
            <span class="text-muted">Total Deliveries</span>
            <span style="font-weight:600">{{ $stats['total'] }}</span>
          </div>
          <div style="display:flex;justify-content:space-between;margin-bottom:.5rem">
            <span class="text-muted">Success Rate</span>
            <span style="font-weight:600;color:var(--success)">
              {{ $stats['total'] > 0 ? round($stats['delivered'] / $stats['total'] * 100) : 0 }}%
            </span>
          </div>
          @endif
        </div>
      </div>
      @endif

      {{-- Danger Zone --}}
      @if(!$isNew)
      <div class="card" style="border-color:var(--danger)">
        <div class="card__header"><h3 class="card__title text-danger"><i data-lucide="alert-triangle" class="w-4 h-4"></i> Danger Zone</h3></div>
        <div class="card__body">
          <button class="btn btn--danger btn--sm" style="width:100%" onclick="deleteWebhook()">
            <i data-lucide="trash-2" class="w-4 h-4"></i> Delete Webhook
          </button>
        </div>
      </div>
      @endif

    </div>
  </div>

</div>
@endsection

@push('scripts')
<script type="module">
import MonkeysJS from 'monkeysjs';

const WH_ID = {{ $webhook->id ?? 'null' }};
const IS_NEW = {{ $isNew ? 'true' : 'false' }};

// ── Save ────────────────────────────────────────────────────────────────
async function saveWebhook() {
  const btn = document.getElementById('save-btn');
  btn.disabled = true;
  btn.innerHTML = '<i data-lucide="loader" class="w-4 h-4 spin"></i> Saving…';

  const events = [...document.querySelectorAll('.wh-event-cb:checked')].map(cb => cb.value);

  const name = document.getElementById('wh-name').value.trim();
  const url_val = document.getElementById('wh-url').value.trim();
  const secret = document.getElementById('wh-secret').value.trim();
  const is_active = document.getElementById('wh-active').checked;

  if (!name) { alert('Webhook name is required.'); resetBtn(btn, 'save'); return; }
  if (!url_val)  { alert('Payload URL is required.');  resetBtn(btn, 'save'); return; }
  if (!events.length){ alert('Select at least one event.');resetBtn(btn, 'save'); return; }

  const payload = {
    name: name,
    url: url_val,
    events: events,
    secret: secret,
    is_active: is_active
  };

  try {
    const url = IS_NEW ? '/admin/webhooks' : `/admin/webhooks/${WH_ID}`;
    const method = IS_NEW ? 'POST' : 'PUT';
    const resp = await CMS.fetch(url, { method, body: JSON.stringify(payload) });
    const data = await resp.json();

    if (data.success) {
      CMS.toast?.('Webhook saved', 'success');
      if (IS_NEW && data.id) {
        window.location.href = `/admin/webhooks/${data.id}`;
      }
    } else {
      alert(data.error || 'Save failed');
    }
  } catch (e) {
    alert('Error: ' + e.message);
  } finally {
    resetBtn(btn, 'save');
  }
}

// ── Test ────────────────────────────────────────────────────────────────
async function testWebhook() {
  const btn = document.getElementById('test-btn');
  btn.disabled = true;
  btn.innerHTML = '<i data-lucide="loader" class="w-4 h-4 spin"></i> Sending…';
  if (window.lucide) lucide.createIcons({ nodes: [btn] });

  try {
    const resp = await CMS.fetch(`/admin/webhooks/${WH_ID}/test`, { method: 'POST', body: '{}' });
    const data = await resp.json();
    if (data.success) {
      alert(`✅ Test delivered!\nHTTP ${data.response_code} in ${data.duration_ms}ms`);
      window.location.reload();
    } else {
      alert(`❌ Test failed.\nHTTP ${data.response_code || 'N/A'}\nStatus: ${data.status}`);
    }
  } catch (e) {
    alert('Error: ' + e.message);
  } finally {
    btn.disabled = false;
    btn.innerHTML = '<i data-lucide="send" class="w-4 h-4"></i> Test';
    if (window.lucide) lucide.createIcons({ nodes: [btn] });
  }
}

// ── Delete ──────────────────────────────────────────────────────────────
async function deleteWebhook() {
  if (!confirm('Delete this webhook? This cannot be undone.')) return;

  try {
    const resp = await CMS.fetch(`/admin/webhooks/${WH_ID}`, { method: 'DELETE' });
    const data = await resp.json();
    if (data.success) {
      window.location.href = '/admin/webhooks';
    } else {
      alert(data.error || 'Delete failed');
    }
  } catch (e) {
    alert('Error: ' + e.message);
  }
}

// ── Helpers ─────────────────────────────────────────────────────────────
function generateSecret() {
  const arr = new Uint8Array(32);
  crypto.getRandomValues(arr);
  const hex = Array.from(arr, b => b.toString(16).padStart(2, '0')).join('');
  document.getElementById('wh-secret').value = hex;
}

function selectAllEvents() {
  document.querySelectorAll('.wh-event-cb').forEach(cb => cb.checked = true);
}

function deselectAllEvents() {
  document.querySelectorAll('.wh-event-cb').forEach(cb => cb.checked = false);
}

function resetBtn(btn, type) {
  btn.disabled = false;
  if (type === 'save') {
    btn.innerHTML = '<i data-lucide="save" class="w-4 h-4"></i> Save';
  }
  if (window.lucide) lucide.createIcons({ nodes: [btn] });
}

window.saveWebhook = saveWebhook;
window.testWebhook = testWebhook;
window.deleteWebhook = deleteWebhook;
window.generateSecret = generateSecret;
window.selectAllEvents = selectAllEvents;
window.deselectAllEvents = deselectAllEvents;
</script>
@endpush

