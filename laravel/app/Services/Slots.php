<?php
namespace App\Services;
use App\Models\{Field, Booking, FieldBlock};
use Carbon\CarbonImmutable;
final class Slots {
    public const RESERVED=['PENDING_PAYMENT','PAID','CHECKED_IN','COMPLETED','NO_SHOW'];
    public function starts(Field $field,string $date):array {
        $zone=config('meda.timezone'); $today=CarbonImmutable::now($zone)->startOfDay();
        try {$day=CarbonImmutable::createFromFormat('!Y-m-d',$date,$zone);} catch(\Throwable){abort(422,'Invalid date.');}
        abort_unless($day && $day->format('Y-m-d')===$date && $day->betweenIncluded($today,$today->addDays(config('meda.advance_days'))),422,'Choose a date inside the booking window.');
        $hours=$field->weekly_hours ? ($field->weekly_hours[$day->dayOfWeekIso]??null) : $field->operating_hours;
        if(!$hours) return [];
        $open=CarbonImmutable::parse($date.' '.$hours['start'],$zone); $close=CarbonImmutable::parse($date.' '.$hours['end'],$zone); $result=[];
        // The final changeover must fit within opening hours as well.
        for($start=$open;$start->addMinutes(75)->lessThanOrEqualTo($close);$start=$start->addMinutes(75)) $result[]=$start->utc();
        return $result;
    }
    public function canStart(CarbonImmutable $start):bool {return $start->greaterThan(now()->addMinutes(config('meda.minimum_notice_minutes',5)));}
    public function blocked(Field $field,CarbonImmutable $start):bool {
        return $field->status!=='ACTIVE' || FieldBlock::where('field_id',$field->id)->where('starts_at','<',$start->addMinutes(75))->where('ends_at','>',$start)->exists();
    }
    public function availability(Field $field,string $date):array {
        $starts=$this->starts($field,$date); if(!$starts) return [];
        $bookings=Booking::where('field_id',$field->id)->whereIn('status',self::RESERVED)->where('start_time','<',end($starts)->addMinutes(75))->where('reserved_until','>',$starts[0])->get();
        $blocks=FieldBlock::where('field_id',$field->id)->where('starts_at','<',end($starts)->addMinutes(75))->where('ends_at','>',$starts[0])->get();
        return array_map(function($start)use($field,$bookings,$blocks){
            $b=$bookings->first(fn($b)=>$b->start_time->lessThan($start->addMinutes(75)) && $b->reserved_until->greaterThan($start) && ($b->status!=='PENDING_PAYMENT'||$b->expires_at?->isFuture()));
            $blocked=$field->status!=='ACTIVE'||$blocks->contains(fn($v)=>$v->starts_at->lessThan($start->addMinutes(75))&&$v->ends_at->greaterThan($start));
            return ['start'=>$start,'status'=>!$this->canStart($start)?'past':($blocked?'maintenance':($b?($b->status==='PENDING_PAYMENT'?'hold':'booked'):'available'))];
        },$starts);
    }
}
