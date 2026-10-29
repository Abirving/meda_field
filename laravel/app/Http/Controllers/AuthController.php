<?php
namespace App\Http\Controllers;
use App\Contracts\FaydaIdentity;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
class AuthController {
 public function form(Request $request){
  if($request->filled('field')){
   $v=$request->validate(['field'=>'required|integer|exists:fields,id','date'=>'required|date_format:Y-m-d']);
   $request->session()->put('url.intended',route('fields.show',[$v['field'],'date'=>$v['date']]));
  }
  return view('login');
 }
 public function login(Request $request,FaydaIdentity $identity){
  if($request->input('mode')==='staff'){
   $data=$request->validate(['email'=>'required|string|max:150','password'=>'required|string|max:200']);
   $column=filter_var($data['email'],FILTER_VALIDATE_EMAIL)?'email':'username';
   if($column==='email')$data['email']=strtolower(trim($data['email']));
   abort_unless(Auth::attempt([$column=>$data['email'],'password'=>$data['password'],'active'=>true,fn($q)=>$q->where('role','<>','USER')]),422,'Invalid staff credentials.');
  }else{
   abort_unless(config('meda.mock_auth')&&app()->environment(['local','testing']),403,'Use verified Fayda sign-in.');
   $data=$request->validate(['fan'=>'required|string|max:50']);$profile=$identity->verify($data['fan']);
   $user=User::firstOrCreate(['national_id'=>$profile['national_id']],array_merge($profile,['role'=>'USER']));
   abort_unless($user->active&&$user->role==='USER',403,'Staff must use their assigned credentials.');Auth::login($user);
  }
  $request->session()->regenerate();
  if(Auth::user()->role!=='USER'&&config('meda.staff_mfa'))return redirect()->route('mfa.show');
  return redirect()->intended(Auth::user()->role==='OPERATOR'?'/gate':(Auth::user()->role==='USER'?'/':'/admin'));
 }
 public function logout(Request $request){Auth::logout();$request->session()->invalidate();$request->session()->regenerateToken();return redirect('/');}
}
