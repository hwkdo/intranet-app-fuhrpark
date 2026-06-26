<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppFuhrpark\Services;

use Carbon\CarbonInterface;
use Hwkdo\IntranetAppFuhrpark\Contracts\BookingCalendarSyncInterface;
use Hwkdo\IntranetAppFuhrpark\Data\BookingStoreData;
use Hwkdo\IntranetAppFuhrpark\Enums\BookingPurpose;
use Hwkdo\IntranetAppFuhrpark\Events\FuhrparkBookingChanged;
use Hwkdo\IntranetAppFuhrpark\Mail\BookingCancelledAdminMail;
use Hwkdo\IntranetAppFuhrpark\Mail\BookingCancelledMail;
use Hwkdo\IntranetAppFuhrpark\Models\Booking;
use Hwkdo\IntranetAppFuhrpark\Models\IntranetAppFuhrparkSettings;
use Hwkdo\IntranetAppFuhrpark\Models\Vehicle;
use Hwkdo\IntranetAppFuhrpark\Support\FuhrparkModels;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;

class BookingService
{
    public function __construct(
        private readonly VehicleAvailabilityService $availabilityService,
        private readonly DriverLicenseService $driverLicenseService,
        private readonly BookingStatusResolver $statusResolver,
        private readonly ElectricVehicleService $electricVehicleService,
        private readonly BookingCalendarSyncInterface $calendarSync,
    ) {}

    /**
     * @return array<int, array<string, mixed>>
     */
    public function calendarEvents(CarbonInterface $start, CarbonInterface $end, ?int $currentUserId = null): array
    {
        return Booking::query()
            ->with(['vehicle', 'driver', 'handout', 'handout.returnRecord', 'logbookEntry'])
            ->where('ends_at', '>=', $start)
            ->where('starts_at', '<=', $end)
            ->get()
            ->map(function (Booking $booking) use ($currentUserId): array {
                $isOwn = $currentUserId !== null && (
                    (int) $booking->user_id === $currentUserId
                    || (int) $booking->driver_id === $currentUserId
                );

                return [
                    'id' => (string) $booking->id,
                    'title' => $this->calendarEventTitle($booking),
                    'start' => $booking->starts_at->toIso8601String(),
                    'end' => $booking->ends_at->toIso8601String(),
                    'classNames' => $this->calendarEventClassNames($booking, $isOwn),
                    'extendedProps' => [
                        'status' => $this->statusResolver->resolve($booking)->value,
                        'driver_id' => $booking->driver_id,
                        'purpose' => $booking->purpose->value,
                        'is_own' => $isOwn,
                    ],
                ];
            })
            ->all();
    }

    public function calendarEventTitle(Booking $booking): string
    {
        if ($booking->purpose === BookingPurpose::Lock) {
            $reason = $booking->lock_reason ?: 'Sperre';

            return $booking->vehicle->license_plate.' ('.$reason.')';
        }

        $nachname = $booking->driver?->nachname ?? '-';

        return $booking->vehicle->license_plate.' ('.$nachname.')';
    }

    /**
     * @return array<int, string>
     */
    private function calendarEventClassNames(Booking $booking, bool $isOwn): array
    {
        if ($booking->purpose === BookingPurpose::Lock) {
            return ['fuhrpark-calendar-event--lock'];
        }

        return [$isOwn ? 'fuhrpark-calendar-event--own' : 'fuhrpark-calendar-event--other'];
    }

    public function create(BookingStoreData $data, Authenticatable $booker, BookingPurpose $purpose = BookingPurpose::Normal): Booking
    {
        $driver = FuhrparkModels::user()::query()->findOrFail($data->driverId);
        $vehicle = $this->resolveVehicle($data);

        if ($vehicle->isElectric() && $purpose === BookingPurpose::Normal && ! $data->electricRouteKm) {
            throw ValidationException::withMessages([
                'electric_route_km' => ['Bitte die geplante Elektro-Strecke in km angeben.'],
            ]);
        }

        $this->assertCanBook($driver, $vehicle, $data->startsAt, $data->endsAt, $data->electricRouteKm, booker: $booker);

        $booking = DB::transaction(function () use ($data, $booker, $purpose, $driver, $vehicle): Booking {
            $booking = Booking::query()->create([
                'vehicle_id' => $vehicle->id,
                'user_id' => $booker->getAuthIdentifier(),
                'driver_id' => $driver->getKey(),
                'purpose' => $purpose,
                'description' => $data->description,
                'is_commute' => $data->isCommute,
                'electric_route_km' => $data->electricRouteKm,
                'starts_at' => $data->startsAt,
                'ends_at' => $data->endsAt,
                'sync_to_calendar' => $data->syncToCalendar,
            ]);

            if ($vehicle->isElectric() && $purpose === BookingPurpose::Normal) {
                $this->createChargeLock($booking, $vehicle);
            }

            FuhrparkBookingChanged::dispatch($booking->fresh(), 'created');

            return $booking->fresh(['vehicle', 'driver']);
        });

        if ($data->syncToCalendar) {
            $this->syncCalendarOnCreate($booking);
        }

        return $booking->fresh(['vehicle', 'driver']);
    }

    public function reschedule(Booking $booking, CarbonInterface $start, CarbonInterface $end, int $vehicleId): Booking
    {
        $vehicle = Vehicle::query()->with('category')->findOrFail($vehicleId);
        $driver = $booking->driver;

        $this->assertCanBook($driver, $vehicle, $start, $end, $booking->electric_route_km, [$booking->id]);

        $booking->update([
            'vehicle_id' => $vehicleId,
            'starts_at' => $start,
            'ends_at' => $end,
        ]);

        $booking = $booking->fresh(['vehicle', 'driver']);

        if ($booking->ms_graph_event_id) {
            $this->syncCalendarOnReschedule($booking, $start, $end);
        }

        FuhrparkBookingChanged::dispatch($booking, 'rescheduled');

        return $booking;
    }

    public function rescheduleByCategory(Booking $booking, CarbonInterface $start, CarbonInterface $end, int $categoryId): Booking
    {
        $vehicle = $this->availabilityService->findBestAvailable(
            $start,
            $end,
            $categoryId,
            $booking->vehicle->standort_id,
            excludeBookingIds: $this->excludeBookingIds($booking),
            electricRouteKm: $booking->electric_route_km,
        );

        if (! $vehicle) {
            throw ValidationException::withMessages([
                'vehicle_category_id' => ['In dieser Kategorie ist kein Fahrzeug im gewählten Zeitraum verfügbar.'],
            ]);
        }

        return $this->reschedule($booking, $start, $end, $vehicle->id);
    }

    public function cancel(Booking $booking, ?string $reason = null, ?Authenticatable $cancelledBy = null, bool $force = false): void
    {
        $booking->load(['vehicle', 'driver', 'handout.returnRecord', 'logbookEntry']);

        if (! $force) {
            if (! $this->statusResolver->canBeCancelledByDriver($booking)) {
                throw ValidationException::withMessages([
                    'booking' => ['Diese Buchung kann nicht gelöscht werden.'],
                ]);
            }

            if ($this->statusResolver->requiresCancellationReason($booking) && blank($reason)) {
                throw ValidationException::withMessages([
                    'cancelReason' => ['Bitte geben Sie eine Begründung an.'],
                ]);
            }
        }

        if ($reason) {
            $booking->update(['purpose_note' => $reason]);
        }

        $this->notifyPartiesOfCancellation($booking);

        if (
            $reason
            && $cancelledBy
            && $this->statusResolver->requiresCancellationReason($booking)
        ) {
            $roleName = IntranetAppFuhrparkSettings::current()->settings->adminNotifyRole;
            $role = Role::findByName($roleName, 'web');

            foreach ($role->users as $admin) {
                Mail::to($admin->email)->send(new BookingCancelledAdminMail($booking, $reason, $cancelledBy));
            }
        }

        Booking::query()
            ->where('charge_lock_for_booking_id', $booking->id)
            ->delete();

        if ($booking->ms_graph_event_id) {
            $this->syncCalendarOnCancel($booking);
        }

        FuhrparkBookingChanged::dispatch($booking, 'cancelled');

        $booking->delete();
    }

    private function assertCanBook(
        Authenticatable $driver,
        Vehicle $vehicle,
        CarbonInterface $start,
        CarbonInterface $end,
        ?int $electricRouteKm = null,
        array $excludeBookingIds = [],
        ?Authenticatable $booker = null,
    ): void {
        $settings = IntranetAppFuhrparkSettings::current()->settings;
        $maxDays = $settings->maxBookingDays ?? config('intranet-app-fuhrpark.limits.max_booking_days', 10);

        if ($start->diffInDays($end) > $maxDays) {
            throw ValidationException::withMessages([
                'ends_at' => ["Buchungen dürfen maximal {$maxDays} Tage dauern."],
            ]);
        }

        if (! $this->driverLicenseService->canBook($driver, $vehicle->vehicle_category_id)) {
            $isSelfBooking = $booker !== null
                && (int) $booker->getAuthIdentifier() === (int) $driver->getKey();

            throw ValidationException::withMessages([
                'driver_id' => [$isSelfBooking
                    ? 'Sie können ohne gültigen Führerschein keine Fahrzeuge in dieser Kategorie für sich selbst buchen.'
                    : 'Der gewählte Fahrer hat keinen gültigen Führerschein für diese Kategorie.'],
            ]);
        }

        if ($this->statusResolver->openLogbookCountForDriver($driver) >= ($settings->maxOpenLogbook ?? 3)) {
            throw ValidationException::withMessages([
                'driver_id' => ['Zu viele offene Fahrtenbucheinträge.'],
            ]);
        }

        if ($this->statusResolver->noShowCountForDriver($driver) >= ($settings->maxNoShow ?? 3)) {
            throw ValidationException::withMessages([
                'driver_id' => ['Zu viele nicht angetretene Buchungen.'],
            ]);
        }

        if (! $this->availabilityService->isAvailable($vehicle, $start, $end, $excludeBookingIds, $electricRouteKm)) {
            throw ValidationException::withMessages([
                'vehicle_id' => ['Fahrzeug im gewünschten Zeitraum nicht verfügbar.'],
            ]);
        }
    }

    private function resolveVehicle(BookingStoreData $data, array $excludeBookingIds = []): Vehicle
    {
        if ($data->vehicleId) {
            return Vehicle::query()->with('category')->findOrFail($data->vehicleId);
        }

        if (! $data->vehicleCategoryId || ! $data->standortId) {
            throw ValidationException::withMessages([
                'vehicle_category_id' => ['Bitte eine Fahrzeugkategorie wählen.'],
            ]);
        }

        $vehicle = $this->availabilityService->findBestAvailable(
            $data->startsAt,
            $data->endsAt,
            $data->vehicleCategoryId,
            $data->standortId,
            excludeBookingIds: $excludeBookingIds,
            electricRouteKm: $data->electricRouteKm,
        );

        if (! $vehicle) {
            throw ValidationException::withMessages([
                'vehicle_category_id' => ['In dieser Kategorie ist kein Fahrzeug im gewählten Zeitraum verfügbar.'],
            ]);
        }

        return $vehicle->load('category');
    }

    /**
     * @return array<int>
     */
    private function excludeBookingIds(Booking $booking): array
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

    private function syncCalendarOnCreate(Booking $booking): void
    {
        try {
            $eventId = $this->calendarSync->createEvent($booking);

            if ($eventId) {
                $booking->update(['ms_graph_event_id' => $eventId]);
            }
        } catch (Throwable $exception) {
            Log::error('Fuhrpark booking calendar sync on create failed', [
                'booking_id' => $booking->id,
                'message' => $exception->getMessage(),
            ]);
        }
    }

    private function syncCalendarOnReschedule(Booking $booking, CarbonInterface $start, CarbonInterface $end): void
    {
        try {
            $this->calendarSync->updateEvent($booking, $start, $end);
        } catch (Throwable $exception) {
            Log::error('Fuhrpark booking calendar sync on reschedule failed', [
                'booking_id' => $booking->id,
                'message' => $exception->getMessage(),
            ]);
        }
    }

    private function syncCalendarOnCancel(Booking $booking): void
    {
        try {
            $this->calendarSync->deleteEvent($booking);
        } catch (Throwable $exception) {
            Log::error('Fuhrpark booking calendar sync on cancel failed', [
                'booking_id' => $booking->id,
                'message' => $exception->getMessage(),
            ]);
        }
    }

    private function createChargeLock(Booking $booking, Vehicle $vehicle): void
    {
        $systemUser = FuhrparkModels::user()::query()
            ->where('username', IntranetAppFuhrparkSettings::current()->settings->systemUserUsername)
            ->first();

        if (! $systemUser) {
            return;
        }

        $minutes = $this->electricVehicleService->chargeMinutesForRoute($vehicle, (int) ($booking->electric_route_km ?? 0));
        $lockStart = $booking->ends_at->copy()->addMinute();
        $lockEnd = $booking->ends_at->copy()->addMinutes($minutes);

        Booking::query()->create([
            'vehicle_id' => $vehicle->id,
            'user_id' => $systemUser->getKey(),
            'driver_id' => $systemUser->getKey(),
            'purpose' => BookingPurpose::ChargeLock,
            'description' => 'Sperre',
            'is_commute' => false,
            'starts_at' => $lockStart,
            'ends_at' => $lockEnd,
            'lock_reason' => 'Laden',
            'lock_user_id' => $systemUser->getKey(),
            'charge_lock_for_booking_id' => $booking->id,
        ]);
    }

    private function notifyPartiesOfCancellation(Booking $booking): void
    {
        $booking->loadMissing(['driver', 'booker']);

        $emails = collect([
            $booking->driver?->email,
            $booking->booker?->email,
        ])
            ->filter(fn (?string $email): bool => filled($email))
            ->unique()
            ->values();

        foreach ($emails as $email) {
            Mail::to($email)->send(new BookingCancelledMail($booking));
        }
    }
}
