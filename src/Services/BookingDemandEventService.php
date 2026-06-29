<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppFuhrpark\Services;

use Carbon\CarbonInterface;
use Hwkdo\IntranetAppFuhrpark\Enums\BookingDemandReason;
use Hwkdo\IntranetAppFuhrpark\Enums\BookingDemandSource;
use Hwkdo\IntranetAppFuhrpark\Models\BookingDemandEvent;
use Illuminate\Support\Carbon;

class BookingDemandEventService
{
    public function record(
        int $userId,
        CarbonInterface $startsAt,
        CarbonInterface $endsAt,
        BookingDemandReason $reason,
        BookingDemandSource $source,
        ?int $standortId = null,
        ?int $vehicleCategoryId = null,
        ?int $vehicleId = null,
        ?int $driverId = null,
        bool $hadAlternativeCategory = false,
    ): ?BookingDemandEvent {
        if ($this->isDuplicate(
            $userId,
            $startsAt,
            $endsAt,
            $reason,
            $source,
            $standortId,
            $vehicleCategoryId,
        )) {
            return null;
        }

        return BookingDemandEvent::query()->create([
            'user_id' => $userId,
            'standort_id' => $standortId,
            'vehicle_category_id' => $vehicleCategoryId,
            'vehicle_id' => $vehicleId,
            'driver_id' => $driverId,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'reason' => $reason,
            'source' => $source,
            'had_alternative_category' => $hadAlternativeCategory,
        ]);
    }

    private function isDuplicate(
        int $userId,
        CarbonInterface $startsAt,
        CarbonInterface $endsAt,
        BookingDemandReason $reason,
        BookingDemandSource $source,
        ?int $standortId,
        ?int $vehicleCategoryId,
    ): bool {
        $windowStart = Carbon::now()->subHour();

        return BookingDemandEvent::query()
            ->where('user_id', $userId)
            ->where('reason', $reason)
            ->where('source', $source)
            ->where('starts_at', $startsAt)
            ->where('ends_at', $endsAt)
            ->when(
                $standortId !== null,
                fn ($query) => $query->where('standort_id', $standortId),
                fn ($query) => $query->whereNull('standort_id'),
            )
            ->when(
                $vehicleCategoryId !== null,
                fn ($query) => $query->where('vehicle_category_id', $vehicleCategoryId),
                fn ($query) => $query->whereNull('vehicle_category_id'),
            )
            ->where('created_at', '>=', $windowStart)
            ->exists();
    }
}
