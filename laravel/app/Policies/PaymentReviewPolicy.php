<?php
namespace App\Policies;
use App\Models\{User,PaymentReview};
class PaymentReviewPolicy {
 public function viewAny(User $u):bool{return $u->active&&$u->role==='SUPER_ADMIN';}
 public function view(User $u,PaymentReview $r):bool{return $this->viewAny($u);}
 public function update(User $u,PaymentReview $r):bool{return $this->viewAny($u);}
 public function create(User $u):bool{return false;}
 public function delete(User $u,PaymentReview $r):bool{return false;}
}
