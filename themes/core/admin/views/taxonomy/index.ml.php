@extends('layouts.admin')

@section('title', 'Taxonomy')
@section('page_title', 'Taxonomy')

@section('breadcrumb')
<a href="/admin" class="breadcrumb__item">Dashboard</a>
<span class="breadcrumb__sep">›</span>
<span class="breadcrumb__item breadcrumb__item--active">Taxonomy</span>
@endsection

@section('content')
<div class="taxonomy-page">

  {{-- Flash Messages --}}
  @php
    $success = $_GET['success'] ?? null;
    $error = $_GET['error'] ?? null;
  @endphp

  @if($success)
  <div class="alert alert--success mb-4">
    <i data-lucide="check-circle-2" class="w-4 h-4"></i>
    <span>{{ $success }}</span>
  </div>
  @endif

  @if($error)
  <div class="alert alert--error mb-4">
    <i data-lucide="alert-circle" class="w-4 h-4"></i>
    <span>{{ $error }}</span>
  </div>
  @endif

  {{-- Header --}}
  <div class="taxonomy-header">
    <div class="taxonomy-header__info">
      <p class="text-muted text-sm">
        Organize content using vocabularies and taxonomy terms. Vocabularies group related terms together.
      </p>
    </div>
    <a href="/admin/taxonomy/create" class="btn btn--primary btn--sm">
      <i data-lucide="plus" class="w-4 h-4"></i>
      <span>Add Vocabulary</span>
    </a>
  </div>

  {{-- Vocabulary Cards --}}
  @if(!empty($vocabularies))
  <div class="taxonomy-grid">
    @foreach($vocabularies as $vocab)
    <div class="taxonomy-card card">
      <div class="taxonomy-card__header">
        <div class="taxonomy-card__icon">
          @if($vocab->hierarchical)
            <i data-lucide="git-branch" class="w-5 h-5"></i>
          @else
            <i data-lucide="tags" class="w-5 h-5"></i>
          @endif
        </div>
        <div class="taxonomy-card__title-wrap">
          <h3 class="taxonomy-card__title">
            <a href="/admin/taxonomy/{{ $vocab->id }}/terms">{{ $vocab->label }}</a>
          </h3>
          <span class="taxonomy-card__machine">{{ $vocab->machine_name }}</span>
        </div>
        <div class="taxonomy-card__count">
          <span class="taxonomy-card__count-value">{{ $termCounts[$vocab->id] ?? 0 }}</span>
          <span class="taxonomy-card__count-label">terms</span>
        </div>
      </div>

      @if($vocab->description)
      <p class="taxonomy-card__desc">{{ $vocab->description }}</p>
      @endif

      <div class="taxonomy-card__badges">
        @if($vocab->hierarchical)
          <span class="badge badge--sm badge--info">Hierarchical</span>
        @else
          <span class="badge badge--sm badge--muted">Flat</span>
        @endif
        @if($vocab->multiple)
          <span class="badge badge--sm badge--success">Multiple</span>
        @else
          <span class="badge badge--sm badge--muted">Single</span>
        @endif
      </div>

      <div class="taxonomy-card__actions">
        <a href="/admin/taxonomy/{{ $vocab->id }}/terms" class="btn btn--sm btn--primary taxonomy-card__cta" title="Manage terms">
          <i data-lucide="list-tree" class="w-4 h-4"></i>
          <span>Manage Terms</span>
        </a>
        <div class="taxonomy-card__secondary">
          <a href="/admin/taxonomy/{{ $vocab->id }}/edit" class="btn btn--xs btn--ghost" title="Edit vocabulary">
            <i data-lucide="pencil" class="w-3.5 h-3.5"></i>
          </a>
          <form action="/admin/taxonomy/{{ $vocab->id }}/delete" method="POST" class="inline"
                data-confirm="Delete vocabulary '{{ addslashes($vocab->label) }}' and all its terms? This cannot be undone." data-confirm-title="Delete Vocabulary">
            <button type="submit" class="btn btn--xs btn--ghost btn--danger" title="Delete vocabulary">
              <i data-lucide="trash-2" class="w-3.5 h-3.5"></i>
            </button>
          </form>
        </div>
      </div>
    </div>
    @endforeach
  </div>
  @else
  <div class="card">
    <div class="card__body">
      <div class="empty-state py-12">
        <div class="empty-state__icon"><i data-lucide="folder-tree" class="w-12 h-12"></i></div>
        <div class="empty-state__title">No vocabularies yet</div>
        <p class="text-muted text-sm mb-4">Create your first vocabulary to start organizing content with taxonomy terms.</p>
        <a href="/admin/taxonomy/create" class="btn btn--primary btn--sm">
          <i data-lucide="plus" class="w-4 h-4"></i>
          <span>Create Vocabulary</span>
        </a>
      </div>
    </div>
  </div>
  @endif

</div>

@push('head')
<style>
.taxonomy-page { padding: 1.5rem 2rem; max-width: 1280px; }

.taxonomy-header {
  display: flex; justify-content: space-between; align-items: center;
  margin-bottom: 1.5rem;
}

.taxonomy-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(380px, 1fr));
  gap: 1rem;
}

.taxonomy-card {
  display: flex; flex-direction: column; gap: 0.75rem;
  padding: 1.25rem 1.5rem;
  transition: border-color 0.2s, box-shadow 0.2s;
}
.taxonomy-card:hover {
  border-color: rgba(99,102,241,0.3);
  box-shadow: 0 0 0 1px rgba(99,102,241,0.1);
}

.taxonomy-card__header {
  display: flex; align-items: flex-start; gap: 0.75rem;
}
.taxonomy-card__icon {
  flex-shrink: 0;
  width: 40px; height: 40px;
  display: flex; align-items: center; justify-content: center;
  background: linear-gradient(135deg, rgba(99,102,241,0.12), rgba(139,92,246,0.08));
  border-radius: 10px; color: #a5b4fc;
}
.taxonomy-card__title-wrap { flex: 1; min-width: 0; }
.taxonomy-card__title {
  font-size: 1rem; font-weight: 600; margin: 0; line-height: 1.3;
}
.taxonomy-card__title a {
  color: #e2e8f0; text-decoration: none; transition: color 0.15s;
}
.taxonomy-card__title a:hover { color: #a5b4fc; }
.taxonomy-card__machine {
  font-size: 0.7rem; color: #64748b; font-family: 'JetBrains Mono', monospace;
}
.taxonomy-card__count {
  flex-shrink: 0; text-align: center;
  padding: 0.35rem 0.75rem;
  background: rgba(99,102,241,0.06); border-radius: 8px;
}
.taxonomy-card__count-value {
  display: block; font-size: 1.25rem; font-weight: 700;
  background: linear-gradient(135deg, #a5b4fc, #818cf8);
  -webkit-background-clip: text; -webkit-text-fill-color: transparent;
}
.taxonomy-card__count-label {
  font-size: 0.6rem; color: #64748b; text-transform: uppercase; letter-spacing: 0.05em;
}

.taxonomy-card__desc {
  font-size: 0.8rem; color: #94a3b8; line-height: 1.5; margin: 0;
}

.taxonomy-card__badges { display: flex; gap: 0.35rem; }
.badge--sm { font-size: 0.6rem; padding: 0.15rem 0.4rem; }
.badge--info { background: rgba(99,102,241,0.12); color: #a5b4fc; }
.badge--muted { background: rgba(100,116,139,0.1); color: #64748b; }
.badge--success { background: rgba(34,197,94,0.12); color: #4ade80; }

.taxonomy-card__actions {
  display: flex; align-items: center; gap: 0.5rem;
  padding-top: 0.75rem;
  border-top: 1px solid rgba(255,255,255,0.04);
}
.taxonomy-card__cta {
  flex: 1; justify-content: center;
  gap: 0.35rem;
}
.taxonomy-card__cta span { margin-left: 0; }
.taxonomy-card__secondary {
  display: flex; gap: 0.2rem; flex-shrink: 0;
}
.btn--danger { color: #f87171 !important; }
.btn--danger:hover { background: rgba(248,113,113,0.1) !important; }
.inline { display: inline; }
</style>
@endpush

@endsection
