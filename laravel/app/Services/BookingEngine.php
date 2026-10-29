<?php
namespace App\Services;
use App\Models\{Booking,User,Field,Credit,PaymentEvent,AuditLog,PaymentReview};
use App\Support\Money;
use App\Events\SlotChanged;
use App\Notifications\BookingConfirmed;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\{DB,Cache};
use Illuminate\Support\Str;
use Illuminate\Database\QueryException;
final class BookingEngine {
 public function __construct(private Slots $slots){}
 private function audit(?User $user,string $action,Booking $b):void {AuditLog::create(['actor_id'=>$user?->id,'action'=>$action,'subject'=>$b->id]);}
 private function changed(Booking $b):void {DB::afterCommit(fn()=>event(new SlotChanged($b->field_id,$b->start_time->timezone(config('meda.timezone'))->toDateString())));}
 public function token(Booking $b):string {
  $key=$b->qr_key_id?config('meda.qr_keys.'.$b->qr_key_id):config('app.key');
  abort_unless(is_string($key)&&strlen($key)>=32,503,'Pass signing key unavailable.');
  return 'MP:'.$b->id.':'.hash_hmac('sha256',$b->id,$key);
 }
 private function key(Booking $b):string{return 'slot:'.$b->field_id.':'.$b->start_time->getTimestamp();}
 private function unlock(Booking $b):void {if($b->lock_owner){try{Cache::store('redis')->restoreLock($this->key($b),$b->lock_owner)->release();}catch(\Throwable $e){report($e);}}}
 private function wallet(User $user,Booking $b,int $delta,string $reason):void {
  $balance=Money::minor($user->store_credit)+$delta;abort_if($balance<0,409,'Credit is no longer available.');
  Credit::create(['user_id'=>$user->id,'booking_id'=>$b->id,'amount'=>Money::decimal($delta),'reason'=>$reason]);
  $user->store_credit=Money::decimal($balance);$user->save();
 }
 public function hold(User $actor,Field $field,string $date,string $time,string $team,string $key):Booking {
  abort_unless($actor->active&&$actor->role==='USER',403);
  $starts=$this->slots->starts($field,$date);$start=collect($starts)->first(fn($s)=>$s->timezone(config('meda.timezone'))->format('H:i')===$time);
  abort_unless($start&&$this->slots->canStart($start),422,'Choose a play time at least five minutes ahead.');
  if($old=Booking::where('user_id',$actor->id)->where('idempotency_key',$key)->first()){
   abort_unless($old->field_id===$field->id&&$old->start_time->equalTo($start),409,'Request key already used for another slot.');return $old;
  }
  $this->expire($field->id);
  $lock=Cache::store('redis')->lock('slot:'.$field->id.':'.$start->getTimestamp(),300);
  if(!$lock->get()){
   $old=Booking::where('user_id',$actor->id)->where('idempotency_key',$key)->first();
   if($old){abort_unless($old->field_id===$field->id&&$old->start_time->equalTo($start),409,'Request key already used for another slot.');return $old;}
   abort(409,'This slot is on hold. Choose another time.');
  }
  try {
   return DB::transaction(function()use($actor,$field,$start,$team,$key,$lock){
    $field=Field::lockForUpdate()->findOrFail($field->id);abort_if($this->slots->blocked($field,$start),409,'Field is closed for this time.');
    abort_unless(collect($this->slots->starts($field,$start->timezone(config('meda.timezone'))->toDateString()))->contains(fn($s)=>$s->equalTo($start))&&$this->slots->canStart($start),409,'The schedule changed. Refresh available slots.');
    $user=User::lockForUpdate()->findOrFail($actor->id);abort_unless($user->active&&$user->role==='USER',403);
    // Locking the field serializes competing changes; the exclusion constraint also protects direct SQL writes.
    abort_if(Booking::where('user_id',$user->id)->where('status','PENDING_PAYMENT')->count()>=config('meda.max_active_holds',2),429,'Finish or release your existing holds first.');
    $overlap=Booking::where('field_id',$field->id)->whereIn('status',Slots::RESERVED)->where('start_time','<',$start->addMinutes(75))->where('reserved_until','>',$start)->exists();
    abort_if($overlap,409,'This slot or its changeover buffer is reserved.');
    $amount=Money::minor($field->price_per_hour);$credit=min($amount,Money::minor($user->store_credit));
    $b=Booking::create(['id'=>(string)Str::uuid(),'user_id'=>$user->id,'field_id'=>$field->id,'start_time'=>$start,'end_time'=>$start->addMinutes(60),'reserved_until'=>$start->addMinutes(75),'status'=>'PENDING_PAYMENT','team'=>$team,'amount'=>Money::decimal($amount),'credit_applied'=>Money::decimal($credit),'telebirr_amount'=>Money::decimal($amount-$credit),'expires_at'=>now()->addSeconds(300)->min($start->subMinutes(1)),'lock_owner'=>$lock->owner(),'idempotency_key'=>$key]);
    if($credit)$this->wallet($user,$b,-$credit,'RESERVE');
    $this->audit($user,'HOLD_CREATED',$b);$this->changed($b);return $b;
   },3);
  } catch(\Throwable $e){$lock->release();
   if($e instanceof QueryException&&in_array($e->getCode(),['23505','23P01'],true)){
    $old=Booking::where('user_id',$actor->id)->where('idempotency_key',$key)->first();if($old){abort_unless($old->field_id===$field->id&&$old->start_time->equalTo($start),409,'Request key already used for another slot.');return $old;}abort(409,'Another booking took this slot.');
   }throw $e;
  }
 }
 public function release(Booking $booking,string $status='EXPIRED'):Booking {
  abort_unless(in_array($status,['EXPIRED','PAYMENT_FAILED','RELEASED'],true),422);
  $b=DB::transaction(function()use($booking,$status){
   $b=Booking::lockForUpdate()->findOrFail($booking->id);if($b->status!=='PENDING_PAYMENT')return $b;
   $u=User::lockForUpdate()->findOrFail($b->user_id);$credit=Money::minor($b->credit_applied);
   if($credit)$this->wallet($u,$b,$credit,'RELEASE');
   $b->status=$status;$b->save();$this->audit(null,$status,$b);$this->changed($b);return $b;
  },3);if($b->status===$status)$this->unlock($b);return $b;
 }
 public function expire(?int $fieldId=null):int {
  $rows=Booking::where('status','PENDING_PAYMENT')->where('expires_at','<=',now())->when($fieldId,fn($q)=>$q->where('field_id',$fieldId))->limit(500)->get();
  foreach($rows as $b)$this->release($b);return $rows->count();
 }
 /** Call only after verified provider data, or from the explicitly local authenticated mock route. */
 public function settle(string $bookingId,string $reference,string $amount,string $currency,bool $success):Booking {
  $b=DB::transaction(function()use($bookingId,$reference,$amount,$currency,$success){
   $field=Field::lockForUpdate()->findOrFail(Booking::findOrFail($bookingId)->field_id);
   $b=Booking::lockForUpdate()->findOrFail($bookingId);
   if($existing=PaymentEvent::where('provider_ref',$reference)->first()){
    abort_unless($existing->booking_id===$b->id&&Money::minor($existing->amount)===Money::minor($amount)&&$existing->currency===$currency,409,'Payment reference conflict.');
    if(($existing->status==='FAILED'&&$success)||($existing->status==='SUCCESS'&&!$success)) {
     PaymentReview::firstOrCreate(['provider_ref'=>$reference],['booking_id'=>$b->id,'amount'=>$amount,'reason'=>'CONTRADICTORY_PROVIDER_RESULT']);
     $existing->status='REVIEW_REQUIRED';$existing->save();
     if($b->status==='PAID'){$b->status='DISRUPTED';$b->qr_hash=null;$b->save();$this->changed($b);}
    }
    return $b;
   }
   abort_unless($currency==='ETB'&&Money::minor($amount)===Money::minor($b->telebirr_amount),422,'Payment amount or currency does not match.');
   // A provider can use a different reference for a contradictory order result.
   // Keep the review attached to the original charge so reserved credit is returned once.
   if(!$success&&$b->payment_ref){
    PaymentEvent::create(['provider_ref'=>$reference,'booking_id'=>$b->id,'amount'=>$amount,'currency'=>$currency,'status'=>'REVIEW_REQUIRED']);
    PaymentReview::firstOrCreate(['provider_ref'=>$b->payment_ref],['booking_id'=>$b->id,'amount'=>$amount,'reason'=>'CONTRADICTORY_PROVIDER_RESULT']);
    if($b->status==='PAID'){$b->status='DISRUPTED';$b->qr_hash=null;$b->save();$this->changed($b);}
    return $b;
   }
   if($b->status!=='PENDING_PAYMENT'||!$b->expires_at?->isFuture()||!$b->start_time->isFuture()||$this->slots->blocked($field,$b->start_time)){
    PaymentEvent::create(['provider_ref'=>$reference,'booking_id'=>$b->id,'amount'=>$amount,'currency'=>$currency,'status'=>$success?'REVIEW_REQUIRED':'FAILED']);
    if($success)PaymentReview::firstOrCreate(['provider_ref'=>$reference],['booking_id'=>$b->id,'amount'=>$amount,'reason'=>'PAYMENT_WITHOUT_USABLE_RESERVATION']);
    if($b->status==='PENDING_PAYMENT')return $this->release($b);
    return $b; // Late/duplicate charges never resurrect a cancelled reservation or create a pass.
   }
   PaymentEvent::create(['provider_ref'=>$reference,'booking_id'=>$b->id,'amount'=>$amount,'currency'=>$currency,'status'=>$success?'SUCCESS':'FAILED']);
   if(!$success)return $this->release($b,'PAYMENT_FAILED');
   $b->status='PAID';$b->payment_ref=$reference;$b->qr_key_id=config('meda.qr_active_key');$b->qr_hash=hash('sha256',$this->token($b));$b->expires_at=null;$b->save();
   $this->audit(null,'PAYMENT_CONFIRMED',$b);$this->changed($b);
   DB::afterCommit(fn()=>$b->user->notify(new BookingConfirmed($b)));
   return $b;
  },3);if($b->status!=='PENDING_PAYMENT')$this->unlock($b);return $b;
 }
 public function cancel(User $actor,Booking $booking):Booking {
  return DB::transaction(function()use($actor,$booking){
   $b=Booking::lockForUpdate()->findOrFail($booking->id);abort_unless($actor->id===$b->user_id&&$actor->active,403);
   if($b->status==='CANCELLED')return $b;
   abort_unless($b->status==='PAID',409,'Only a paid, unused pass can be cancelled.');
   abort_unless(now()->addHours(2)->lessThan($b->start_time),422,'Cancellation requires more than 2 hours before play.');
   $user=User::lockForUpdate()->findOrFail($actor->id);$this->wallet($user,$b,Money::minor($b->amount),'CANCELLATION');
   $b->status='CANCELLED';$b->qr_hash=null;$b->save();$this->audit($actor,'CANCELLED_FOR_CREDIT',$b);$this->changed($b);return $b;
  },3);
 }
 public function checkIn(User $actor,int $fieldId,string $token):Booking {
  abort_unless($actor->active&&$actor->role==='OPERATOR',403);
  return DB::transaction(function()use($actor,$fieldId,$token){
   $field=Field::visibleTo($actor)->lockForUpdate()->findOrFail($fieldId);
   $b=Booking::where('qr_hash',hash('sha256',$token))->lockForUpdate()->firstOrFail();
   abort_unless($b->field_id===$field->id,422,'Wrong field.');
   abort_if($this->slots->blocked($field,$b->start_time),409,'Field closed. Contact the duty supervisor.');
   abort_unless($b->status==='PAID'&&$b->payment_ref,409,'This pass is unpaid, cancelled, or already used.');
   abort_unless(now()->greaterThanOrEqualTo($b->start_time)&&now()->lessThan($b->end_time),422,'Pass is valid only during the booked play hour.');
   $b->status='CHECKED_IN';$b->checked_in_at=now();$b->save();$this->audit($actor,'CHECKED_IN',$b);return $b;
  },3);
 }
}
