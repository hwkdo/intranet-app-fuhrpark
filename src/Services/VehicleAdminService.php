<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppFuhrpark\Services;

use Carbon\CarbonInterface;
use Hwkdo\IntranetAppFuhrpark\Enums\BookingPurpose;
use Hwkdo\IntranetAppFuhrpark\Events\FuhrparkBookingChanged;
use Hwkdo\IntranetAppFuhrpark\Models\Booking;
use Hwkdo\IntranetAppFuhrpark\Models\Vehicle;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class VehicleAdminService
{
    public function __construct(
        private readonly BookingAvailabilityService $availabilityService,
    ) {}

    public function deactivate(Vehicle $vehicle, Authenticatable $admin, string $reason): Vehicle
    {
        if (blank($reason)) {
            throw ValidationException::withMessages([
                'inactiveReason' => ['Bitte einen Grund für die Deaktivierung angeben.'],
            ]);
        }

        $conflicts = $this->conflictingBookingsForDeactivation($vehicle);

        if ($conflicts->isNotEmpty()) {
            throw ValidationException::withMessages([
                'inactiveReason' => ['Es bestehen noch Buchungen für dieses Fahrzeug. Bitte zuerst alle umbuchen oder löschen.'],
            ]);
        }

        $vehicle->update([
            'active' => false,
            'inactive_reason' => $reason,
            'inactive_by_user_id' => $admin->getAuthIdentifier(),
        ]);

        return $vehicle->fresh();
    }

    public function activate(Vehicle $vehicle): Vehicle
    {
        $vehicle->update([
            'active' => true,
            'inactive_reason' => null,
            'inactive_by_user_id' => null,
        ]);

        return $vehicle->fresh();
    }

    public function createLockBooking(
        Vehicle $vehicle,
        Authenticatable $admin,
        CarbonInterface $start,
        CarbonInterface $end,
        string $reason,
    ): Booking {
        if ($end->lte($start)) {
            throw ValidationException::withMessages([
                'lockEnd' => ['„Sperre bis“ muss nach „Sperre von“ liegen.'],
            ]);
        }

        if (blank($reason)) {
            throw ValidationException::withMessages([
                'lockReason' => ['Bitte einen Sperrgrund angeben.'],
            ]);
        }

        if (! $vehicle->active) {
            throw ValidationException::withMessages([
                'lockStart' => ['Das Fahrzeug ist nicht aktiv.'],
            ]);
        }

        $overlappingLock = Booking::query()
            ->where('vehicle_id', $vehicle->id)
            ->where('starts_at', '<', $end)
            ->where('ends_at', '>', $start)
            ->whereIn('purpose', [BookingPurpose::Lock, BookingPurpose::ChargeLock])
            ->exists();

        if ($overlappingLock) {
            throw ValidationException::withMessages([
                'lockStart' => ['Im gewählten Zeitraum besteht bereits eine Sperre.'],
            ]);
        }

        $conflicts = $this->conflictingBookingsForLock($vehicle, $start, $end);

        if ($conflicts->isNotEmpty()) {
            throw ValidationException::withMessages([
                'lockStart' => ['Im Sperrzeitraum bestehen noch Buchungen. Bitte zuerst alle umbuchen oder löschen.'],
            ]);
        }

        $booking = Booking::query()->create([
            'vehicle_id' => $vehicle->id,
            'user_id' => $admin->getAuthIdentifier(),
            'driver_id' => $admin->getAuthIdentifier(),
            'purpose' => BookingPurpose::Lock,
            'description' => 'Sperre',
            'lock_reason' => $reason,
            'lock_user_id' => $admin->getAuthIdentifier(),
            'starts_at' => $start,
            'ends_at' => $end,
        ]);

        $booking = $booking->fresh(['vehicle', 'driver']);

        FuhrparkBookingChanged::dispatch($booking, 'created');

        return $booking;
    }

    /**
     * @return Collection<int, Booking>
     */
    public function activeLockBookings(Vehicle $vehicle): Collection
    {
        return Booking::query()
            ->where('vehicle_id', $vehicle->id)
            ->where('purpose', BookingPurpose::Lock)
            ->where('ends_at', '>', now())
            ->orderBy('starts_at')
            ->get();
    }

    public function removeLockBooking(Vehicle $vehicle, int $bookingId): void
    {
        $booking = Booking::query()
            ->where('vehicle_id', $vehicle->id)
            ->whereKey($bookingId)
            ->where('purpose', BookingPurpose::Lock)
            ->firstOrFail();

        FuhrparkBookingChanged::dispatch($booking, 'cancelled');

        $booking->delete();
    }

    public function conflictingBookingsForLock(Vehicle $vehicle, CarbonInterface $start, CarbonInterface $end): Collection
    {
        return Booking::query()
            ->with(['driver', 'vehicle.category', 'handout'])
            ->where('vehicle_id', $vehicle->id)
            ->where('starts_at', '<', $end)
            ->where('ends_at', '>', $start)
            ->whereNull('purpose_note')
            ->whereDoesntHave('handout')
            ->orderBy('starts_at')
            ->get()
            ->filter(fn (Booking $booking): bool => ! in_array($booking->purpose, [BookingPurpose::Lock, BookingPurpose::ChargeLock], true))
            ->values();
    }

    /**
     * @return Collection<int, Booking>
     */
    public function conflictingBookingsForDeactivation(Vehicle $vehicle): Collection
    {
        return Booking::query()
            ->with(['driver', 'vehicle.category', 'handout'])
            ->where('vehicle_id', $vehicle->id)
            ->where('ends_at', '>', now())
            ->whereNull('purpose_note')
            ->whereDoesntHave('handout')
            ->orderBy('starts_at')
            ->get()
            ->filter(fn (Booking $booking): bool => ! in_array($booking->purpose, [BookingPurpose::Lock, BookingPurpose::ChargeLock], true))
            ->values();
    }

    public function updateAvailability(Vehicle $vehicle, ?CarbonInterface $from, ?CarbonInterface $until): Vehicle
    {
        if ($from && $until && $until->lt($from)) {
            throw ValidationException::withMessages([
                'available_until' => ['„Verfügbar bis“ muss nach „Verfügbar ab“ liegen.'],
            ]);
        }

        $conflicts = $this->conflictingBookingsForAvailability($vehicle, $from, $until);

        if ($conflicts->isNotEmpty()) {
            throw ValidationException::withMessages([
                'availableFrom' => ['Im gewählten Verfügbarkeitszeitraum bestehen noch Buchungen. Bitte zuerst alle umbuchen oder löschen.'],
            ]);
        }

        $vehicle->update([
            'available_from' => $from,
            'available_until' => $until,
        ]);

        return $vehicle->fresh();
    }

    public function conflictingBookings(Vehicle $vehicle, CarbonInterface $start, CarbonInterface $end): Collection
    {
        return Booking::query()
            ->with(['driver', 'vehicle'])
            ->where('vehicle_id', $vehicle->id)
            ->where('starts_at', '<', $end)
            ->where('ends_at', '>', $start)
            ->whereNull('purpose_note')
            ->get()
            ->filter(fn (Booking $b): bool => ! in_array($b->purpose, [BookingPurpose::Lock, BookingPurpose::ChargeLock], true));
    }

    /**
     * @return Collection<int, Booking>
     */
    public function conflictingBookingsForAvailability(
        Vehicle $vehicle,
        ?CarbonInterface $from,
        ?CarbonInterface $until,
    ): Collection {
        if (! $from && ! $until) {
            return collect();
        }

        return Booking::query()
            ->with(['driver', 'vehicle'])
            ->where('vehicle_id', $vehicle->id)
            ->whereNull('purpose_note')
            ->whereDoesntHave('handout')
            ->where(function ($query) use ($from, $until): void {
                if ($from) {
                    $query->orWhere('starts_at', '<', $from);
                }

                if ($until) {
                    $query->orWhere('ends_at', '>', $until);
                }
            })
            ->orderBy('starts_at')
            ->get()
            ->filter(fn (Booking $booking): bool => ! in_array($booking->purpose, [BookingPurpose::Lock, BookingPurpose::ChargeLock], true))
            ->values();
    }
}
