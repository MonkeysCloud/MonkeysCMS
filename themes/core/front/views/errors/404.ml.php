@extends('layouts.front')

@section('title', '404 — Page Not Found | ' . ($site_name ?? 'MonkeysCMS'))

@section('content')
<div class="container" style="padding: 6rem 0 8rem; text-align: center;">
  <div style="font-size: 6rem; font-weight: 900; color: var(--front-accent); opacity: .3; line-height: 1;">404</div>
  <h1 style="font-size: 1.8rem; font-weight: 700; color: var(--front-heading); margin: 1rem 0 .75rem;">Page Not Found</h1>
  <p style="color: var(--front-muted); font-size: 1.05rem; max-width: 420px; margin: 0 auto 2rem;">
    The page you're looking for doesn't exist or has been moved.
  </p>
  <div style="display: flex; gap: .75rem; justify-content: center; flex-wrap: wrap;">
    <a href="/" class="btn-front btn-front--primary">Back to Home</a>
    <a href="/search" class="btn-front btn-front--secondary">Search</a>
  </div>
</div>
@endsection
