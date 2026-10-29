<?php
namespace Tests;
abstract class TestCase extends \Illuminate\Foundation\Testing\TestCase {
 public function createApplication(){
  $app=parent::createApplication();
  if(!$app->environment('testing')||config('database.connections.pgsql.database')!=='meda_test'||config('database.connections.pgsql.url'))throw new \RuntimeException('Tests require APP_ENV=testing, DB_DATABASE=meda_test and no DB_URL.');
  config(['cache.prefix'=>'meda_test_'.bin2hex(random_bytes(6)),'session.driver'=>'array']);
  return $app;
 }
}
