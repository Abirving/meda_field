<?php
namespace App\Policies;
use App\Models\{User,PaymentEvent};
class PaymentEventPolicy {
 public function viewAny(User $u):bool{return $u->active&&$u->role==='SUPER_ADMIN';}
 public function view(User $u,PaymentEvent $p):bool{return $this->viewAny($u);}
 public function create(User $u):bool{return false;}
 public function update(User $u,PaymentEvent $p):bool{return false;}
 public function delete(User $u,PaymentEvent $p):bool{return false;}
 public function deleteAny(User $u):bool{return false;}
}
