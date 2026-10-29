<?php
namespace App\Http\Controllers;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Password,Hash};
use Illuminate\Support\Str;
use Illuminate\Auth\Events\PasswordReset;
class RecoveryController {
    public function request(){return view('auth.forgot-password');}
    public function send(Request $r){
        $r->validate(['email'=>'required|email|max:150']);
        $user=User::where('email',strtolower($r->string('email')->toString()))->where('active',true)->where('role','<>','USER')->first();
        if($user)Password::sendResetLink(['email'=>$user->email]);
        return back()->with('status','If an active staff account matches, a recovery link has been sent. Customers sign in again through Fayda.');
    }
    public function form(Request $r,string $token){return view('auth.reset-password',['token'=>$token,'email'=>$r->query('email')]);}
    public function reset(Request $r){
        $v=$r->validate(['token'=>'required','email'=>'required|email','password'=>['required','confirmed',\Illuminate\Validation\Rules\Password::min(12)]]);
        $status=Password::reset($v,function(User $u,string $password){abort_unless($u->active&&$u->role!=='USER',422,'Invalid recovery request.');$u->forceFill(['password'=>Hash::make($password),'remember_token'=>Str::random(60),'email_verified_at'=>now()])->save();event(new PasswordReset($u));});
        return $status===Password::PASSWORD_RESET?redirect()->route('login')->with('status','Password reset. Sign in and complete two-factor verification.'):back()->withErrors(['email'=>__($status)]);
    }
}
