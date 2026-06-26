<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppFuhrpark\Policies;

use Hwkdo\IntranetAppFuhrpark\Enums\BookingStatus;
use Hwkdo\IntranetAppFuhrpark\Models\Booking;
use Hwkdo\IntranetAppFuhrpark\Services\BookingStatusResolver;
use Illuminate\Contracts\Auth\Authenticatable;

class BookingPolicy
{
    public function __construct(
        private readonly BookingStatusResolver $statusResolver,
    ) {}

    public function view(Authenticatable $user, Booking $booking): bool
    {
        return $this->isInvolved($user, $booking)
            || $user->can('manage-app-fuhrpark')
            || $user->can('operate-app-fuhrpark-zentrale');
    }

    public function update(Authenticatable $user, Booking $booking): bool
    {
        if ($user->can('manage-app-fuhrpark')) {
            return true;
        }

        if (! $this->isInvolved($user, $booking)) {
            return false;
        }

        $status = $this->statusResolver->resolve($booking);

        return in_array($status, [BookingStatus::Reserved, BookingStatus::HandedOut], true);
    }

    public function cancel(Authenticatable $user, Booking $booking): bool
    {
        if (! $this->isInvolved($user, $booking)) {
            return false;
        }

        return $this->statusResolver->canBeCancelledByDriver($booking);
    }

    public function delete(Authenticatable $user, Booking $booking): bool
    {
        return $user->can('manage-app-fuhrpark');
    }

    public function handout(Authenticatable $user, Booking $booking): bool
    {
        return $user->can('operate-app-fuhrpark-zentrale') || $user->can('manage-app-fuhrpark');
    }

    public function returnVehicle(Authenticatable $user, Booking $booking): bool
    {
        return $this->handout($user, $booking);
    }

    public function viewLogbook(Authenticatable $user, Booking $booking): bool
    {
        if ($booking->logbookEntry === null) {
            return false;
        }

        if ($user->can('manage-app-fuhrpark')) {
            return true;
        }

        return $this->isInvolved($user, $booking);
    }

    private function isInvolved(Authenticatable $user, Booking $booking): bool
    {
        return (int) $user->getAuthIdentifier() === (int) $booking->user_id
            || (int) $user->getAuthIdentifier() === (int) $booking->driver_id;
    }
}
