<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppFuhrpark\Mcp\Support;

use Hwkdo\IntranetAppFuhrpark\Enums\BookingPurpose;
use Hwkdo\IntranetAppFuhrpark\Enums\BookingStatus;
use Hwkdo\IntranetAppFuhrpark\Models\Booking;
use Hwkdo\IntranetAppFuhrpark\Services\BookingStatusResolver;
use Illuminate\Support\Collection;

class McpMissingLogbookSupport
{
    /**
     * @return Collection<int, Booking>
     */
    public static function bookingsForDriver(int $driverId): Collection
    {
        $statusResolver = app(BookingStatusResolver::class);

        return Booking::query()
            ->where('driver_id', $driverId)
            ->whereNotIn('purpose', [BookingPurpose::Lock, BookingPurpose::ChargeLock])
            ->with(['vehicle.category', 'handout.returnRecord', 'logbookEntry'])
            ->orderByDesc('starts_at')
            ->get()
            ->filter(fn (Booking $booking): bool => $statusResolver->resolve($booking) === BookingStatus::Returned)
            ->values();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function summarizeForDriver(int $driverId, ?object $viewer = null): array
    {
        $presenter = app(McpBookingPresenter::class);

        return self::bookingsForDriver($driverId)
            ->map(fn (Booking $booking): array => $presenter->summarize($booking, $viewer))
            ->all();
    }
}
