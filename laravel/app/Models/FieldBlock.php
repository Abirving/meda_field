<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class FieldBlock extends Model {
    protected $guarded=[];
    protected function casts():array{return ['starts_at'=>'immutable_datetime','ends_at'=>'immutable_datetime'];}
    public function field(){return $this->belongsTo(Field::class);}
}
