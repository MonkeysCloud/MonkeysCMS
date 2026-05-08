@extends('layouts.public')

@section('title', $title ?? 'Form Closed')

@section('content')
<div style="max-width:600px;margin:4rem auto;text-align:center;padding:0 1rem">
  <div style="font-size:3rem;margin-bottom:1rem">🔒</div>
  <h1>{{ $webform->label }}</h1>
  @if(($reason ?? '') === 'max_reached')
    <p style="color:var(--text-muted);margin-top:1rem">This form has reached its maximum number of submissions.</p>
  @else
    <p style="color:var(--text-muted);margin-top:1rem">This form is currently closed and not accepting submissions.</p>
  @endif
</div>
@endsection
