<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppFuhrpark\Notifications;

use Hwkdo\IntranetAppBase\Notifications\IntranetNotification;
use Hwkdo\IntranetAppFuhrpark\IntranetAppFuhrpark;
use Hwkdo\IntranetAppFuhrpark\Models\Booking;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Notifications\Messages\MailMessage;

class BookingCancelledAdminNotification extends IntranetNotification
{
    public function __construct(
        public readonly Booking $booking,
        public readonly string $reason,
        public readonly Authenticatable $cancelledBy,
    ) {
        parent::__construct();
    }

    public function typeKey(): string
    {
        return 'fuhrpark.booking_cancelled_admin';
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Fuhrpark | Buchung mit Begründung gelöscht')
            ->line('Eine Buchung wurde mit Begründung gelöscht.')
            ->line('Grund: '.$this->reason)
            ->line('Gelöscht von: '.($this->cancelledBy->name ?? 'Unbekannt'))
            ->action('Fuhrpark öffnen', route('apps.fuhrpark.index'));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return $this->inboxPayload(
            title: 'Fuhrpark-Buchung storniert (Admin)',
            body: $this->reason,
            url: route('apps.fuhrpark.index'),
            appIdentifier: IntranetAppFuhrpark::identifier(),
        );
    }
}
