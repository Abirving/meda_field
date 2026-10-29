<?php
namespace App\Services;
use App\Contracts\TelebirrGateway;
use App\Models\{Booking,Field,PaymentAttempt,PaymentReview};
use App\Support\Money;
use Illuminate\Support\Facades\{DB,Cache};
use Illuminate\Support\Str;
final class Payments {
    public function __construct(private TelebirrGateway $gateway,private BookingEngine $engine,private Slots $slots){}
    public function start(Booking $booking):PaymentAttempt {
        abort_unless(config('telebirr.enabled')&&config('telebirr.contract_approved'),503,'Telebirr checkout is not available yet.');
        return Cache::store('redis')->lock('checkout:'.$booking->id,45)->block(5,function()use($booking){
            $new=false;
            $a=DB::transaction(function()use($booking,&$new){
                $field=Field::lockForUpdate()->findOrFail($booking->field_id);$b=Booking::lockForUpdate()->findOrFail($booking->id);
                abort_unless($b->status==='PENDING_PAYMENT'&&$b->expires_at?->greaterThan(now()->addSeconds(30))&&$b->start_time->isFuture()&&!$this->slots->blocked($field,$b->start_time),409,'This hold can no longer enter checkout.');
                if($a=PaymentAttempt::where('booking_id',$b->id)->first())return $a;
                $new=true;
                return PaymentAttempt::create(['id'=>Str::uuid()->toString(),'booking_id'=>$b->id,'merchant_order_id'=>str_replace('-','',Str::uuid()->toString()),'amount'=>$b->telebirr_amount,'currency'=>'ETB','state'=>'REQUESTED','expires_at'=>$b->expires_at]);
            },3);
            // One durable merchant order per booking. Never retry an uncertain external POST.
            if(!$new)return $a;
            try {
                $checkout=$this->gateway->initiate($a);$base=parse_url((string)config('telebirr.checkout_url'))?:[];$actual=parse_url($checkout['url']??'')?:[];
                abort_unless(($actual['scheme']??null)==='https'&&($actual['host']??null)===($base['host']??null)&&($actual['port']??443)===($base['port']??443)&&($actual['path']??'')===($base['path']??'')&&!isset($actual['user'])&&!isset($actual['fragment']),502,'Invalid checkout destination.');
                // A callback may have settled while the checkout request was returning.
                PaymentAttempt::whereKey($a->id)->where('state','REQUESTED')->update(['provider_order_id'=>$checkout['provider_order_id'],'checkout_url'=>$checkout['url'],'state'=>'PENDING']);
            }catch(\Throwable){PaymentAttempt::whereKey($a->id)->where('state','REQUESTED')->update(['state'=>'UNKNOWN','last_error'=>'CHECKOUT_UNCERTAIN']);}
            return $a->fresh();
        });
    }
    public function accept(array $v,string $source):Booking {
        $a=PaymentAttempt::where('merchant_order_id',$v['merchant_order_id'])->firstOrFail();
        abort_unless($v['currency']===$a->currency&&Money::minor($v['amount'])===Money::minor($a->amount),422,'Payment amount mismatch.');
        return DB::transaction(function()use($a,$v,$source){
            Field::lockForUpdate()->findOrFail($a->booking->field_id);
            Booking::lockForUpdate()->findOrFail($a->booking_id);
            $a=PaymentAttempt::lockForUpdate()->findOrFail($a->id);
            $fingerprint=hash('sha256',json_encode([$a->id,$v['reference'],$v['amount'],$v['currency'],$v['outcome']]));
            if(DB::table('payment_receipts')->where('fingerprint',$fingerprint)->exists())return $a->booking;
            DB::table('payment_receipts')->insert(['payment_attempt_id'=>$a->id,'fingerprint'=>$fingerprint,'provider_ref'=>$v['reference'],'outcome'=>$v['outcome'],'amount'=>$v['amount'],'currency'=>$v['currency'],'source'=>$source,'received_at'=>now()]);
            $b=$a->booking;
            if(in_array($v['outcome'],['SUCCESS','FAILED'],true))$b=$this->engine->settle($a->booking_id,$v['reference'],$v['amount'],$v['currency'],$v['outcome']==='SUCCESS');
            if(PaymentReview::where('booking_id',$b->id)->where('status','OPEN')->exists())$a->state='REVIEW_REQUIRED';
            elseif(in_array($v['outcome'],['SUCCESS','FAILED'],true))$a->state=$v['outcome'];
            elseif(!in_array($a->state,['SUCCESS','FAILED','REVIEW_REQUIRED'],true))$a->state='PENDING';
            $a->last_checked_at=now();$a->last_error=null;$a->save();return $b;
        },3);
    }
    public function reconcile(PaymentAttempt $a):void {
        Cache::store('redis')->lock('query:'.$a->id,30)->get(function()use($a){
            $a->increment('query_count');$a->update(['last_checked_at'=>now()]);
            try {$this->accept($this->gateway->query($a),'QUERY');}
            catch(\Throwable){$a->update(['last_error'=>'QUERY_UNAVAILABLE']);}
        });
    }
}
