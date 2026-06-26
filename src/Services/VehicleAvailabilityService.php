<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppFuhrpark\Services;

use Carbon\CarbonInterface;
use Hwkdo\IntranetAppFuhrpark\Data\CategoryBookingOptionData;
use Hwkdo\IntranetAppFuhrpark\Models\Vehicle;
use Hwkdo\IntranetAppFuhrpark\Models\VehicleCategory;
use Illuminate\Support\Collection;

class VehicleAvailabilityService
{
    public function __construct(
        private readonly ElectricVehicleService $electricVehicleService,
    ) {}

    /**
     * @param  array<int>  $excludeBookingIds
     */
    public function isAvailable(
        Vehicle $vehicle,
        CarbonInterface $start,
        CarbonInterface $end,
        array $excludeBookingIds = [],
        ?int $electricRouteKm = null,
    ): bool {
        if (! $vehicle->active) {
            return false;
        }

        if ($vehicle->available_from && $start < $vehicle->available_from) {
            return false;
        }

        if ($vehicle->available_until && $end > $vehicle->available_until) {
            return false;
        }

        $effectiveEnd = $this->electricVehicleService->effectiveEnd($vehicle, $end, $electricRouteKm);

        $bookings = $vehicle->bookings()
            ->when($excludeBookingIds !== [], fn ($q) => $q->whereNotIn('id', $excludeBookingIds))
            ->where('ends_at', '>', $start)
            ->where('starts_at', '<', $effectiveEnd)
            ->exists();

        return ! $bookings;
    }

    /**
     * @param  array<int>  $excludeBookingIds
     * @return Collection<int, Vehicle>
     */
    public function findAvailable(
        CarbonInterface $start,
        CarbonInterface $end,
        ?int $categoryId = null,
        ?int $standortId = null,
        ?int $excludeVehicleId = null,
        array $excludeBookingIds = [],
        ?int $electricRouteKm = null,
    ): Collection {
        $query = Vehicle::query()->where('active', true);

        if ($categoryId) {
            $query->where('vehicle_category_id', $categoryId);
        }

        if ($standortId) {
            $query->where('standort_id', $standortId);
        }

        if ($excludeVehicleId) {
            $query->where('id', '!=', $excludeVehicleId);
        }

        return $query->get()->filter(
            fn (Vehicle $vehicle): bool => $this->isAvailable($vehicle, $start, $end, $excludeBookingIds, $electricRouteKm)
        )->values();
    }

    public function findBestAvailable(
        CarbonInterface $start,
        CarbonInterface $end,
        int $categoryId,
        int $standortId,
        ?int $excludeVehicleId = null,
        array $excludeBookingIds = [],
        ?int $electricRouteKm = null,
    ): ?Vehicle {
        $available = $this->findAvailable($start, $end, $categoryId, $standortId, $excludeVehicleId, $excludeBookingIds, $electricRouteKm);

        if ($available->isEmpty()) {
            return null;
        }

        if ($available->count() === 1) {
            return $available->first();
        }

        return $available->sortBy(fn (Vehicle $v): int => $this->currentKm($v))->first();
    }

    /**
     * @param  array<int>  $excludeBookingIds
     * @return Collection<int, VehicleCategory>
     */
    public function findAvailableCategories(
        CarbonInterface $start,
        CarbonInterface $end,
        int $standortId,
        array $excludeBookingIds = [],
        ?int $electricRouteKm = null,
    ): Collection {
        return VehicleCategory::query()
            ->whereHas('vehicles', fn ($q) => $q->where('standort_id', $standortId)->where('active', true))
            ->orderBy('name')
            ->get()
            ->filter(
                fn (VehicleCategory $category): bool => $this->findAvailable(
                    $start,
                    $end,
                    $category->id,
                    $standortId,
                    excludeBookingIds: $excludeBookingIds,
                    electricRouteKm: $electricRouteKm,
                )->isNotEmpty()
            )
            ->values();
    }

    /**
     * @param  array<int>  $excludeBookingIds
     * @return Collection<int, CategoryBookingOptionData>
     */
    public function categoryBookingOptions(
        CarbonInterface $start,
        CarbonInterface $end,
        int $standortId,
        array $excludeBookingIds = [],
        ?int $electricRouteKm = null,
        ?int $excludeVehicleId = null,
    ): Collection {
        return VehicleCategory::query()
            ->whereHas('vehicles', fn ($q) => $q->where('standort_id', $standortId)->where('active', true))
            ->orderBy('name')
            ->get()
            ->map(function (VehicleCategory $category) use ($start, $end, $standortId, $excludeBookingIds, $electricRouteKm, $excludeVehicleId): CategoryBookingOptionData {
                $isAvailable = $this->findAvailable(
                    $start,
                    $end,
                    $category->id,
                    $standortId,
                    excludeVehicleId: $excludeVehicleId,
                    excludeBookingIds: $excludeBookingIds,
                    electricRouteKm: $electricRouteKm,
                )->isNotEmpty();

                return new CategoryBookingOptionData($category, $isAvailable);
            })
            ->values();
    }

    /**
     * @param  array<int>  $excludeBookingIds
     * @return Collection<int, VehicleCategory>
     */
    public function categoriesWithAvailabilityAtStandort(int $standortId): Collection
    {
        return VehicleCategory::query()
            ->whereHas('vehicles', fn ($q) => $q->where('standort_id', $standortId)->where('active', true))
            ->orderBy('name')
            ->get();
    }

    private function currentKm(Vehicle $vehicle): int
    {
        if ($vehicle->is_new) {
            return $vehicle->initial_km;
        }

        $last = $vehicle->bookings()
            ->whereNotNull('km_end')
            ->where('ends_at', '<=', now())
            ->orderByDesc('ends_at')
            ->first();

        return $last?->km_end ?? $vehicle->initial_km;
    }
}
