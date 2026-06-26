<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppFuhrpark\Services;

use Carbon\CarbonInterface;
use Hwkdo\IntranetAppFuhrpark\Contracts\BookingCalendarSyncInterface;
use Hwkdo\IntranetAppFuhrpark\Models\Booking;
use Hwkdo\MsGraphLaravel\Client;
use Illuminate\Support\Facades\Log;
use Microsoft\Graph\Generated\Models\BodyType;
use Microsoft\Graph\Generated\Models\DateTimeTimeZone;
use Microsoft\Graph\Generated\Models\Event;
use Microsoft\Graph\Generated\Models\ItemBody;
use Microsoft\Graph\Generated\Models\Location;
use Microsoft\Graph\GraphServiceClient;
use Throwable;

class MsGraphBookingCalendarSync implements BookingCalendarSyncInterface
{
    private ?GraphServiceClient $graph = null;

    public function __construct(
        private readonly Client $client,
    ) {}

    public function createEvent(Booking $booking): ?string
    {
        $upn = $this->driverUpn($booking);

        if (! $upn) {
            return null;
        }

        try {
            $booking->loadMissing(['vehicle', 'driver']);

            $created = $this->graph()
                ->users()
                ->byUserId($upn)
                ->calendar()
                ->events()
                ->post($this->buildEvent($booking))
                ->wait();

            return $created?->getId();
        } catch (Throwable $exception) {
            Log::error('Fuhrpark calendar sync create failed', [
                'booking_id' => $booking->id,
                'driver_id' => $booking->driver_id,
                'message' => $exception->getMessage(),
            ]);

            return null;
        }
    }

    public function updateEvent(Booking $booking, CarbonInterface $start, CarbonInterface $end): void
    {
        $upn = $this->driverUpn($booking);
        $eventId = $booking->ms_graph_event_id;

        if (! $upn || ! $eventId) {
            return;
        }

        try {
            $booking->loadMissing(['vehicle', 'driver']);

            $patch = new Event;
            $patch->setSubject($this->eventSubject($booking));
            $patch->setBody($this->buildEventBody($booking));
            $patch->setLocation($this->buildEventLocation($booking));
            $patch->setStart($this->toDateTimeTimeZone($start));
            $patch->setEnd($this->toDateTimeTimeZone($end));

            $this->graph()
                ->users()
                ->byUserId($upn)
                ->events()
                ->byEventId($eventId)
                ->patch($patch)
                ->wait();
        } catch (Throwable $exception) {
            Log::error('Fuhrpark calendar sync update failed', [
                'booking_id' => $booking->id,
                'event_id' => $eventId,
                'message' => $exception->getMessage(),
            ]);
        }
    }

    public function deleteEvent(Booking $booking): void
    {
        $upn = $this->driverUpn($booking);
        $eventId = $booking->ms_graph_event_id;

        if (! $upn || ! $eventId) {
            return;
        }

        try {
            $this->graph()
                ->users()
                ->byUserId($upn)
                ->events()
                ->byEventId($eventId)
                ->delete()
                ->wait();
        } catch (Throwable $exception) {
            Log::error('Fuhrpark calendar sync delete failed', [
                'booking_id' => $booking->id,
                'event_id' => $eventId,
                'message' => $exception->getMessage(),
            ]);
        }
    }

    private function graph(): GraphServiceClient
    {
        return $this->graph ??= ($this->client)();
    }

    private function driverUpn(Booking $booking): ?string
    {
        $booking->loadMissing('driver');
        $driver = $booking->driver;

        if (! $driver || blank($driver->username ?? null)) {
            return null;
        }

        return $driver->upn;
    }

    private function buildEvent(Booking $booking): Event
    {
        $event = new Event;
        $event->setSubject($this->eventSubject($booking));
        $event->setBody($this->buildEventBody($booking));
        $event->setLocation($this->buildEventLocation($booking));
        $event->setStart($this->toDateTimeTimeZone($booking->starts_at));
        $event->setEnd($this->toDateTimeTimeZone($booking->ends_at));
        $event->setIsAllDay(false);

        return $event;
    }

    private function eventSubject(Booking $booking): string
    {
        $booking->loadMissing('vehicle');

        return sprintf(
            'Fuhrpark %s – %s',
            $booking->vehicle->license_plate,
            $booking->description,
        );
    }

    private function buildEventBody(Booking $booking): ItemBody
    {
        $body = new ItemBody;
        $body->setContentType(new BodyType(BodyType::HTML));
        $body->setContent($this->buildEventBodyHtml($booking));

        return $body;
    }

    private function buildEventBodyHtml(Booking $booking): string
    {
        $booking->loadMissing(['vehicle', 'driver']);

        $lines = [
            '<p><strong>Fuhrpark-Buchung</strong></p>',
            '<ul>',
            '<li><strong>Fahrzeug:</strong> '.e($booking->vehicle->license_plate).'</li>',
            '<li><strong>Zweck:</strong> '.e($booking->description).'</li>',
            '<li><strong>Fahrer:</strong> '.e($booking->driver->name ?? '-').'</li>',
            '<li><strong>Von:</strong> '.e($booking->starts_at->format('d.m.Y H:i')).'</li>',
            '<li><strong>Bis:</strong> '.e($booking->ends_at->format('d.m.Y H:i')).'</li>',
            '</ul>',
        ];

        return implode('', $lines);
    }

    private function buildEventLocation(Booking $booking): Location
    {
        $booking->loadMissing('vehicle');

        $location = new Location;
        $location->setDisplayName($booking->vehicle->license_plate);

        return $location;
    }

    private function toDateTimeTimeZone(CarbonInterface $datetime): DateTimeTimeZone
    {
        $dateTimeTimeZone = new DateTimeTimeZone;
        $dateTimeTimeZone->setDateTime($datetime->toDateTimeString());
        $dateTimeTimeZone->setTimeZone($this->timezoneFor($datetime));

        return $dateTimeTimeZone;
    }

    private function timezoneFor(CarbonInterface $datetime): string
    {
        $timezone = $datetime->getTimezone()->getName();

        if (str_starts_with($timezone, '+') || str_starts_with($timezone, '-')) {
            return 'Europe/Berlin';
        }

        return $timezone;
    }
}
