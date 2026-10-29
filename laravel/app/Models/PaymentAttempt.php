<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class PaymentAttempt extends Model {
    public $incrementing=false;
    protected $keyType='string';
    protected $guarded=[];
    protected $hidden=['checkout_url'];
    protected function casts():array {return ['amount'=>'decimal:2','expires_at'=>'immutable_datetime','last_checked_at'=>'immutable_datetime'];}
    public function booking(){return $this->belongsTo(Booking::class);}
}
