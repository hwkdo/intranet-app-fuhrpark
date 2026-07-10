<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppFuhrpark\Mcp\Support;

use Carbon\Carbon;
use Carbon\CarbonInterface;
use Hwkdo\IntranetAppFuhrpark\Data\AvailabilityResult;
use Hwkdo\IntranetAppFuhrpark\Data\CategoryAvailabilityData;
use Hwkdo\IntranetAppFuhrpark\Models\Booking;
use Hwkdo\IntranetAppFuhrpark\Models\Vehicle;
use Hwkdo\IntranetAppFuhrpark\Services\BookingAvailabilityService;
use Illuminate\Validation\ValidationException;

class McpRescheduleSupport
{
    /**
     * @return array{0: CarbonInterface, 1: CarbonInterface}
     */
    public static function parsePeriod(string $startsAt, string $endsAt): array
    {
        $start = Carbon::parse($startsAt);
        $end = Carbon::parse($endsAt);

        if ($end->lte($start)) {
            throw ValidationException::withMessages([
                'ends_at' => ['Das Ende muss nach dem Beginn liegen.'],
            ]);
        }

        return [$start, $end];
    }

    public static function findAlternatives(Booking $booking, CarbonInterface $start, CarbonInterface $end): AvailabilityResult
    {
        $booking->loadMissing('vehicle.category');

        return app(BookingAvailabilityService::class)->findAlternatives($booking, $start, $end);
    }

    /**
     * @return array<string, mixed>
     */
    public static function formatAvailability(AvailabilityResult $result, Booking $booking, bool $canSelectVehicle): array
    {
        $sameCategory = $result->sameCategory
            ->map(fn (Vehicle $vehicle): array => self::formatVehicle($vehicle, $booking))
            ->values()
            ->all();

        $otherCategories = collect($result->otherCategories)
            ->map(fn (CategoryAvailabilityData $group): array => [
                'vehicle_category_id' => $group->category->id,
                'name' => $group->category->name,
                'available_vehicles_count' => $group->vehicles->count(),
                'vehicles' => $canSelectVehicle
                    ? $group->vehicles->map(fn (Vehicle $vehicle): array => self::formatVehicle($vehicle))->values()->all()
                    : [],
            ])
            ->values()
            ->all();

        $defaultCategoryId = $result->hasSameCategoryAlternatives()
            ? $booking->vehicle->vehicle_category_id
            : null;

        return [
            'none_available' => $result->noneAvailable,
            'can_select_vehicle' => $canSelectVehicle,
            'same_category_available' => $result->hasSameCategoryAlternatives(),
            'other_categories_available' => $result->hasOtherCategoryAlternatives(),
            'current_vehicle_category_id' => $booking->vehicle->vehicle_category_id,
            'default_vehicle_category_id' => $defaultCategoryId,
            'same_category_vehicles' => $sameCategory,
            'other_categories' => $otherCategories,
        ];
    }

    public static function isVehicleAllowed(AvailabilityResult $result, int $vehicleId): bool
    {
        if ($result->sameCategory->contains('id', $vehicleId)) {
            return true;
        }

        foreach ($result->otherCategories as $group) {
            if ($group->vehicles->contains('id', $vehicleId)) {
                return true;
            }
        }

        return false;
    }

    public static function isCategoryAllowed(AvailabilityResult $result, Booking $booking, int $categoryId): bool
    {
        if ($result->hasSameCategoryAlternatives() && $booking->vehicle->vehicle_category_id === $categoryId) {
            return true;
        }

        foreach ($result->otherCategories as $group) {
            if ($group->category->id === $categoryId) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<string, mixed>
     */
    public static function formatVehicle(Vehicle $vehicle, ?Booking $booking = null): array
    {
        return [
            'id' => $vehicle->id,
            'license_plate' => $vehicle->license_plate,
            'manufacturer' => $vehicle->manufacturer,
            'model' => $vehicle->model,
            'vehicle_category_id' => $vehicle->vehicle_category_id,
            'is_current_vehicle' => $booking !== null && (int) $vehicle->id === (int) $booking->vehicle_id,
        ];
    }
}
