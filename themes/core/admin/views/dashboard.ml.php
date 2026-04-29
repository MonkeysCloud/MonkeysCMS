@extends('layouts.admin')

@section('title', 'Dashboard')
@section('page_title', 'Dashboard')

@section('breadcrumb')
<span class="breadcrumb__item breadcrumb__item--active">Dashboard</span>
@endsection

@section('content')
<div id="dashboard-app">

  {{-- Stats Row --}}
  <div class="grid grid-4 mb-6">
    {{-- Content --}}
    @php $totalContent = 0; @endphp
    @foreach($stats['content'] ?? [] as $type => $statuses)
      @php $totalContent += array_sum($statuses); @endphp
    @endforeach

    <div class="stat-card stat-card--primary">
      <div class="stat-card__value">{{ $totalContent }}</div>
      <div class="stat-card__label">Total Content</div>
      <div class="stat-card__icon">📝</div>
    </div>

    <div class="stat-card stat-card--secondary">
      <div class="stat-card__value">{{ $stats['media'] ?? 0 }}</div>
      <div class="stat-card__label">Media Files</div>
      <div class="stat-card__icon">🖼️</div>
    </div>

    <div class="stat-card stat-card--success">
      <div class="stat-card__value">{{ $stats['users'] ?? 0 }}</div>
      <div class="stat-card__label">Active Users</div>
      <div class="stat-card__icon">👥</div>
    </div>

    <div class="stat-card stat-card--info">
      <div class="stat-card__value">{{ count($stats['content'] ?? []) }}</div>
      <div class="stat-card__label">Content Types</div>
      <div class="stat-card__icon">⚙️</div>
    </div>
  </div>

  {{-- Content Overview + Quick Actions --}}
  <div class="grid grid-2 mb-6">
    {{-- Content by Type --}}
    <div class="card">
      <div class="card__header">
        <h3 class="card__title">Content Overview</h3>
      </div>
      <div class="card__body p-0">
        <table class="table table--striped">
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
              <td class="font-medium" style="text-transform:capitalize;">{{ $type }}</td>
              <td><span class="badge badge--success">{{ $statuses['published'] ?? 0 }}</span></td>
              <td><span class="badge badge--warning">{{ $statuses['draft'] ?? 0 }}</span></td>
              <td>{{ array_sum($statuses) }}</td>
            </tr>
            @endforeach
            @empty($stats['content'])
            <tr>
              <td colspan="4" class="text-center text-muted py-4">No content yet</td>
            </tr>
            @endempty
          </tbody>
        </table>
      </div>
    </div>

    {{-- Quick Actions --}}
    <div class="card">
      <div class="card__header">
        <h3 class="card__title">Quick Actions</h3>
      </div>
      <div class="card__body">
        <div class="quick-actions">
          <a href="/admin/content/create/article" class="quick-action">
            <span class="quick-action__icon">📰</span>
            <span class="quick-action__label">New Article</span>
          </a>
          <a href="/admin/content/create/page" class="quick-action">
            <span class="quick-action__icon">📄</span>
            <span class="quick-action__label">New Page</span>
          </a>
          <a href="/admin/media" class="quick-action">
            <span class="quick-action__icon">🖼️</span>
            <span class="quick-action__label">Media Library</span>
          </a>
          <a href="/admin/appearance" class="quick-action">
            <span class="quick-action__icon">🎨</span>
            <span class="quick-action__label">Appearance</span>
          </a>
          <a href="/admin/menus" class="quick-action">
            <span class="quick-action__icon">☰</span>
            <span class="quick-action__label">Menus</span>
          </a>
          <a href="/admin/settings" class="quick-action">
            <span class="quick-action__icon">🔧</span>
            <span class="quick-action__label">Settings</span>
          </a>
        </div>
      </div>
    </div>
  </div>

  {{-- Recent Content --}}
  <div class="card">
    <div class="card__header flex-between">
      <h3 class="card__title">Recent Content</h3>
      <a href="/admin/content" class="btn btn--sm btn--ghost">View All →</a>
    </div>
    <div class="card__body p-0">
      @isset($recent)
      <table class="table table--hover">
        <thead>
          <tr>
            <th>Title</th>
            <th>Type</th>
            <th>Status</th>
            <th>Updated</th>
          </tr>
        </thead>
        <tbody>
          @foreach($recent as $node)
          <tr>
            <td><a href="/admin/content/{{ $node->id }}/edit">{{ $node->title }}</a></td>
            <td class="text-muted text-sm">{{ $node->content_type ?? 'article' }}</td>
            <td><span class="badge badge--{{ $node->status ?? 'draft' }}">{{ ucfirst($node->status ?? 'draft') }}</span></td>
            <td class="text-muted text-sm">{{ $node->updated_at ?? '' }}</td>
          </tr>
          @endforeach
        </tbody>
      </table>
      @else
      <div class="empty-state">
        <div class="empty-state__icon">📝</div>
        <div class="empty-state__title">No content yet</div>
        <p class="text-muted">Create your first piece of content to get started.</p>
        <a href="/admin/content/create/article" class="btn btn--primary mt-4">Create Content</a>
      </div>
      @endisset
    </div>
  </div>
</div>
@endsection
