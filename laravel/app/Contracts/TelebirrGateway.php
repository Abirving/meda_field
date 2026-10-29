<?php
namespace App\Contracts;
use App\Models\PaymentAttempt;
use Illuminate\Http\Request;
interface TelebirrGateway {
    public function initiate(PaymentAttempt $attempt):array;
    public function verifiedCallback(Request $request):array;
    public function query(PaymentAttempt $attempt):array;
}
