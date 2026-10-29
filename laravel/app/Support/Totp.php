<?php
namespace App\Support;
/** RFC 6238, HMAC-SHA1, 30-second steps, six digits. */
final class Totp {
    public function secret():string {
        $alphabet='ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';$bits='';
        foreach(str_split(random_bytes(20))as$c)$bits.=str_pad(decbin(ord($c)),8,'0',STR_PAD_LEFT);
        $out='';foreach(str_split($bits,5)as$b)$out.=$alphabet[bindec($b)];return $out;
    }
    public function code(string $secret,int $step,int $digits=6):string {
        $bits='';foreach(str_split($secret)as$c){$i=strpos('ABCDEFGHIJKLMNOPQRSTUVWXYZ234567',$c);if($i===false)throw new \InvalidArgumentException('Invalid OTP secret');$bits.=str_pad(decbin($i),5,'0',STR_PAD_LEFT);}
        $key='';foreach(str_split($bits,8)as$b)if(strlen($b)===8)$key.=chr(bindec($b));
        $mac=hash_hmac('sha1',pack('N2',intdiv($step,4294967296),$step%4294967296),$key,true);
        $offset=ord($mac[19])&15;$number=unpack('N',substr($mac,$offset,4))[1]&0x7fffffff;
        return str_pad((string)($number%(10**$digits)),$digits,'0',STR_PAD_LEFT);
    }
    public function match(string $secret,string $code,?int $lastStep=null):?int {
        if(!preg_match('/^\d{6}$/',$code))return null;
        $now=intdiv(time(),30);
        foreach([$now,$now-1,$now+1]as$step)if($step>($lastStep??-1)&&hash_equals($this->code($secret,$step),$code))return $step;
        return null;
    }
}
