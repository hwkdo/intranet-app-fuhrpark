<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppFuhrpark\Contracts;

use Carbon\CarbonInterface;
use Hwkdo\IntranetAppFuhrpark\Models\Booking;

interface BookingCalendarSyncInterface
{
    public function createEvent(Booking $booking): ?string;

    public function updateEvent(Booking $booking, CarbonInterface $start, CarbonInterface $end): void;

    public function deleteEvent(Booking $booking): void;
}
