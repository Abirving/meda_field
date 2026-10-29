<?php
namespace App\Support;
final class Money {
 public static function minor(string $value):int {if(!preg_match('/^(\d+)(?:\.(\d{1,2}))?$/',$value,$m))throw new \InvalidArgumentException('Invalid decimal money');return ((int)$m[1])*100+(int)str_pad($m[2]??'',2,'0');}
 public static function decimal(int $value):string {return ($value<0?'-':'').intdiv(abs($value),100).'.'.str_pad((string)(abs($value)%100),2,'0',STR_PAD_LEFT);}
}
