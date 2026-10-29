<?php
namespace App\Policies;
use App\Models\{User,Booking,Field};
class BookingPolicy {
 public function viewAny(User $u):bool{return $u->active&&in_array($u->role,['SUPER_ADMIN','SUBCITY_ADMIN','WOREDA_ADMIN']);}
 public function view(User $u,Booking $b):bool{return $u->active&&($u->id===$b->user_id||Field::visibleTo($u)->whereKey($b->field_id)->exists());}
 public function pay(User $u,Booking $b):bool{return $u->active&&$u->role==='USER'&&$u->id===$b->user_id;}
 public function create(User $u):bool{return false;}
 public function update(User $u,Booking $b):bool{return false;}
 public function delete(User $u,Booking $b):bool{return false;}
 public function deleteAny(User $u):bool{return false;}
}
