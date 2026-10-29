<?php
namespace App\Services;
use phpseclib3\Crypt\{PublicKeyLoader,RSA};
final class TelebirrSignature {
    public function canonical(array $data):string {
        $flat=[];
        foreach($data as $k=>$v){
            if(in_array($k,['sign','sign_type'],true))continue;
            if($k==='biz_content'){
                abort_unless(is_array($v),422,'Invalid payment envelope.');
                foreach($v as $name=>$value){abort_if(array_key_exists($name,$data)||array_key_exists($name,$flat),422,'Ambiguous payment envelope.');$flat[$name]=$value;}
            }else{$flat[$k]=$v;}
        }
        foreach($flat as $k=>$v){
            abort_unless((is_string($v)||is_int($v))&&is_string($k)&&preg_match('/^[A-Za-z0-9_]+$/',$k),422,'Invalid payment parameter type.');
            abort_if(str_contains((string)$v,'&')||str_contains((string)$v,'='),422,'Ambiguous payment parameter value.');
        }
        ksort($flat,SORT_STRING);
        return implode('&',array_map(fn($k)=>$k.'='.$flat[$k],array_keys($flat)));
    }
    private function key(string $setting){
        $path=config('telebirr.'.$setting);abort_unless($path&&is_readable($path),503,'Payment signing configuration unavailable.');
        $key=PublicKeyLoader::load(file_get_contents($path));
        $algorithm=config('telebirr.signature_algorithm');
        abort_unless(in_array($algorithm,['pss-sha256','pkcs1-sha256'],true),503,'Confirm the provider signature algorithm before enabling checkout.');
        return $key->withHash('sha256')->withMGFHash('sha256')->withPadding($algorithm==='pss-sha256'?RSA::SIGNATURE_PSS:RSA::SIGNATURE_PKCS1)->withSaltLength(32);
    }
    public function sign(array $data):string {return base64_encode($this->key('private_key_path')->sign($this->canonical($data)));}
    public function verify(array $data):void {
        abort_unless(($data['sign_type']??null)===config('telebirr.sign_type')&&is_string($data['sign']??null),422,'Missing payment signature.');
        $signature=base64_decode($data['sign'],true);
        abort_unless($signature!==false&&$this->key('provider_key_path')->verify($this->canonical($data),$signature),422,'Invalid payment signature.');
    }
}
