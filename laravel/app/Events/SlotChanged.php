<?php
namespace App\Events;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Queue\SerializesModels;
class SlotChanged implements ShouldBroadcast {
 use InteractsWithSockets,SerializesModels;
 public function __construct(public int $fieldId,public string $date){}
 public function broadcastOn():array{return [new Channel('fields.'.$this->fieldId)];}
 public function broadcastAs():string{return 'slots.changed';}
 public function broadcastWith():array{return ['date'=>$this->date];}
}
