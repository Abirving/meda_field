<?php
namespace App\Services;
use App\Contracts\TelebirrGateway;
use App\Models\PaymentAttempt;
use Illuminate\Http\Request;
final class UnavailableTelebirr implements TelebirrGateway {
 public function initiate(PaymentAttempt $attempt):array{abort(503,'Telebirr checkout is not enabled.');}
 public function verifiedCallback(Request $request):array{abort(503,'Telebirr checkout is not enabled.');}
 public function query(PaymentAttempt $attempt):array{abort(503,'Telebirr checkout is not enabled.');}
}
