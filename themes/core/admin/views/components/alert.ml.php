{{-- Alert Component --}}
{{-- Usage: @include('components.alert', ['type' => 'success', 'message' => 'Saved!']) --}}
@php
$colors = [
    'success' => ['bg' => 'rgba(34, 197, 94, 0.1)', 'border' => '#22c55e', 'icon' => '✅'],
    'error'   => ['bg' => 'rgba(239, 68, 68, 0.1)', 'border' => '#ef4444', 'icon' => '❌'],
    'warning' => ['bg' => 'rgba(234, 179, 8, 0.1)',  'border' => '#eab308', 'icon' => '⚠️'],
    'info'    => ['bg' => 'rgba(59, 130, 246, 0.1)', 'border' => '#3b82f6', 'icon' => 'ℹ️'],
];
$c = $colors[$type ?? 'info'] ?? $colors['info'];
@endphp
<div role="alert" style="display:flex; align-items:center; gap:0.75rem; padding:0.75rem 1rem; background:{{ $c['bg'] }}; border:1px solid {{ $c['border'] }}; border-radius:8px; margin-bottom:1rem;">
  <span>{{ $c['icon'] }}</span>
  <span style="flex:1; font-size:0.9rem;">{{ $message ?? '' }}</span>
  @if($dismissible ?? false)
  <button style="background:none; border:none; cursor:pointer; font-size:1rem; color:inherit;" data-dismiss>✕</button>
  @endif
</div>
