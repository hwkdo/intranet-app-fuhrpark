<?php

declare(strict_types=1);

use App\Models\Standort;
use App\Models\User;
use Hwkdo\IntranetAppFuhrpark\Data\BookingStoreData;
use Hwkdo\IntranetAppFuhrpark\Models\DriverLicense;
use Hwkdo\IntranetAppFuhrpark\Models\Vehicle;
use Hwkdo\IntranetAppFuhrpark\Models\VehicleCategory;
use Hwkdo\IntranetAppFuhrpark\Services\BookingService;
use Hwkdo\IntranetAppFuhrpark\Services\DriverLicenseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

test('driver license is valid when valid until and restriction are in the future', function (): void {
    $user = User::factory()->create();

    DriverLicense::factory()->create([
        'user_id' => $user->id,
        'valid_until' => now()->addYear(),
        'restricted_until' => now()->addMonths(6),
    ]);

    expect(app(DriverLicenseService::class)->isValid($user))->toBeTrue();
});

test('driver license is invalid when valid until is expired', function (): void {
    $user = User::factory()->create();

    DriverLicense::factory()->expired()->create([
        'user_id' => $user->id,
    ]);

    expect(app(DriverLicenseService::class)->isValid($user))->toBeFalse();
});

test('driver license is invalid when official restriction is expired', function (): void {
    $user = User::factory()->create();

    DriverLicense::factory()->restrictedExpired()->create([
        'user_id' => $user->id,
    ]);

    expect(app(DriverLicenseService::class)->isValid($user))->toBeFalse();
});

test('can book without license when category does not require license', function (): void {
    $user = User::factory()->create();
    $category = VehicleCategory::factory()->create(['requires_license' => false]);

    expect(app(DriverLicenseService::class)->canBook($user, $category->id))->toBeTrue();
});

test('cannot book without license when category requires license', function (): void {
    $user = User::factory()->create();
    $category = VehicleCategory::factory()->create(['requires_license' => true]);

    expect(app(DriverLicenseService::class)->canBook($user, $category->id))->toBeFalse();
});

test('user without valid license cannot book for themselves in license required category', function (): void {
    $user = User::factory()->create();
    $category = VehicleCategory::factory()->create(['requires_license' => true]);
    $vehicle = Vehicle::factory()->create([
        'vehicle_category_id' => $category->id,
        'standort_id' => Standort::query()->create(['name' => 'Test'])->id,
    ]);

    $start = now()->addDays(2)->setTime(8, 0);
    $end = $start->copy()->addHours(4);

    app(BookingService::class)->create(
        new BookingStoreData(
            vehicleId: $vehicle->id,
            driverId: $user->id,
            description: 'Selbstbuchung',
            startsAt: $start,
            endsAt: $end,
        ),
        $user,
    );
})->throws(ValidationException::class, 'Sie können ohne gültigen Führerschein keine Fahrzeuge in dieser Kategorie für sich selbst buchen.');

test('user without valid license can book for themselves in category without license requirement', function (): void {
    $user = User::factory()->create();
    $category = VehicleCategory::factory()->create(['requires_license' => false]);
    $standort = Standort::query()->create(['name' => 'Test']);

    Vehicle::factory()->create([
        'vehicle_category_id' => $category->id,
        'standort_id' => $standort->id,
    ]);

    $start = now()->addDays(2)->setTime(8, 0);
    $end = $start->copy()->addHours(4);

    $booking = app(BookingService::class)->create(
        new BookingStoreData(
            driverId: $user->id,
            description: 'Selbstbuchung ohne FS-Pflicht',
            startsAt: $start,
            endsAt: $end,
            vehicleCategoryId: $category->id,
            standortId: $standort->id,
        ),
        $user,
    );

    expect($booking->driver_id)->toBe($user->id);
});

test('user without valid license can book for another driver with valid license in license required category', function (): void {
    $booker = User::factory()->create();
    $driver = User::factory()->create();
    fuhrparkGrantValidLicense($driver);
    $category = VehicleCategory::factory()->create(['requires_license' => true]);
    $vehicle = Vehicle::factory()->create([
        'vehicle_category_id' => $category->id,
        'standort_id' => Standort::query()->create(['name' => 'Test'])->id,
    ]);

    $start = now()->addDays(2)->setTime(8, 0);
    $end = $start->copy()->addHours(4);

    $booking = app(BookingService::class)->create(
        new BookingStoreData(
            vehicleId: $vehicle->id,
            driverId: $driver->id,
            description: 'Buchung für Kollege',
            startsAt: $start,
            endsAt: $end,
        ),
        $booker,
    );

    expect($booking->driver_id)->toBe($driver->id)
        ->and($booking->user_id)->toBe($booker->id);
});

test('user cannot book for driver without valid license in license required category', function (): void {
    $booker = User::factory()->create();
    fuhrparkGrantValidLicense($booker);
    $driver = User::factory()->create();
    $category = VehicleCategory::factory()->create(['requires_license' => true]);
    $vehicle = Vehicle::factory()->create([
        'vehicle_category_id' => $category->id,
        'standort_id' => Standort::query()->create(['name' => 'Test'])->id,
    ]);

    $start = now()->addDays(2)->setTime(8, 0);
    $end = $start->copy()->addHours(4);

    app(BookingService::class)->create(
        new BookingStoreData(
            vehicleId: $vehicle->id,
            driverId: $driver->id,
            description: 'Ungültiger Fahrer',
            startsAt: $start,
            endsAt: $end,
        ),
        $booker,
    );
})->throws(ValidationException::class, 'Der gewählte Fahrer hat keinen gültigen Führerschein für diese Kategorie.');

test('user can book for driver without license in category without license requirement', function (): void {
    $booker = User::factory()->create();
    $driver = User::factory()->create();
    $category = VehicleCategory::factory()->create(['requires_license' => false]);
    $standort = Standort::query()->create(['name' => 'Test']);

    Vehicle::factory()->create([
        'vehicle_category_id' => $category->id,
        'standort_id' => $standort->id,
    ]);

    $start = now()->addDays(2)->setTime(8, 0);
    $end = $start->copy()->addHours(4);

    $booking = app(BookingService::class)->create(
        new BookingStoreData(
            driverId: $driver->id,
            description: 'Ohne FS-Pflicht',
            startsAt: $start,
            endsAt: $end,
            vehicleCategoryId: $category->id,
            standortId: $standort->id,
        ),
        $booker,
    );

    expect($booking->driver_id)->toBe($driver->id);
});
