<?php
namespace App\Services;
use App\Models\{User,Field,FieldBlock,Booking,PaymentReview,AuditLog};
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use App\Notifications\BookingUpdate;
final class FieldOperations {
    public function block(User $actor,Field $field,string $start,string $end,string $reason):void {
        DB::transaction(function()use($actor,$field,$start,$end,$reason){
            $field=Field::lockForUpdate()->findOrFail($field->id);
            abort_unless($actor->canManageLocation($field->location),403);
            $from=CarbonImmutable::parse($start,config('meda.timezone'))->utc();
            $to=CarbonImmutable::parse($end,config('meda.timezone'))->utc();
            abort_unless($to->greaterThan($from)&&$to->isFuture()&&strlen(trim($reason))>=5,422,'Use a valid closure period and reason.');
            FieldBlock::create(['field_id'=>$field->id,'starts_at'=>$from,'ends_at'=>$to,'reason'=>$reason,'created_by'=>$actor->id]);
            AuditLog::create(['actor_id'=>$actor->id,'action'=>'CALENDAR_BLOCKED','subject'=>(string)$field->id,'details'=>['from'=>$from,'to'=>$to,'reason'=>$reason]]);
            $this->disrupt($field,$from,$to,$reason);
        },3);
    }
    public function disrupt(Field $field,?CarbonImmutable $from,?CarbonImmutable $to,string $reason):void {
        $bookings=Booking::where('field_id',$field->id)->whereIn('status',['PENDING_PAYMENT','PAID'])->where('end_time','>',now())->when($from,fn($q)=>$q->where('reserved_until','>',$from))->when($to,fn($q)=>$q->where('start_time','<',$to))->lockForUpdate()->get();
        foreach($bookings as $b){
            if($b->status==='PENDING_PAYMENT') app(BookingEngine::class)->release($b,'RELEASED');
            else {
                PaymentReview::firstOrCreate(['provider_ref'=>$b->payment_ref],['booking_id'=>$b->id,'amount'=>$b->amount,'reason'=>'FIELD_CLOSURE']);
                $b->status='DISRUPTED';$b->qr_hash=null;
            }
            $b->closure_reason=$reason;$b->save();
            DB::afterCommit(fn()=>$b->user->notify(new BookingUpdate($b,'Field closure','Your reservation was affected by a field closure. Open the booking for details and contact support about the payment resolution.')));
        }
    }
}
