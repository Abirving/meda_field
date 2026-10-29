<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class VerifiedIdentity extends Model {protected $guarded=[]; protected $hidden=['subject_hash'];}
