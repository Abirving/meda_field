<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
class Field extends Model {
 protected $attributes=['status'=>'ACTIVE','type'=>'FOOTBALL'];
 protected $guarded=['id'];
 protected function casts():array{return ['weekly_hours'=>'array','operating_hours'=>'array','price_per_hour'=>'decimal:2'];}
 public function location(){return $this->belongsTo(Location::class);}
 public function operator(){return $this->belongsTo(User::class,'operator_user_id');}
 public function scopeVisibleTo(Builder $q,User $u):Builder {
  if($u->role==='SUPER_ADMIN')return $q;
  if($u->role==='OPERATOR')return $q->where('operator_user_id',$u->id);
  if(in_array($u->role,['WOREDA_ADMIN','SUBCITY_ADMIN']))return $q->whereIn('location_id',Location::visibleTo($u)->select('id'));
  return $q->whereRaw('1=0');
 }
 public ?string $closureReason=null;
 public function save(array $options=[]){return \Illuminate\Support\Facades\DB::transaction(fn()=>parent::save($options));}
 protected static function booted():void {static::saving(function(Field $field){
  if($field->exists){
   $old=static::lockForUpdate()->findOrFail($field->id);
   if(auth()->check())abort_unless(auth()->user()->canManageLocation($old->location),403);
   if($field->isDirty(['operating_hours','weekly_hours','location_id']) && Booking::where('field_id',$field->id)->where('reserved_until','>',now())->whereIn('status',\App\Services\Slots::RESERVED)->exists()) abort(422,'Resolve future reservations before changing the calendar or location.');
  }
  if(auth()->check())abort_unless(auth()->user()->canManageLocation(Location::findOrFail($field->location_id)),403);
  if($field->operator_user_id){$u=User::findOrFail($field->operator_user_id);abort_unless($u->active && $u->role==='OPERATOR'&&$u->location_id===$field->location_id,422,'Assign an active operator from this woreda.');}
  $hours=$field->operating_hours;abort_unless(preg_match('/^([01][0-9]|2[0-3]):[0-5][0-9]$/',$hours['start']??'')&&preg_match('/^([01][0-9]|2[0-3]):[0-5][0-9]$/',$hours['end']??'')&&$hours['end']>$hours['start'],422,'Use a valid same-day opening interval.');
  foreach($field->weekly_hours??[] as $day=>$h){abort_unless(in_array((int)$day,range(1,7),true)&&($h===null||(preg_match('/^([01][0-9]|2[0-3]):[0-5][0-9]$/',$h['start']??'')&&preg_match('/^([01][0-9]|2[0-3]):[0-5][0-9]$/',$h['end']??'')&&$h['end']>$h['start'])),422,'Invalid weekly opening hours.');}
 });
 static::updated(function(Field $field){
  $changes=\Illuminate\Support\Arr::only($field->getChanges(),['name','location_id','operator_user_id','price_per_hour','status','operating_hours','weekly_hours']);
  if($changes) AuditLog::create(['actor_id'=>auth()->id(),'action'=>'FIELD_UPDATED','subject'=>(string)$field->id,'details'=>['before'=>\Illuminate\Support\Arr::only($field->getOriginal(),array_keys($changes)),'after'=>$changes]]);
  if($field->wasChanged('status')&&$field->status!=='ACTIVE')app(\App\Services\FieldOperations::class)->disrupt($field,null,null,$field->closureReason??'Field closed by operations');
 });}
}
