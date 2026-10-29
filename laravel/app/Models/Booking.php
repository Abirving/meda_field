<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Booking extends Model {
 public $incrementing=false;protected $keyType='string';protected $guarded=[];
 protected function casts():array{return ['start_time'=>'immutable_datetime','end_time'=>'immutable_datetime','reserved_until'=>'immutable_datetime','expires_at'=>'immutable_datetime','checked_in_at'=>'immutable_datetime','amount'=>'decimal:2','credit_applied'=>'decimal:2','telebirr_amount'=>'decimal:2'];}
 public function user(){return $this->belongsTo(User::class);}
 public function field(){return $this->belongsTo(Field::class);}
 public function getDisplayStartAttribute():string{return $this->start_time->timezone(config('meda.timezone'))->format('D, d M · H:i');}
}
