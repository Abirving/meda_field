<?php
namespace App\Notifications;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;
use App\Models\Booking;
class BookingConfirmed extends Notification implements ShouldQueue {
 use Queueable;
 public function __construct(public Booking $booking){}
 public function via(object $notifiable):array{return $notifiable->email_verified_at?['mail']:[];}
 public function toMail(object $notifiable):MailMessage{return (new MailMessage)->subject('Your Meda Pass is ready')->line($this->booking->field->name.' · '.$this->booking->display_start.' EAT')->action('View your pass',route('bookings.show',$this->booking));}
}
