<?php
use Illuminate\Support\Facades\{Artisan,Schedule,DB,Cache};
use App\Models\{Booking,PaymentAttempt,PaymentReview,User,AuditLog};
use App\Services\{BookingEngine,Payments,Readiness};
use App\Notifications\BookingUpdate;
Artisan::command('meda:expire-holds',function(BookingEngine $engine){$total=0;do{$n=$engine->expire();$total+=$n;}while($n===500);Cache::put('health:expiry',now()->toIso8601String(),600);$this->info('Expired: '.$total);});
Artisan::command('meda:reconcile-payments',function(Payments $payments){
 PaymentAttempt::whereIn('state',['REQUESTED','PENDING','UNKNOWN'])->where(fn($q)=>$q->whereNull('last_checked_at')->orWhere('last_checked_at','<',now()->subMinutes(2)))->orderBy('last_checked_at')->limit(100)->get()->each(fn($a)=>$payments->reconcile($a));
 Cache::put('health:reconciliation',now()->toIso8601String(),600);$this->info('Reconciliation batch completed.');
});
Artisan::command('meda:reminders',function(){
 Booking::where('status','PAID')->whereNull('reminder_sent_at')->whereBetween('start_time',[now(),now()->addHour()])->limit(200)->get()->each(function($b){
  DB::transaction(function()use($b){$b=Booking::lockForUpdate()->findOrFail($b->id);if($b->reminder_sent_at)return;$b->reminder_sent_at=now();$b->save();$b->user->notify((new BookingUpdate($b,'Your game starts soon','Your reserved play hour starts within one hour. Bring your pass to the assigned field.'))->afterCommit());});
 });$this->info('Reminders queued.');
});
Artisan::command('meda:doctor {--strict}',function(Readiness $readiness){
 $issues=$readiness->issues();foreach($issues as $issue)$this->warn($issue);
 try {DB::select('SELECT 1');Cache::put('health:probe','ok',10);if(Cache::get('health:probe')!=='ok')throw new RuntimeException;}
 catch(Throwable){$issues[]='Database or Redis connection failed.';$this->error(end($issues));}
 $this->info(count($issues).' launch configuration issue(s).');return $this->option('strict')&&$issues?1:0;
});
Artisan::command('meda:operations-check',function(){
 $overdue=Booking::where('status','PENDING_PAYMENT')->where('expires_at','<',now()->subMinutes(2))->count();
 $reviews=PaymentReview::where('status','OPEN')->count();$unknown=PaymentAttempt::whereIn('state',['REQUESTED','PENDING','UNKNOWN'])->where('created_at','<',now()->subMinutes(10))->count();
 $mismatches=DB::select('SELECT u.id FROM users u LEFT JOIN (SELECT user_id, SUM(amount) AS balance FROM credits GROUP BY user_id) c ON c.user_id=u.id WHERE u.store_credit <> COALESCE(c.balance,0)');
 $expiry=Cache::get('health:expiry');$reconciliation=Cache::get('health:reconciliation');
 $this->table(['Metric','Count'],[['Overdue holds',$overdue],['Open payment cases',$reviews],['Uncertain payments over ten minutes',$unknown],['Credit mismatches',count($mismatches)],['Expiry heartbeat',$expiry??'missing'],['Reconciliation heartbeat',$reconciliation??'missing']]);
 return $overdue||$unknown||count($mismatches)||!$expiry||!$reconciliation?1:0;
});
Artisan::command('meda:bootstrap-admin {email} {--name=Administrator}',function(){
 if(User::where('role','SUPER_ADMIN')->where('active',true)->exists()){$this->error('Use staff invitations after the first administrator exists.');return 1;}
 $email=strtolower($this->argument('email'));if(!filter_var($email,FILTER_VALIDATE_EMAIL))return 1;
 $password=$this->secret('Temporary password (at least 12 characters)');if(strlen((string)$password)<12){$this->error('Password too short.');return 1;}
 DB::transaction(function()use($email,$password){DB::select('SELECT pg_advisory_xact_lock(804281)');abort_if(User::where('role','SUPER_ADMIN')->where('active',true)->exists(),409);$u=User::create(['email'=>$email,'first_name'=>$this->option('name'),'password'=>$password,'role'=>'SUPER_ADMIN','active'=>true]);AuditLog::create(['actor_id'=>$u->id,'action'=>'ADMIN_BOOTSTRAPPED','subject'=>(string)$u->id]);});
 $this->info('Administrator created. Complete MFA at first sign-in.');
});
Schedule::command('meda:expire-holds')->everyMinute()->withoutOverlapping();
Schedule::command('meda:reconcile-payments')->everyMinute()->withoutOverlapping();
Schedule::command('meda:reminders')->everyFiveMinutes()->withoutOverlapping();
Schedule::call(function(){Booking::where('status','CHECKED_IN')->where('end_time','<=',now())->update(['status'=>'COMPLETED']);Booking::where('status','PAID')->where('end_time','<=',now())->update(['status'=>'NO_SHOW']);})->name('meda:complete-bookings')->everyMinute()->withoutOverlapping();
