<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppFuhrpark\Services;

use Carbon\CarbonInterface;
use Hwkdo\IntranetAppFuhrpark\Data\AvailabilityResult;
use Hwkdo\IntranetAppFuhrpark\Data\CategoryAvailabilityData;
use Hwkdo\IntranetAppFuhrpark\Models\Booking;
use Hwkdo\IntranetAppFuhrpark\Models\Vehicle;
use Hwkdo\IntranetAppFuhrpark\Models\VehicleCategory;
use Illuminate\Support\Collection;

class BookingAvailabilityService
{
    public function __construct(
        private readonly VehicleAvailabilityService $vehicleAvailabilityService,
    ) {}

    public function findAlternatives(
        Booking $booking,
        CarbonInterface $start,
        CarbonInterface $end,
        ?int $preferredCategoryId = null,
    ): AvailabilityResult {
        $vehicle = $booking->vehicle;
        $categoryId = $preferredCategoryId ?? $vehicle->vehicle_category_id;
        $standortId = $vehicle->standort_id;
        $excludeBookingIds = $this->excludeBookingIds($booking);
        $electricRouteKm = $booking->electric_route_km;

        $sameCategory = $this->vehicleAvailabilityService->findAvailable(
            $start,
            $end,
            $categoryId,
            $standortId,
            excludeBookingIds: $excludeBookingIds,
            electricRouteKm: $electricRouteKm,
        );

        $otherCategories = $this->findOtherCategoryAlternatives(
            $start,
            $end,
            $categoryId,
            $standortId,
            $excludeBookingIds,
            $electricRouteKm,
        );

        return new AvailabilityResult(
            sameCategory: $sameCategory,
            otherCategories: $otherCategories,
            noneAvailable: $sameCategory->isEmpty() && $otherCategories === [],
        );
    }

    /**
     * @param  array<int>  $excludeBookingIds
     * @return array<int, CategoryAvailabilityData>
     */
    private function findOtherCategoryAlternatives(
        CarbonInterface $start,
        CarbonInterface $end,
        int $categoryId,
        int $standortId,
        array $excludeBookingIds,
        ?int $electricRouteKm,
    ): array {
        $otherCategories = [];
        $categories = VehicleCategory::query()
            ->where('id', '!=', $categoryId)
            ->whereHas('vehicles', fn ($q) => $q->where('standort_id', $standortId)->where('active', true))
            ->orderBy('name')
            ->get();

        foreach ($categories as $category) {
            $vehicles = $this->vehicleAvailabilityService->findAvailable(
                $start,
                $end,
                $category->id,
                $standortId,
                excludeBookingIds: $excludeBookingIds,
                electricRouteKm: $electricRouteKm,
            );

            if ($vehicles->isNotEmpty()) {
                $otherCategories[] = new CategoryAvailabilityData($category, $vehicles);
            }
        }

        return $otherCategories;
    }

    /**
     * @return Collection<int, VehicleCategory>
     */
    public function findRescheduleCategories(Booking $booking, CarbonInterface $start, CarbonInterface $end): Collection
    {
        $vehicle = $booking->vehicle;
        $preferredCategoryId = $vehicle->vehicle_category_id;

        $categories = $this->vehicleAvailabilityService->findAvailableCategories(
            $start,
            $end,
            $vehicle->standort_id,
            $this->excludeBookingIds($booking),
            $booking->electric_route_km,
        );

        $preferred = $categories->firstWhere('id', $preferredCategoryId);
        $others = $categories->where('id', '!=', $preferredCategoryId)->sortBy('name')->values();

        if ($preferred) {
            return collect([$preferred])->concat($others);
        }

        return $others;
    }

    public function findBestAlternativeVehicle(
        Booking $booking,
        int $categoryId,
        ?int $excludeVehicleId = null,
    ): ?Vehicle {
        $vehicle = $booking->vehicle;

        return $this->vehicleAvailabilityService->findBestAvailable(
            $booking->starts_at,
            $booking->ends_at,
            $categoryId,
            $vehicle->standort_id,
            excludeVehicleId: $excludeVehicleId,
            excludeBookingIds: $this->excludeBookingIds($booking),
            electricRouteKm: $booking->electric_route_km,
        );
    }

    /**
     * @return array<int>
     */
    public function excludeBookingIds(Booking $booking): array
    {
        $ids = [$booking->id];

        $chargeLock = Booking::query()
            ->where('charge_lock_for_booking_id', $booking->id)
            ->value('id');

        if ($chargeLock) {
            $ids[] = (int) $chargeLock;
        }

        return $ids;
    }
}
