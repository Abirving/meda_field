<?php
namespace App\Http\Controllers;
use App\Models\{Field,Location};
use App\Services\{Slots,BookingEngine};
use Illuminate\Http\Request;
class FieldController {
 public function index(Request $r){$fields=Field::with('location')->where('status','ACTIVE')->when($r->filled('subcity'),fn($q)=>$q->whereHas('location',fn($l)=>$l->where('subcity',$r->string('subcity')->toString())))->when($r->filled('type'),fn($q)=>$q->where('type',$r->string('type')->toString()))->when($r->filled('search'),fn($q)=>$q->where('name','ilike','%'.$r->string('search')->toString().'%'))->orderBy('name')->paginate(12)->withQueryString();$subcities=Location::distinct()->orderBy('subcity')->pluck('subcity');return view($r->header('HX-Request')?'partials.fields':'home',compact('fields','subcities'));}
 public function show(Request $r,Field $field,Slots $slots,BookingEngine $engine){$date=$r->input('date',now(config('meda.timezone'))->toDateString());$engine->expire($field->id);$availability=$slots->availability($field,$date);return view('field',compact('field','date','availability'));}
 public function slots(Request $r,Field $field,Slots $slots,BookingEngine $engine){$date=$r->input('date',now(config('meda.timezone'))->toDateString());$engine->expire($field->id);$availability=$slots->availability($field,$date);return response()->view('partials.slots',compact('field','date','availability'))->header('Cache-Control','no-store');}
}
