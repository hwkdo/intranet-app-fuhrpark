<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppFuhrpark\Services;

use Carbon\CarbonInterface;
use Hwkdo\IntranetAppFuhrpark\Enums\BookingPurpose;
use Hwkdo\IntranetAppFuhrpark\Models\Booking;
use Hwkdo\IntranetAppFuhrpark\Models\Handout;
use Hwkdo\IntranetAppFuhrpark\Models\Vehicle;
use Hwkdo\IntranetAppFuhrpark\Models\VehicleReturn;
use Hwkdo\IntranetAppFuhrpark\Support\FuhrparkModels;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
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
                $now->copy()->endOfYear(),
                'Aktuelles Jahr ('.$now->year.')',
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
