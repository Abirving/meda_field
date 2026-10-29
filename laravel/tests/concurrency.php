<?php
/** Run only on a disposable native PostgreSQL meda_test database after PHPUnit. */
require __DIR__.'/../vendor/autoload.php';
$app=require __DIR__.'/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use App\Models\{User,Field,Location,Booking};
use App\Services\BookingEngine;
use Illuminate\Support\Facades\{Event,Notification};
use Illuminate\Support\Str;
if(!app()->environment('testing')||config('database.connections.pgsql.database')!=='meda_test'||config('database.connections.pgsql.url'))throw new RuntimeException('Disposable meda_test database required; DB_URL must be empty.');
Event::fake([\App\Events\SlotChanged::class]);Notification::fake();
if(($argv[1]??'')==='worker'){
 while(microtime(true)<(float)$argv[5])usleep(10000);
 try{app(BookingEngine::class)->hold(User::findOrFail($argv[2]),Field::findOrFail($argv[3]),$argv[4],'08:00','Concurrency test',(string)Str::uuid());echo 'HELD';}
 catch(\Symfony\Component\HttpKernel\Exception\HttpException $e){if($e->getStatusCode()!==409)throw $e;echo 'CONFLICT';}
 exit;
}
$suffix=Str::random(10);$date=now(config('meda.timezone'))->addDay()->toDateString();
$l=Location::create(['region'=>'TEST','subcity'=>'Concurrency','woreda'=>$suffix]);
$users=[User::create(['first_name'=>'Concurrent A']),User::create(['first_name'=>'Concurrent B'])];
$fields=[];foreach(['A','B','C']as$name)$fields[]=Field::create(['location_id'=>$l->id,'name'=>'TEST '.$name,'price_per_hour'=>'300.00','operating_hours'=>['start'=>'08:00','end'=>'22:00']]);
$run=function(array $jobs)use($date){
 $processes=[];$start=microtime(true)+1;
 foreach($jobs as[$user,$field]){
  $p=proc_open([PHP_BINARY,__FILE__,'worker',(string)$user->id,(string)$field->id,$date,(string)$start],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);
  if(!is_resource($p))throw new RuntimeException('Worker could not start.');fclose($pipes[0]);$processes[]=[$p,$pipes];
 }
 $results=[];foreach($processes as[$p,$pipes]){$result=stream_get_contents($pipes[1]);$error=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);if(proc_close($p)!==0)throw new RuntimeException('Concurrency worker failed: '.$error);$results[]=trim($result);}sort($results);return $results;
};
try{
 if($run([[$users[0],$fields[0]],[$users[1],$fields[0]]])!==['CONFLICT','HELD'])throw new RuntimeException('Same-slot exclusivity failed.');
 foreach(Booking::where('field_id',$fields[0]->id)->get()as$b)app(BookingEngine::class)->release($b,'RELEASED');
 $users[0]->store_credit='500.00';$users[0]->save();
 if($run([[$users[0],$fields[1]],[$users[0],$fields[2]]])!==['HELD','HELD'])throw new RuntimeException('Concurrent wallet bookings failed.');
 $held=Booking::where('user_id',$users[0]->id)->where('status','PENDING_PAYMENT');
 if($users[0]->fresh()->store_credit!=='0.00'||$held->sum('credit_applied')!='500.00'||$held->sum('telebirr_amount')!='100.00')throw new RuntimeException('Concurrent wallet conservation failed.');
 echo "PASS: separate PHP processes enforce same-slot exclusivity and shared-wallet conservation.\n";
}finally{foreach(Booking::whereIn('field_id',array_map(fn($f)=>$f->id,$fields))->where('status','PENDING_PAYMENT')->get()as$b)app(BookingEngine::class)->release($b,'RELEASED');}
