<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppFuhrpark\Notifications;

use Hwkdo\IntranetAppBase\Notifications\IntranetNotification;
use Hwkdo\IntranetAppFuhrpark\IntranetAppFuhrpark;
use Hwkdo\IntranetAppFuhrpark\Models\Booking;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Notifications\Messages\MailMessage;

class VehicleDamageReportedNotification extends IntranetNotification
{
    public function __construct(
        public readonly Booking $booking,
        public readonly Authenticatable $reporter,
    ) {
        parent::__construct();
    }

    public function typeKey(): string
    {
        return 'fuhrpark.vehicle_damage';
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Fuhrpark | Fahrzeugschaden gemeldet')
            ->line('Es wurde ein Fahrzeugschaden gemeldet.')
            ->line('Gemeldet von: '.($this->reporter->name ?? 'Unbekannt'))
            ->action('Fuhrpark öffnen', route('apps.fuhrpark.index'));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return $this->inboxPayload(
            title: 'Fahrzeugschaden gemeldet',
            body: 'Gemeldet von: '.($this->reporter->name ?? 'Unbekannt'),
            url: route('apps.fuhrpark.index'),
            appIdentifier: IntranetAppFuhrpark::identifier(),
        );
    }
}
