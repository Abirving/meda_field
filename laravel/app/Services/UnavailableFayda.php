<?php
namespace App\Services;
use App\Contracts\FaydaIdentity;
final class UnavailableFayda implements FaydaIdentity { public function verify(string $fan):array {abort(503,'Licensed Fayda authentication is not configured.');} }
