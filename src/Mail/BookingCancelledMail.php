<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppFuhrpark\Mail;

use Hwkdo\IntranetAppFuhrpark\Models\Booking;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class BookingCancelledMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public Booking $booking,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Fuhrpark | Buchung gelöscht',
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'intranet-app-fuhrpark::emails.booking-cancelled',
        );
    }
}
