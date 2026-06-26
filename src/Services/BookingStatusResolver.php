<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppFuhrpark\Services;

use Carbon\CarbonInterface;
use Hwkdo\IntranetAppFuhrpark\Enums\BookingStatus;
use Hwkdo\IntranetAppFuhrpark\Models\Booking;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;

class BookingStatusResolver
{
    public function resolve(Booking $booking): BookingStatus
    {
        $now = now();

        if ($booking->starts_at > $now) {
            return BookingStatus::Reserved;
        }

        if ($booking->handout && ! $booking->returnRecord) {
            return BookingStatus::HandedOut;
        }

        if ($booking->handout && $booking->returnRecord && ! $booking->logbookEntry) {
            return BookingStatus::Returned;
        }

        if ($booking->handout && $booking->returnRecord && $booking->logbookEntry) {
            return BookingStatus::Completed;
        }

        $limit = $booking->starts_at->copy()->addDay();

        if ($now < $limit) {
            return BookingStatus::Overdue;
        }

        return BookingStatus::NoShow;
    }

    public function isAwaitingHandoutToday(Booking $booking): bool
    {
        if ($booking->purpose?->isBlocking()) {
            return false;
        }

        if ($booking->handout !== null) {
            return false;
        }

        if (! $booking->starts_at->isSameDay(now())) {
            return false;
        }

        return in_array($this->resolve($booking), [
            BookingStatus::Reserved,
            BookingStatus::Overdue,
        ], true);
    }

    public function isCurrentlyHandedOut(Booking $booking): bool
    {
        if ($booking->purpose?->isBlocking()) {
            return false;
        }

        return $this->resolve($booking) === BookingStatus::HandedOut;
    }

    public function isAwaitingReturnToday(Booking $booking): bool
    {
        if (! $this->isCurrentlyHandedOut($booking)) {
            return false;
        }

        return $booking->ends_at->isSameDay(now());
    }

    public function canBeCancelledByDriver(Booking $booking): bool
    {
        if ($booking->purpose?->isBlocking()) {
            return false;
        }

        $status = $this->resolve($booking);

        if (in_array($status, [
            BookingStatus::HandedOut,
            BookingStatus::Returned,
            BookingStatus::Completed,
        ], true)) {
            return false;
        }

        if ($booking->handout !== null) {
            return false;
        }

        return in_array($status, [
            BookingStatus::Reserved,
            BookingStatus::Overdue,
            BookingStatus::NoShow,
        ], true);
    }

    public function canCancelWithoutReason(Booking $booking): bool
    {
        return $this->canBeCancelledByDriver($booking)
            && $this->resolve($booking) === BookingStatus::Reserved;
    }

    public function requiresCancellationReason(Booking $booking): bool
    {
        if (! $this->canBeCancelledByDriver($booking)) {
            return false;
        }

        return in_array($this->resolve($booking), [
            BookingStatus::Overdue,
            BookingStatus::NoShow,
        ], true);
    }

    public function openLogbookCountForDriver(Authenticatable|Model $driver): int
    {
        return Booking::query()
            ->where('driver_id', $driver->getKey())
            ->with(['handout.returnRecord', 'logbookEntry'])
            ->get()
            ->filter(fn (Booking $b): bool => $this->resolve($b) === BookingStatus::Returned)
            ->count();
    }

    public function noShowCountForDriver(Authenticatable|Model $driver): int
    {
        return Booking::query()
            ->where('driver_id', $driver->getKey())
            ->with(['handout.returnRecord', 'logbookEntry'])
            ->get()
            ->filter(fn (Booking $b): bool => $this->resolve($b) === BookingStatus::NoShow)
            ->count();
    }

    public function countByStatusForDriver(Authenticatable|Model $driver, BookingStatus $status): int
    {
        return Booking::query()
            ->where('driver_id', $driver->getKey())
            ->with(['handout.returnRecord', 'logbookEntry'])
            ->get()
            ->filter(fn (Booking $b): bool => $this->resolve($b) === $status)
            ->count();
    }

    public function bookingsOverlapping(CarbonInterface $start, CarbonInterface $end, ?int $vehicleId = null): bool
    {
        $query = Booking::query()
            ->where('starts_at', '<', $end)
            ->where('ends_at', '>', $start);

        if ($vehicleId) {
            $query->where('vehicle_id', $vehicleId);
        }

        return $query->exists();
    }
}
