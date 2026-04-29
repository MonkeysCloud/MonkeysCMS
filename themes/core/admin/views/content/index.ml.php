@extends('layouts.admin')

@section('title', 'Content')
@section('page_title', 'Content')

@section('breadcrumb')
<a href="/admin" class="breadcrumb__item">Dashboard</a>
<span class="breadcrumb__sep">›</span>
<span class="breadcrumb__item breadcrumb__item--active">Content</span>
@endsection

@section('page_actions')
<div class="dropdown">
  <button class="btn btn--primary btn--sm" $m-on:click="showCreateMenu = !showCreateMenu">
    + New Content
  </button>
  <div class="dropdown__menu" $m-show="showCreateMenu">
    @foreach($contentTypes ?? [] as $ct)
    <a href="/admin/content/create/{{ $ct->machine_name ?? $ct }}" class="dropdown__item">
      {{ $ct->label ?? ucfirst($ct) }}
    </a>
    @endforeach
  </div>
</div>
@endsection

@section('content')
<div id="content-app">

  {{-- Filters --}}
  <div class="card mb-4">
    <div class="card__body">
      <div class="flex gap-4 flex-wrap">
        <div class="form-group" style="flex:1; min-width:200px;">
          <input type="text" class="form-input" placeholder="Search content..." $m-model="search" $m-on:input="filterContent()">
        </div>
        <div class="form-group">
          <select class="form-select" $m-model="filterType" $m-on:change="filterContent()">
            <option value="">All Types</option>
            @foreach($contentTypes ?? [] as $ct)
            <option value="{{ $ct->machine_name ?? $ct }}">{{ $ct->label ?? ucfirst($ct) }}</option>
            @endforeach
          </select>
        </div>
        <div class="form-group">
          <select class="form-select" $m-model="filterStatus" $m-on:change="filterContent()">
            <option value="">All Status</option>
            <option value="published">Published</option>
            <option value="draft">Draft</option>
            <option value="archived">Archived</option>
          </select>
        </div>
      </div>
    </div>
  </div>

  {{-- Content Table --}}
  <div class="card">
    <div class="card__body p-0">
      <table class="table table--hover">
        <thead>
          <tr>
            <th class="table__check"><input type="checkbox" $m-on:change="toggleAll($event)"></th>
            <th>Title</th>
            <th>Type</th>
            <th>Author</th>
            <th>Status</th>
            <th>Updated</th>
            <th class="table__actions">Actions</th>
          </tr>
        </thead>
        <tbody>
          @foreach($nodes ?? [] as $node)
          <tr>
            <td class="table__check"><input type="checkbox" value="{{ $node->id }}"></td>
            <td>
              <a href="/admin/content/{{ $node->id }}/edit" class="font-medium">{{ $node->title }}</a>
              @isset($node->slug)
              <div class="text-xs text-muted">/{{ $node->slug }}</div>
              @endisset
            </td>
            <td class="text-sm">{{ $node->content_type ?? '' }}</td>
            <td class="text-sm text-muted">{{ $node->author_name ?? '' }}</td>
            <td><span class="badge badge--{{ $node->status ?? 'draft' }}">{{ ucfirst($node->status ?? 'draft') }}</span></td>
            <td class="text-sm text-muted">{{ $node->updated_at ?? '' }}</td>
            <td class="table__actions">
              <a href="/admin/content/{{ $node->id }}/edit" class="btn btn--xs btn--ghost">Edit</a>
              <a href="/admin/mosaic/{{ $node->id }}" class="btn btn--xs btn--ghost">Mosaic</a>
              <button class="btn btn--xs btn--ghost text-danger" $m-on:click="deleteNode({{ $node->id }})">Delete</button>
            </td>
          </tr>
          @endforeach
          @empty($nodes)
          <tr>
            <td colspan="7">
              <div class="empty-state">
                <div class="empty-state__icon">📝</div>
                <div class="empty-state__title">No content found</div>
                <p class="text-muted">Create your first piece of content.</p>
              </div>
            </td>
          </tr>
          @endempty
        </tbody>
      </table>
    </div>
  </div>

  {{-- Pagination --}}
  @isset($pagination)
  <div class="flex-between mt-4">
    <span class="text-sm text-muted">Showing {{ $pagination['from'] ?? 0 }}–{{ $pagination['to'] ?? 0 }} of {{ $pagination['total'] ?? 0 }}</span>
    <div class="pagination">
      @for($i = 1; $i <= ($pagination['pages'] ?? 1); $i++)
      <a href="?page={{ $i }}" class="pagination__item {{ ($pagination['current'] ?? 1) == $i ? 'active' : '' }}">{{ $i }}</a>
      @endfor
    </div>
  </div>
  @endisset
</div>
@endsection
