<?php
namespace App\Http\Controllers;
use App\Models\{Credit,Booking};
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
class AccountController {
    public function show(Request $r){return view('account',['user'=>$r->user(),'credits'=>Credit::where('user_id',$r->user()->id)->latest()->paginate(20),'reserved'=>Booking::where('user_id',$r->user()->id)->where('status','PENDING_PAYMENT')->sum('credit_applied')]);}
    public function update(Request $r){
        if(is_string($r->input('email')))$r->merge(['email'=>strtolower(trim($r->input('email')))]);
        $u=$r->user();$v=$r->validate(['first_name'=>'required|string|max:100','last_name'=>'nullable|string|max:100','email'=>['required','email','max:150',Rule::unique('users')->ignore($u->id)],'language'=>'required|in:en,am','terms'=>'accepted']);
        if($u->email!==strtolower($v['email']))$u->email_verified_at=null;
        $u->first_name=$v['first_name'];$u->last_name=$v['last_name']??'';$u->email=strtolower($v['email']);$u->language=$v['language'];$u->terms_accepted_at=now();$u->terms_version=config('meda.terms_version');$u->save();
        if(!$u->hasVerifiedEmail())$u->sendEmailVerificationNotification();
        return back()->with('status',$u->hasVerifiedEmail()?'Profile saved.':'Profile saved. Check your email to verify your contact address.');
    }
}
