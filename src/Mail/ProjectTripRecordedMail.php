<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppFuhrpark\Mail;

use Hwkdo\IntranetAppFuhrpark\Models\LogbookEntry;
use Hwkdo\IntranetAppFuhrpark\Models\Project;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ProjectTripRecordedMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public LogbookEntry $entry,
        public ?Project $project,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Fuhrpark | Projektfahrt erfasst',
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'intranet-app-fuhrpark::emails.project-trip-recorded',
        );
    }
}
