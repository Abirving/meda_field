<?php
namespace App\Http\Middleware;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
class SessionGuard {
    public function handle(Request $r,Closure $next){
        if($u=$r->user()){
            if(!$u->active||(int)$r->session()->get('auth_version',0)!==(int)$u->session_version){
                Auth::logout();$r->session()->invalidate();$r->session()->regenerateToken();
                return redirect()->route('login')->with('status','Please sign in again. Your account access changed.');
            }
        }
        $response=$next($r);
        $response->headers->set('X-Content-Type-Options','nosniff');
        $response->headers->set('Referrer-Policy','same-origin');
        $response->headers->set('X-Frame-Options','SAMEORIGIN');
        $response->headers->set('Permissions-Policy','camera=(self), microphone=(), geolocation=()');
        if($r->isSecure())$response->headers->set('Strict-Transport-Security','max-age=31536000');
        if($r->user()||$r->is('login','auth/*','mfa*','password/*'))$response->headers->set('Cache-Control','private, no-store');
        return $response;
    }
}
