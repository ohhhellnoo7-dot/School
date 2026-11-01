@extends('school.auth.base')
@section('title', 'Sign in')
@section('content')
<header class="auth-welcome"><div class="eyebrow">WELCOME TO YOUR SCHOOL PORTAL</div><h1>A good day to learn. <span aria-hidden="true">✨</span></h1><p>Sign in to stay connected with your school.</p></header>
<form method="post" action="{{ route('login') }}" class="auth-login">
@csrf
<div class="mb-3"><label class="form-label" for="email">{{ __('Email address') }}</label><div class="auth-input"><i class="bi bi-envelope" aria-hidden="true"></i><input id="email" class="form-control" type="email" name="email" value="{{ old('email', request()->query('email')) }}" placeholder="you@greenfield.edu" autocomplete="username" required autofocus></div></div>
<div class="mb-3"><label class="form-label" for="password">{{ __('Password') }}</label><div class="auth-input"><i class="bi bi-lock" aria-hidden="true"></i><input id="password" class="form-control" type="password" name="password" autocomplete="current-password" required><button type="button" class="password-toggle" aria-label="Show password" aria-pressed="false" aria-controls="password"><i class="bi bi-eye" aria-hidden="true"></i></button></div></div>
<div class="form-check auth-remember"><input id="remember" name="remember" type="checkbox" class="form-check-input" value="1" @checked(old('remember'))><label for="remember" class="form-check-label">{{ __('Keep me signed in') }}</label></div>
<button class="btn btn-primary w-100 auth-submit" type="submit">{{ __('Sign in') }} <i class="bi bi-arrow-right" aria-hidden="true"></i></button>
</form>
<p class="auth-account">Need an account? <a href="#account-help">Ask your school administrator</a></p>
<div class="auth-help" id="account-help"><i class="bi bi-info-circle" aria-hidden="true"></i><p>Need access to your school records?<br><span>Contact your school administrator to link your account.</span></p></div>
@endsection
