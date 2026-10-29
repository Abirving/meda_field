<?php
namespace App\Policies;
use App\Models\{User,Location};
class LocationPolicy {
 public function viewAny(User $u):bool{return $u->active&&in_array($u->role,['SUPER_ADMIN','SUBCITY_ADMIN','WOREDA_ADMIN']);}
 public function view(User $u,Location $l):bool{return $u->canManageLocation($l);}
 public function create(User $u):bool{return $u->active&&$u->role==='SUPER_ADMIN';}
 public function update(User $u,Location $l):bool{return $this->create($u);}
 public function delete(User $u,Location $l):bool{return false;}
 public function deleteAny(User $u):bool{return false;}
}
