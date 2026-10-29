<?php
namespace App\Policies;
use App\Models\User;
class UserPolicy {
 public function viewAny(User $u):bool{return $u->active&&in_array($u->role,['SUPER_ADMIN','SUBCITY_ADMIN','WOREDA_ADMIN']);}
 public function create(User $u):bool{return $this->viewAny($u);}
 public function view(User $u,User $target):bool{return $this->update($u,$target);}
 public function update(User $u,User $target):bool{return $u->active&&($u->role==='SUPER_ADMIN'||($target->role==='OPERATOR'&&$target->location&&$u->canManageLocation($target->location)));}
 public function delete(User $u,User $target):bool{return false;}
 public function deleteAny(User $u):bool{return false;}
}
