<?php
namespace Tests\Feature;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{DB,Event,Notification,Cache,Hash};
use Illuminate\Support\Str;
use App\Models\{User,Field,Location,Booking,PaymentAttempt,PaymentReview,Credit};
use App\Services\{BookingEngine,Slots,Payments,PaymentResolution,FieldOperations};
use App\Contracts\TelebirrGateway;
use App\Support\Totp;
use Symfony\Component\HttpKernel\Exception\HttpException;
class ProductionReadinessTest extends TestCase {
 use RefreshDatabase;
 private User $u; private Field $field; private BookingEngine $engine;
 protected function setUp():void {
  parent::setUp(); Event::fake([\App\Events\SlotChanged::class]); Notification::fake();
  config(['meda.staff_mfa'=>false]);$this->travelTo(\Carbon\CarbonImmutable::parse('2030-10-27T07:00:00+03:00'));
  $l=Location::create(['region'=>'Addis Ababa','subcity'=>'Bole','woreda'=>'03']);
  $this->u=User::create(['first_name'=>'Player','email'=>'player@example.test','email_verified_at'=>now(),'terms_version'=>config('meda.terms_version'),'role'=>'USER']);
  $this->field=Field::create(['location_id'=>$l->id,'name'=>'Real field','price_per_hour'=>'800.00','operating_hours'=>['start'=>'08:00','end'=>'22:00']]);$this->engine=app(BookingEngine::class);
 }
 protected function tearDown():void {foreach(Booking::all()as$b)if($b->lock_owner)Cache::store('redis')->restoreLock('slot:'.$b->field_id.':'.$b->start_time->getTimestamp(),$b->lock_owner)->release();parent::tearDown();}
 private function hold(string $time='08:00'):Booking{return $this->engine->hold($this->u,$this->field,'2030-10-28',$time,'Players',(string)Str::uuid());}
 private function paid():Booking{$b=$this->hold();return $this->engine->settle($b->id,'P'.Str::uuid(),$b->telebirr_amount,'ETB',true);}
 private function admin():User{return User::create(['first_name'=>'Admin','email'=>'admin@example.test','role'=>'SUPER_ADMIN','password'=>'a-long-test-password']);}
 private function login(User $u):self{$this->actingAs($u);$this->withSession(['auth_version'=>$u->session_version]);return $this;}
 public function test_availability_checks_overlap_when_grid_moves():void {
  $this->hold();DB::table('fields')->where('id',$this->field->id)->update(['operating_hours'=>json_encode(['start'=>'08:30','end'=>'22:00'])]);
  $slots=app(Slots::class)->availability($this->field->fresh(),'2030-10-28');$this->assertSame('hold',$slots[0]['status']);
 }
 public function test_calendar_edits_are_blocked_with_future_reservations():void {$this->hold();$this->expectException(HttpException::class);$this->field->update(['operating_hours'=>['start'=>'08:30','end'=>'22:00']]);}
 public function test_field_closure_releases_pending_credit_and_late_money_enters_review():void {
  $this->u->store_credit='300.00';$this->u->save();$b=$this->hold();$this->field->status='MAINTENANCE';$this->field->save();
  $this->assertSame('RELEASED',$b->fresh()->status);$this->assertSame('300.00',$this->u->fresh()->store_credit);
  $this->engine->settle($b->id,'late-closed','500.00','ETB',true);$this->assertNull($b->fresh()->qr_hash);$this->assertDatabaseHas('payment_reviews',['provider_ref'=>'late-closed','status'=>'OPEN']);
 }
 public function test_scheduled_closure_disrupts_paid_pass_and_resolves_credit_once():void {
  $b=$this->paid();$a=$this->admin();app(FieldOperations::class)->block($a,$this->field,'2030-10-28 07:00','2030-10-28 10:00','Maintenance repairs');
  $this->assertSame('DISRUPTED',$b->fresh()->status);$this->assertNull($b->fresh()->qr_hash);
  $r=PaymentReview::firstOrFail();$svc=app(PaymentResolution::class);$svc->resolve($a,$r,'STORE_CREDIT','CASE-123','Provider charge verified and customer accepted store credit.');$svc->resolve($a,$r,'STORE_CREDIT','CASE-123','Provider charge verified and customer accepted store credit.');
  $this->assertSame('800.00',$this->u->fresh()->store_credit);$this->assertSame(1,Credit::where('reason','REVIEW_'.$r->id)->count());
 }
 public function test_failed_then_successful_reference_is_reviewed_without_resurrecting_pass():void {$b=$this->hold();$this->engine->settle($b->id,'changed','800.00','ETB',false);$this->engine->settle($b->id,'changed','800.00','ETB',true);$this->assertSame('PAYMENT_FAILED',$b->fresh()->status);$this->assertDatabaseHas('payment_reviews',['reason'=>'CONTRADICTORY_PROVIDER_RESULT']);}
 public function test_success_then_failure_quarantines_pass():void {$b=$this->paid();$this->engine->settle($b->id,$b->payment_ref,'800.00','ETB',false);$this->assertSame('DISRUPTED',$b->fresh()->status);$this->assertNull($b->fresh()->qr_hash);}
 public function test_customer_cannot_hoard_more_than_two_holds():void {$this->hold();$this->hold('09:15');try{$this->hold('10:30');$this->fail();}catch(HttpException $e){$this->assertSame(429,$e->getStatusCode());}}
 public function test_final_buffer_fits_before_closing():void {$this->field->operating_hours=['start'=>'08:00','end'=>'09:00'];$this->field->save();$this->assertSame([],app(Slots::class)->starts($this->field,'2030-10-28'));}
 public function test_weekly_closed_day_has_no_slots():void {$this->field->weekly_hours=['1'=>['start'=>'08:00','end'=>'22:00']];$this->field->save();$this->assertSame([],app(Slots::class)->starts($this->field,'2030-10-29'));}
 public function test_repeated_checkout_reuses_one_durable_order():void {
  config(['telebirr.enabled'=>true,'telebirr.contract_approved'=>true,'telebirr.checkout_url'=>'https://pay.example.test/checkout']);$b=$this->hold();
  $g=\Mockery::mock(TelebirrGateway::class);$g->shouldReceive('initiate')->once()->andReturn(['provider_order_id'=>'prepay-1','url'=>'https://pay.example.test/checkout?order=1']);$this->app->instance(TelebirrGateway::class,$g);
  $svc=app(Payments::class);$first=$svc->start($b);$second=$svc->start($b);$this->assertSame($first->id,$second->id);$this->assertSame(1,PaymentAttempt::count());$this->assertSame('PENDING',$second->state);
 }
 public function test_uncertain_checkout_is_not_reissued():void {
  config(['telebirr.enabled'=>true,'telebirr.contract_approved'=>true]);$b=$this->hold();$g=\Mockery::mock(TelebirrGateway::class);$g->shouldReceive('initiate')->once()->andThrow(new \RuntimeException('Connection interrupted'));$this->app->instance(TelebirrGateway::class,$g);
  $svc=app(Payments::class);$this->assertSame('UNKNOWN',$svc->start($b)->state);$this->assertSame('UNKNOWN',$svc->start($b)->state);
 }
 public function test_receipt_replay_has_one_financial_effect():void {
  $b=$this->hold();$a=PaymentAttempt::create(['id'=>Str::uuid(),'booking_id'=>$b->id,'merchant_order_id'=>'uniqueorder1','amount'=>'800.00','currency'=>'ETB','expires_at'=>$b->expires_at]);
  $v=['merchant_order_id'=>'uniqueorder1','reference'=>'verified-payment','amount'=>'800.00','currency'=>'ETB','outcome'=>'SUCCESS'];$svc=app(Payments::class);$svc->accept($v,'CALLBACK');$svc->accept($v,'CALLBACK');$this->assertSame('PAID',$b->fresh()->status);$this->assertSame(1,DB::table('payment_receipts')->count());
 }
 public function test_old_session_is_revoked_when_account_disabled():void {$u=$this->u;$this->login($u);DB::table('users')->where('id',$u->id)->update(['active'=>false,'session_version'=>2]);$this->actingAs($u->fresh());$this->get('/account')->assertRedirect('/login');$this->assertGuest();}
 public function test_customer_profile_cannot_promote_role():void {$this->login($this->u)->post('/account',['first_name'=>'Renamed','last_name'=>'Player','email'=>'player@example.test','language'=>'en','terms'=>1,'role'=>'SUPER_ADMIN'])->assertRedirect();$this->assertSame('USER',$this->u->fresh()->role);}
 public function test_unverified_contact_cannot_book():void {$this->u->email_verified_at=null;$this->u->save();$this->login($this->u)->post('/fields/'.$this->field->id.'/holds',['date'=>'2030-10-28','time'=>'08:00','team'=>'Players','idempotency_key'=>Str::uuid()])->assertRedirect('/account');$this->assertSame(0,Booking::count());}
 public function test_last_administrator_cannot_be_disabled():void {$admin=$this->admin();$this->expectException(HttpException::class);$admin->active=false;$admin->save();}
 public function test_staff_is_challenged_for_second_factor():void {config(['meda.staff_mfa'=>true]);$a=$this->admin();$this->login($a)->get('/admin')->assertRedirect('/mfa');}
 public function test_mfa_enrollment_and_replay_protection():void {
  config(['meda.staff_mfa'=>true]);$a=$this->admin();$secret=app(Totp::class)->secret();$code=app(Totp::class)->code($secret,intdiv(time(),30));
  $this->login($a)->withSession(['mfa_setup'=>$secret])->post('/mfa',['code'=>$code])->assertOk()->assertSee('Save your recovery codes');
  $this->assertNotNull($a->fresh()->totp_secret);$this->post('/mfa',['code'=>$code])->assertStatus(422);
 }
 public function test_unapproved_live_fan_entry_is_rejected():void {config(['meda.mock_auth'=>false]);$this->post('/login',['fan'=>'1234567890'])->assertForbidden();$this->assertGuest();}
 public function test_customer_cannot_view_another_payment_record():void {$b=$this->paid();$other=User::create(['first_name'=>'Other']);$this->login($other)->get('/bookings/'.$b->id.'/receipt')->assertForbidden();}
 public function test_order_failure_with_another_reference_quarantines_paid_pass():void {
  $b=$this->paid();$this->engine->settle($b->id,'different-failed-ref','800.00','ETB',false);
  $this->assertSame('DISRUPTED',$b->fresh()->status);$this->assertNull($b->fresh()->qr_hash);
  $this->assertDatabaseHas('payment_reviews',['provider_ref'=>$b->payment_ref,'reason'=>'CONTRADICTORY_PROVIDER_RESULT']);
 }
 public function test_guest_sign_in_preserves_selected_field_and_date():void {
  $url=route('fields.show',[$this->field->id,'date'=>'2030-10-28']);
  $this->get('/login?field='.$this->field->id.'&date=2030-10-28')->assertOk();
  $this->post('/login',['fan'=>'9999999998'])->assertRedirect($url);
 }
 public function test_email_change_removes_verification_and_cannot_change_wallet():void {
  $this->login($this->u)->post('/account',['first_name'=>'Player','email'=>'CHANGED@example.test','language'=>'en','terms'=>1,'store_credit'=>'99999.00'])->assertRedirect();
  $this->assertNull($this->u->fresh()->email_verified_at);$this->assertSame('changed@example.test',$this->u->fresh()->email);$this->assertSame('0.00',$this->u->fresh()->store_credit);
  Notification::assertSentTo($this->u,\Illuminate\Auth\Notifications\VerifyEmail::class);
 }
 public function test_staff_recovery_token_resets_password_once_and_revokes_sessions():void {
  $admin=$this->admin();$version=$admin->session_version;$token=\Illuminate\Support\Facades\Password::createToken($admin);
  $data=['email'=>$admin->email,'token'=>$token,'password'=>'changed-secure-password','password_confirmation'=>'changed-secure-password'];
  $this->post('/password/reset',$data)->assertRedirect('/login');
  $this->assertTrue(Hash::check('changed-secure-password',$admin->fresh()->password));
  $this->assertGreaterThan($version,$admin->fresh()->session_version);$this->assertNotNull($admin->fresh()->email_verified_at);
  $this->post('/password/reset',$data)->assertSessionHasErrors('email');
 }
 public function test_recovery_message_does_not_disclose_accounts():void {
  $admin=$this->admin();$this->post('/password/forgot',['email'=>$admin->email])->assertRedirect()->assertSessionHas('status');$message=session('status');
  $this->post('/password/forgot',['email'=>'missing@example.test'])->assertRedirect()->assertSessionHas('status',$message);
  Notification::assertSentTo($admin,\Illuminate\Auth\Notifications\ResetPassword::class);
 }
 public function test_admin_pages_and_customer_views_render():void {
  $this->get('/')->assertOk();$this->get('/fields/'.$this->field->id.'?date=2030-10-28')->assertOk()->assertSee('Sign in to book');
  $this->login($this->u)->get('/account')->assertOk();$b=$this->paid();$this->get('/bookings/'.$b->id)->assertOk();
  $this->get('/bookings/'.$b->id.'/receipt')->assertOk();$this->get('/bookings/'.$b->id.'/qr')->assertOk();
  auth()->logout();$this->login($this->admin());
  foreach(['/admin','/admin/fields','/admin/users','/admin/payment-reviews','/admin/payment-events','/admin/payment-attempts']as$url)$this->get($url)->assertOk();
 }
 public function test_admin_can_edit_weekly_hours_and_suspend_customer_without_promoting_them():void {
  $this->field->weekly_hours=['1'=>['start'=>'09:00','end'=>'18:00']];$this->field->save();
  $this->login($this->admin());\Filament\Facades\Filament::setCurrentPanel(\Filament\Facades\Filament::getPanel('admin'));
  \Livewire\Livewire::test(\App\Filament\Resources\FieldResource\Pages\ManageFields::class)->mountTableAction('edit',$this->field)->assertHasNoTableActionErrors();
  \Livewire\Livewire::test(\App\Filament\Resources\UserResource\Pages\ManageUsers::class)->callTableAction('edit',$this->u,['first_name'=>'Player','last_name'=>'','email'=>$this->u->email,'active'=>false])->assertHasNoTableActionErrors();
  $this->assertFalse($this->u->fresh()->active);$this->assertSame('USER',$this->u->fresh()->role);
 }
 public function test_production_refuses_incomplete_configuration():void {
  $this->app->detectEnvironment(fn()=>'production');
  $this->get('/')->assertStatus(503);$this->postJson('/api/payments/telebirr/callback',[])->assertStatus(503);
 }
}
