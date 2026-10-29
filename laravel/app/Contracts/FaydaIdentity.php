<?php
namespace App\Contracts;
interface FaydaIdentity { /** Return verified identity fields only; never trust client-provided profile data. */ public function verify(string $fan):array; }
