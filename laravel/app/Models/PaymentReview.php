<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class PaymentReview extends Model {
    protected $guarded=[];
    protected function casts():array{return ['amount'=>'decimal:2','resolved_at'=>'immutable_datetime'];}
    public function booking(){return $this->belongsTo(Booking::class);}
}
