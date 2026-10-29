<?php
namespace App\Http\Middleware;
use Closure;
use Illuminate\Http\Request;
class RequireMfa {
    public function handle(Request $r,Closure $next){
        if($r->user()&&$r->user()->role!=='USER'&&config('meda.staff_mfa')&&!$r->session()->get('mfa_verified'))return redirect()->route('mfa.show');
        return $next($r);
    }
}
