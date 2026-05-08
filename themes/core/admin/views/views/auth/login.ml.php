@extends('layouts.auth')

@section('title', 'Sign In — MonkeysCMS')

@section('subtitle', 'Sign in to your admin account')

@section('content')

<div id="login-error" class="alert alert--error" style="display:none">
  <i data-lucide="alert-circle" class="w-4 h-4"></i>
  <span id="login-error-msg"></span>
</div>

<div id="login-success" class="alert alert--success" style="display:none">
  <i data-lucide="check-circle" class="w-4 h-4"></i>
  <span id="login-success-msg"></span>
</div>

@render($form)

@endsection