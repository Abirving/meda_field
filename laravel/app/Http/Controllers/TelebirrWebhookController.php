<?php
namespace App\Http\Controllers;
use App\Contracts\TelebirrGateway;
use App\Services\Payments;
use Illuminate\Http\Request;
class TelebirrWebhookController {
    public function __invoke(Request $r,TelebirrGateway $gateway,Payments $payments){
        $payments->accept($gateway->verifiedCallback($r),'CALLBACK');
        return response((string)config('telebirr.acknowledgement'),200,['Content-Type'=>'text/plain']);
    }
}
