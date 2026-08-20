<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppFuhrpark\Notifications;

use App\Models\User;
use Hwkdo\IntranetAppBase\Notifications\IntranetNotification;
use Hwkdo\IntranetAppFuhrpark\IntranetAppFuhrpark;
use Hwkdo\IntranetAppFuhrpark\Models\Booking;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Notifications\Messages\MailMessage;

class BookingCancelledNotification extends IntranetNotification
{
    public function __construct(
        public readonly Booking $booking,
    ) {
        parent::__construct();
    }

    public function typeKey(): string
    {
        return 'fuhrpark.booking_cancelled';
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Fuhrpark | Buchung gelöscht')
            ->line('Eine Fuhrpark-Buchung wurde gelöscht.')
            ->line('Fahrzeug: '.($this->booking->vehicle?->name ?? 'Unbekannt'))
            ->action('Fuhrpark öffnen', route('apps.fuhrpark.index'));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return $this->inboxPayload(
            title: 'Fuhrpark-Buchung gelöscht',
            body: 'Fahrzeug: '.($this->booking->vehicle?->name ?? 'Unbekannt'),
            url: route('apps.fuhrpark.index'),
            appIdentifier: IntranetAppFuhrpark::identifier(),
        );
    }
}
