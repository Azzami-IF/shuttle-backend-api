<?php

namespace App\Mail;

use App\Models\Booking;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class BookingConfirmationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Booking $booking) {}

    public function envelope(): Envelope
    {
        $origin = optional($this->booking->schedule)->origin ?? 'Origin';
        $destination = optional($this->booking->schedule)->destination ?? 'Destination';

        return new Envelope(
            subject: "Booking Confirmation - $origin to $destination",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.booking-confirmation',
            with: [
                'booking' => $this->booking,
                'schedule' => optional($this->booking->schedule),
            ],
        );
    }
}