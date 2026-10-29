<?php
namespace App\Providers;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Gate;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
class AppServiceProvider extends ServiceProvider {
 public function register():void {
  $this->app->bind(\App\Contracts\FaydaIdentity::class,fn()=>config('meda.mock_auth')?new \App\Services\MockFayda:new \App\Services\UnavailableFayda);
  $this->app->bind(\App\Contracts\TelebirrGateway::class,fn()=>config('telebirr.enabled')&&config('telebirr.contract_approved')?new \App\Services\TelebirrH5(new \App\Services\TelebirrSignature):new \App\Services\UnavailableTelebirr);
 }
 public function boot():void {
  \Illuminate\Support\Facades\Event::listen(\Illuminate\Auth\Events\Login::class,function($event){if(request()->hasSession()){request()->session()->put('auth_version',(int)$event->user->session_version);request()->session()->forget('mfa_verified');}});
  foreach(['Field','Location','User','Booking','PaymentEvent','PaymentReview','PaymentAttempt'] as $model)Gate::policy('App\\Models\\'.$model,'App\\Policies\\'.$model.'Policy');
  RateLimiter::for('login',fn(Request $r)=>[Limit::perMinute(10)->by($r->ip()),Limit::perMinute(5)->by(hash('sha256',(string)$r->input('email',$r->input('fan','')).$r->ip()))]);
  RateLimiter::for('booking',fn(Request $r)=>Limit::perMinute(30)->by($r->user()?->id??$r->ip()));
  RateLimiter::for('mfa',fn(Request $r)=>[Limit::perMinute(5)->by('mfa:'.($r->user()?->id??$r->ip())),Limit::perHour(30)->by('mfa-hour:'.($r->user()?->id??$r->ip()))]);
 }
}
