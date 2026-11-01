<!doctype html>
<html lang="{{ app()->getLocale() }}"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>@yield('title') · Scholar</title>
<link href="{{ asset('vendor/bootstrap/bootstrap.min.css') }}" rel="stylesheet">
<link href="{{ asset('vendor/bootstrap-icons/bootstrap-icons.min.css') }}" rel="stylesheet">
<link href="{{ asset('css/school.css') }}" rel="stylesheet">
@include('school.partials.theme-head')
<link href="{{ asset('css/school-auth.css') }}?v={{ filemtime(public_path('css/school-auth.css')) }}" rel="stylesheet">
<script src="{{ asset('js/school-auth.js') }}" defer></script>
</head><body class="school-auth"><main class="auth-page">
<section class="auth-story" aria-label="Greenfield Academy">
<a class="brand" href="{{ url('/') }}"><span class="brand-mark"><i class="bi bi-mortarboard-fill" aria-hidden="true"></i></span><span>scholar.<small>School Portal</small></span></a>
<div class="auth-story-copy"><div class="academy-pill">GREENFIELD ACADEMY</div>
<h1>Great school days<br><span>start here.</span></h1>
<p>A smarter, simpler way to manage your school community, learning, and school life — all in one place.</p>
<div class="auth-features">
<div><i class="bi bi-mortarboard-fill" aria-hidden="true"></i><span>Manage<br>Learning</span></div>
<div><i class="bi bi-people-fill" aria-hidden="true"></i><span>Stay<br>Connected</span></div>
<div><i class="bi bi-bar-chart-fill" aria-hidden="true"></i><span>Track<br>Progress</span></div>
</div></div><small class="auth-copyright">© {{ now()->year }} Scholar · Building brighter futures.</small>
</section>
<section class="auth-form"><div class="auth-box">
<div class="auth-theme">@include('school.partials.language-picker')@include('school.partials.theme-picker')</div>
<div class="auth-emblem"><i class="bi bi-mortarboard-fill" aria-hidden="true"></i></div>
@if($errors->any())<div class="alert alert-danger" role="alert">@foreach($errors->all() as $error)<div>{{ $error }}</div>@endforeach</div>@endif
@yield('content')
</div></section></main></body></html>
