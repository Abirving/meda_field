<?php
namespace App\Http\Controllers;
use App\Models\{Booking,Field};
use App\Services\{BookingEngine,Payments};
use App\Models\{PaymentAttempt,PaymentReview};
use App\Contracts\TelebirrGateway;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use BaconQrCode\{Writer,Renderer\ImageRenderer,Renderer\RendererStyle\RendererStyle,Renderer\Image\SvgImageBackEnd};
class BookingController {
 public function index(Request $r){$bookings=Booking::with('field.location')->where('user_id',$r->user()->id)->latest('start_time')->paginate(15);return view('bookings',compact('bookings'));}
 public function hold(Request $r,Field $field,BookingEngine $engine){$v=$r->validate(['date'=>'required|date_format:Y-m-d','time'=>'required|date_format:H:i','team'=>'required|string|min:2|max:60','idempotency_key'=>'required|uuid']);$b=$engine->hold($r->user(),$field,$v['date'],$v['time'],$v['team'],$v['idempotency_key']);return redirect()->route('bookings.show',$b);}
 public function show(Booking $booking,BookingEngine $engine){Gate::authorize('view',$booking);if($booking->status==='PENDING_PAYMENT'&&!$booking->expires_at->isFuture())$booking=$engine->release($booking);$attempt=PaymentAttempt::where('booking_id',$booking->id)->first();$reviews=PaymentReview::where('booking_id',$booking->id)->get();return view('booking',compact('booking','attempt','reviews'));}
 public function pay(Request $r,Booking $booking,BookingEngine $engine,Payments $payments){Gate::authorize('pay',$booking);abort_unless($booking->status==='PENDING_PAYMENT',409,'This hold is no longer pending.');
  if(config('meda.mock_payments')&&app()->environment(['local','testing'])){$v=$r->validate(['result'=>'required|in:success,failed']);$engine->settle($booking->id,'MOCK-'.Str::uuid(),$booking->telebirr_amount,'ETB',$v['result']==='success');return back();}
  if($booking->telebirr_amount==='0.00'){$engine->settle($booking->id,'CREDIT-'.$booking->id,'0.00','ETB',true);return back();}
  $attempt=$payments->start($booking);if($attempt->state==='PENDING'&&$attempt->checkout_url)return redirect()->away($attempt->checkout_url);return back()->with('status','Your existing payment is being checked. Do not submit another payment.');
 }
 public function release(Request $r,Booking $booking,BookingEngine $engine){Gate::authorize('pay',$booking);$engine->release($booking,'RELEASED');return redirect()->route('fields.show',$booking->field_id);}
 public function cancel(Request $r,Booking $booking,BookingEngine $engine){Gate::authorize('pay',$booking);$engine->cancel($r->user(),$booking);return back()->with('status','Cancelled. The full amount is now store credit. Book a new slot to reschedule.');}
 public function receipt(Booking $booking){Gate::authorize('view',$booking);return view('receipt',compact('booking'));}
 public function qr(Booking $booking,BookingEngine $engine){Gate::authorize('view',$booking);abort_unless(in_array($booking->status,['PAID','CHECKED_IN'],true),409,'No valid pass.');$writer=new Writer(new ImageRenderer(new RendererStyle(240),new SvgImageBackEnd));return response($writer->writeString($engine->token($booking)),200,['Content-Type'=>'image/svg+xml','Cache-Control'=>'private, no-store','X-Content-Type-Options'=>'nosniff']);}
}
