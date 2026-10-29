<?php
namespace App\Services;
use Firebase\JWT\{JWT,JWK};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
final class FaydaOidc {
    private function endpoint(string $name):string {
        $url=config('fayda.'.$name);
        abort_unless(is_string($url)&&filter_var($url,FILTER_VALIDATE_URL)&&parse_url($url,PHP_URL_SCHEME)==='https'&&!parse_url($url,PHP_URL_USER)&&!parse_url($url,PHP_URL_FRAGMENT),503,'Identity service is not configured.');return $url;
    }
    public function redirect(Request $r):string {
        abort_unless(config('fayda.enabled')&&config('fayda.client_id')&&strlen((string)config('fayda.subject_pepper'))>=32,503,'Fayda sign-in is not enabled yet.');
        $state=Str::random(64);$nonce=Str::random(64);$verifier=Str::random(96);
        $r->session()->put('oidc',['state'=>$state,'nonce'=>$nonce,'verifier'=>$verifier,'created'=>time()]);
        return $this->endpoint('authorization_url').'?'.http_build_query(['client_id'=>config('fayda.client_id'),'response_type'=>'code','redirect_uri'=>route('fayda.callback'),'scope'=>config('fayda.scopes'),'state'=>$state,'nonce'=>$nonce,'code_challenge'=>rtrim(strtr(base64_encode(hash('sha256',$verifier,true)),'+/','-_'),'='),'code_challenge_method'=>'S256'],'','&',PHP_QUERY_RFC3986);
    }
    public function verify(Request $r):array {
        abort_unless(config('fayda.enabled'),503);
        $attempt=$r->session()->pull('oidc');
        abort_unless($attempt&&time()-$attempt['created']<=600&&is_string($r->query('state'))&&hash_equals($attempt['state'],$r->query('state'))&&is_string($r->query('code'))&&!$r->has('error'),419,'Sign-in expired or was declined. Start again.');
        $url=$this->endpoint('token_url');$client=(string)config('fayda.client_id');
        $data=['grant_type'=>'authorization_code','code'=>$r->query('code'),'redirect_uri'=>route('fayda.callback'),'client_id'=>$client,'code_verifier'=>$attempt['verifier']];
        $http=Http::asForm()->acceptJson()->timeout(15)->connectTimeout(5)->withoutRedirecting();
        if(config('fayda.client_auth')==='private_key_jwt'){
            $path=config('fayda.private_key_path');abort_unless($path&&is_readable($path),503,'Identity signing key unavailable.');
            $data['client_assertion_type']='urn:ietf:params:oauth:client-assertion-type:jwt-bearer';
            $data['client_assertion']=JWT::encode(['iss'=>$client,'sub'=>$client,'aud'=>$url,'iat'=>time(),'exp'=>time()+120,'jti'=>Str::uuid()->toString()],file_get_contents($path),'RS256',config('fayda.key_id'));
        }elseif(config('fayda.client_auth')==='client_secret_basic')$http=$http->withBasicAuth($client,(string)config('fayda.client_secret'));
        else abort(503,'Unsupported identity client authentication.');
        $response=$http->post($url,$data);abort_unless($response->successful(),502,'Identity verification could not be completed. Try signing in again.');
        $token=$response->json('id_token');abort_unless(is_string($token)&&strlen($token)<20000,502,'Identity provider returned an invalid response.');
        try {
            $keys=Http::acceptJson()->timeout(10)->withoutRedirecting()->get($this->endpoint('jwks_url'))->throw()->json('keys');
            $keys=array_values(array_filter($keys??[],fn($k)=>($k['kty']??null)==='RSA'&&($k['alg']??'RS256')==='RS256'&&($k['use']??'sig')==='sig'));
            $claims=(array)JWT::decode($token,JWK::parseKeySet(['keys'=>$keys],'RS256'));
        }catch(\Throwable){abort(422,'Identity token could not be verified. Start sign-in again.');}
        $aud=(array)($claims['aud']??[]);
        abort_unless(($claims['iss']??null)===config('fayda.issuer')&&in_array($client,$aud,true)&&(!isset($claims['azp'])||$claims['azp']===$client)&&(count($aud)<=1||($claims['azp']??null)===$client)&&isset($claims['exp'],$claims['iat'])&&is_string($claims['sub']??null)&&strlen($claims['sub'])>0&&strlen($claims['sub'])<=255&&is_string($claims['nonce']??null)&&hash_equals($attempt['nonce'],$claims['nonce']),422,'Identity claims do not match this sign-in.');
        return $claims;
    }
}
