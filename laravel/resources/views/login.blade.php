@extends('layouts.app')
@section('title','Sign in')
@section('content')
<section class="auth panel"><p class="eyebrow">WELCOME TO MEDA PASS</p><h1>Welcome back.</h1><p>Sign in to see your bookings and reserve your next game.</p>
@if(config('meda.mock_auth')&&app()->environment(['local','testing']))
<form method="POST" action="/login">@csrf<input type="hidden" name="mode" value="citizen"><label>10-digit test FAN<input name="fan" inputmode="numeric" maxlength="10" minlength="10" required></label><p class="notice">Local test accounts only. Do not enter a real identity number.</p><button class="button full">Open test account</button></form>
@elseif(config('fayda.enabled'))<a class="button full" href="{{ route('fayda.start') }}">Continue with Fayda →</a><p class="muted">Verify with Fayda to securely access your account.</p>
@else<p class="notice">Customer sign-in is not open yet. Please check back before booking.</p>@endif
<p>New here? <a href="{{ route('signup') }}">Create an account</a></p>
<details><summary>Staff sign-in</summary><form method="POST" action="/login">@csrf<input type="hidden" name="mode" value="staff"><label>Username or email<input name="email" autocomplete="username" required maxlength="150"></label><label>Password<input type="password" name="password" autocomplete="current-password" required></label><button class="button full">Sign in as staff</button></form><p><a href="{{ route('password.request') }}">Forgot your staff password?</a></p></details>
</section>@endsection
