<?php
namespace App\Services;
use App\Contracts\TelebirrGateway;
use App\Models\PaymentAttempt;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
final class TelebirrH5 implements TelebirrGateway {
    public function __construct(private TelebirrSignature $signatures){}
    private function endpoint(string $key):string {
        $url=config('telebirr.'.$key);
        abort_unless(config('telebirr.enabled')&&config('telebirr.contract_approved')&&is_string($url)&&filter_var($url,FILTER_VALIDATE_URL)&&parse_url($url,PHP_URL_SCHEME)==='https'&&!parse_url($url,PHP_URL_USER)&&!parse_url($url,PHP_URL_QUERY)&&!parse_url($url,PHP_URL_FRAGMENT),503,'Payment service is awaiting merchant configuration.');return $url;
    }
    private function request(string $endpoint,string $method,array $biz):array {
        $token=Http::acceptJson()->timeout(10)->connectTimeout(5)->withoutRedirecting()->withHeaders(['X-APP-Key'=>config('telebirr.fabric_app_id')])->post($this->endpoint('token_url'),['appSecret'=>config('telebirr.app_secret')]);
        abort_unless($token->successful()&&is_string($token->json('token')),502,'Payment service could not be reached.');
        $body=['timestamp'=>(string)time(),'nonce_str'=>Str::random(32),'method'=>$method,'version'=>'1.0','biz_content'=>$biz];
        $body['sign']=$this->signatures->sign($body);$body['sign_type']=config('telebirr.sign_type');
        $response=Http::acceptJson()->timeout(15)->connectTimeout(5)->withoutRedirecting()->withHeaders(['X-APP-Key'=>config('telebirr.fabric_app_id'),'Authorization'=>config('telebirr.authorization_prefix').$token->json('token')])->post($this->endpoint($endpoint),$body);
        abort_unless($response->successful()&&is_array($response->json()),502,'Payment status is uncertain. We will check it before another payment.');
        $data=$response->json();$this->signatures->verify($data);return $data;
    }
    public function initiate(PaymentAttempt $a):array {
        abort_unless($a->expires_at->greaterThan(now()->addSeconds(30)),409,'Too little time remains on this hold.');
        $minutes=max(1,(int)floor(now()->diffInSeconds($a->expires_at)/60));
        $data=$this->request('preorder_url','payment.preorder',['appid'=>config('telebirr.merchant_app_id'),'merch_code'=>config('telebirr.merchant_code'),'merch_order_id'=>$a->merchant_order_id,'trade_type'=>'Checkout','title'=>'Meda Pass reservation','total_amount'=>$a->amount,'trans_currency'=>'ETB','timeout_express'=>$minutes.'m','notify_url'=>route('telebirr.callback'),'redirect_url'=>route('bookings.show',$a->booking_id)]);
        $prepay=data_get($data,'biz_content.prepay_id');
        abort_unless(is_string($prepay)&&strlen($prepay)>0&&strlen($prepay)<=255,502,'Payment order was not confirmed. Its status will be checked.');
        $params=['appid'=>config('telebirr.merchant_app_id'),'merch_code'=>config('telebirr.merchant_code'),'prepay_id'=>$prepay,'nonce_str'=>Str::random(32),'timestamp'=>(string)time()];
        $params['sign']=$this->signatures->sign($params);$params['sign_type']=config('telebirr.sign_type');
        return ['provider_order_id'=>$prepay,'url'=>$this->endpoint('checkout_url').'?'.http_build_query($params,'','&',PHP_QUERY_RFC3986)];
    }
    private function normalize(array $data):array {
        $this->signatures->verify($data);$biz=$data['biz_content']??$data;
        abort_unless(($biz['appid']??null)===config('telebirr.merchant_app_id')&&($biz['merch_code']??null)===config('telebirr.merchant_code'),422,'Payment merchant mismatch.');
        $order=$biz['merch_order_id']??null;$ref=$biz[config('telebirr.reference_field')]??null;$amount=$biz['total_amount']??null;$currency=$biz['trans_currency']??null;$status=$biz[config('telebirr.status_field')]??null;
        abort_unless(is_string($order)&&preg_match('/^[A-Za-z0-9]{1,64}$/',$order)&&is_string($amount)&&preg_match('/^\d{1,10}(\.\d{1,2})?$/',$amount)&&$currency==='ETB'&&is_string($status),422,'Payment response fields are invalid.');
        $outcome=$status===config('telebirr.success_status')?'SUCCESS':(in_array($status,config('telebirr.failure_statuses'),true)?'FAILED':'PENDING');
        abort_unless($outcome==='PENDING'||(is_string($ref)&&strlen($ref)>0&&strlen($ref)<=255),422,'Missing provider payment reference.');
        return ['merchant_order_id'=>$order,'reference'=>$ref??$order,'amount'=>Money::decimal(Money::minor($amount)),'currency'=>$currency,'outcome'=>$outcome];
    }
    public function verifiedCallback(Request $r):array {abort_if(strlen($r->getContent())>32768,413);return $this->normalize($r->json()->all());}
    public function query(PaymentAttempt $a):array {
        $result=$this->normalize($this->request('query_url',config('telebirr.query_method'),['appid'=>config('telebirr.merchant_app_id'),'merch_code'=>config('telebirr.merchant_code'),'merch_order_id'=>$a->merchant_order_id]));
        abort_unless($result['merchant_order_id']===$a->merchant_order_id,422,'Payment query returned a different order.');
        return $result;
    }
}
