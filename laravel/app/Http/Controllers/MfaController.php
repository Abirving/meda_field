<?php
namespace App\Http\Controllers;
use App\Models\User;
use App\Support\Totp;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{DB,Hash};
class MfaController {
    public function show(Request $r,Totp $totp){
        abort_if($r->user()->role==='USER',403);
        if(!$r->user()->totp_secret&&!$r->session()->has('mfa_setup'))$r->session()->put('mfa_setup',$totp->secret());
        return view('auth.mfa',['setup'=>!$r->user()->totp_secret,'secret'=>$r->session()->get('mfa_setup'),'codes'=>[]]);
    }
    public function verify(Request $r,Totp $totp){
        abort_if($r->user()->role==='USER',403);$v=$r->validate(['code'=>'required|string|max:100']);$codes=[];
        DB::transaction(function()use($r,$v,$totp,&$codes){
            $user=User::lockForUpdate()->findOrFail($r->user()->id);$setup=!$user->totp_secret;
            $secret=$setup?$r->session()->get('mfa_setup'):$user->totp_secret;abort_unless($secret,419);
            $step=$totp->match($secret,$v['code'],$user->totp_last_step);$recovery=false;
            if(!$setup&&$step===null){
                $hashes=$user->recovery_codes??[];
                foreach($hashes as $i=>$hash)if(Hash::check($v['code'],$hash)){unset($hashes[$i]);$recovery=true;break;}
                if($recovery)$user->recovery_codes=array_values($hashes);
            }
            abort_unless($step!==null||$recovery,422,'Invalid or already used verification code.');
            if($setup){$user->totp_secret=$secret;for($i=0;$i<10;$i++)$codes[]=bin2hex(random_bytes(8));$user->recovery_codes=array_map(fn($code)=>Hash::make($code),$codes);}
            if($step!==null)$user->totp_last_step=$step;$user->save();
        });
        $r->session()->forget('mfa_setup');$r->session()->regenerate();$r->session()->put('mfa_verified',true);
        if($codes)return response()->view('auth.mfa',['setup'=>false,'secret'=>null,'codes'=>$codes])->header('Cache-Control','no-store');
        return redirect()->intended($r->user()->role==='OPERATOR'?'/gate':'/admin');
    }
}
