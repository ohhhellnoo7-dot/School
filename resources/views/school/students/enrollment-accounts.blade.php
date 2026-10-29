@if(auth()->user()?->role === 'administrator' && session('enrollment_accounts'))
<section class="panel p-4 mb-4" aria-label="Enrollment login details">
<h2 class="h5">Student &amp; parent login details</h2>
<p>Save these details now. New passwords are shown only once. Each link fills in the email; a password is still required to sign in.</p>
<div class="row g-3">
@foreach(session('enrollment_accounts') as $account)
<div class="col-md-6"><div class="border rounded p-3">
<h3 class="h6">{{ $account['role'] }} account</h3>
<div>Email: <strong>{{ $account['email'] }}</strong></div>
<div>Password: <strong>{{ $account['password'] ?? 'Existing password (unchanged)' }}</strong></div>
<a href="{{ $account['url'] }}">{{ $account['role'] }} login link</a>
<div class="small text-break">{{ $account['url'] }}</div>
</div></div>
@endforeach
</div></section>
@endif
