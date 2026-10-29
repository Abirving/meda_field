<?php
namespace App\Http\Middleware;
use Closure;
use Illuminate\Http\Request;
use App\Services\Readiness;
class ProductionSafety {
 public function handle(Request $r,Closure $next){
  if(app()->environment('production')&&app(Readiness::class)->issues())return response()->view('errors.launch',[],503)->header('Retry-After','3600');
  return $next($r);
 }
}
