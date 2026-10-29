<?php
namespace App\Http\Controllers;
use App\Models\{Field,Booking};
use App\Services\BookingEngine;
use Illuminate\Http\Request;
class GateController {
 public function index(Request $r){$fields=Field::visibleTo($r->user())->get();$start=now(config('meda.timezone'))->startOfDay()->utc();$bookings=Booking::with('field','user')->whereIn('field_id',$fields->pluck('id'))->where('start_time','>=',$start)->where('start_time','<',$start->copy()->addDay())->orderBy('start_time')->get();return view(request()->routeIs('gate.schedule')?'partials.gate-schedule':'gate',compact('fields','bookings'));}
 public function schedule(Request $r){return $this->index($r);}
 public function check(Request $r,BookingEngine $engine){$v=$r->validate(['field_id'=>'required|integer','token'=>'required|string|max:180']);$b=$engine->checkIn($r->user(),(int)$v['field_id'],$v['token']);return back()->with('status',$b->team.' checked in.');}
}
