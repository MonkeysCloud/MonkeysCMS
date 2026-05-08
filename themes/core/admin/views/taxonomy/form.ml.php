@extends('layouts.admin')

@section('title', $title)
@section('page_title', $title)

@section('breadcrumb')
<a href="/admin" class="breadcrumb__item">Dashboard</a>
<span class="breadcrumb__sep">›</span>
<a href="/admin/taxonomy" class="breadcrumb__item">Taxonomy</a>
<span class="breadcrumb__sep">›</span>
<span class="breadcrumb__item breadcrumb__item--active">{{ $isNew ? 'Create' : 'Edit' }}</span>
@endsection

@section('content')
<div class="taxonomy-form-page">
  {!! $formHtml !!}
</div>

@push('head')
<style>
.taxonomy-form-page { padding: 1.5rem 2rem; max-width: 1100px; }

.form-static {
  padding: 0.5rem 0.75rem;
  background: rgba(255,255,255,0.03);
  border: 1px solid rgba(255,255,255,0.06);
  border-radius: 8px;
}
.form-static code {
  font-size: 0.8rem; color: #a5b4fc;
}

/* Two-column grid layout for the FormRenderer */
.admin-grid--2 {
  display: grid;
  grid-template-columns: 1fr 320px;
  gap: 1.25rem;
  align-items: start;
}
@media (max-width: 900px) {
  .admin-grid--2 { grid-template-columns: 1fr; }
}
</style>
@endpush

@endsection
