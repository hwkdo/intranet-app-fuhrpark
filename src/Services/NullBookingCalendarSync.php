<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppFuhrpark\Services;

use Carbon\CarbonInterface;
use Hwkdo\IntranetAppFuhrpark\Contracts\BookingCalendarSyncInterface;
use Hwkdo\IntranetAppFuhrpark\Models\Booking;

class NullBookingCalendarSync implements BookingCalendarSyncInterface
{
    public function createEvent(Booking $booking): ?string
    {
        return null;
    }

    public function updateEvent(Booking $booking, CarbonInterface $start, CarbonInterface $end): void {}

    public function deleteEvent(Booking $booking): void {}
}
