<?php
namespace App\Policies;
use App\Models\{User,PaymentAttempt};
class PaymentAttemptPolicy {
 public function viewAny(User $u):bool{return $u->active&&$u->role==='SUPER_ADMIN';}
 public function view(User $u,PaymentAttempt $a):bool{return $this->viewAny($u);}
 public function create(User $u):bool{return false;}
 public function update(User $u,PaymentAttempt $a):bool{return false;}
 public function delete(User $u,PaymentAttempt $a):bool{return false;}
}
