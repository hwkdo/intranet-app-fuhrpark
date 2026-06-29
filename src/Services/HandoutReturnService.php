<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppFuhrpark\Services;

use Hwkdo\IntranetAppFuhrpark\Enums\BookingPurpose;
use Hwkdo\IntranetAppFuhrpark\Mail\VehicleDamageReportedMail;
use Hwkdo\IntranetAppFuhrpark\Models\Booking;
use Hwkdo\IntranetAppFuhrpark\Models\Handout;
use Hwkdo\IntranetAppFuhrpark\Models\IntranetAppFuhrparkSettings;
use Hwkdo\IntranetAppFuhrpark\Models\VehicleReturn;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;

class HandoutReturnService
{
    public function __construct(
        private readonly BookingStatusResolver $statusResolver,
    ) {}

    public function canHandout(Booking $booking): bool
    {
        return $this->predecessorForHandout($booking) === null;
    }

    public function predecessorForHandout(Booking $booking): ?Booking
    {
        $predecessor = $this->predecessorWithKm($booking);

        if ($predecessor === null || $predecessor->returnRecord !== null) {
            return null;
        }

        return $predecessor->loadMissing(['vehicle', 'driver', 'handout.returnRecord', 'logbookEntry']);
    }

    public function handout(Booking $booking, Authenticatable $processor, int $driverId, ?array $signatureData = null): Handout
    {
        if (! $this->canHandout($booking)) {
            throw ValidationException::withMessages([
                'booking' => ['Vorgängerbuchung wurde noch nicht zurückgegeben.'],
            ]);
        }

        if (blank($signatureData['data'] ?? null)) {
            throw ValidationException::withMessages([
                'signature' => ['Bitte erfassen Sie zuerst eine Unterschrift.'],
            ]);
        }

        $handout = Handout::query()->create([
            'booking_id' => $booking->id,
            'driver_id' => $driverId,
            'processed_by_user_id' => $processor->getAuthIdentifier(),
            'signature_data' => $signatureData,
        ]);

        $kmStart = $this->resolveKmStart($booking);
        $booking->update(['km_start' => $kmStart]);

        return $handout;
    }

    /**
     * @param  array<string, bool>  $checklist
     */
    public function returnVehicle(
        Handout $handout,
        Authenticatable $processor,
        int $driverId,
        int $kmEnd,
        array $checklist,
        bool $hasDamage,
        ?string $damageNote = null,
        ?array $signatureData = null,
    ): VehicleReturn {
        if (blank($signatureData['data'] ?? null)) {
            throw ValidationException::withMessages([
                'signature' => ['Bitte erfassen Sie zuerst eine Unterschrift.'],
            ]);
        }

        $return = VehicleReturn::query()->create([
            'handout_id' => $handout->id,
            'driver_id' => $driverId,
            'processed_by_user_id' => $processor->getAuthIdentifier(),
            'km_end' => $kmEnd,
            'checklist' => $checklist,
            'has_damage' => $hasDamage,
            'damage_note' => $damageNote,
            'signature_data' => $signatureData,
        ]);

        $booking = $handout->booking;
        $booking->update([
            'km_end' => $kmEnd,
            'ends_at' => min(now(), $booking->ends_at),
        ]);

        if ($hasDamage) {
            $this->notifyAdminsOfDamage($booking, $processor);
        }

        return $return;
    }

    private function resolveKmStart(Booking $booking): int
    {
        $predecessor = $this->predecessorWithKm($booking);

        if ($predecessor?->km_end) {
            return (int) $predecessor->km_end;
        }

        return (int) $booking->vehicle->initial_km;
    }

    private function predecessorWithKm(Booking $booking): ?Booking
    {
        return Booking::query()
            ->where('vehicle_id', $booking->vehicle_id)
            ->where('purpose', '!=', BookingPurpose::Lock)
            ->where('starts_at', '<', $booking->starts_at)
            ->whereNotNull('km_end')
            ->orderByDesc('starts_at')
            ->first();
    }

    private function notifyAdminsOfDamage(Booking $booking, Authenticatable $reporter): void
    {
        $roleName = IntranetAppFuhrparkSettings::current()->settings->adminNotifyRole;
        $role = Role::findByName($roleName, 'web');

        foreach ($role->users as $user) {
            Mail::to($user->email)->queue(new VehicleDamageReportedMail($booking, $reporter));
        }
    }
}
