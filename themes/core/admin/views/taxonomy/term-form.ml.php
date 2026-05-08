@extends('layouts.admin')

@section('title', $title)
@section('page_title', $title)

@section('breadcrumb')
<a href="/admin" class="breadcrumb__item">Dashboard</a>
<span class="breadcrumb__sep">›</span>
<a href="/admin/taxonomy" class="breadcrumb__item">Taxonomy</a>
<span class="breadcrumb__sep">›</span>
<a href="/admin/taxonomy/{{ $vocabulary->id }}/terms" class="breadcrumb__item">{{ $vocabulary->label }}</a>
<span class="breadcrumb__sep">›</span>
<span class="breadcrumb__item breadcrumb__item--active">{{ $isNew ? 'Add Term' : 'Edit' }}</span>
@endsection

@section('content')
<div class="term-form-page">
  {!! $formHtml !!}
</div>

@push('head')
<style>
.term-form-page { padding: 1.5rem 2rem; max-width: 1100px; }

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
