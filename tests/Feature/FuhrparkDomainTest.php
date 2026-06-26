<?php

declare(strict_types=1);

use App\Models\Standort;
use App\Models\User;
use Carbon\Carbon;
use Hwkdo\IntranetAppFuhrpark\Data\AppSettings;
use Hwkdo\IntranetAppFuhrpark\Data\BookingStoreData;
use Hwkdo\IntranetAppFuhrpark\Enums\BookingPurpose;
use Hwkdo\IntranetAppFuhrpark\Enums\BookingStatus;
use Hwkdo\IntranetAppFuhrpark\Models\Booking;
use Hwkdo\IntranetAppFuhrpark\Models\Handout;
use Hwkdo\IntranetAppFuhrpark\Models\IntranetAppFuhrparkSettings;
use Hwkdo\IntranetAppFuhrpark\Models\LogbookEntry;
use Hwkdo\IntranetAppFuhrpark\Models\Vehicle;
use Hwkdo\IntranetAppFuhrpark\Models\VehicleReturn;
use Hwkdo\IntranetAppFuhrpark\Models\VehicleCategory;
use Hwkdo\IntranetAppFuhrpark\Policies\BookingPolicy;
use Hwkdo\IntranetAppFuhrpark\Policies\VehiclePolicy;
use Hwkdo\IntranetAppFuhrpark\Services\BookingAvailabilityService;
use Hwkdo\IntranetAppFuhrpark\Services\BookingService;
use Hwkdo\IntranetAppFuhrpark\Services\BookingStatusResolver;
use Hwkdo\IntranetAppFuhrpark\Services\HandoutReturnService;
use Hwkdo\IntranetAppFuhrpark\Services\LogbookService;
use Hwkdo\IntranetAppFuhrpark\Services\VehicleAdminService;
use Hwkdo\IntranetAppFuhrpark\Services\VehicleAvailabilityService;
use Hwkdo\IntranetAppFuhrpark\Support\FuhrparkModels;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Volt\Volt;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

function fuhrparkStandort(): Standort
{
    $standort = Standort::query()->create(['name' => 'Test-Standort']);

    fuhrparkMarkVehicleStandort($standort);

    return $standort;
}

function fuhrparkVehicle(?VehicleCategory $category = null, ?Standort $standort = null): Vehicle
{
    $category ??= VehicleCategory::factory()->create();
    $standort ??= fuhrparkStandort();

    return Vehicle::factory()->create([
        'vehicle_category_id' => $category->id,
        'standort_id' => $standort->id,
    ]);
}

test('vehicle is unavailable before available from', function (): void {
    $vehicle = fuhrparkVehicle();
    $vehicle->update([
        'available_from' => now()->addDays(5),
    ]);

    $start = now()->addDay()->setTime(8, 0);
    $end = $start->copy()->addHours(4);

    $service = app(VehicleAvailabilityService::class);

    expect($service->isAvailable($vehicle->fresh(), $start, $end))->toBeFalse();
});

test('vehicle is unavailable after available until', function (): void {
    $vehicle = fuhrparkVehicle();
    $vehicle->update([
        'available_until' => now()->addDays(3),
    ]);

    $start = now()->addDays(5)->setTime(8, 0);
    $end = $start->copy()->addHours(4);

    $service = app(VehicleAvailabilityService::class);

    expect($service->isAvailable($vehicle->fresh(), $start, $end))->toBeFalse();
});

test('vehicle is available within availability window', function (): void {
    $vehicle = fuhrparkVehicle();
    $vehicle->update([
        'available_from' => now()->addDay(),
        'available_until' => now()->addDays(10),
    ]);

    $start = now()->addDays(3)->setTime(8, 0);
    $end = $start->copy()->addHours(4);

    $service = app(VehicleAvailabilityService::class);

    expect($service->isAvailable($vehicle->fresh(), $start, $end))->toBeTrue();
});

test('vehicle admin service updates availability fields', function (): void {
    $vehicle = fuhrparkVehicle();
    $from = now()->addDays(2)->startOfHour();
    $until = now()->addDays(20)->startOfHour();

    $updated = app(VehicleAdminService::class)->updateAvailability($vehicle, $from, $until);

    expect($updated->available_from?->eq($from))->toBeTrue()
        ->and($updated->available_until?->eq($until))->toBeTrue();
});

test('vehicle admin service clears availability when null passed', function (): void {
    $vehicle = fuhrparkVehicle();
    $vehicle->update([
        'available_from' => now()->addDay(),
        'available_until' => now()->addDays(10),
    ]);

    $updated = app(VehicleAdminService::class)->updateAvailability($vehicle, null, null);

    expect($updated->available_from)->toBeNull()
        ->and($updated->available_until)->toBeNull();
});

test('vehicle admin service rejects availability until before from', function (): void {
    $vehicle = fuhrparkVehicle();
    $from = now()->addDays(10);
    $until = now()->addDays(2);

    app(VehicleAdminService::class)->updateAvailability($vehicle, $from, $until);
})->throws(ValidationException::class);

test('vehicle admin service finds conflicting bookings for availability window', function (): void {
    $vehicle = fuhrparkVehicle();
    $availableFrom = now()->addDays(5)->setTime(8, 0);

    $conflictingBooking = Booking::factory()->create([
        'vehicle_id' => $vehicle->id,
        'starts_at' => now()->addDays(2)->setTime(8, 0),
        'ends_at' => now()->addDays(2)->setTime(12, 0),
    ]);

    Booking::factory()->create([
        'vehicle_id' => $vehicle->id,
        'starts_at' => now()->addDays(10)->setTime(8, 0),
        'ends_at' => now()->addDays(10)->setTime(12, 0),
    ]);

    $conflicts = app(VehicleAdminService::class)->conflictingBookingsForAvailability(
        $vehicle,
        $availableFrom,
        null,
    );

    expect($conflicts)->toHaveCount(1)
        ->and($conflicts->first()->id)->toBe($conflictingBooking->id);
});

test('booking service rejects booking outside vehicle availability window', function (): void {
    $user = User::factory()->create();
    fuhrparkGrantValidLicense($user);
    $vehicle = fuhrparkVehicle();
    $vehicle->update([
        'available_from' => now()->addDays(5),
    ]);

    $start = now()->addDay()->setTime(8, 0);
    $end = $start->copy()->addHours(4);

    app(BookingService::class)->create(
        new BookingStoreData(
            vehicleId: $vehicle->id,
            driverId: $user->id,
            description: 'Außerhalb Verfügbarkeit',
            startsAt: $start,
            endsAt: $end,
        ),
        $user,
    );
})->throws(ValidationException::class);

test('vehicle is unavailable when booking overlaps', function (): void {
    $vehicle = fuhrparkVehicle();
    $start = now()->addDay()->setTime(8, 0);
    $end = $start->copy()->addHours(4);

    Booking::factory()->create([
        'vehicle_id' => $vehicle->id,
        'starts_at' => $start,
        'ends_at' => $end,
    ]);

    $service = app(VehicleAvailabilityService::class);

    expect($service->isAvailable($vehicle, $start, $end))->toBeFalse();
});

test('vehicle availability excludes booking id for reschedule', function (): void {
    $vehicle = fuhrparkVehicle();
    $start = now()->addDay()->setTime(8, 0);
    $end = $start->copy()->addHours(4);

    $booking = Booking::factory()->create([
        'vehicle_id' => $vehicle->id,
        'starts_at' => $start,
        'ends_at' => $end,
    ]);

    $service = app(VehicleAvailabilityService::class);

    expect($service->isAvailable($vehicle, $start, $end, [$booking->id]))->toBeTrue();
});

test('vehicle category average electric range uses eighty percent factor', function (): void {
    $category = VehicleCategory::factory()->create([
        'is_electric' => true,
        'electric_range_avg_km' => 250,
    ]);

    expect($category->averageElectricRangeKm())->toBe(200);
});

test('booking service rejects electric booking without route km', function (): void {
    $user = User::factory()->create();
    fuhrparkGrantValidLicense($user);
    $category = VehicleCategory::factory()->electric()->create();
    $standort = fuhrparkStandort();

    Vehicle::factory()->electric()->create([
        'vehicle_category_id' => $category->id,
        'standort_id' => $standort->id,
    ]);

    $start = now()->addDays(2)->setTime(8, 0);
    $end = $start->copy()->addHours(4);

    app(BookingService::class)->create(
        new BookingStoreData(
            driverId: $user->id,
            description: 'Elektro ohne Strecke',
            startsAt: $start,
            endsAt: $end,
            vehicleCategoryId: $category->id,
            standortId: $standort->id,
        ),
        $user,
    );
})->throws(ValidationException::class);

test('category booking options include unavailable categories as ausgebucht', function (): void {
    $categoryAvailable = VehicleCategory::factory()->create(['name' => 'Kompakt']);
    $categoryBooked = VehicleCategory::factory()->create(['name' => 'Transporter']);
    $standort = fuhrparkStandort();
    fuhrparkVehicle($categoryAvailable, $standort);
    $bookedVehicle = fuhrparkVehicle($categoryBooked, $standort);

    $start = now()->addDays(2)->setTime(8, 0);
    $end = $start->copy()->addHours(4);

    Booking::factory()->create([
        'vehicle_id' => $bookedVehicle->id,
        'starts_at' => $start,
        'ends_at' => $end,
    ]);

    $options = app(VehicleAvailabilityService::class)->categoryBookingOptions($start, $end, $standort->id);

    expect($options)->toHaveCount(2)
        ->and($options->firstWhere(fn ($option) => $option->category->id === $categoryAvailable->id)?->isAvailable)->toBeTrue()
        ->and($options->firstWhere(fn ($option) => $option->category->id === $categoryBooked->id)?->isAvailable)->toBeFalse()
        ->and($options->firstWhere(fn ($option) => $option->category->id === $categoryBooked->id)?->label())->toBe('Transporter (ausgebucht)');
});

test('vehicle standort resolves fahrzeugstandort mapping for user', function (): void {
    $vehicleStandort = fuhrparkStandort();
    $userStandort = Standort::query()->create(['name' => 'Büro-Standort']);

    fuhrparkMarkVehicleStandort($userStandort, isVehicleStandort: false, vehicleStandortId: $vehicleStandort->id);
    $category = VehicleCategory::factory()->create();
    fuhrparkVehicle($category, $vehicleStandort);

    $start = now()->addDays(2)->setTime(8, 0);
    $end = $start->copy()->addHours(4);

    $categories = app(VehicleAvailabilityService::class)->findAvailableCategories(
        $start,
        $end,
        FuhrparkModels::vehicleStandortIdFor($userStandort->id),
    );

    expect($categories)->toHaveCount(1)
        ->and($categories->first()->id)->toBe($category->id);
});

test('booking service creates booking for category and assigns best vehicle', function (): void {
    $user = User::factory()->create();
    fuhrparkGrantValidLicense($user);
    $category = VehicleCategory::factory()->create();
    $standort = fuhrparkStandort();
    $vehicleA = fuhrparkVehicle($category, $standort);
    $vehicleA->update(['initial_km' => 10000]);
    $vehicleB = fuhrparkVehicle($category, $standort);
    $vehicleB->update(['initial_km' => 1000]);

    $start = now()->addDays(2)->setTime(8, 0);
    $end = $start->copy()->addHours(4);

    $booking = app(BookingService::class)->create(
        new BookingStoreData(
            driverId: $user->id,
            description: 'Kategorie-Buchung',
            startsAt: $start,
            endsAt: $end,
            vehicleCategoryId: $category->id,
            standortId: $standort->id,
        ),
        $user,
    );

    expect($booking->vehicle_id)->toBe($vehicleB->id);
});

test('booking service reschedules by category and assigns best vehicle', function (): void {
    $user = User::factory()->create();
    $category = VehicleCategory::factory()->create();
    $standort = fuhrparkStandort();
    $vehicleA = fuhrparkVehicle($category, $standort);
    $vehicleA->update(['initial_km' => 10000]);
    $vehicleB = fuhrparkVehicle($category, $standort);
    $vehicleB->update(['initial_km' => 1000]);

    $start = now()->addDays(2)->setTime(8, 0);
    $end = $start->copy()->addHours(4);

    $booking = Booking::factory()->create([
        'vehicle_id' => $vehicleA->id,
        'driver_id' => $user->id,
        'user_id' => $user->id,
        'starts_at' => $start,
        'ends_at' => $end,
    ]);

    $newStart = $start->copy()->addHours(2);
    $newEnd = $end->copy()->addHours(2);

    app(BookingService::class)->rescheduleByCategory($booking, $newStart, $newEnd, $category->id);

    expect($booking->fresh()->vehicle_id)->toBe($vehicleB->id);
});

test('find available categories returns only categories with free vehicles', function (): void {
    $categoryA = VehicleCategory::factory()->create(['name' => 'Kompakt']);
    $categoryB = VehicleCategory::factory()->create(['name' => 'Transporter']);
    $standort = fuhrparkStandort();
    $vehicleA = fuhrparkVehicle($categoryA, $standort);
    fuhrparkVehicle($categoryB, $standort);

    $start = now()->addDays(2)->setTime(8, 0);
    $end = $start->copy()->addHours(4);

    Booking::factory()->create([
        'vehicle_id' => $vehicleA->id,
        'starts_at' => $start,
        'ends_at' => $end,
    ]);

    $categories = app(VehicleAvailabilityService::class)->findAvailableCategories($start, $end, $standort->id);

    expect($categories)->toHaveCount(1)
        ->and($categories->first()->id)->toBe($categoryB->id);
});

test('booking service creates multi-day booking', function (): void {
    $user = User::factory()->create();
    fuhrparkGrantValidLicense($user);
    $vehicle = fuhrparkVehicle();
    $start = now()->addDays(2)->setTime(8, 0);
    $end = $start->copy()->addDays(2)->setTime(17, 0);

    $booking = app(BookingService::class)->create(
        new BookingStoreData(
            vehicleId: $vehicle->id,
            driverId: $user->id,
            description: 'Mehrtägige Fahrt',
            startsAt: $start,
            endsAt: $end,
        ),
        $user,
    );

    expect($booking->starts_at->eq($start))->toBeTrue()
        ->and($booking->ends_at->eq($end))->toBeTrue();
});

test('booking service reschedules with new time window', function (): void {
    $user = User::factory()->create();
    fuhrparkGrantValidLicense($user);
    $vehicle = fuhrparkVehicle();
    $start = now()->addDays(2)->setTime(8, 0);
    $end = $start->copy()->addHours(4);

    $booking = Booking::factory()->create([
        'vehicle_id' => $vehicle->id,
        'driver_id' => $user->id,
        'user_id' => $user->id,
        'starts_at' => $start,
        'ends_at' => $end,
    ]);

    $newStart = $start->copy()->addHours(2);
    $newEnd = $end->copy()->addHours(2);

    app(BookingService::class)->reschedule($booking, $newStart, $newEnd, $vehicle->id);

    $booking->refresh();

    expect($booking->starts_at->eq($newStart))->toBeTrue()
        ->and($booking->ends_at->eq($newEnd))->toBeTrue()
        ->and($booking->vehicle_id)->toBe($vehicle->id);
});

test('booking service reschedules to multi-day period', function (): void {
    $user = User::factory()->create();
    fuhrparkGrantValidLicense($user);
    $vehicle = fuhrparkVehicle();
    $start = now()->addDays(2)->setTime(8, 0);
    $end = $start->copy()->addHours(4);

    $booking = Booking::factory()->create([
        'vehicle_id' => $vehicle->id,
        'driver_id' => $user->id,
        'user_id' => $user->id,
        'starts_at' => $start,
        'ends_at' => $end,
    ]);

    $newStart = $start->copy()->addDay()->setTime(9, 0);
    $newEnd = $newStart->copy()->addDays(2)->setTime(16, 0);

    app(BookingService::class)->reschedule($booking, $newStart, $newEnd, $vehicle->id);

    $booking->refresh();

    expect($booking->starts_at->eq($newStart))->toBeTrue()
        ->and($booking->ends_at->eq($newEnd))->toBeTrue();
});

test('calendar events include driver last name in title', function (): void {
    $driver = User::factory()->create(['vorname' => 'Max', 'nachname' => 'Mustermann']);
    $vehicle = fuhrparkVehicle();
    $start = now()->addDays(2)->setTime(8, 0);
    $end = $start->copy()->addHours(4);

    $booking = Booking::factory()->create([
        'vehicle_id' => $vehicle->id,
        'user_id' => $driver->id,
        'driver_id' => $driver->id,
        'description' => 'Kundentermin',
        'starts_at' => $start,
        'ends_at' => $end,
    ]);

    $events = app(BookingService::class)->calendarEvents(
        $start->copy()->startOfMonth(),
        $end->copy()->endOfMonth(),
        $driver->id,
    );

    expect($events)->toHaveCount(1)
        ->and($events[0]['title'])->toBe($vehicle->license_plate.' (Mustermann)')
        ->and(app(BookingService::class)->calendarEventTitle($booking->fresh(['vehicle', 'driver'])))
        ->toBe($vehicle->license_plate.' (Mustermann)');
});

test('calendar events mark own bookings with distinct class', function (): void {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $vehicle = fuhrparkVehicle();
    $start = now()->addDays(2)->setTime(8, 0);
    $end = $start->copy()->addHours(4);

    Booking::factory()->create([
        'vehicle_id' => $vehicle->id,
        'user_id' => $user->id,
        'driver_id' => $user->id,
        'starts_at' => $start,
        'ends_at' => $end,
    ]);

    Booking::factory()->create([
        'vehicle_id' => $vehicle->id,
        'user_id' => $otherUser->id,
        'driver_id' => $otherUser->id,
        'starts_at' => $start->copy()->addHours(5),
        'ends_at' => $end->copy()->addHours(5),
    ]);

    $events = app(BookingService::class)->calendarEvents(
        $start->copy()->startOfMonth(),
        $end->copy()->endOfMonth(),
        $user->id,
    );

    expect($events)->toHaveCount(2)
        ->and(collect($events)->firstWhere('extendedProps.is_own', true)['classNames'])
        ->toBe(['fuhrpark-calendar-event--own'])
        ->and(collect($events)->firstWhere('extendedProps.is_own', false)['classNames'])
        ->toBe(['fuhrpark-calendar-event--other']);
});

test('calendar events mark lock bookings with red lock class and reason title', function (): void {
    $admin = User::factory()->create();
    $vehicle = fuhrparkVehicle();
    $start = now()->addDays(2)->setTime(8, 0);
    $end = $start->copy()->addHours(8);

    app(VehicleAdminService::class)->createLockBooking(
        $vehicle,
        $admin,
        $start,
        $end,
        'Werkstatt',
    );

    $events = app(BookingService::class)->calendarEvents(
        $start->copy()->startOfMonth(),
        $end->copy()->endOfMonth(),
    );

    expect($events)->toHaveCount(1)
        ->and($events[0]['classNames'])->toBe(['fuhrpark-calendar-event--lock'])
        ->and($events[0]['title'])->toBe($vehicle->license_plate.' (Werkstatt)');
});

test('booking availability uses requested time window', function (): void {
    $vehicle = fuhrparkVehicle();
    $morningStart = now()->addDays(2)->setTime(8, 0);
    $morningEnd = $morningStart->copy()->addHours(4);

    $booking = Booking::factory()->create([
        'vehicle_id' => $vehicle->id,
        'starts_at' => $morningStart,
        'ends_at' => $morningEnd,
    ]);

    $afternoonStart = $morningStart->copy()->setTime(14, 0);
    $afternoonEnd = $afternoonStart->copy()->addHours(4);

    $result = app(BookingAvailabilityService::class)->findAlternatives($booking, $afternoonStart, $afternoonEnd);

    expect($result->sameCategory)->toHaveCount(1)
        ->and($result->sameCategory->first()->id)->toBe($vehicle->id);
});

test('booking availability returns same category vehicles and other categories together', function (): void {
    $categoryA = VehicleCategory::factory()->create(['name' => 'Kleinwagen']);
    $categoryB = VehicleCategory::factory()->create(['name' => 'Kombi']);
    $standort = fuhrparkStandort();
    $vehicleA = fuhrparkVehicle($categoryA, $standort);
    $vehicleB = fuhrparkVehicle($categoryA, $standort);
    fuhrparkVehicle($categoryB, $standort);

    $start = now()->addDays(2)->setTime(8, 0);
    $end = $start->copy()->addHours(4);

    $booking = Booking::factory()->create([
        'vehicle_id' => $vehicleA->id,
        'starts_at' => $start,
        'ends_at' => $end,
    ]);

    $result = app(BookingAvailabilityService::class)->findAlternatives($booking, $start, $end);

    expect($result->sameCategory)->toHaveCount(1)
        ->and($result->sameCategory->first()->id)->toBe($vehicleB->id)
        ->and($result->hasSameCategoryAlternatives())->toBeTrue()
        ->and($result->hasOtherCategoryAlternatives())->toBeTrue()
        ->and($result->otherCategories)->toHaveCount(1)
        ->and($result->otherCategories[0]->category->id)->toBe($categoryB->id)
        ->and($result->noneAvailable)->toBeFalse();
});

test('booking availability returns same category vehicles first', function (): void {
    $category = VehicleCategory::factory()->create();
    $standort = fuhrparkStandort();
    $vehicleA = fuhrparkVehicle($category, $standort);
    $vehicleB = fuhrparkVehicle($category, $standort);

    $start = now()->addDays(2)->setTime(8, 0);
    $end = $start->copy()->addHours(4);

    $booking = Booking::factory()->create([
        'vehicle_id' => $vehicleA->id,
        'starts_at' => $start,
        'ends_at' => $end,
    ]);

    $result = app(BookingAvailabilityService::class)->findAlternatives($booking, $start, $end);

    expect($result->sameCategory)->toHaveCount(1)
        ->and($result->sameCategory->first()->id)->toBe($vehicleB->id)
        ->and($result->hasSameCategoryAlternatives())->toBeTrue()
        ->and($result->otherCategories)->toBe([])
        ->and($result->noneAvailable)->toBeFalse();
});

test('booking service rejects when max booking days exceeded', function (): void {
    $user = User::factory()->create();
    fuhrparkGrantValidLicense($user);
    $vehicle = fuhrparkVehicle();
    $start = now()->addDay();
    $end = $start->copy()->addDays(15);

    app(BookingService::class)->create(
        new BookingStoreData(
            vehicleId: $vehicle->id,
            driverId: $user->id,
            description: 'Test',
            startsAt: $start,
            endsAt: $end,
        ),
        $user,
    );
})->throws(ValidationException::class);

test('booking policy allows driver to cancel reserved booking', function (): void {
    $user = User::factory()->create();
    $booking = Booking::factory()->create([
        'user_id' => $user->id,
        'driver_id' => $user->id,
        'starts_at' => now()->addDay(),
        'ends_at' => now()->addDay()->addHours(4),
    ]);

    $policy = app(BookingPolicy::class);

    expect($policy->cancel($user, $booking))->toBeTrue()
        ->and($policy->delete($user, $booking))->toBeFalse();
});

test('booking policy denies driver cancel for handed out booking', function (): void {
    $user = User::factory()->create();
    $processor = User::factory()->create();
    $vehicle = fuhrparkVehicle();
    $booking = Booking::factory()->create([
        'vehicle_id' => $vehicle->id,
        'user_id' => $user->id,
        'driver_id' => $user->id,
        'starts_at' => now()->subHour(),
        'ends_at' => now()->addHours(3),
    ]);

    Handout::query()->create([
        'booking_id' => $booking->id,
        'driver_id' => $user->id,
        'processed_by_user_id' => $processor->id,
        'signature_data' => ['data' => 'signed'],
    ]);

    $policy = app(BookingPolicy::class);
    $resolver = app(BookingStatusResolver::class);
    $fresh = $booking->fresh(['handout.returnRecord', 'logbookEntry']);

    expect($resolver->resolve($fresh))->toBe(BookingStatus::HandedOut)
        ->and($resolver->canBeCancelledByDriver($fresh))->toBeFalse()
        ->and($policy->cancel($user, $fresh))->toBeFalse();
});

test('booking cancel service rejects handed out booking even for fuhrpark admin', function (): void {
    Permission::findOrCreate('manage-app-fuhrpark', 'web');

    $admin = User::factory()->create();
    $admin->givePermissionTo('manage-app-fuhrpark');

    $user = User::factory()->create();
    $processor = User::factory()->create();
    $vehicle = fuhrparkVehicle();
    $booking = Booking::factory()->create([
        'vehicle_id' => $vehicle->id,
        'user_id' => $user->id,
        'driver_id' => $user->id,
        'starts_at' => now()->subHour(),
        'ends_at' => now()->addHours(3),
    ]);

    Handout::query()->create([
        'booking_id' => $booking->id,
        'driver_id' => $user->id,
        'processed_by_user_id' => $processor->id,
        'signature_data' => ['data' => 'signed'],
    ]);

    $fresh = $booking->fresh(['handout.returnRecord', 'logbookEntry']);

    expect(fn () => app(BookingService::class)->cancel($fresh, null, $admin))
        ->toThrow(ValidationException::class);
});

test('booking policy denies driver cancel for returned booking', function (): void {
    $user = User::factory()->create();
    $processor = User::factory()->create();
    $vehicle = fuhrparkVehicle();
    $booking = Booking::factory()->create([
        'vehicle_id' => $vehicle->id,
        'user_id' => $user->id,
        'driver_id' => $user->id,
        'starts_at' => now()->subHours(4),
        'ends_at' => now()->addHours(2),
    ]);

    $handout = Handout::query()->create([
        'booking_id' => $booking->id,
        'driver_id' => $user->id,
        'processed_by_user_id' => $processor->id,
        'signature_data' => ['data' => 'signed'],
    ]);

    VehicleReturn::query()->create([
        'handout_id' => $handout->id,
        'driver_id' => $user->id,
        'processed_by_user_id' => $processor->id,
        'km_end' => 1000,
        'checklist' => [],
        'has_damage' => false,
    ]);

    $policy = app(BookingPolicy::class);

    expect($policy->cancel($user, $booking->fresh(['handout.returnRecord', 'logbookEntry'])))->toBeFalse();
});

test('booking cancel requires reason for overdue booking', function (): void {
    $user = User::factory()->create();
    fuhrparkGrantValidLicense($user);
    $vehicle = fuhrparkVehicle();
    $booking = Booking::factory()->create([
        'vehicle_id' => $vehicle->id,
        'user_id' => $user->id,
        'driver_id' => $user->id,
        'starts_at' => now()->subHours(2),
        'ends_at' => now()->addHours(2),
    ]);

    $service = app(BookingService::class);

    expect(fn () => $service->cancel($booking, null, $user))
        ->toThrow(ValidationException::class);

    $service->cancel($booking, 'Krankheit', $user);

    expect(Booking::query()->find($booking->id))->toBeNull();
});

test('driver can view logbook for completed booking', function (): void {
    $user = User::factory()->create();
    $processor = User::factory()->create();
    $vehicle = fuhrparkVehicle();
    $booking = Booking::factory()->create([
        'vehicle_id' => $vehicle->id,
        'user_id' => $user->id,
        'driver_id' => $user->id,
        'starts_at' => now()->subDay(),
        'ends_at' => now()->subDay()->addHours(4),
        'km_start' => 1000,
        'km_end' => 1042,
    ]);

    $handout = Handout::query()->create([
        'booking_id' => $booking->id,
        'driver_id' => $user->id,
        'processed_by_user_id' => $processor->id,
        'signature_data' => ['data' => 'signed'],
    ]);

    VehicleReturn::query()->create([
        'handout_id' => $handout->id,
        'driver_id' => $user->id,
        'processed_by_user_id' => $processor->id,
        'km_end' => 1042,
        'checklist' => [],
        'has_damage' => false,
    ]);

    LogbookEntry::query()->create([
        'booking_id' => $booking->id,
        'user_id' => $user->id,
        'route' => 'Aachen - Köln',
        'km_commute' => 20,
        'km_project' => 22,
        'fueled' => true,
        'cleaned' => false,
    ]);

    $policy = app(BookingPolicy::class);

    expect($policy->viewLogbook($user, $booking->fresh('logbookEntry')))->toBeTrue();
});

test('view logbook is denied without logbook entry', function (): void {
    $user = User::factory()->create();
    fuhrparkGrantValidLicense($user);
    $vehicle = fuhrparkVehicle();
    $booking = Booking::factory()->create([
        'vehicle_id' => $vehicle->id,
        'user_id' => $user->id,
        'driver_id' => $user->id,
        'starts_at' => now()->subDay(),
        'ends_at' => now()->subDay()->addHours(4),
    ]);

    $policy = app(BookingPolicy::class);

    expect($policy->viewLogbook($user, $booking->fresh('logbookEntry')))->toBeFalse();
});

test('logbook service returns vehicle entries sorted by booking start', function (): void {
    $user = User::factory()->create();
    fuhrparkGrantValidLicense($user);
    $vehicle = fuhrparkVehicle();

    $olderBooking = Booking::factory()->create([
        'vehicle_id' => $vehicle->id,
        'driver_id' => $user->id,
        'starts_at' => now()->subDays(3),
        'ends_at' => now()->subDays(3)->addHours(2),
    ]);

    $newerBooking = Booking::factory()->create([
        'vehicle_id' => $vehicle->id,
        'driver_id' => $user->id,
        'starts_at' => now()->subDay(),
        'ends_at' => now()->subDay()->addHours(2),
    ]);

    LogbookEntry::query()->create([
        'booking_id' => $olderBooking->id,
        'user_id' => $user->id,
        'route' => 'Alt',
        'km_commute' => 10,
        'km_project' => 0,
    ]);

    LogbookEntry::query()->create([
        'booking_id' => $newerBooking->id,
        'user_id' => $user->id,
        'route' => 'Neu',
        'km_commute' => 5,
        'km_project' => 0,
    ]);

    $entries = app(LogbookService::class)->entriesForVehicle($vehicle);

    expect($entries)->toHaveCount(2)
        ->and($entries->first()->route)->toBe('Alt')
        ->and($entries->last()->route)->toBe('Neu');
});

test('vehicle logbook pdf route is restricted to admins', function (): void {
    $user = User::factory()->create();
    fuhrparkGrantValidLicense($user);
    $vehicle = fuhrparkVehicle();

    expect(app(VehiclePolicy::class)->viewLogbook($user, $vehicle))->toBeFalse();
});

test('predecessor for handout returns blocking booking with relations', function (): void {
    $vehicle = fuhrparkVehicle();
    $driver = User::factory()->create();
    $processor = User::factory()->create();

    $predecessor = Booking::factory()->create([
        'vehicle_id' => $vehicle->id,
        'driver_id' => $driver->id,
        'starts_at' => now()->subDays(2),
        'ends_at' => now()->subDays(2)->addHours(4),
        'km_end' => 1000,
    ]);

    Handout::query()->create([
        'booking_id' => $predecessor->id,
        'driver_id' => $driver->id,
        'processed_by_user_id' => $processor->id,
    ]);

    $booking = Booking::factory()->create([
        'vehicle_id' => $vehicle->id,
        'driver_id' => $driver->id,
        'starts_at' => now()->subHour(),
        'ends_at' => now()->addHours(3),
    ]);

    $service = app(HandoutReturnService::class);
    $result = $service->predecessorForHandout($booking);

    expect($result)->not->toBeNull()
        ->and($result->id)->toBe($predecessor->id)
        ->and($result->relationLoaded('vehicle'))->toBeTrue()
        ->and($result->relationLoaded('driver'))->toBeTrue();
});

test('handout is blocked when predecessor has no return', function (): void {
    $vehicle = fuhrparkVehicle();
    $driver = User::factory()->create();
    $processor = User::factory()->create();

    $predecessor = Booking::factory()->create([
        'vehicle_id' => $vehicle->id,
        'starts_at' => now()->subDays(2),
        'ends_at' => now()->subDays(2)->addHours(4),
        'km_end' => 1000,
    ]);

    $booking = Booking::factory()->create([
        'vehicle_id' => $vehicle->id,
        'driver_id' => $driver->id,
        'starts_at' => now()->subHour(),
        'ends_at' => now()->addHours(3),
    ]);

    Handout::query()->create([
        'booking_id' => $predecessor->id,
        'driver_id' => $driver->id,
        'processed_by_user_id' => $processor->id,
    ]);

    $service = app(HandoutReturnService::class);

    expect($service->canHandout($booking))->toBeFalse();
});

test('handout requires signature', function (): void {
    $processor = User::factory()->create();
    $driver = User::factory()->create();
    $vehicle = fuhrparkVehicle();
    $booking = Booking::factory()->create([
        'vehicle_id' => $vehicle->id,
        'driver_id' => $driver->id,
        'starts_at' => now(),
        'ends_at' => now()->addHours(4),
    ]);

    $service = app(HandoutReturnService::class);

    expect(fn () => $service->handout($booking, $processor, $driver->id))
        ->toThrow(ValidationException::class);

    $handout = $service->handout($booking, $processor, $driver->id, ['data' => 'signature-payload']);

    expect($handout->signature_data)->toBe(['data' => 'signature-payload']);
});

test('awaiting handout today includes reserved and overdue bookings without handout', function (): void {
    Carbon::setTestNow(Carbon::today()->setTime(10, 0));
    $vehicle = fuhrparkVehicle();
    $resolver = app(BookingStatusResolver::class);

    $futureStart = Booking::factory()->create([
        'vehicle_id' => $vehicle->id,
        'starts_at' => now()->setTime(14, 0),
        'ends_at' => now()->setTime(18, 0),
    ]);

    $pastStart = Booking::factory()->create([
        'vehicle_id' => $vehicle->id,
        'starts_at' => now()->setTime(8, 0),
        'ends_at' => now()->setTime(12, 0),
    ]);

    expect($resolver->isAwaitingHandoutToday($futureStart))->toBeTrue()
        ->and($resolver->resolve($futureStart))->toBe(BookingStatus::Reserved)
        ->and($resolver->isAwaitingHandoutToday($pastStart))->toBeTrue()
        ->and($resolver->resolve($pastStart))->toBe(BookingStatus::Overdue);
});

test('awaiting handout today excludes handed out and blocking bookings', function (): void {
    Carbon::setTestNow(Carbon::today()->setTime(10, 0));
    $vehicle = fuhrparkVehicle();
    $driver = User::factory()->create();
    $processor = User::factory()->create();
    $resolver = app(BookingStatusResolver::class);

    $handedOut = Booking::factory()->create([
        'vehicle_id' => $vehicle->id,
        'driver_id' => $driver->id,
        'starts_at' => now()->setTime(8, 0),
        'ends_at' => now()->setTime(12, 0),
    ]);

    Handout::query()->create([
        'booking_id' => $handedOut->id,
        'driver_id' => $driver->id,
        'processed_by_user_id' => $processor->id,
    ]);

    $lock = Booking::factory()->create([
        'vehicle_id' => $vehicle->id,
        'purpose' => BookingPurpose::Lock,
        'starts_at' => now()->setTime(8, 0),
        'ends_at' => now()->setTime(18, 0),
    ]);

    expect($resolver->isAwaitingHandoutToday($handedOut->fresh('handout')))->toBeFalse()
        ->and($resolver->isAwaitingHandoutToday($lock))->toBeFalse();
});

test('awaiting return today includes only handed out vehicles due today', function (): void {
    Carbon::setTestNow(Carbon::today()->setTime(10, 0));
    $vehicle = fuhrparkVehicle();
    $driver = User::factory()->create();
    $processor = User::factory()->create();
    $resolver = app(BookingStatusResolver::class);

    $dueToday = Booking::factory()->create([
        'vehicle_id' => $vehicle->id,
        'driver_id' => $driver->id,
        'starts_at' => now()->subHours(2),
        'ends_at' => now()->setTime(18, 0),
    ]);

    Handout::query()->create([
        'booking_id' => $dueToday->id,
        'driver_id' => $driver->id,
        'processed_by_user_id' => $processor->id,
    ]);

    $dueTomorrow = Booking::factory()->create([
        'vehicle_id' => fuhrparkVehicle()->id,
        'driver_id' => $driver->id,
        'starts_at' => now()->subHour(),
        'ends_at' => now()->addDay()->setTime(18, 0),
    ]);

    Handout::query()->create([
        'booking_id' => $dueTomorrow->id,
        'driver_id' => $driver->id,
        'processed_by_user_id' => $processor->id,
    ]);

    expect($resolver->isAwaitingReturnToday($dueToday->fresh(['handout.returnRecord'])))->toBeTrue()
        ->and($resolver->isAwaitingReturnToday($dueTomorrow->fresh(['handout.returnRecord'])))->toBeFalse()
        ->and($resolver->isCurrentlyHandedOut($dueToday->fresh(['handout.returnRecord'])))->toBeTrue()
        ->and($resolver->isCurrentlyHandedOut($dueTomorrow->fresh(['handout.returnRecord'])))->toBeTrue();
});

test('workshop trip creates booking with workshop purpose', function (): void {
    $admin = User::factory()->create();
    $driver = User::factory()->create();
    fuhrparkGrantValidLicense($driver);
    $vehicle = fuhrparkVehicle();

    $booking = app(LogbookService::class)->createWorkshopTrip(
        $vehicle,
        $admin,
        $driver->id,
        Carbon::now()->addDay(),
        Carbon::now()->addDay()->addHours(2),
    );

    expect($booking->purpose)->toBe(BookingPurpose::Workshop)
        ->and($booking->description)->toBe('Werkstattfahrt');
});

test('electric vehicle booking creates charge lock', function (): void {
    $systemUser = User::factory()->create(['username' => 'system']);
    $driver = User::factory()->create();
    fuhrparkGrantValidLicense($driver);
    $vehicle = Vehicle::factory()->electric()->create([
        'standort_id' => fuhrparkStandort()->id,
    ]);

    IntranetAppFuhrparkSettings::query()->firstOrCreate(
        [],
        ['settings' => new AppSettings],
    );

    $start = now()->addDays(3)->setTime(8, 0);
    $end = $start->copy()->addHours(4);

    $booking = app(BookingService::class)->create(
        new BookingStoreData(
            vehicleId: $vehicle->id,
            driverId: $driver->id,
            description: 'Elektro',
            startsAt: $start,
            endsAt: $end,
            electricRouteKm: 100,
        ),
        $driver,
    );

    expect(Booking::query()->where('charge_lock_for_booking_id', $booking->id)->exists())->toBeTrue();
});

test('admin can create non-electric vehicle category when electric checkbox is unchecked', function (): void {
    Permission::findOrCreate('manage-app-fuhrpark', 'web');

    $admin = User::factory()->create();
    $admin->givePermissionTo('manage-app-fuhrpark');

    Volt::actingAs($admin)
        ->test('intranet-app-fuhrpark::apps.fuhrpark.admin.categories')
        ->set('name', 'Smart')
        ->set('isElectric', null)
        ->set('requiresLicense', true)
        ->call('save')
        ->assertHasNoErrors();

    $category = VehicleCategory::query()->where('name', 'Smart')->first();

    expect($category)->not->toBeNull()
        ->and($category->is_electric)->toBeFalse()
        ->and($category->requires_license)->toBeTrue();
});
