<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppFuhrpark\Services;

use Carbon\CarbonInterface;
use Hwkdo\IntranetAppFuhrpark\Models\Vehicle;

class ElectricVehicleService
{
    public function effectiveEnd(Vehicle $vehicle, CarbonInterface $end, ?int $electricRouteKm): CarbonInterface
    {
        if (! $vehicle->isElectric() || ! $electricRouteKm) {
            return $end;
        }

        return $end->copy()->addMinutes($this->chargeMinutesForRoute($vehicle, $electricRouteKm));
    }

    public function chargeMinutesForRoute(Vehicle $vehicle, int $routeKm): int
    {
        $chargeMinutes = $vehicle->electric_charge_minutes ?? $vehicle->category?->electric_charge_minutes_avg ?? 60;
        $range = $vehicle->effectiveElectricRangeKm() ?? $vehicle->category?->electric_range_avg_km ?? 200;

        if ($range <= 0) {
            return $chargeMinutes;
        }

        return (int) ceil(($chargeMinutes / $range) * $routeKm);
    }
}
