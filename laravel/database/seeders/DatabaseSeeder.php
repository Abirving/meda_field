<?php
namespace Database\Seeders;
use Illuminate\Database\Seeder;
use App\Models\{User,Location,Field};
class DatabaseSeeder extends Seeder {
 public function run():void {
  if(!app()->environment(['local','testing']))throw new \RuntimeException('Demo seeds are limited to local/testing.');
  $password=env('MEDA_SEED_PASSWORD');if(!$password||strlen($password)<12)throw new \RuntimeException('Set MEDA_SEED_PASSWORD to at least 12 characters.');
  $location=Location::firstOrCreate(['region'=>'Addis Ababa','subcity'=>'Bole','woreda'=>'03']);
  foreach(['admin'=>'SUPER_ADMIN','subcity'=>'SUBCITY_ADMIN','woreda'=>'WOREDA_ADMIN','operator'=>'OPERATOR'] as $name=>$role){
   User::firstOrCreate(['email'=>$name.'@meda.test'],['national_id'=>'STAFF-DEMO-'.$name,'first_name'=>ucfirst($name),'last_name'=>'Demo','username'=>$name,'password'=>$password,'role'=>$role,'location_id'=>$location->id]);
  }
  User::firstOrCreate(['national_id'=>'1000000001'],['first_name'=>'Abel','last_name'=>'Demo','role'=>'USER']);
  $operator=User::where('email','operator@meda.test')->firstOrFail();
  foreach([['Bole Community Field','FOOTBALL','800.00','An open neighborhood pitch for your after-work game.'],['Atlas Community Court','BASKETBALL','600.00','A welcoming basketball court for friends and local teams.']] as $data){
   Field::firstOrCreate(['name'=>$data[0],'location_id'=>$location->id],['operator_user_id'=>$operator->id,'type'=>$data[1],'price_per_hour'=>$data[2],'status'=>'ACTIVE','description'=>$data[3],'address'=>'Bole, Addis Ababa (sample venue)','operating_hours'=>['start'=>'08:00','end'=>'22:00']]);
  }
 }
}
