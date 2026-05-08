@extends('layouts.admin')

@section('title', 'Extend')

@section('breadcrumb')
<a href="/admin">Dashboard</a> › <span>Extend</span>
@endsection

@section('page_title', 'Extend')

@section('page_actions')
<a href="/admin/plugins/upload" class="btn btn--primary btn--sm">
  <i data-lucide="upload" class="w-4 h-4"></i> Upload Plugin
</a>
@endsection

@section('content')
<div class="admin-page">

  {{-- ── Stats Row ──────────────────────────────────────────────────────── --}}
  <div class="stat-row" style="display:grid;grid-template-columns:repeat(3,1fr);gap:1rem;margin-bottom:1.5rem">
    <div class="card card--stat">
      <div class="card__body" style="text-align:center;padding:1rem">
        <div style="font-size:2rem;font-weight:700;color:var(--text)">{{ $total }}</div>
        <div class="text-xs text-muted">TOTAL PLUGINS</div>
      </div>
    </div>
    <div class="card card--stat">
      <div class="card__body" style="text-align:center;padding:1rem">
        <div style="font-size:2rem;font-weight:700;color:var(--success)">{{ $enabled }}</div>
        <div class="text-xs text-muted">ENABLED</div>
      </div>
    </div>
    <div class="card card--stat">
      <div class="card__body" style="text-align:center;padding:1rem">
        <div style="font-size:2rem;font-weight:700;color:var(--text-muted)">{{ $total - $enabled }}</div>
        <div class="text-xs text-muted">DISABLED</div>
      </div>
    </div>
  </div>

  {{-- ── Custom Plugins ────────────────────────────────────────────────── --}}
  @if(!empty($custom))
  <div class="card" style="margin-bottom:1.5rem">
    <div class="card__header">
      <h3 class="card__title"><i data-lucide="code-2" class="w-4 h-4"></i> Custom</h3>
    </div>
    <div class="card__body" style="padding:0">
      <table class="data-table">
        <thead>
          <tr>
            <th style="width:3rem"></th>
            <th>Name</th>
            <th>Description</th>
            <th>Version</th>
            <th>Author</th>
            <th style="width:10rem">Status</th>
            <th style="width:10rem">Actions</th>
          </tr>
        </thead>
        <tbody>
          @foreach($custom as $p)
          @php $meta = $p['metadata']; $isEnabled = $p['enabled']; @endphp
          <tr>
            <td style="text-align:center"><i data-lucide="puzzle" class="w-5 h-5" style="color:var(--accent)"></i></td>
            <td>
              <strong>{{ $meta->name }}</strong>
              <div class="text-xs text-muted">{{ $meta->machineName }}</div>
            </td>
            <td class="text-sm">{{ $meta->description }}</td>
            <td><span class="badge badge--neutral">{{ $meta->version }}</span></td>
            <td class="text-sm text-muted">{{ $meta->author }}</td>
            <td>
              @if($isEnabled)
                <span class="badge badge--success">Enabled</span>
              @else
                <span class="badge badge--neutral">Disabled</span>
              @endif
            </td>
            <td>
              <div style="display:flex;gap:.5rem;align-items:center">
                @if($isEnabled)
                  <form action="/admin/plugins/disable" method="POST" style="margin:0">
                    <input type="hidden" name="plugin" value="{{ $meta->machineName }}">
                    <button type="submit" class="btn btn--ghost btn--xs" title="Disable">
                      <i data-lucide="toggle-right" class="w-4 h-4"></i>
                    </button>
                  </form>
                  <a href="/admin/plugins/{{ $meta->vendor }}/{{ $meta->name }}/settings" class="btn btn--ghost btn--xs" title="Settings">
                    <i data-lucide="settings" class="w-4 h-4"></i>
                  </a>
                @else
                  <form action="/admin/plugins/enable" method="POST" style="margin:0">
                    <input type="hidden" name="plugin" value="{{ $meta->machineName }}">
                    <button type="submit" class="btn btn--primary btn--xs" title="Enable">
                      <i data-lucide="toggle-left" class="w-4 h-4"></i> Enable
                    </button>
                  </form>
                  <form action="/admin/plugins/uninstall" method="POST" style="margin:0" onsubmit="return confirm('Uninstall {{ $meta->name }}? This will remove all plugin data.')">
                    <input type="hidden" name="plugin" value="{{ $meta->machineName }}">
                    <button type="submit" class="btn btn--danger btn--xs" title="Uninstall">
                      <i data-lucide="trash-2" class="w-4 h-4"></i>
                    </button>
                  </form>
                @endif
              </div>
            </td>
          </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  </div>
  @endif

  {{-- ── Contrib Plugins ───────────────────────────────────────────────── --}}
  @if(!empty($contrib))
  <div class="card" style="margin-bottom:1.5rem">
    <div class="card__header">
      <h3 class="card__title"><i data-lucide="package" class="w-4 h-4"></i> Community</h3>
    </div>
    <div class="card__body" style="padding:0">
      <table class="data-table">
        <thead>
          <tr>
            <th style="width:3rem"></th>
            <th>Name</th>
            <th>Description</th>
            <th>Version</th>
            <th>Author</th>
            <th style="width:10rem">Status</th>
            <th style="width:10rem">Actions</th>
          </tr>
        </thead>
        <tbody>
          @foreach($contrib as $p)
          @php $meta = $p['metadata']; $isEnabled = $p['enabled']; @endphp
          <tr>
            <td style="text-align:center"><i data-lucide="package" class="w-5 h-5" style="color:var(--warning)"></i></td>
            <td>
              <strong>{{ $meta->name }}</strong>
              <div class="text-xs text-muted">{{ $meta->machineName }}</div>
            </td>
            <td class="text-sm">{{ $meta->description }}</td>
            <td><span class="badge badge--neutral">{{ $meta->version }}</span></td>
            <td class="text-sm text-muted">{{ $meta->author }}</td>
            <td>
              @if($isEnabled)
                <span class="badge badge--success">Enabled</span>
              @else
                <span class="badge badge--neutral">Disabled</span>
              @endif
            </td>
            <td>
              <div style="display:flex;gap:.5rem;align-items:center">
                @if($isEnabled)
                  <form action="/admin/plugins/disable" method="POST" style="margin:0">
                    <input type="hidden" name="plugin" value="{{ $meta->machineName }}">
                    <button type="submit" class="btn btn--ghost btn--xs" title="Disable">
                      <i data-lucide="toggle-right" class="w-4 h-4"></i>
                    </button>
                  </form>
                  <a href="/admin/plugins/{{ $meta->vendor }}/{{ $meta->name }}/settings" class="btn btn--ghost btn--xs" title="Settings">
                    <i data-lucide="settings" class="w-4 h-4"></i>
                  </a>
                @else
                  <form action="/admin/plugins/enable" method="POST" style="margin:0">
                    <input type="hidden" name="plugin" value="{{ $meta->machineName }}">
                    <button type="submit" class="btn btn--primary btn--xs" title="Enable">
                      <i data-lucide="toggle-left" class="w-4 h-4"></i> Enable
                    </button>
                  </form>
                  <form action="/admin/plugins/uninstall" method="POST" style="margin:0" onsubmit="return confirm('Uninstall {{ $meta->name }}? This will remove all plugin data.')">
                    <input type="hidden" name="plugin" value="{{ $meta->machineName }}">
                    <button type="submit" class="btn btn--danger btn--xs" title="Uninstall">
                      <i data-lucide="trash-2" class="w-4 h-4"></i>
                    </button>
                  </form>
                @endif
              </div>
            </td>
          </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  </div>
  @endif

  {{-- ── Empty State ───────────────────────────────────────────────────── --}}
  @if(empty($custom) && empty($contrib))
  <div class="card">
    <div class="card__body" style="text-align:center;padding:3rem">
      <i data-lucide="puzzle" class="w-12 h-12" style="color:var(--text-muted);margin-bottom:1rem"></i>
      <h3 style="margin-bottom:.5rem">No plugins installed</h3>
      <p class="text-muted">Upload a plugin ZIP or place plugin directories in <code>plugins/custom/</code> or <code>plugins/contrib/</code>.</p>
      <a href="/admin/plugins/upload" class="btn btn--primary" style="margin-top:1rem">
        <i data-lucide="upload" class="w-4 h-4"></i> Upload Plugin
      </a>
    </div>
  </div>
  @endif

</div>
@endsection
