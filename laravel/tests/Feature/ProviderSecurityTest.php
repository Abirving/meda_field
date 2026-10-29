<?php
namespace Tests\Feature;

use Tests\TestCase;
use App\Services\{FaydaOidc,TelebirrH5,TelebirrSignature};
use Firebase\JWT\JWT;
use Illuminate\Http\Request;
use Illuminate\Session\{Store,ArraySessionHandler};
use Illuminate\Support\Facades\Http;
use Symfony\Component\HttpKernel\Exception\HttpException;

class ProviderSecurityTest extends TestCase
{
    private string $private='';
    private array $jwk;
    private array $paths=[];

    protected function setUp():void
    {
        parent::setUp();
        $key=openssl_pkey_new(['private_key_bits'=>2048,'private_key_type'=>OPENSSL_KEYTYPE_RSA]);
        openssl_pkey_export($key,$this->private);
        $details=openssl_pkey_get_details($key);
        $b64=fn($s)=>rtrim(strtr(base64_encode($s),'+/','-_'),'=');
        $this->jwk=['kid'=>'test-provider','kty'=>'RSA','alg'=>'RS256','use'=>'sig','n'=>$b64($details['rsa']['n']),'e'=>$b64($details['rsa']['e'])];
        foreach(['private'=>$this->private,'public'=>$details['key']] as $type=>$content){
            $path=tempnam(sys_get_temp_dir(),'meda-test-');chmod($path,0600);file_put_contents($path,$content);$this->paths[$type]=$path;
        }
        config([
            'fayda.enabled'=>true,'fayda.client_id'=>'meda-client','fayda.subject_pepper'=>str_repeat('p',32),
            'fayda.issuer'=>'https://identity.example.test','fayda.authorization_url'=>'https://identity.example.test/authorize',
            'fayda.token_url'=>'https://identity.example.test/token','fayda.jwks_url'=>'https://identity.example.test/keys',
            'fayda.client_auth'=>'client_secret_basic','fayda.client_secret'=>'synthetic-only',
            'telebirr.private_key_path'=>$this->paths['private'],'telebirr.provider_key_path'=>$this->paths['public'],
            'telebirr.signature_algorithm'=>'pkcs1-sha256','telebirr.sign_type'=>'SHA256WithRSA',
            'telebirr.merchant_app_id'=>'merchant-app','telebirr.merchant_code'=>'12345',
        ]);
        Http::preventStrayRequests();
    }
    protected function tearDown():void
    {
        foreach($this->paths as $path)unlink($path);
        parent::tearDown();
    }
    private function request():Request
    {
        $r=Request::create('/auth/fayda/callback','GET',['state'=>'state-test','code'=>'one-use-code']);
        $session=new Store('security-test',new ArraySessionHandler(120));$session->start();
        $session->put('oidc',['state'=>'state-test','nonce'=>'nonce-test','verifier'=>'pkce-test','created'=>time()]);
        $r->setLaravelSession($session);return $r;
    }
    private function claims(array $replace=[]):array
    {
        return array_replace(['iss'=>'https://identity.example.test','sub'=>'verified-subject','aud'=>'meda-client','iat'=>time()-5,'exp'=>time()+120,'nonce'=>'nonce-test'], $replace);
    }
    private function identityResponse(array $claims,?string $key=null):void
    {
        $token=JWT::encode($claims,$key??$this->private,'RS256','test-provider');
        Http::fake(['*/token'=>Http::response(['id_token'=>$token]),'*/keys'=>Http::response(['keys'=>[$this->jwk]])]);
    }
    private function rejects(callable $call,int $status=422):void
    {
        try{$call();$this->fail('Untrusted input was accepted.');}catch(HttpException $e){$this->assertSame($status,$e->getStatusCode());}
    }
    public function test_oidc_authorization_uses_pkce_and_unique_state():void
    {
        $r=$this->request();parse_str(parse_url(app(FaydaOidc::class)->redirect($r),PHP_URL_QUERY),$query);
        $this->assertSame('S256',$query['code_challenge_method']);
        $this->assertSame(rtrim(strtr(base64_encode(hash('sha256',$r->session()->get('oidc.verifier'),true)),'+/','-_'),'='),$query['code_challenge']);
        $this->assertGreaterThanOrEqual(32,strlen($query['state']));
    }
    public function test_oidc_accepts_signed_identity_and_consumes_state_once():void
    {
        $this->identityResponse($this->claims());$r=$this->request();
        $this->assertSame('verified-subject',app(FaydaOidc::class)->verify($r)['sub']);
        Http::assertSent(fn($request)=>$request->url()==='https://identity.example.test/token'&&$request['code_verifier']==='pkce-test');
        $this->rejects(fn()=>app(FaydaOidc::class)->verify($r),419);
    }
    public function test_oidc_rejects_wrong_nonce_issuer_audience_authorized_party_and_expiry():void
    {
        foreach([['nonce'=>'attacker'],['iss'=>'https://attacker.example.test'],['aud'=>'other-client'],['aud'=>['meda-client','other-client']],['azp'=>'other-client'],['exp'=>time()-1],['iat'=>time()+300]]as$invalid){
            $this->identityResponse($this->claims($invalid));$this->rejects(fn()=>app(FaydaOidc::class)->verify($this->request()));
        }
    }
    public function test_oidc_rejects_wrong_signature():void
    {
        $other=openssl_pkey_new(['private_key_bits'=>2048]);openssl_pkey_export($other,$pem);
        $this->identityResponse($this->claims(),$pem);$this->rejects(fn()=>app(FaydaOidc::class)->verify($this->request()));
    }
    public function test_oidc_rejects_missing_wrong_and_expired_state_before_network():void
    {
        foreach(['wrong','expired','missing']as$case){$r=$this->request();
            if($case==='wrong')$r->query->set('state','wrong');
            elseif($case==='expired')$r->session()->put('oidc.created',time()-601);
            else $r->session()->forget('oidc');
            $this->rejects(fn()=>app(FaydaOidc::class)->verify($r),419);
        }Http::assertNothingSent();
    }
    private function envelope(array $replace=[]):array
    {
        $v=['nonce_str'=>'nonce','timestamp'=>(string)time(),'biz_content'=>array_replace(['appid'=>'merchant-app','merch_code'=>'12345','merch_order_id'=>'order123','payment_order_id'=>'provider-ref','total_amount'=>'800.00','trans_currency'=>'ETB','trade_status'=>'SUCCESS'],$replace),'sign_type'=>'SHA256WithRSA'];
        $v['sign']=app(TelebirrSignature::class)->sign($v);return $v;
    }
    private function parseCallback(array $v):array
    {
        return app(TelebirrH5::class)->verifiedCallback(Request::create('/api/payments/telebirr/callback','POST',[],[],[],['CONTENT_TYPE'=>'application/json'],json_encode($v)));
    }
    public function test_both_explicit_signature_algorithms_verify_and_reject_tampering():void
    {
        foreach(['pkcs1-sha256','pss-sha256']as$algorithm){config(['telebirr.signature_algorithm'=>$algorithm]);$v=$this->envelope();
            $this->assertSame('SUCCESS',$this->parseCallback($v)['outcome']);
            $v['biz_content']['total_amount']='1.00';$this->rejects(fn()=>$this->parseCallback($v));
        }
    }
    public function test_signed_wrong_merchant_currency_and_missing_reference_are_rejected():void
    {
        foreach([['appid'=>'other'],['merch_code'=>'other'],['trans_currency'=>'USD'],['payment_order_id'=>''],['total_amount'=>'8e2']]as$bad)$this->rejects(fn()=>$this->parseCallback($this->envelope($bad)));
    }
    public function test_ambiguous_and_unsigned_payment_payloads_are_rejected():void
    {
        $this->rejects(fn()=>$this->parseCallback(['biz_content'=>[]]));
        $this->rejects(fn()=>app(TelebirrSignature::class)->canonical(['appid'=>'one','biz_content'=>['appid'=>'two']]));
        $this->rejects(fn()=>app(TelebirrSignature::class)->canonical(['a'=>'one&b=two']));
    }
    public function test_h5_checkout_posts_a_signed_order_and_returns_the_configured_host():void
    {
        config(['telebirr.enabled'=>true,'telebirr.contract_approved'=>true,'telebirr.token_url'=>'https://pay.example.test/token','telebirr.preorder_url'=>'https://pay.example.test/preorder','telebirr.checkout_url'=>'https://pay.example.test/checkout']);
        $response=['biz_content'=>['prepay_id'=>'prepay123'],'sign_type'=>'SHA256WithRSA'];$response['sign']=app(TelebirrSignature::class)->sign($response);
        Http::fake(['*/token'=>Http::response(['token'=>'test-token']),'*/preorder'=>Http::response($response)]);
        $a=new \App\Models\PaymentAttempt(['merchant_order_id'=>'order123','booking_id'=>(string)\Illuminate\Support\Str::uuid(),'amount'=>'800.00','expires_at'=>now()->addMinutes(5)]);
        $checkout=app(TelebirrH5::class)->initiate($a);$this->assertSame('prepay123',$checkout['provider_order_id']);
        $this->assertStringStartsWith('https://pay.example.test/checkout?',$checkout['url']);
        Http::assertSent(function($request){if(!str_ends_with($request->url(),'/preorder'))return false;app(TelebirrSignature::class)->verify($request->data());return $request['biz_content']['total_amount']==='800.00'&&$request['biz_content']['merch_order_id']==='order123';});
    }
    public function test_provider_query_cannot_substitute_another_order():void
    {
        config(['telebirr.enabled'=>true,'telebirr.contract_approved'=>true,'telebirr.token_url'=>'https://pay.example.test/token','telebirr.query_url'=>'https://pay.example.test/query']);
        Http::fake(['*/token'=>Http::response(['token'=>'test-token']),'*/query'=>Http::response($this->envelope(['merch_order_id'=>'differentOrder']))]);
        $this->rejects(fn()=>app(TelebirrH5::class)->query(new \App\Models\PaymentAttempt(['merchant_order_id'=>'order123'])));
    }
}
