<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppFuhrpark\Notifications;

use Hwkdo\IntranetAppBase\Notifications\IntranetNotification;
use Hwkdo\IntranetAppFuhrpark\IntranetAppFuhrpark;
use Hwkdo\IntranetAppFuhrpark\Models\LogbookEntry;
use Hwkdo\IntranetAppFuhrpark\Models\Project;
use Illuminate\Notifications\Messages\MailMessage;

class ProjectTripRecordedNotification extends IntranetNotification
{
    public function __construct(
        public readonly LogbookEntry $entry,
        public readonly ?Project $project,
    ) {
        parent::__construct();
    }

    public function typeKey(): string
    {
        return 'fuhrpark.project_trip';
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Fuhrpark | Projektfahrt erfasst')
            ->line('Eine Projektfahrt wurde erfasst.')
            ->line('Projekt: '.($this->project?->name ?? 'Unbekannt'))
            ->action('Fuhrpark öffnen', route('apps.fuhrpark.index'));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return $this->inboxPayload(
            title: 'Projektfahrt erfasst',
            body: 'Projekt: '.($this->project?->name ?? 'Unbekannt'),
            url: route('apps.fuhrpark.index'),
            appIdentifier: IntranetAppFuhrpark::identifier(),
        );
    }
}
