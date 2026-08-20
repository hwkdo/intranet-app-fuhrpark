<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppFuhrpark\Notifications;

use Hwkdo\IntranetAppBase\Notifications\IntranetNotification;
use Hwkdo\IntranetAppFuhrpark\IntranetAppFuhrpark;
use Hwkdo\IntranetAppFuhrpark\Models\LogbookEntry;
use Hwkdo\IntranetAppFuhrpark\Models\Project;
use Illuminate\Notifications\Messages\MailMessage;

class ProjectTripChangedNotification extends IntranetNotification
{
    public function __construct(
        public readonly LogbookEntry $entry,
        public readonly ?Project $project,
        public readonly int $oldKm,
    ) {
        parent::__construct();
    }

    public function typeKey(): string
    {
        return 'fuhrpark.project_trip_changed';
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Fuhrpark | Projektfahrt geändert')
            ->line('Eine Projektfahrt wurde geändert.')
            ->line('Projekt: '.($this->project?->name ?? 'Unbekannt'))
            ->line('Vorherige km: '.$this->oldKm)
            ->action('Fuhrpark öffnen', route('apps.fuhrpark.index'));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return $this->inboxPayload(
            title: 'Projektfahrt geändert',
            body: 'Projekt: '.($this->project?->name ?? 'Unbekannt'),
            url: route('apps.fuhrpark.index'),
            appIdentifier: IntranetAppFuhrpark::identifier(),
        );
    }
}
