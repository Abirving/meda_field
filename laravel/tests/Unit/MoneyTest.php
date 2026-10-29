<?php
namespace Tests\Unit;
use PHPUnit\Framework\TestCase;
use App\Support\Money;
class MoneyTest extends TestCase {
 public function test_money_uses_integer_minor_units():void {$this->assertSame(80005,Money::minor('800.05'));$this->assertSame('800.05',Money::decimal(80005));$this->assertSame('-2.05',Money::decimal(-205));}
 public function test_excess_precision_is_rejected():void {$this->expectException(\InvalidArgumentException::class);Money::minor('10.001');}
}
