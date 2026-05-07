@extends('layouts.front')

@section('title', '500 — Server Error | ' . ($site_name ?? 'MonkeysCMS'))

@section('content')
<div class="container" style="padding: 6rem 0 8rem; text-align: center;">
  <div style="font-size: 6rem; font-weight: 900; color: #ef4444; opacity: .3; line-height: 1;">500</div>
  <h1 style="font-size: 1.8rem; font-weight: 700; color: var(--front-heading); margin: 1rem 0 .75rem;">Something Went Wrong</h1>
  <p style="color: var(--front-muted); font-size: 1.05rem; max-width: 420px; margin: 0 auto 2rem;">
    We're experiencing a technical issue. Please try again later.
  </p>
  <a href="/" class="btn-front btn-front--primary">Back to Home</a>
</div>
@endsection
