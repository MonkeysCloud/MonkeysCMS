@extends('layouts.admin')

@section('title', 'Content Types')
@section('page_title', 'Content Types')

@section('breadcrumb')
<a href="/admin" class="breadcrumb__item">Dashboard</a>
<span class="breadcrumb__sep">›</span>
<span class="breadcrumb__item breadcrumb__item--active">Content Types</span>
@endsection

@section('page_actions')
<a href="/admin/content-types/create" class="btn btn--primary btn--sm">
  <i data-lucide="plus" class="w-4 h-4"></i>
  New Content Type
</a>
@endsection

@section('content')
<div class="content-types-grid">
  @foreach($contentTypes ?? [] as $ct)
  <div class="ct-card" id="ct-{{ $ct->type_id }}">
    <div class="ct-card__header">
      <div class="ct-card__icon">
        <i data-lucide="{{ $ct->icon }}" class="w-6 h-6"></i>
      </div>
      <div class="ct-card__meta">
        <h3 class="ct-card__title">{{ $ct->label }}</h3>
        <span class="ct-card__machine text-xs text-muted">{{ $ct->type_id }}</span>
      </div>
      @if($ct->is_system)
      <span class="badge badge--muted badge--sm">System</span>
      @endif
    </div>

    @if($ct->description)
    <p class="ct-card__desc">{{ $ct->description }}</p>
    @endif

    <div class="ct-card__features">
      @if($ct->publishable)
      <span class="ct-feature" title="Publishable"><i data-lucide="check-circle" class="w-3.5 h-3.5"></i> Publish</span>
      @endif
      @if($ct->revisionable)
      <span class="ct-feature" title="Revisionable"><i data-lucide="history" class="w-3.5 h-3.5"></i> Revisions</span>
      @endif
      @if($ct->hasMosaic)
      <span class="ct-feature ct-feature--mosaic" title="Mosaic Builder"><i data-lucide="layout-grid" class="w-3.5 h-3.5"></i> Mosaic</span>
      @endif
      @if($ct->has_taxonomy)
      <span class="ct-feature" title="Taxonomy"><i data-lucide="tags" class="w-3.5 h-3.5"></i> Tags</span>
      @endif
      @if($ct->has_media)
      <span class="ct-feature" title="Media"><i data-lucide="image" class="w-3.5 h-3.5"></i> Media</span>
      @endif
    </div>

    <div class="ct-card__url">
      <i data-lucide="link" class="w-3.5 h-3.5 text-muted"></i>
      <code class="text-xs">{{ $ct->routePattern }}</code>
    </div>

    <div class="ct-card__actions">
      <a href="/admin/content-types/{{ $ct->type_id }}/fields" class="btn btn--sm btn--ghost">
        <i data-lucide="list" class="w-4 h-4"></i> Fields
      </a>
      <a href="/admin/content-types/{{ $ct->type_id }}/edit" class="btn btn--sm btn--ghost">
        <i data-lucide="pencil" class="w-4 h-4"></i> Edit
      </a>
      @if(!$ct->is_system)
      <form action="/admin/content-types/{{ $ct->type_id }}/delete" method="POST" style="display:inline"
            onsubmit="return confirm('Delete {{ $ct->label }}? This cannot be undone.')">
        <button type="submit" class="btn btn--sm btn--ghost text-danger">
          <i data-lucide="trash-2" class="w-4 h-4"></i>
        </button>
      </form>
      @endif
    </div>
  </div>
  @endforeach

  @empty($contentTypes)
  <div class="empty-state" style="grid-column: 1 / -1">
    <div class="empty-state__icon"><i data-lucide="database" class="w-12 h-12"></i></div>
    <div class="empty-state__title">No content types</div>
    <p class="text-muted">Create your first content type to start managing content.</p>
    <a href="/admin/content-types/create" class="btn btn--primary mt-3">Create Content Type</a>
  </div>
  @endempty
</div>

@push('head')
<style>
.content-types-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(340px, 1fr));
  gap: 1.25rem;
}
.ct-card {
  background: var(--card-bg, rgba(20, 22, 38, 0.6));
  border: 1px solid var(--border, rgba(255,255,255,0.06));
  border-radius: 16px;
  padding: 1.5rem;
  transition: border-color 0.2s, box-shadow 0.2s;
}
.ct-card:hover {
  border-color: rgba(99, 102, 241, 0.2);
  box-shadow: 0 4px 20px rgba(0,0,0,0.15);
}
.ct-card__header {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  margin-bottom: 0.75rem;
}
.ct-card__icon {
  width: 44px; height: 44px;
  display: flex; align-items: center; justify-content: center;
  background: linear-gradient(135deg, rgba(99,102,241,0.15), rgba(139,92,246,0.1));
  border-radius: 12px;
  color: #818cf8;
  flex-shrink: 0;
}
.ct-card__meta { flex: 1; min-width: 0; }
.ct-card__title {
  font-size: 1rem; font-weight: 600; color: #e2e8f0;
  margin: 0; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
}
.ct-card__desc {
  font-size: 0.8rem; color: #64748b; margin: 0 0 0.75rem; line-height: 1.5;
}
.ct-card__features {
  display: flex; flex-wrap: wrap; gap: 0.5rem; margin-bottom: 0.75rem;
}
.ct-feature {
  display: inline-flex; align-items: center; gap: 0.25rem;
  font-size: 0.7rem; color: #94a3b8;
  background: rgba(255,255,255,0.04); padding: 0.2rem 0.5rem;
  border-radius: 6px; border: 1px solid rgba(255,255,255,0.06);
}
.ct-feature--mosaic { color: #818cf8; border-color: rgba(99,102,241,0.15); background: rgba(99,102,241,0.06); }
.ct-card__url {
  display: flex; align-items: center; gap: 0.4rem;
  margin-bottom: 1rem; padding: 0.4rem 0.6rem;
  background: rgba(0,0,0,0.2); border-radius: 8px;
}
.ct-card__url code { color: #94a3b8; }
.ct-card__actions {
  display: flex; gap: 0.5rem; padding-top: 0.75rem;
  border-top: 1px solid rgba(255,255,255,0.04);
}
</style>
@endpush
@endsection
