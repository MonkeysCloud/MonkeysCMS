@extends('layouts.admin')

@section('title', 'Cron Dashboard')
@section('page_title', 'Cron Dashboard')

@section('breadcrumb')
<a href="/admin" class="breadcrumb__item">Dashboard</a>
<span class="breadcrumb__sep">›</span>
<span class="breadcrumb__item breadcrumb__item--active">Cron</span>
@endsection

@section('page_actions')
<button class="btn btn--ghost btn--sm" onclick="clearHistory()" title="Clear old log entries">
  <i data-lucide="trash-2" class="w-4 h-4"></i> Cleanup
</button>
@endsection

@section('content')
@php
  $statusColors = [
      'success' => ['bg' => 'rgba(52,211,153,.1)',  'color' => '#34d399', 'icon' => 'check-circle'],
      'failed'  => ['bg' => 'rgba(248,113,113,.1)', 'color' => '#f87171', 'icon' => 'x-circle'],
      'running' => ['bg' => 'rgba(96,165,250,.1)',  'color' => '#60a5fa', 'icon' => 'loader'],
  ];
@endphp

{{-- Stats Row --}}
<div class="cron-stats mb-4">
  <div class="cron-stat">
    <span class="cron-stat__value">{{ count($tasks) }}</span>
    <span class="cron-stat__label">Scheduled Tasks</span>
  </div>
  <div class="cron-stat">
    @php
      $successCount = array_sum(array_map(fn($t) => (int)($t['stats']['success_count'] ?? 0), $tasks));
    @endphp
    <span class="cron-stat__value" style="color:#34d399">{{ $successCount }}</span>
    <span class="cron-stat__label">Total Successes</span>
  </div>
  <div class="cron-stat">
    @php
      $failedCount = array_sum(array_map(fn($t) => (int)($t['stats']['failed_count'] ?? 0), $tasks));
    @endphp
    <span class="cron-stat__value" style="color:{{ $failedCount > 0 ? '#f87171' : '#94a3b8' }}">{{ $failedCount }}</span>
    <span class="cron-stat__label">Total Failures</span>
  </div>
  <div class="cron-stat">
    <span class="cron-stat__value">{{ $history['total'] ?? 0 }}</span>
    <span class="cron-stat__label">Log Entries</span>
  </div>
</div>

{{-- Registered Tasks --}}
<div class="card mb-4">
  <div class="card__header">
    <h3 class="card__title"><i data-lucide="clock" class="w-4 h-4"></i> Scheduled Tasks</h3>
  </div>
  <div class="card__body p-0">
    <table class="table table--hover">
      <thead>
        <tr>
          <th>Task</th>
          <th style="width:140px">Schedule</th>
          <th style="width:140px">Next Run</th>
          <th style="width:100px">Last Status</th>
          <th style="width:100px">Avg Time</th>
          <th style="width:90px">Runs</th>
          <th style="width:100px">Actions</th>
        </tr>
      </thead>
      <tbody>
        @foreach($tasks as $task)
        @php
          $lastStatus = $task['last_run']['status'] ?? null;
          $sc = $statusColors[$lastStatus] ?? ['bg' => 'rgba(148,163,184,.08)', 'color' => '#64748b', 'icon' => 'minus'];
          $avgMs = (int)($task['stats']['avg_duration'] ?? 0);
          $totalRuns = (int)($task['stats']['total_runs'] ?? 0);
        @endphp
        <tr>
          <td>
            <div class="cron-task-name">{{ $task['id'] }}</div>
            @if(!empty($task['tags']))
            <div class="cron-task-tags">
              @foreach($task['tags'] as $tag)
              <span class="cron-tag">{{ $tag }}</span>
              @endforeach
            </div>
            @endif
          </td>
          <td>
            <code class="cron-expression">{{ $task['expression'] }}</code>
          </td>
          <td>
            @if($task['next_run'])
            <span class="text-sm" style="color:#94a3b8">{{ $task['next_run'] }}</span>
            @else
            <span class="text-muted text-xs">—</span>
            @endif
          </td>
          <td>
            @if($lastStatus)
            <span class="cron-status-badge" style="background:{{ $sc['bg'] }};color:{{ $sc['color'] }}">
              <i data-lucide="{{ $sc['icon'] }}" class="w-3 h-3"></i>
              {{ ucfirst($lastStatus) }}
            </span>
            @else
            <span class="text-muted text-xs">Never</span>
            @endif
          </td>
          <td>
            @if($avgMs > 0)
            <span class="text-sm" style="color:#94a3b8">{{ $avgMs < 1000 ? $avgMs . 'ms' : round($avgMs/1000, 1) . 's' }}</span>
            @else
            <span class="text-muted text-xs">—</span>
            @endif
          </td>
          <td>
            <span class="text-sm" style="color:#94a3b8">{{ $totalRuns }}</span>
          </td>
          <td>
            <button class="btn btn--primary btn--xs cron-run-btn"
                    data-task-id="{{ $task['id'] }}"
                    onclick="runTask(this)"
                    title="Run now">
              <i data-lucide="play" class="w-3.5 h-3.5"></i> Run
            </button>
          </td>
        </tr>
        @endforeach

        @if(empty($tasks))
        <tr>
          <td colspan="7">
            <div class="empty-state">
              <div class="empty-state__icon"><i data-lucide="clock" class="w-12 h-12"></i></div>
              <div class="empty-state__title">No scheduled tasks registered</div>
              <p class="text-muted">Register tasks in your service provider using the Schedule class.</p>
            </div>
          </td>
        </tr>
        @endif
      </tbody>
    </table>
  </div>
</div>

{{-- Execution History --}}
<div class="card">
  <div class="card__header">
    <h3 class="card__title"><i data-lucide="scroll-text" class="w-4 h-4"></i> Execution History</h3>
    <div class="card__actions">
      <form method="GET" action="/admin/cron" class="cron-filters">
        <select name="task" class="form-select form-select--sm" onchange="this.form.submit()">
          <option value="">All Tasks</option>
          @foreach($tasks as $t)
          <option value="{{ $t['id'] }}" {{ ($filterTask ?? '') === $t['id'] ? 'selected' : '' }}>{{ $t['id'] }}</option>
          @endforeach
        </select>
        <select name="status" class="form-select form-select--sm" onchange="this.form.submit()">
          <option value="">All Status</option>
          <option value="success" {{ ($filterStatus ?? '') === 'success' ? 'selected' : '' }}>Success</option>
          <option value="failed" {{ ($filterStatus ?? '') === 'failed' ? 'selected' : '' }}>Failed</option>
          <option value="running" {{ ($filterStatus ?? '') === 'running' ? 'selected' : '' }}>Running</option>
        </select>
        @if($filterTask || $filterStatus)
        <a href="/admin/cron" class="btn btn--ghost btn--xs" title="Clear filters">
          <i data-lucide="x" class="w-3.5 h-3.5"></i>
        </a>
        @endif
      </form>
    </div>
  </div>
  <div class="card__body p-0">
    <table class="table table--hover">
      <thead>
        <tr>
          <th style="width:160px">When</th>
          <th>Task</th>
          <th style="width:100px">Status</th>
          <th style="width:100px">Duration</th>
          <th style="width:80px">Output</th>
        </tr>
      </thead>
      <tbody>
        @foreach($history['items'] ?? [] as $entry)
        @php
          $sc = $statusColors[$entry['status']] ?? ['bg' => 'rgba(148,163,184,.08)', 'color' => '#64748b', 'icon' => 'minus'];
          $ms = (int)($entry['duration_ms'] ?? 0);
        @endphp
        <tr>
          <td>
            <span class="text-sm" style="color:#94a3b8">{{ date('M j, H:i:s', strtotime($entry['started_at'])) }}</span>
          </td>
          <td>
            <span class="cron-task-name cron-task-name--sm">{{ $entry['task_name'] ?? $entry['task_id'] }}</span>
          </td>
          <td>
            <span class="cron-status-badge" style="background:{{ $sc['bg'] }};color:{{ $sc['color'] }}">
              <i data-lucide="{{ $sc['icon'] }}" class="w-3 h-3"></i>
              {{ ucfirst($entry['status']) }}
            </span>
          </td>
          <td>
            @if($ms > 0)
            <span class="text-sm" style="color:#94a3b8">{{ $ms < 1000 ? $ms . 'ms' : round($ms/1000, 2) . 's' }}</span>
            @else
            <span class="text-muted text-xs">—</span>
            @endif
          </td>
          <td>
            @if($entry['output'] || $entry['error'])
            <button class="btn btn--ghost btn--xs" onclick="this.closest('tr').nextElementSibling?.classList.toggle('hidden')">
              <i data-lucide="code" class="w-3.5 h-3.5"></i>
            </button>
            @else
            <span class="text-muted text-xs">—</span>
            @endif
          </td>
        </tr>
        @if($entry['output'] || $entry['error'])
        <tr class="hidden">
          <td colspan="5" style="padding:0 1rem .75rem">
            <div class="cron-output-panel">
              @if($entry['output'])
              <div class="cron-output-block">
                <div class="cron-output-block__label">Output</div>
                <pre><code>{{ $entry['output'] }}</code></pre>
              </div>
              @endif
              @if($entry['error'])
              <div class="cron-output-block cron-output-block--error">
                <div class="cron-output-block__label" style="color:#f87171">Error</div>
                <pre><code>{{ $entry['error'] }}</code></pre>
              </div>
              @endif
            </div>
          </td>
        </tr>
        @endif
        @endforeach

        @if(empty($history['items']))
        <tr>
          <td colspan="5">
            <div class="empty-state">
              <div class="empty-state__icon"><i data-lucide="inbox" class="w-10 h-10"></i></div>
              <div class="empty-state__title">No execution history</div>
              <p class="text-muted">Run a task to see results here.</p>
            </div>
          </td>
        </tr>
        @endif
      </tbody>
    </table>
  </div>
</div>

{{-- Pagination --}}
@if(($history['pages'] ?? 1) > 1)
<div class="flex-between mt-4">
  <span class="text-sm text-muted">Page {{ $history['page'] }} of {{ $history['pages'] }} ({{ $history['total'] }} entries)</span>
  <div class="pagination">
    @if($history['page'] > 1)
    <a href="/admin/cron?page={{ $history['page'] - 1 }}{{ $filterTask ? '&task=' . urlencode($filterTask) : '' }}{{ $filterStatus ? '&status=' . urlencode($filterStatus) : '' }}" class="pagination__item">&laquo;</a>
    @endif
    @for($i = max(1, $history['page'] - 3); $i <= min($history['pages'], $history['page'] + 3); $i++)
    <a href="/admin/cron?page={{ $i }}{{ $filterTask ? '&task=' . urlencode($filterTask) : '' }}{{ $filterStatus ? '&status=' . urlencode($filterStatus) : '' }}"
       class="pagination__item {{ $i === $history['page'] ? 'active' : '' }}">{{ $i }}</a>
    @endfor
    @if($history['page'] < $history['pages'])
    <a href="/admin/cron?page={{ $history['page'] + 1 }}{{ $filterTask ? '&task=' . urlencode($filterTask) : '' }}{{ $filterStatus ? '&status=' . urlencode($filterStatus) : '' }}" class="pagination__item">&raquo;</a>
    @endif
  </div>
</div>
@endif
@endsection

@push('scripts')
<script>
async function runTask(btn) {
  const taskId = btn.dataset.taskId;
  const originalHtml = btn.innerHTML;
  btn.disabled = true;
  btn.innerHTML = '<i data-lucide="loader" class="w-3.5 h-3.5 spin"></i> Running…';
  if (window.lucide) lucide.createIcons({ nodes: [btn] });

  try {
    const resp = await CMS.fetch('/admin/cron/' + encodeURIComponent(taskId) + '/run', {
      method: 'POST',
      body: JSON.stringify({}),
    });
    const data = await resp.json();

    if (data.success) {
      btn.innerHTML = '<i data-lucide="check" class="w-3.5 h-3.5"></i> Done';
      btn.classList.remove('btn--primary');
      btn.classList.add('btn--success');
      if (window.lucide) lucide.createIcons({ nodes: [btn] });
      setTimeout(() => location.reload(), 1200);
    } else {
      btn.innerHTML = '<i data-lucide="x" class="w-3.5 h-3.5"></i> Error';
      btn.classList.remove('btn--primary');
      btn.classList.add('btn--danger');
      if (window.lucide) lucide.createIcons({ nodes: [btn] });
      alert('Task failed: ' + (data.error || 'Unknown error'));
      setTimeout(() => {
        btn.innerHTML = originalHtml;
        btn.classList.remove('btn--danger');
        btn.classList.add('btn--primary');
        btn.disabled = false;
        if (window.lucide) lucide.createIcons({ nodes: [btn] });
      }, 2000);
    }
  } catch (err) {
    btn.innerHTML = originalHtml;
    btn.disabled = false;
    if (window.lucide) lucide.createIcons({ nodes: [btn] });
    alert('Failed to execute task: ' + (err.message || 'Network error'));
  }
}

async function clearHistory() {
  const days = prompt('Delete log entries older than how many days?', '30');
  if (!days) return;

  try {
    const resp = await CMS.fetch('/admin/cron/clear', {
      method: 'POST',
      body: JSON.stringify({ days: parseInt(days) }),
    });
    const data = await resp.json();
    alert(data.message || 'Done');
    location.reload();
  } catch (err) {
    alert('Failed: ' + (err.message || 'Network error'));
  }
}
</script>
@endpush

@push('head')
<style>
/* ── Cron Dashboard Styles ─────────────────────────────────────────── */
.cron-stats {
  display: flex;
  gap: .75rem;
}

.cron-stat {
  flex: 1;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: .25rem;
  padding: .75rem 1rem;
  background: rgba(20, 22, 38, .6);
  border: 1px solid rgba(255,255,255,.06);
  border-radius: 12px;
}

.cron-stat__value {
  font-size: 1.4rem;
  font-weight: 700;
  color: #e2e8f0;
}

.cron-stat__label {
  font-size: .72rem;
  color: #64748b;
  text-transform: uppercase;
  letter-spacing: .04em;
  font-weight: 600;
}

/* ── Task Name ─────────────────────────────────────────────────────── */
.cron-task-name {
  color: #e2e8f0;
  font-weight: 500;
  font-size: .85rem;
}

.cron-task-name--sm {
  font-size: .8rem;
  font-weight: 400;
}

.cron-task-tags {
  display: flex;
  gap: .3rem;
  margin-top: .2rem;
}

.cron-tag {
  font-size: .65rem;
  padding: .1rem .4rem;
  border-radius: 4px;
  background: rgba(129,140,248,.1);
  color: #818cf8;
  font-weight: 600;
  letter-spacing: .02em;
}

/* ── Expression ────────────────────────────────────────────────────── */
.cron-expression {
  font-size: .75rem;
  color: #a78bfa;
  background: rgba(167,139,250,.08);
  padding: .15rem .45rem;
  border-radius: 4px;
  font-family: 'JetBrains Mono', 'Fira Code', monospace;
}

/* ── Status Badge ──────────────────────────────────────────────────── */
.cron-status-badge {
  display: inline-flex;
  align-items: center;
  gap: .25rem;
  padding: .15rem .5rem;
  border-radius: 6px;
  font-size: .72rem;
  font-weight: 600;
  letter-spacing: .01em;
}

/* ── Filters ───────────────────────────────────────────────────────── */
.cron-filters {
  display: flex;
  align-items: center;
  gap: .5rem;
}

.cron-filters .form-select--sm {
  width: auto;
  min-width: 120px;
  max-width: 180px;
}

/* ── Output Panel ──────────────────────────────────────────────────── */
.cron-output-panel {
  display: flex;
  flex-direction: column;
  gap: .5rem;
}

.cron-output-block {
  background: rgba(15,23,42,.7);
  border: 1px solid rgba(255,255,255,.06);
  border-radius: 8px;
  padding: .6rem .8rem;
}

.cron-output-block--error {
  border-color: rgba(248,113,113,.15);
}

.cron-output-block__label {
  font-size: .68rem;
  font-weight: 700;
  color: #64748b;
  text-transform: uppercase;
  letter-spacing: .04em;
  margin-bottom: .3rem;
}

.cron-output-block pre {
  margin: 0;
  white-space: pre-wrap;
  word-break: break-word;
}

.cron-output-block code {
  font-size: .75rem;
  color: #94a3b8;
  font-family: 'JetBrains Mono', 'Fira Code', monospace;
}

/* ── Run button ────────────────────────────────────────────────────── */
.cron-run-btn {
  white-space: nowrap;
}

.btn--success {
  background: rgba(52,211,153,.15) !important;
  color: #34d399 !important;
  border-color: rgba(52,211,153,.3) !important;
}

.btn--danger {
  background: rgba(248,113,113,.15) !important;
  color: #f87171 !important;
  border-color: rgba(248,113,113,.3) !important;
}

/* ── Spin animation ────────────────────────────────────────────────── */
@keyframes spin { from { transform: rotate(0) } to { transform: rotate(360deg) } }
.spin { animation: spin .8s linear infinite; }

.hidden { display: none !important; }

.card__header {
  display: flex;
  align-items: center;
  justify-content: space-between;
}

.card__title {
  display: flex;
  align-items: center;
  gap: .4rem;
  font-size: .9rem;
  font-weight: 600;
  color: #e2e8f0;
}

.card__actions {
  display: flex;
  align-items: center;
  gap: .5rem;
}
</style>
@endpush
