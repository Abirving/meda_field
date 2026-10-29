<?php
namespace App\Http\Middleware;
use Closure;
use Illuminate\Http\Request;
class Onboarded {
    public function handle(Request $r,Closure $next){
        if((!$r->user()->hasVerifiedEmail()||$r->user()->terms_version!==config('meda.terms_version')))return redirect()->route('account')->with('status','Complete your profile and verify your email before booking.');
        return $next($r);
    }
}
