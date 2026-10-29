@extends('layouts.app')
@section('title','Staff verification')
@section('content')<section class="auth panel"><h1>Protect your staff account.</h1>
@if($codes)<h2>Save your recovery codes</h2><p>Each code works once if you lose your authenticator. Keep them somewhere private. They will not be shown again.</p><ul>@foreach($codes as $code)<li><code>{{ $code }}</code></li>@endforeach</ul><a class="button full" href="{{ auth()->user()->role==='OPERATOR'?route('gate'):'/admin' }}">Continue to workspace</a>
@else
@if($setup)<p>Add an account to your authenticator app using this setup key. Choose time based, six-digit codes.</p><p class="notice break"><code>{{ $secret }}</code></p><p>Account: {{ auth()->user()->email }} · Meda Pass</p>@else<p>Enter the current code from your authenticator, or one unused recovery code.</p>@endif
<form method="POST" action="{{ route('mfa.verify') }}">@csrf<label>Verification code<input name="code" required autocomplete="one-time-code" maxlength="100" autofocus></label><button class="button full">Verify and continue</button></form>@endif</section>@endsection
