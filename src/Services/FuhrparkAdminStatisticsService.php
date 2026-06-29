<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppFuhrpark\Services;

use Carbon\CarbonInterface;
use Hwkdo\IntranetAppFuhrpark\Enums\BookingDemandReason;
use Hwkdo\IntranetAppFuhrpark\Enums\BookingDemandSource;
use Hwkdo\IntranetAppFuhrpark\Enums\BookingPurpose;
use Hwkdo\IntranetAppFuhrpark\Models\Booking;
use Hwkdo\IntranetAppFuhrpark\Models\BookingDemandEvent;
use Hwkdo\IntranetAppFuhrpark\Models\Handout;
use Hwkdo\IntranetAppFuhrpark\Models\IntranetAppFuhrparkSettings;
use Hwkdo\IntranetAppFuhrpark\Models\Vehicle;
use Hwkdo\IntranetAppFuhrpark\Models\VehicleCategory;
use Hwkdo\IntranetAppFuhrpark\Models\VehicleReturn;
use Hwkdo\IntranetAppFuhrpark\Support\FuhrparkModels;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class FuhrparkAdminStatisticsService
{
  /**
   * @return array{
   *     period: string,
   *     period_label: string,
   *     overview: array{
   *         total_km: int,
   *         completed_trips: int,
   *         average_km_per_trip: float,
   *         total_bookings: int,
   *         commute_bookings: int,
   *         handouts: int,
   *         returns: int,
   *         active_vehicles: int,
   *         electric_route_km: int,
   *         combustion_route_km: int,
   *     },
   *     top_vehicle_by_km: ?array{license_plate: string, km: int, trips: int},
   *     top_vehicle_by_bookings: ?array{license_plate: string, bookings: int},
   *     top_driver_by_km: ?array{name: string, km: int, trips: int},
   *     top_driver_by_bookings: ?array{name: string, bookings: int},
   *     top_vehicles_by_km: list<array{license_plate: string, km: int, trips: int}>,
   *     top_drivers_by_km: list<array{name: string, km: int, trips: int}>,
   *     bookings_by_purpose: list<array{purpose: string, label: string, count: int}>,
   * }
   */
    public function collect(string $period = 'month'): array
    {
        [$from, $to, $periodLabel] = $this->resolvePeriod($period);

        $completedTripsQuery = $this->completedTripsQuery($from, $to);
        $tripBookingsQuery = $this->tripBookingsQuery($from, $to);

        $totalKm = (int) (clone $completedTripsQuery)->sum(DB::raw('km_end - km_start'));
        $completedTrips = (clone $completedTripsQuery)->count();
        $totalBookings = (clone $tripBookingsQuery)->count();
        $commuteBookings = (clone $tripBookingsQuery)->where('is_commute', true)->count();
        $electricRouteKm = (int) (clone $tripBookingsQuery)
            ->whereNotNull('electric_route_km')
            ->sum('electric_route_km');

        $combustionRouteKm = (int) (clone $completedTripsQuery)
            ->whereHas('vehicle', fn (Builder $query): Builder => $query->where('fuel_type', '!=', 'electric'))
            ->sum(DB::raw('km_end - km_start'));

        $handouts = Handout::query()
            ->whereHas('booking', fn (Builder $query) => $this->applyPeriod($query, $from, $to))
            ->count();

        $returns = VehicleReturn::query()
            ->whereHas('handout.booking', fn (Builder $query) => $this->applyPeriod($query, $from, $to))
            ->count();

        return [
            'period' => $period,
            'period_label' => $periodLabel,
            'overview' => [
                'total_km' => $totalKm,
                'completed_trips' => $completedTrips,
                'average_km_per_trip' => $completedTrips > 0 ? round($totalKm / $completedTrips, 1) : 0.0,
                'total_bookings' => $totalBookings,
                'commute_bookings' => $commuteBookings,
                'handouts' => $handouts,
                'returns' => $returns,
                'active_vehicles' => Vehicle::query()->where('active', true)->count(),
                'electric_route_km' => $electricRouteKm,
                'combustion_route_km' => $combustionRouteKm,
            ],
            'top_vehicle_by_km' => $this->topVehicleByKm($from, $to),
            'top_vehicle_by_bookings' => $this->topVehicleByBookings($from, $to),
            'top_driver_by_km' => $this->topDriverByKm($from, $to),
            'top_driver_by_bookings' => $this->topDriverByBookings($from, $to),
            'top_vehicles_by_km' => $this->topVehiclesByKm($from, $to, limit: 5),
            'top_drivers_by_km' => $this->topDriversByKm($from, $to, limit: 5),
            'bookings_by_purpose' => $this->bookingsByPurpose($from, $to),
            'utilization' => $this->collectUtilization($from, $to),
            'unmet_demand' => $this->collectUnmetDemand($from, $to),
        ];
    }

    /**
     * @return array{
     *     total_events: int,
     *     hard_shortage_events: int,
     *     with_alternative_category: int,
     *     successful_bookings: int,
     *     denial_rate_percent: float,
     *     by_source: list<array{source: string, label: string, count: int}>,
     *     by_reason: list<array{reason: string, label: string, count: int}>,
     *     by_category: list<array{category: string, count: int}>,
     *     by_standort: list<array{standort: string, count: int}>,
     * }
     */
    private function collectUnmetDemand(?CarbonInterface $from, ?CarbonInterface $to): array
    {
        $eventsQuery = BookingDemandEvent::query()
            ->when($from !== null, fn (Builder $query): Builder => $query->where('created_at', '>=', $from))
            ->when($to !== null, fn (Builder $query): Builder => $query->where('created_at', '<=', $to));

        $totalEvents = (clone $eventsQuery)->count();
        $hardShortageEvents = (clone $eventsQuery)->where('had_alternative_category', false)->count();
        $withAlternativeCategory = (clone $eventsQuery)->where('had_alternative_category', true)->count();
        $successfulBookings = (clone $this->tripBookingsQuery($from, $to))->count();

        $denialRatePercent = ($totalEvents + $successfulBookings) > 0
            ? round(($totalEvents / ($totalEvents + $successfulBookings)) * 100, 1)
            : 0.0;

        $bySource = (clone $eventsQuery)
            ->toBase()
            ->select('source', DB::raw('COUNT(*) as event_count'))
            ->groupBy('source')
            ->orderByDesc('event_count')
            ->get()
            ->map(fn ($row): array => [
                'source' => (string) $row->source,
                'label' => BookingDemandSource::from((string) $row->source)->label(),
                'count' => (int) $row->event_count,
            ])
            ->values()
            ->all();

        $byReason = (clone $eventsQuery)
            ->toBase()
            ->select('reason', DB::raw('COUNT(*) as event_count'))
            ->groupBy('reason')
            ->orderByDesc('event_count')
            ->get()
            ->map(fn ($row): array => [
                'reason' => (string) $row->reason,
                'label' => BookingDemandReason::from((string) $row->reason)->label(),
                'count' => (int) $row->event_count,
            ])
            ->values()
            ->all();

        $categoryCounts = (clone $eventsQuery)
            ->whereNotNull('vehicle_category_id')
            ->toBase()
            ->select('vehicle_category_id', DB::raw('COUNT(*) as event_count'))
            ->groupBy('vehicle_category_id')
            ->orderByDesc('event_count')
            ->limit(5)
            ->pluck('event_count', 'vehicle_category_id');

        $categoryNames = VehicleCategory::query()
            ->whereIn('id', $categoryCounts->keys())
            ->pluck('name', 'id');

        $byCategory = $categoryCounts
            ->map(fn (int|string $count, int|string $categoryId): array => [
                'category' => $categoryNames->get((int) $categoryId, 'Unbekannt'),
                'count' => (int) $count,
            ])
            ->values()
            ->all();

        $standortCounts = (clone $eventsQuery)
            ->whereNotNull('standort_id')
            ->toBase()
            ->select('standort_id', DB::raw('COUNT(*) as event_count'))
            ->groupBy('standort_id')
            ->orderByDesc('event_count')
            ->limit(5)
            ->pluck('event_count', 'standort_id');

        $standortNames = FuhrparkModels::standort()::query()
            ->whereIn('id', $standortCounts->keys())
            ->pluck('name', 'id');

        $byStandort = $standortCounts
            ->map(fn (int|string $count, int|string $standortId): array => [
                'standort' => $standortNames->get((int) $standortId, 'Unbekannt'),
                'count' => (int) $count,
            ])
            ->values()
            ->all();

        return [
            'total_events' => $totalEvents,
            'hard_shortage_events' => $hardShortageEvents,
            'with_alternative_category' => $withAlternativeCategory,
            'successful_bookings' => $successfulBookings,
            'denial_rate_percent' => $denialRatePercent,
            'by_source' => $bySource,
            'by_reason' => $byReason,
            'by_category' => $byCategory,
            'by_standort' => $byStandort,
        ];
    }

    /**
     * @return array{
     *     fleet_size: int,
     *     booked_vehicle_hours: float,
     *     available_vehicle_hours: float,
     *     average_utilization_percent: float,
     *     peak_concurrent_bookings: int,
     *     peak_utilization_percent: float,
     *     full_capacity_days: int,
     *     high_demand_days: int,
     *     vehicles_without_bookings: int,
     *     vehicles_low_utilization: int,
     *     assessment: array{status: string, label: string, hint: string},
     *     vehicles: list<array{license_plate: string, booked_hours: float, available_hours: float, utilization_percent: float, bookings: int}>,
     *     business_hours_label: string,
     * }
     */
    private function collectUtilization(?CarbonInterface $from, ?CarbonInterface $to): array
    {
        [$windowFrom, $windowTo] = $this->resolveUtilizationWindow($from, $to);
        $businessHoursLabel = $this->businessHoursLabel();

        $candidateVehicles = Vehicle::query()
            ->where('active', true)
            ->orderBy('license_plate')
            ->get();

        $vehiclesInScope = $candidateVehicles
            ->filter(fn (Vehicle $vehicle): bool => $this->vehicleIsAvailableInPeriod($vehicle, $windowFrom, $windowTo))
            ->values();

        if ($vehiclesInScope->isEmpty()) {
            return [
                'fleet_size' => 0,
                'booked_vehicle_hours' => 0.0,
                'available_vehicle_hours' => 0.0,
                'average_utilization_percent' => 0.0,
                'peak_concurrent_bookings' => 0,
                'peak_utilization_percent' => 0.0,
                'full_capacity_days' => 0,
                'high_demand_days' => 0,
                'vehicles_without_bookings' => 0,
                'vehicles_low_utilization' => 0,
                'assessment' => [
                    'status' => 'balanced',
                    'label' => 'Keine verfügbaren Fahrzeuge',
                    'hint' => 'Im gewählten Zeitraum stand kein aktives Fahrzeug zur Verfügung.',
                ],
                'vehicles' => [],
                'business_hours_label' => $businessHoursLabel,
            ];
        }

        $vehiclesById = $vehiclesInScope->keyBy('id');
        $vehicleIdsInScope = $vehiclesInScope->pluck('id')->all();

        $bookings = $this->tripBookingsQuery($windowFrom, $windowTo)
            ->whereIn('vehicle_id', $vehicleIdsInScope)
            ->get(['id', 'vehicle_id', 'starts_at', 'ends_at']);

        $availableVehicleHours = 0.0;
        $availableHoursPerVehicle = [];

        foreach ($vehiclesInScope as $vehicle) {
            $hours = $this->availableBusinessHoursForVehicle($vehicle, $windowFrom, $windowTo);
            $availableHoursPerVehicle[$vehicle->id] = $hours;
            $availableVehicleHours += $hours;
        }

        $utilizedVehicles = $vehiclesInScope
            ->filter(fn (Vehicle $vehicle): bool => ($availableHoursPerVehicle[$vehicle->id] ?? 0.0) > 0)
            ->values();

        $fleetSize = $utilizedVehicles->count();

        $bookedVehicleHours = 0.0;
        /** @var array<int, float> $bookedHoursPerVehicle */
        $bookedHoursPerVehicle = [];
        /** @var array<int, int> $bookingCountPerVehicle */
        $bookingCountPerVehicle = [];

        foreach ($bookings as $booking) {
            $vehicle = $vehiclesById->get($booking->vehicle_id);

            if ($vehicle === null) {
                continue;
            }

            $hours = $this->bookedBusinessHoursForVehicle(
                $vehicle,
                $booking->starts_at,
                $booking->ends_at,
                $windowFrom,
                $windowTo,
            );

            if ($hours <= 0) {
                continue;
            }

            $bookedVehicleHours += $hours;
            $bookedHoursPerVehicle[$booking->vehicle_id] = ($bookedHoursPerVehicle[$booking->vehicle_id] ?? 0) + $hours;
            $bookingCountPerVehicle[$booking->vehicle_id] = ($bookingCountPerVehicle[$booking->vehicle_id] ?? 0) + 1;
        }

        $averageUtilizationPercent = $availableVehicleHours > 0
            ? round(($bookedVehicleHours / $availableVehicleHours) * 100, 1)
            : 0.0;

        $peakStats = $this->calculatePeakUtilization($bookings, $windowFrom, $windowTo, $utilizedVehicles);

        $vehiclesWithoutBookings = $utilizedVehicles
            ->filter(fn (Vehicle $vehicle): bool => ! isset($bookingCountPerVehicle[$vehicle->id]))
            ->count();

        $vehicleRows = $utilizedVehicles->map(function (Vehicle $vehicle) use (
            $availableHoursPerVehicle,
            $bookedHoursPerVehicle,
            $bookingCountPerVehicle,
        ): array {
            $availableHours = $availableHoursPerVehicle[$vehicle->id] ?? 0.0;
            $bookedHours = $bookedHoursPerVehicle[$vehicle->id] ?? 0.0;

            return [
                'license_plate' => $vehicle->license_plate,
                'booked_hours' => round($bookedHours, 1),
                'available_hours' => round($availableHours, 1),
                'utilization_percent' => $availableHours > 0
                    ? round(($bookedHours / $availableHours) * 100, 1)
                    : 0.0,
                'bookings' => $bookingCountPerVehicle[$vehicle->id] ?? 0,
            ];
        })
            ->sortByDesc('utilization_percent')
            ->values()
            ->all();

        $vehiclesLowUtilization = collect($vehicleRows)
            ->filter(fn (array $row): bool => $row['utilization_percent'] < 15)
            ->count();

        return [
            'fleet_size' => $fleetSize,
            'booked_vehicle_hours' => round($bookedVehicleHours, 1),
            'available_vehicle_hours' => round($availableVehicleHours, 1),
            'average_utilization_percent' => $averageUtilizationPercent,
            'peak_concurrent_bookings' => $peakStats['peak_concurrent'],
            'peak_utilization_percent' => $peakStats['peak_percent'],
            'full_capacity_days' => $peakStats['full_capacity_days'],
            'high_demand_days' => $peakStats['high_demand_days'],
            'vehicles_without_bookings' => $vehiclesWithoutBookings,
            'vehicles_low_utilization' => $vehiclesLowUtilization,
            'assessment' => $this->assessFleetUtilization(
                $averageUtilizationPercent,
                $peakStats['peak_percent'],
                $vehiclesWithoutBookings,
                $fleetSize,
                $peakStats['full_capacity_days'],
            ),
            'vehicles' => $vehicleRows,
            'business_hours_label' => $businessHoursLabel,
        ];
    }

    /**
     * @return array{start: int, end: int, days: list<int>}
     */
    private function businessHoursConfig(): array
    {
        $settings = IntranetAppFuhrparkSettings::current()?->settings;

        $start = $settings?->utilizationBusinessHourStart
            ?? (int) config('intranet-app-fuhrpark.utilization.business_hour_start', 7);
        $end = $settings?->utilizationBusinessHourEnd
            ?? (int) config('intranet-app-fuhrpark.utilization.business_hour_end', 18);

        /** @var list<int> $days */
        $days = $settings !== null
            ? $this->normalizeBusinessDays($settings->utilizationBusinessDays)
            : $this->normalizeBusinessDays(config('intranet-app-fuhrpark.utilization.business_days', [1, 2, 3, 4, 5]));

        $start = max(0, min(23, $start));
        $end = max(0, min(23, $end));

        if ($end <= $start) {
            $end = min(23, $start + 1);
        }

        return [
            'start' => $start,
            'end' => $end,
            'days' => $days,
        ];
    }

    /**
     * @param  array<int, mixed>|null  $days
     * @return list<int>
     */
    private function normalizeBusinessDays(?array $days): array
    {
        if ($days === null || $days === []) {
            return [1, 2, 3, 4, 5];
        }

        $normalized = collect($days)
            ->map(fn (mixed $day): int => (int) $day)
            ->filter(fn (int $day): bool => $day >= 1 && $day <= 7)
            ->unique()
            ->sort()
            ->values()
            ->all();

        return $normalized !== [] ? $normalized : [1, 2, 3, 4, 5];
    }

    private function businessHoursLabel(): string
    {
        $config = $this->businessHoursConfig();

        $dayLabels = [
            1 => 'Mo',
            2 => 'Di',
            3 => 'Mi',
            4 => 'Do',
            5 => 'Fr',
            6 => 'Sa',
            7 => 'So',
        ];

        $days = collect($config['days'])
            ->map(fn (int $day): string => $dayLabels[$day] ?? (string) $day)
            ->implode('–');

        if ($days === 'Mo–Di–Mi–Do–Fr' || $config['days'] === [1, 2, 3, 4, 5]) {
            $days = 'Mo–Fr';
        }

        return sprintf(
            '%02d:00–%02d:00 (%s)',
            $config['start'],
            $config['end'],
            $days,
        );
    }

    /**
     * @param  callable(CarbonInterface, CarbonInterface): void  $callback
     */
    private function eachBusinessHourSegment(
        CarbonInterface $from,
        CarbonInterface $to,
        callable $callback,
    ): void {
        $config = $this->businessHoursConfig();
        $cursor = $from->copy()->startOfDay();
        $end = $to->copy()->endOfDay();

        while ($cursor->lessThanOrEqualTo($end)) {
            if (in_array($cursor->isoWeekday(), $config['days'], true)) {
                $segmentStart = $cursor->copy()->setTime($config['start'], 0);
                $segmentEnd = $cursor->copy()->setTime($config['end'], 0);

                if ($segmentEnd->greaterThan($from)) {
                    $clippedStart = $segmentStart->greaterThan($from) ? $segmentStart : $from;
                    $clippedEnd = $segmentEnd->lessThan($to) ? $segmentEnd : $to;

                    if ($clippedEnd->greaterThan($clippedStart)) {
                        $callback($clippedStart, $clippedEnd);
                    }
                }
            }

            $cursor->addDay();
        }
    }

    private function vehicleIsAvailableInPeriod(
        Vehicle $vehicle,
        CarbonInterface $from,
        CarbonInterface $to,
    ): bool {
        if (! $vehicle->active) {
            return false;
        }

        if ($vehicle->available_until !== null && $vehicle->available_until->lessThanOrEqualTo($from)) {
            return false;
        }

        if ($vehicle->available_from !== null && $vehicle->available_from->greaterThanOrEqualTo($to)) {
            return false;
        }

        return true;
    }

    private function availableBusinessHoursForVehicle(
        Vehicle $vehicle,
        CarbonInterface $from,
        CarbonInterface $to,
    ): float {
        if (! $this->vehicleIsAvailableInPeriod($vehicle, $from, $to)) {
            return 0.0;
        }

        $hours = 0.0;

        $this->eachBusinessHourSegment($from, $to, function (CarbonInterface $segmentStart, CarbonInterface $segmentEnd) use ($vehicle, &$hours): void {
            $windowStart = $segmentStart;
            $windowEnd = $segmentEnd;

            if ($vehicle->available_from !== null && $vehicle->available_from->greaterThan($windowStart)) {
                $windowStart = $vehicle->available_from->copy();
            }

            if ($vehicle->available_until !== null && $vehicle->available_until->lessThan($windowEnd)) {
                $windowEnd = $vehicle->available_until->copy();
            }

            if ($windowEnd->greaterThan($windowStart)) {
                $hours += $windowStart->floatDiffInHours($windowEnd);
            }
        });

        return $hours;
    }

    private function bookedBusinessHoursForVehicle(
        Vehicle $vehicle,
        CarbonInterface $bookingStart,
        CarbonInterface $bookingEnd,
        CarbonInterface $from,
        CarbonInterface $to,
    ): float {
        if (! $this->vehicleIsAvailableInPeriod($vehicle, $from, $to)) {
            return 0.0;
        }

        $hours = 0.0;

        $this->eachBusinessHourSegment($from, $to, function (CarbonInterface $segmentStart, CarbonInterface $segmentEnd) use ($vehicle, $bookingStart, $bookingEnd, &$hours): void {
            $overlapStart = $bookingStart->greaterThan($segmentStart) ? $bookingStart : $segmentStart;
            $overlapEnd = $bookingEnd->lessThan($segmentEnd) ? $bookingEnd : $segmentEnd;

            if ($overlapEnd->lessThanOrEqualTo($overlapStart)) {
                return;
            }

            $windowStart = $overlapStart;
            $windowEnd = $overlapEnd;

            if ($vehicle->available_from !== null && $vehicle->available_from->greaterThan($windowStart)) {
                $windowStart = $vehicle->available_from->copy();
            }

            if ($vehicle->available_until !== null && $vehicle->available_until->lessThan($windowEnd)) {
                $windowEnd = $vehicle->available_until->copy();
            }

            if ($windowEnd->greaterThan($windowStart)) {
                $hours += $windowStart->floatDiffInHours($windowEnd);
            }
        });

        return $hours;
    }

    /**
     * @return array{0: CarbonInterface, 1: CarbonInterface}
     */
    private function resolveUtilizationWindow(?CarbonInterface $from, ?CarbonInterface $to): array
    {
        if ($from !== null && $to !== null) {
            return [$from, $to];
        }

        $earliestBooking = Booking::query()->min('starts_at');
        $windowFrom = $earliestBooking !== null
            ? Carbon::parse($earliestBooking)->startOfDay()
            : now()->subYear()->startOfDay();

        return [$windowFrom, now()];
    }

    /**
     * @param  Collection<int, Booking>  $bookings
     * @param  Collection<int, Vehicle>  $vehiclesInScope
     * @return array{peak_concurrent: int, peak_percent: float, full_capacity_days: int, high_demand_days: int}
     */
    private function calculatePeakUtilization(
        Collection $bookings,
        CarbonInterface $from,
        CarbonInterface $to,
        Collection $vehiclesInScope,
    ): array {
        $events = [];

        foreach ($bookings as $booking) {
            $this->eachBusinessHourSegment($from, $to, function (CarbonInterface $segmentStart, CarbonInterface $segmentEnd) use ($booking, &$events): void {
                $overlapStart = $booking->starts_at->greaterThan($segmentStart) ? $booking->starts_at : $segmentStart;
                $overlapEnd = $booking->ends_at->lessThan($segmentEnd) ? $booking->ends_at : $segmentEnd;

                if ($overlapEnd->greaterThan($overlapStart)) {
                    $events[] = ['time' => $overlapStart->timestamp, 'delta' => 1];
                    $events[] = ['time' => $overlapEnd->timestamp, 'delta' => -1];
                }
            });
        }

        $peakConcurrent = 0;

        if ($events !== []) {
            usort($events, fn (array $a, array $b): int => $a['time'] <=> $b['time']);

            $current = 0;

            foreach ($events as $event) {
                $current += $event['delta'];
                $peakConcurrent = max($peakConcurrent, $current);
            }
        }

        $fullCapacityDays = 0;
        $highDemandDays = 0;
        $maxDayFleetSize = 0;
        $config = $this->businessHoursConfig();
        $cursor = $from->copy()->startOfDay();

        while ($cursor->lessThanOrEqualTo($to)) {
            if (! in_array($cursor->isoWeekday(), $config['days'], true)) {
                $cursor->addDay();

                continue;
            }

            $dayFleetSize = $this->countVehiclesAvailableOnBusinessDay($vehiclesInScope, $cursor);
            $maxDayFleetSize = max($maxDayFleetSize, $dayFleetSize);

            $dayEvents = [];

            foreach ($bookings as $booking) {
                $segmentStart = $cursor->copy()->setTime($config['start'], 0);
                $segmentEnd = $cursor->copy()->setTime($config['end'], 0);
                $overlapStart = $booking->starts_at->greaterThan($segmentStart) ? $booking->starts_at : $segmentStart;
                $overlapEnd = $booking->ends_at->lessThan($segmentEnd) ? $booking->ends_at : $segmentEnd;

                if ($overlapEnd->greaterThan($overlapStart)) {
                    $dayEvents[] = ['time' => $overlapStart->timestamp, 'delta' => 1];
                    $dayEvents[] = ['time' => $overlapEnd->timestamp, 'delta' => -1];
                }
            }

            $dayPeak = 0;

            if ($dayEvents !== []) {
                usort($dayEvents, fn (array $a, array $b): int => $a['time'] <=> $b['time']);

                $dayCurrent = 0;

                foreach ($dayEvents as $event) {
                    $dayCurrent += $event['delta'];
                    $dayPeak = max($dayPeak, $dayCurrent);
                }
            }

            if ($dayFleetSize > 0 && $dayPeak >= $dayFleetSize) {
                $fullCapacityDays++;
            }

            $highDemandThreshold = (int) ceil($dayFleetSize * 0.8);

            if ($dayFleetSize > 0 && $dayPeak >= $highDemandThreshold) {
                $highDemandDays++;
            }

            $cursor->addDay();
        }

        return [
            'peak_concurrent' => $peakConcurrent,
            'peak_percent' => $maxDayFleetSize > 0 ? round(($peakConcurrent / $maxDayFleetSize) * 100, 1) : 0.0,
            'full_capacity_days' => $fullCapacityDays,
            'high_demand_days' => $highDemandDays,
        ];
    }

    /**
     * @param  Collection<int, Vehicle>  $vehicles
     */
    private function countVehiclesAvailableOnBusinessDay(Collection $vehicles, CarbonInterface $day): int
    {
        $config = $this->businessHoursConfig();
        $segmentStart = $day->copy()->setTime($config['start'], 0);
        $segmentEnd = $day->copy()->setTime($config['end'], 0);

        return $vehicles->filter(
            fn (Vehicle $vehicle): bool => $this->availableBusinessHoursForVehicle($vehicle, $segmentStart, $segmentEnd) > 0,
        )->count();
    }

    /**
     * @return array{status: string, label: string, hint: string}
     */
    private function assessFleetUtilization(
        float $averageUtilizationPercent,
        float $peakUtilizationPercent,
        int $vehiclesWithoutBookings,
        int $fleetSize,
        int $fullCapacityDays,
    ): array {
        $idleSharePercent = $fleetSize > 0 ? ($vehiclesWithoutBookings / $fleetSize) * 100 : 0.0;

        if ($peakUtilizationPercent >= 90 || $fullCapacityDays > 0 || $averageUtilizationPercent >= 65) {
            return [
                'status' => 'shortage',
                'label' => 'Tendenz: zu wenig Fahrzeuge',
                'hint' => 'Hohe Auslastung oder Volllast-Tage im Zeitraum. Zusätzliche Fahrzeuge, Standort-Umverteilung oder Buchungsfenster prüfen.',
            ];
        }

        if ($averageUtilizationPercent < 20 && $idleSharePercent >= 25) {
            return [
                'status' => 'surplus',
                'label' => 'Tendenz: Überkapazität',
                'hint' => 'Viele Fahrzeuge ohne Buchungen und insgesamt niedrige Auslastung. Flottengröße oder Standortverteilung kritisch prüfen.',
            ];
        }

        return [
            'status' => 'balanced',
            'label' => 'Flotte wirkt ausgewogen',
            'hint' => 'Auslastung und Spitzenlast liegen in einem typischen Zielkorridor. Regelmäßige Kontrolle reicht aus.',
        ];
    }

    /**
     * @return array{0: ?CarbonInterface, 1: ?CarbonInterface, 2: string}
     */
    private function resolvePeriod(string $period): array
    {
        $now = now();

        return match ($period) {
            'year' => [
                $now->copy()->startOfYear(),
                $now->copy(),
                'Aktuelles Jahr ('.$now->year.', bis '.$now->translatedFormat('d.m.').')',
            ],
            'all' => [
                null,
                null,
                'Gesamt',
            ],
            default => [
                $now->copy()->startOfMonth(),
                $now->copy()->endOfMonth(),
                'Aktueller Monat ('.$now->translatedFormat('F Y').')',
            ],
        };
    }

  /**
   * @return Builder<Booking>
   */
    private function tripBookingsQuery(?CarbonInterface $from, ?CarbonInterface $to): Builder
    {
        return $this->applyPeriod(
            Booking::query()->whereNotIn('purpose', [
                BookingPurpose::Lock->value,
                BookingPurpose::ChargeLock->value,
            ]),
            $from,
            $to,
        );
    }

  /**
   * @return Builder<Booking>
   */
    private function completedTripsQuery(?CarbonInterface $from, ?CarbonInterface $to): Builder
    {
        return $this->tripBookingsQuery($from, $to)
            ->whereNotNull('km_start')
            ->whereNotNull('km_end')
            ->whereColumn('km_end', '>=', 'km_start');
    }

  /**
   * @param  Builder<Booking>  $query
   * @return Builder<Booking>
   */
    private function applyPeriod(Builder $query, ?CarbonInterface $from, ?CarbonInterface $to): Builder
    {
        return $query
            ->when($from !== null, fn (Builder $builder): Builder => $builder->where('starts_at', '>=', $from))
            ->when($to !== null, fn (Builder $builder): Builder => $builder->where('starts_at', '<=', $to));
    }

    /**
     * @return ?array{license_plate: string, km: int, trips: int}
     */
    private function topVehicleByKm(?CarbonInterface $from, ?CarbonInterface $to): ?array
    {
        $row = (clone $this->completedTripsQuery($from, $to))
            ->select('vehicle_id', DB::raw('SUM(km_end - km_start) as driven_km'), DB::raw('COUNT(*) as trips'))
            ->groupBy('vehicle_id')
            ->orderByDesc('driven_km')
            ->with('vehicle:id,license_plate')
            ->first();

        if ($row === null || $row->vehicle === null) {
            return null;
        }

        return [
            'license_plate' => $row->vehicle->license_plate,
            'km' => (int) $row->driven_km,
            'trips' => (int) $row->trips,
        ];
    }

    /**
     * @return ?array{license_plate: string, bookings: int}
     */
    private function topVehicleByBookings(?CarbonInterface $from, ?CarbonInterface $to): ?array
    {
        $row = (clone $this->tripBookingsQuery($from, $to))
            ->select('vehicle_id', DB::raw('COUNT(*) as bookings'))
            ->groupBy('vehicle_id')
            ->orderByDesc('bookings')
            ->with('vehicle:id,license_plate')
            ->first();

        if ($row === null || $row->vehicle === null) {
            return null;
        }

        return [
            'license_plate' => $row->vehicle->license_plate,
            'bookings' => (int) $row->bookings,
        ];
    }

    /**
     * @return ?array{name: string, km: int, trips: int}
     */
    private function topDriverByKm(?CarbonInterface $from, ?CarbonInterface $to): ?array
    {
        $row = (clone $this->completedTripsQuery($from, $to))
            ->select('driver_id', DB::raw('SUM(km_end - km_start) as driven_km'), DB::raw('COUNT(*) as trips'))
            ->groupBy('driver_id')
            ->orderByDesc('driven_km')
            ->first();

        if ($row === null) {
            return null;
        }

        $driver = FuhrparkModels::user()::query()->find($row->driver_id);

        return [
            'name' => $driver?->name ?? 'Fahrer #'.$row->driver_id,
            'km' => (int) $row->driven_km,
            'trips' => (int) $row->trips,
        ];
    }

    /**
     * @return ?array{name: string, bookings: int}
     */
    private function topDriverByBookings(?CarbonInterface $from, ?CarbonInterface $to): ?array
    {
        $row = (clone $this->tripBookingsQuery($from, $to))
            ->select('driver_id', DB::raw('COUNT(*) as bookings'))
            ->groupBy('driver_id')
            ->orderByDesc('bookings')
            ->first();

        if ($row === null) {
            return null;
        }

        $driver = FuhrparkModels::user()::query()->find($row->driver_id);

        return [
            'name' => $driver?->name ?? 'Fahrer #'.$row->driver_id,
            'bookings' => (int) $row->bookings,
        ];
    }

    /**
     * @return list<array{license_plate: string, km: int, trips: int}>
     */
    private function topVehiclesByKm(?CarbonInterface $from, ?CarbonInterface $to, int $limit = 5): array
    {
        return (clone $this->completedTripsQuery($from, $to))
            ->select('vehicle_id', DB::raw('SUM(km_end - km_start) as driven_km'), DB::raw('COUNT(*) as trips'))
            ->groupBy('vehicle_id')
            ->orderByDesc('driven_km')
            ->limit($limit)
            ->with('vehicle:id,license_plate')
            ->get()
            ->filter(fn (Booking $booking): bool => $booking->vehicle !== null)
            ->map(fn (Booking $booking): array => [
                'license_plate' => $booking->vehicle->license_plate,
                'km' => (int) $booking->driven_km,
                'trips' => (int) $booking->trips,
            ])
            ->values()
            ->all();
    }

    /**
     * @return list<array{name: string, km: int, trips: int}>
     */
    private function topDriversByKm(?CarbonInterface $from, ?CarbonInterface $to, int $limit = 5): array
    {
        $rows = (clone $this->completedTripsQuery($from, $to))
            ->select('driver_id', DB::raw('SUM(km_end - km_start) as driven_km'), DB::raw('COUNT(*) as trips'))
            ->groupBy('driver_id')
            ->orderByDesc('driven_km')
            ->limit($limit)
            ->get();

        $driverIds = $rows->pluck('driver_id')->filter()->all();
        $drivers = FuhrparkModels::user()::query()
            ->whereIn('id', $driverIds)
            ->get()
            ->keyBy('id');

        return $rows->map(function (Booking $booking) use ($drivers): array {
            $driver = $drivers->get($booking->driver_id);

            return [
                'name' => $driver?->name ?? 'Fahrer #'.$booking->driver_id,
                'km' => (int) $booking->driven_km,
                'trips' => (int) $booking->trips,
            ];
        })->all();
    }

    /**
     * @return list<array{purpose: string, label: string, count: int}>
     */
    private function bookingsByPurpose(?CarbonInterface $from, ?CarbonInterface $to): array
    {
        $counts = $this->applyPeriod(Booking::query(), $from, $to)
            ->select('purpose', DB::raw('COUNT(*) as count'))
            ->groupBy('purpose')
            ->pluck('count', 'purpose');

        return collect(BookingPurpose::cases())
            ->map(fn (BookingPurpose $purpose): array => [
                'purpose' => $purpose->value,
                'label' => $purpose->label(),
                'count' => (int) ($counts[$purpose->value] ?? 0),
            ])
            ->filter(fn (array $row): bool => $row['count'] > 0)
            ->values()
            ->all();
    }
}
