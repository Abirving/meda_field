<?php
namespace App\Services;
use App\Contracts\FaydaIdentity;
final class MockFayda implements FaydaIdentity {
 public function verify(string $fan):array {
  abort_unless(app()->environment(['local','testing'])&&config('meda.mock_auth'),503,'Mock authentication is disabled.');
  abort_unless(preg_match('/^[0-9]{10}$/',$fan),422,'Use a 10-digit test FAN.');
  return ['national_id'=>$fan,'first_name'=>'Test','last_name'=>'Citizen '.substr($fan,-4),'dob'=>null,'photo'=>null,'phone'=>null];
 }
}
