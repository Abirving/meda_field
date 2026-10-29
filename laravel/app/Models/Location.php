<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
class Location extends Model {
 protected $guarded=['id'];
 public function getLabelAttribute():string{return $this->region.' / '.$this->subcity.' / Woreda '.$this->woreda;}
 public function scopeVisibleTo(Builder $q,User $u):Builder {
  if($u->role==='SUPER_ADMIN')return $q;
  if($u->role==='SUBCITY_ADMIN')return $q->where('region',$u->location?->region)->where('subcity',$u->location?->subcity);
  return $q->where('id',$u->location_id??0);
 }
}
