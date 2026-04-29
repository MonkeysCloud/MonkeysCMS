@extends('layouts.admin')

@section('title', 'Dashboard')
@section('toolbar_title', 'Dashboard')

@section('content')
<div id="dashboard-app">

  {{-- Stats Cards --}}
  <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap:1rem; margin-bottom:2rem;">
    {{-- Content Stats --}}
    @php $totalContent = 0; @endphp
    @foreach($stats['content'] ?? [] as $type => $statuses)
      @php $typeTotal = array_sum($statuses); $totalContent += $typeTotal; @endphp
    @endforeach

    <div class="card">
      <div class="card__body" style="text-align:center;">
        <div style="font-size:2.5rem; font-weight:700; color:var(--cms-primary);">{{ $totalContent }}</div>
        <div style="font-size:0.875rem; color:var(--cms-text-muted); margin-top:0.25rem;">Total Content</div>
      </div>
    </div>

    <div class="card">
      <div class="card__body" style="text-align:center;">
        <div style="font-size:2.5rem; font-weight:700; color:var(--cms-secondary);">{{ $stats['media'] ?? 0 }}</div>
        <div style="font-size:0.875rem; color:var(--cms-text-muted); margin-top:0.25rem;">Media Files</div>
      </div>
    </div>

    <div class="card">
      <div class="card__body" style="text-align:center;">
        <div style="font-size:2.5rem; font-weight:700; color:var(--cms-success);">{{ $stats['users'] ?? 0 }}</div>
        <div style="font-size:0.875rem; color:var(--cms-text-muted); margin-top:0.25rem;">Active Users</div>
      </div>
    </div>
  </div>

  {{-- Content by Type --}}
  <div class="card" style="margin-bottom:1.5rem;">
    <div class="card__header">
      <h3 class="card__title">Content Overview</h3>
    </div>
    <div class="card__body">
      <table class="table">
        <thead>
          <tr>
            <th>Type</th>
            <th>Published</th>
            <th>Draft</th>
            <th>Total</th>
          </tr>
        </thead>
        <tbody>
          @foreach($stats['content'] ?? [] as $type => $statuses)
          <tr>
            <td style="text-transform:capitalize;">{{ $type }}</td>
            <td><span class="badge badge--published">{{ $statuses['published'] ?? 0 }}</span></td>
            <td><span class="badge badge--draft">{{ $statuses['draft'] ?? 0 }}</span></td>
            <td>{{ array_sum($statuses) }}</td>
          </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  </div>

  {{-- Quick Actions --}}
  <div class="card">
    <div class="card__header">
      <h3 class="card__title">Quick Actions</h3>
    </div>
    <div class="card__body" style="display:flex; gap:0.75rem; flex-wrap:wrap;">
      <a href="/admin/content/create/article" class="btn btn-primary">📰 New Article</a>
      <a href="/admin/content/create/page" class="btn btn-primary">📄 New Page</a>
      <a href="/admin/media" class="btn btn-secondary">🖼️ Media Library</a>
      <a href="/admin/settings" class="btn btn-secondary">🔧 Settings</a>
    </div>
  </div>
</div>
@endsection
