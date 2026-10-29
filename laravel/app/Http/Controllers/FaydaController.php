<?php
namespace App\Http\Controllers;
use App\Models\{User,VerifiedIdentity,AuditLog};
use App\Services\FaydaOidc;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{DB,Auth};
class FaydaController {
    public function start(Request $r,FaydaOidc $oidc){return redirect()->away($oidc->redirect($r));}
    public function callback(Request $r,FaydaOidc $oidc){
        $claims=$oidc->verify($r);$hash=hash_hmac('sha256',$claims['iss']."\0".$claims['sub'],(string)config('fayda.subject_pepper'));
        $u=DB::transaction(function()use($claims,$hash){
            DB::select('SELECT pg_advisory_xact_lock(?)',[hexdec(substr($hash,0,7))]);
            if($id=VerifiedIdentity::where('provider','fayda')->where('subject_hash',$hash)->first())return User::findOrFail($id->user_id);
            $u=User::create(['first_name'=>mb_substr((string)($claims['given_name']??'Player'),0,100),'last_name'=>mb_substr((string)($claims['family_name']??''),0,100),'role'=>'USER','active'=>true]);
            VerifiedIdentity::create(['user_id'=>$u->id,'provider'=>'fayda','subject_hash'=>$hash]);
            AuditLog::create(['actor_id'=>$u->id,'action'=>'VERIFIED_ACCOUNT_CREATED','subject'=>(string)$u->id]);return $u;
        });
        abort_unless($u->active&&$u->role==='USER',403,'Account unavailable. Contact support.');
        Auth::login($u);$r->session()->regenerate();
        return $u->hasVerifiedEmail()&&$u->terms_version===config('meda.terms_version')?redirect()->intended('/'):redirect()->route('account');
    }
}
