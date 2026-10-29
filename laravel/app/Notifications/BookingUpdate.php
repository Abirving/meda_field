<?php
namespace App\Notifications;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\{Notification,Messages\MailMessage};
use App\Models\Booking;
class BookingUpdate extends Notification implements ShouldQueue {
    use Queueable;
    public function __construct(public Booking $booking,public string $heading,public string $message){}
    public function via(object $user):array{return $user->email_verified_at?['mail']:[];}
    public function toMail(object $user):MailMessage{return (new MailMessage)->subject('Meda Pass · '.$this->heading)->line($this->message)->line($this->booking->field->name.' · '.$this->booking->display_start.' EAT')->action('View booking',route('bookings.show',$this->booking));}
}
