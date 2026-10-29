<?php
return ['default'=>env('QUEUE_CONNECTION','redis'),'connections'=>['sync'=>['driver'=>'sync'],'redis'=>['driver'=>'redis','connection'=>'default','queue'=>'default','retry_after'=>90,'block_for'=>5,'after_commit'=>true]],'failed'=>['driver'=>'database-uuids','database'=>'pgsql','table'=>'failed_jobs']];
