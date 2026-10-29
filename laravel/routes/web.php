<?php
use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use App\Http\Controllers\{AuthController,FieldController,BookingController,GateController,FaydaController,RecoveryController,MfaController,AccountController};
Route::get('/',[FieldController::class,'index'])->name('home');
Route::view('/terms','policies.terms')->name('terms');
Route::view('/privacy','policies.privacy')->name('privacy');
Route::view('/support','support')->name('support');
Route::get('/login',[AuthController::class,'form'])->middleware('guest')->name('login');
Route::get('/signup',fn()=>view('signup'))->middleware('guest')->name('signup');
Route::post('/login',[AuthController::class,'login'])->middleware(['guest','throttle:login']);
Route::get('/auth/fayda',[FaydaController::class,'start'])->middleware(['guest','throttle:login'])->name('fayda.start');
Route::get('/auth/fayda/callback',[FaydaController::class,'callback'])->middleware(['guest','throttle:login'])->name('fayda.callback');
Route::get('/password/forgot',[RecoveryController::class,'request'])->middleware('guest')->name('password.request');
Route::post('/password/forgot',[RecoveryController::class,'send'])->middleware(['guest','throttle:3,1,forgot:'])->name('password.email');
Route::get('/password/reset/{token}',[RecoveryController::class,'form'])->middleware('guest')->name('password.reset');
Route::post('/password/reset',[RecoveryController::class,'reset'])->middleware(['guest','throttle:5,1,reset:'])->name('password.update');
Route::post('/logout',[AuthController::class,'logout'])->middleware('auth')->name('logout');
Route::get('/mfa',[MfaController::class,'show'])->middleware('auth')->name('mfa.show');
Route::post('/mfa',[MfaController::class,'verify'])->middleware(['auth','throttle:mfa'])->name('mfa.verify');
Route::middleware(['auth','mfa'])->group(function(){
 Route::get('/account',[AccountController::class,'show'])->name('account');
 Route::post('/account',[AccountController::class,'update'])->middleware('throttle:6,1')->name('account.update');
 Route::get('/email/verify',fn()=>redirect()->route('account'))->name('verification.notice');
 Route::get('/email/verify/{id}/{hash}',function(EmailVerificationRequest $r){$r->fulfill();return redirect()->route('account')->with('status','Email verified. You can now book a field.');})->middleware(['signed','throttle:6,1'])->name('verification.verify');
 Route::post('/email/verification-notification',function(Request $r){if(!$r->user()->hasVerifiedEmail()&&$r->user()->email)$r->user()->sendEmailVerificationNotification();return back()->with('status','Verification email requested.');})->middleware('throttle:2,1')->name('verification.send');
});
Route::get('/fields/{field}',[FieldController::class,'show'])->name('fields.show');
Route::get('/fields/{field}/slots',[FieldController::class,'slots'])->middleware('throttle:120,1')->name('fields.slots');
Route::middleware(['auth','staff:USER','throttle:booking'])->group(function(){
 Route::get('/bookings',[BookingController::class,'index'])->name('bookings.index');
 Route::post('/fields/{field}/holds',[BookingController::class,'hold'])->middleware('onboarded')->name('holds.store');
 Route::post('/bookings/{booking}/pay',[BookingController::class,'pay'])->middleware('onboarded')->name('bookings.pay');
 Route::post('/bookings/{booking}/release',[BookingController::class,'release'])->name('bookings.release');
 Route::post('/bookings/{booking}/cancel',[BookingController::class,'cancel'])->name('bookings.cancel');
});
Route::middleware(['auth','mfa'])->group(function(){
 Route::get('/bookings/{booking}',[BookingController::class,'show'])->name('bookings.show');
 Route::get('/bookings/{booking}/qr',[BookingController::class,'qr'])->name('bookings.qr');
 Route::get('/bookings/{booking}/receipt',[BookingController::class,'receipt'])->name('bookings.receipt');
});
Route::middleware(['auth','staff:OPERATOR','mfa'])->group(function(){
 Route::get('/gate',[GateController::class,'index'])->name('gate');
 Route::get('/gate/schedule',[GateController::class,'schedule'])->name('gate.schedule');
 Route::post('/gate/check',[GateController::class,'check'])->middleware('throttle:booking')->name('gate.check');
});
