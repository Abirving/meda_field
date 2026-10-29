<?php
namespace App\Policies;
use App\Models\{User,Field};
class FieldPolicy {
 public function viewAny(User $u):bool{return $u->active&&in_array($u->role,['SUPER_ADMIN','SUBCITY_ADMIN','WOREDA_ADMIN']);}
 public function view(User $u,Field $f):bool{return $this->update($u,$f);}
 public function create(User $u):bool{return $this->viewAny($u);}
 public function update(User $u,Field $f):bool{return $this->viewAny($u)&&$u->canManageLocation($f->location);}
 public function delete(User $u,Field $f):bool{return false;}
 public function deleteAny(User $u):bool{return false;}
}
