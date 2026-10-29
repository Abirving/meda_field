<?php
namespace App\Models;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Support\Facades\DB;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
class User extends Authenticatable implements FilamentUser, MustVerifyEmail {
    use Notifiable;
    protected $attributes=['active'=>true,'store_credit'=>'0.00','role'=>'USER','session_version'=>1,'language'=>'en'];
    protected $guarded=['id','store_credit'];
    protected $hidden=['password','remember_token','national_id','dob','photo','totp_secret','recovery_codes'];
    protected function casts():array{return ['password'=>'hashed','dob'=>'date','store_credit'=>'decimal:2','active'=>'boolean','email_verified_at'=>'datetime','terms_accepted_at'=>'datetime','totp_secret'=>'encrypted','recovery_codes'=>'encrypted:array'];}
    public function location(){return $this->belongsTo(Location::class);}
    public function getNameAttribute():string{return trim($this->first_name.' '.$this->last_name);}
    public function getFilamentName():string{return $this->name;}
    public function canAccessPanel(Panel $panel):bool{return $this->active&&in_array($this->role,['SUPER_ADMIN','SUBCITY_ADMIN','WOREDA_ADMIN'],true);}
    public function canManageLocation(Location $location):bool{return $this->active&&($this->role==='SUPER_ADMIN'||($this->role==='SUBCITY_ADMIN'&&$this->location?->subcity===$location->subcity&&$this->location?->region===$location->region)||($this->role==='WOREDA_ADMIN'&&$this->location_id===$location->id));}
    public function save(array $options=[]){return DB::transaction(fn()=>parent::save($options));}
    protected static function booted():void {
        static::saving(function(User $target){
            if($target->last_name===null)$target->last_name='';
            if(is_string($target->email))$target->email=strtolower(trim($target->email));
            if($target->exists&&$target->isDirty('email'))$target->email_verified_at=null;
            $actor=auth()->user();
            $sensitive=['role','location_id','active','national_id','username'];
            $profile=['email','password','first_name','last_name'];
            if($actor&&$target->isDirty(array_merge($sensitive,$profile))){
                $self=$target->exists&&$actor->id===$target->id;
                $profileOnly=$self&&!$target->isDirty($sensitive);
                if(!$profileOnly&&$actor->role!=='SUPER_ADMIN'){
                    abort_unless($target->role==='OPERATOR'&&$target->location_id&&$actor->canManageLocation(Location::findOrFail($target->location_id)),403);
                    if($target->exists) abort_unless($target->getOriginal('role')==='OPERATOR'&&$actor->canManageLocation(Location::findOrFail($target->getOriginal('location_id'))),403);
                }
                abort_if($self&&(!$target->active||$target->isDirty('role')),422,'You cannot disable or demote your own account.');
            }
            if($target->exists&&$target->isDirty(['role','active','password','location_id'])){
                DB::select('SELECT pg_advisory_xact_lock(804281)');
                if($target->getOriginal('role')==='SUPER_ADMIN'&&($target->role!=='SUPER_ADMIN'||!$target->active)){
                    abort_unless(static::where('role','SUPER_ADMIN')->where('active',true)->where('id','<>',$target->id)->exists(),422,'Keep at least one active super administrator.');
                }
                $target->session_version=(int)static::whereKey($target->id)->value('session_version')+1;
            }
        });
        static::updated(function(User $target){
            $changes=\Illuminate\Support\Arr::only($target->getChanges(),['role','active','location_id','session_version']);
            if($changes)AuditLog::create(['actor_id'=>auth()->id(),'action'=>'ACCOUNT_ACCESS_CHANGED','subject'=>(string)$target->id,'details'=>['before'=>\Illuminate\Support\Arr::only($target->getOriginal(),array_keys($changes)),'after'=>$changes]]);
        });
    }
}
