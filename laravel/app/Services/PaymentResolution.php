<?php
namespace App\Services;
use App\Models\{User,Booking,PaymentReview,Credit,AuditLog};
use App\Support\Money;
use App\Notifications\BookingUpdate;
use Illuminate\Support\Facades\DB;
final class PaymentResolution {
    public function resolve(User $actor,PaymentReview $review,string $action,string $evidence,string $notes):void {
        abort_unless($actor->active&&$actor->role==='SUPER_ADMIN',403);
        abort_unless(in_array($action,['STORE_CREDIT','EXTERNAL_REFUND','NO_CHARGE'],true)&&strlen(trim($evidence))>=5&&strlen(trim($notes))>=20,422,'Record the verified provider evidence and resolution notes.');
        DB::transaction(function()use($actor,$review,$action,$evidence,$notes){
            // Use the same lock order as settlement: booking, user, then review.
            $b=Booking::lockForUpdate()->findOrFail($review->booking_id);
            $u=User::lockForUpdate()->findOrFail($b->user_id);
            $review=PaymentReview::lockForUpdate()->findOrFail($review->id);
            if($review->status==='RESOLVED')return;
            $original=$b->status==='DISRUPTED'&&$b->payment_ref===$review->provider_ref;
            $reserved=$original?Money::minor($b->credit_applied):0;
            $delta=$action==='STORE_CREDIT'?($review->reason==='FIELD_CLOSURE'?Money::minor($b->amount):Money::minor($review->amount)+$reserved):$reserved;
            if($delta){Credit::create(['user_id'=>$u->id,'booking_id'=>$b->id,'amount'=>Money::decimal($delta),'reason'=>'REVIEW_'.$review->id]);$u->store_credit=Money::decimal(Money::minor($u->store_credit)+$delta);$u->save();}
            if($original){$b->status='CANCELLED_BY_VENUE';$b->qr_hash=null;$b->save();}
            $review->fill(['status'=>'RESOLVED','resolved_by'=>$actor->id,'resolution'=>$action.': '.$notes,'evidence_reference'=>$evidence,'resolved_at'=>now()])->save();
            AuditLog::create(['actor_id'=>$actor->id,'action'=>'PAYMENT_REVIEW_RESOLVED','subject'=>(string)$review->id,'details'=>['action'=>$action,'credit'=>Money::decimal($delta),'evidence'=>$evidence]]);
            DB::afterCommit(fn()=>$u->notify(new BookingUpdate($b,'Payment review completed','Your payment review has been completed. Resolution: '.str_replace('_',' ',strtolower($action)).'. Credit added: ETB '.Money::decimal($delta).'. Contact support for details.')));
        },3);
    }
}
