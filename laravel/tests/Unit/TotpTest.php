<?php
namespace Tests\Unit;
use PHPUnit\Framework\TestCase;
use App\Support\Totp;
class TotpTest extends TestCase {
 public function test_rfc6238_sha1_vectors():void {$t=new Totp;$s='GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ';foreach([59=>'94287082',1111111109=>'07081804',1111111111=>'14050471',1234567890=>'89005924',2000000000=>'69279037']as$time=>$code)$this->assertSame($code,$t->code($s,intdiv($time,30),8));}
}
