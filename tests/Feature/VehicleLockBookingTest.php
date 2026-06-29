<?php

declare(strict_types=1);

use App\Models\Standort;
use App\Models\User;
use Carbon\Carbon;
use Hwkdo\IntranetAppFuhrpark\Enums\BookingPurpose;
use Hwkdo\IntranetAppFuhrpark\Events\FuhrparkBookingChanged;
use Hwkdo\IntranetAppFuhrpark\Livewire\Admin\VehicleLockConflictReschedule;
use Hwkdo\IntranetAppFuhrpark\Mail\BookingCancelledMail;
use Hwkdo\IntranetAppFuhrpark\Models\Booking;
use Hwkdo\IntranetAppFuhrpark\Models\Handout;
use Hwkdo\IntranetAppFuhrpark\Models\Vehicle;
use Hwkdo\IntranetAppFuhrpark\Models\VehicleCategory;
use Hwkdo\IntranetAppFuhrpark\Services\BookingService;
use Hwkdo\IntranetAppFuhrpark\Services\LogbookService;
use Hwkdo\IntranetAppFuhrpark\Services\VehicleAdminService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

function vehicleLockStandort(): Standort
{
    return Standort::query()->create(['name' => 'Sperr-Standort']);
}

function vehicleLockVehicle(?VehicleCategory $category = null, ?Standort $standort = null): Vehicle
{
    $category ??= VehicleCategory::factory()->create();
    $standort ??= vehicleLockStandort();

    return Vehicle::factory()->create([
        'vehicle_category_id' => $category->id,
        'standort_id' => $standort->id,
    ]);
}

test('admin can create vehicle lock without driver license', function (): void {
    Event::fake([FuhrparkBookingChanged::class]);

    $admin = User::factory()->create();
    $category = VehicleCategory::factory()->create(['requires_license' => true]);
    $vehicle = vehicleLockVehicle($category);
    $start = now()->addDay()->setTime(8, 0);
    $end = $start->copy()->addHours(8);

    $booking = app(VehicleAdminService::class)->createLockBooking(
        $vehicle,
        $admin,
        $start,
        $end,
        'Werkstatt',
    );

    expect($booking->purpose)->toBe(BookingPurpose::Lock)
        ->and($booking->lock_reason)->toBe('Werkstatt')
        ->and($booking->lock_user_id)->toBe($admin->id)
        ->and($booking->vehicle_id)->toBe($vehicle->id);

    Event::assertDispatched(FuhrparkBookingChanged::class);
});

test('vehicle lock rejects overlapping normal bookings', function (): void {
    $admin = User::factory()->create();
    $vehicle = vehicleLockVehicle();
    $start = now()->addDays(2)->setTime(8, 0);
    $end = $start->copy()->addHours(4);

    Booking::factory()->create([
        'vehicle_id' => $vehicle->id,
        'starts_at' => $start,
        'ends_at' => $end,
        'purpose' => BookingPurpose::Normal,
    ]);

    app(VehicleAdminService::class)->createLockBooking($vehicle, $admin, $start, $end, 'Sonderfall');
})->throws(ValidationException::class);

test('vehicle lock ignores bookings that already have a handout', function (): void {
    $admin = User::factory()->create();
    $vehicle = vehicleLockVehicle();
    $start = now()->addDays(2)->setTime(8, 0);
    $end = $start->copy()->addHours(4);

    $booking = Booking::factory()->create([
        'vehicle_id' => $vehicle->id,
        'starts_at' => $start,
        'ends_at' => $end,
        'purpose' => BookingPurpose::Normal,
    ]);

    Handout::query()->create([
        'booking_id' => $booking->id,
        'driver_id' => $booking->driver_id,
        'processed_by_user_id' => $admin->id,
        'signature_data' => ['data' => 'signed'],
    ]);

    $conflicts = app(VehicleAdminService::class)->conflictingBookingsForLock($vehicle, $start, $end);

    expect($conflicts)->toBeEmpty();

    $lock = app(VehicleAdminService::class)->createLockBooking($vehicle, $admin, $start, $end, 'Werkstatt');

    expect($lock->purpose)->toBe(BookingPurpose::Lock);
});

test('vehicle lock conflict reschedule shows category availability in dropdown', function (): void {
    Permission::findOrCreate('manage-app-fuhrpark', 'web');

    $admin = User::factory()->create();
    $admin->givePermissionTo('manage-app-fuhrpark');

    $standort = vehicleLockStandort();
    $categoryAvailable = VehicleCategory::factory()->create(['name' => 'PKW']);
    $categoryBooked = VehicleCategory::factory()->create(['name' => 'Transporter']);
    $lockedVehicle = vehicleLockVehicle($categoryBooked, $standort);
    vehicleLockVehicle($categoryAvailable, $standort);

    $start = now()->addDays(3)->setTime(8, 0);
    $end = $start->copy()->addHours(4);

    $booking = Booking::factory()->create([
        'vehicle_id' => $lockedVehicle->id,
        'starts_at' => $start,
        'ends_at' => $end,
        'purpose' => BookingPurpose::Normal,
    ]);

    Livewire::actingAs($admin)
        ->test(VehicleLockConflictReschedule::class, [
            'bookingId' => $booking->id,
            'excludeVehicleId' => $lockedVehicle->id,
        ])
        ->assertSet('categoryId', null)
        ->assertSee('Kategorie wählen')
        ->assertSee('PKW')
        ->assertSee('Transporter (ausgebucht)');
});

test('vehicle lock conflict reschedule moves booking to another vehicle', function (): void {
    Permission::findOrCreate('manage-app-fuhrpark', 'web');

    $admin = User::factory()->create();
    $admin->givePermissionTo('manage-app-fuhrpark');

    $category = VehicleCategory::factory()->create();
    $standort = vehicleLockStandort();
    $lockedVehicle = vehicleLockVehicle($category, $standort);
    $alternativeVehicle = vehicleLockVehicle($category, $standort);

    $start = now()->addDays(3)->setTime(8, 0);
    $end = $start->copy()->addHours(4);

    $booking = Booking::factory()->create([
        'vehicle_id' => $lockedVehicle->id,
        'starts_at' => $start,
        'ends_at' => $end,
        'purpose' => BookingPurpose::Normal,
    ]);

    Livewire::actingAs($admin)
        ->test(VehicleLockConflictReschedule::class, [
            'bookingId' => $booking->id,
            'excludeVehicleId' => $lockedVehicle->id,
        ])
        ->set('categoryId', $category->id)
        ->call('checkReschedule')
        ->assertSet('targetVehicleId', $alternativeVehicle->id)
        ->call('confirmReschedule')
        ->assertSet('resolved', true);

    expect($booking->fresh()->vehicle_id)->toBe($alternativeVehicle->id);
});

test('vehicle lock conflict shows delete button when no category is reschedulable', function (): void {
    Permission::findOrCreate('manage-app-fuhrpark', 'web');

    $admin = User::factory()->create();
    $admin->givePermissionTo('manage-app-fuhrpark');

    $category = VehicleCategory::factory()->create(['name' => 'PKW']);
    $lockedVehicle = vehicleLockVehicle($category);

    $start = now()->addDays(3)->setTime(8, 0);
    $end = $start->copy()->addHours(4);

    $booking = Booking::factory()->create([
        'vehicle_id' => $lockedVehicle->id,
        'starts_at' => $start,
        'ends_at' => $end,
        'purpose' => BookingPurpose::Normal,
    ]);

    Livewire::actingAs($admin)
        ->test(VehicleLockConflictReschedule::class, [
            'bookingId' => $booking->id,
            'excludeVehicleId' => $lockedVehicle->id,
        ])
        ->assertSet('hasNoReschedulableCategories', true)
        ->assertSee('Keine Kategorie zum Umbuchen verfügbar')
        ->assertSee('Löschen')
        ->assertDontSee('Prüfen');
});

test('vehicle lock conflict delete notifies driver and booker by email', function (): void {
    Mail::fake();

    Permission::findOrCreate('manage-app-fuhrpark', 'web');

    $admin = User::factory()->create();
    $admin->givePermissionTo('manage-app-fuhrpark');

    $booker = User::factory()->create(['email' => 'booker@example.com']);
    $driver = User::factory()->create(['email' => 'driver@example.com']);
    $category = VehicleCategory::factory()->create();
    $lockedVehicle = vehicleLockVehicle($category);

    $start = now()->addDays(3)->setTime(8, 0);
    $end = $start->copy()->addHours(4);

    $booking = Booking::factory()->create([
        'vehicle_id' => $lockedVehicle->id,
        'user_id' => $booker->id,
        'driver_id' => $driver->id,
        'starts_at' => $start,
        'ends_at' => $end,
        'purpose' => BookingPurpose::Normal,
    ]);

    Livewire::actingAs($admin)
        ->test(VehicleLockConflictReschedule::class, [
            'bookingId' => $booking->id,
            'excludeVehicleId' => $lockedVehicle->id,
        ])
        ->call('deleteBooking')
        ->assertSet('resolved', true);

    Mail::assertQueued(BookingCancelledMail::class, 2);
    Mail::assertQueued(BookingCancelledMail::class, fn (BookingCancelledMail $mail): bool => $mail->hasTo('driver@example.com'));
    Mail::assertQueued(BookingCancelledMail::class, fn (BookingCancelledMail $mail): bool => $mail->hasTo('booker@example.com'));

    expect(Booking::query()->find($booking->id))->toBeNull();
});

test('booking cancellation sends only one email when driver is booker', function (): void {
    Mail::fake();

    $user = User::factory()->create(['email' => 'same@example.com']);
    $vehicle = vehicleLockVehicle();
    $booking = Booking::factory()->create([
        'vehicle_id' => $vehicle->id,
        'user_id' => $user->id,
        'driver_id' => $user->id,
    ]);

    app(BookingService::class)->cancel($booking, force: true);

    Mail::assertQueued(BookingCancelledMail::class, 1);
    Mail::assertQueued(BookingCancelledMail::class, fn (BookingCancelledMail $mail): bool => $mail->hasTo('same@example.com'));
});

test('vehicle lock rejects overlapping lock bookings', function (): void {
    $admin = User::factory()->create();
    $vehicle = vehicleLockVehicle();
    $start = Carbon::parse('2026-06-29 07:14');
    $end = Carbon::parse('2026-06-29 15:14');

    Booking::factory()->create([
        'vehicle_id' => $vehicle->id,
        'starts_at' => $start,
        'ends_at' => $end,
        'purpose' => BookingPurpose::Lock,
        'lock_reason' => 'Bestehend',
        'lock_user_id' => $admin->id,
    ]);

    app(VehicleAdminService::class)->createLockBooking($vehicle, $admin, $start, $end, 'Neu');
})->throws(ValidationException::class);

test('admin can remove active vehicle lock booking', function (): void {
    Event::fake([FuhrparkBookingChanged::class]);

    $admin = User::factory()->create();
    $vehicle = vehicleLockVehicle();
    $start = now()->addDay()->setTime(8, 0);
    $end = $start->copy()->addHours(8);

    $lock = app(VehicleAdminService::class)->createLockBooking($vehicle, $admin, $start, $end, 'Werkstatt');

    expect(app(VehicleAdminService::class)->activeLockBookings($vehicle))->toHaveCount(1);

    app(VehicleAdminService::class)->removeLockBooking($vehicle, $lock->id);

    expect(app(VehicleAdminService::class)->activeLockBookings($vehicle->fresh()))->toBeEmpty()
        ->and(Booking::query()->find($lock->id))->toBeNull();

    Event::assertDispatched(FuhrparkBookingChanged::class);
});

test('vehicle deactivation rejects future bookings without handout', function (): void {
    $admin = User::factory()->create();
    $vehicle = vehicleLockVehicle();
    $start = now()->addDays(2)->setTime(8, 0);
    $end = $start->copy()->addHours(4);

    Booking::factory()->create([
        'vehicle_id' => $vehicle->id,
        'starts_at' => $start,
        'ends_at' => $end,
        'purpose' => BookingPurpose::Normal,
    ]);

    app(VehicleAdminService::class)->deactivate($vehicle, $admin, 'Ausgemustert');
})->throws(ValidationException::class);

test('vehicle can be deactivated and reactivated when no future bookings exist', function (): void {
    $admin = User::factory()->create();
    $vehicle = vehicleLockVehicle();

    $deactivated = app(VehicleAdminService::class)->deactivate($vehicle, $admin, 'Ausgemustert');

    expect($deactivated->active)->toBeFalse()
        ->and($deactivated->inactive_reason)->toBe('Ausgemustert')
        ->and($deactivated->inactive_by_user_id)->toBe($admin->id);

    $reactivated = app(VehicleAdminService::class)->activate($deactivated);

    expect($reactivated->active)->toBeTrue()
        ->and($reactivated->inactive_reason)->toBeNull()
        ->and($reactivated->inactive_by_user_id)->toBeNull();
});

test('vehicle availability update rejects conflicting bookings', function (): void {
    $vehicle = vehicleLockVehicle();
    $availableFrom = now()->addDays(5)->setTime(8, 0);

    Booking::factory()->create([
        'vehicle_id' => $vehicle->id,
        'starts_at' => now()->addDays(2)->setTime(8, 0),
        'ends_at' => now()->addDays(2)->setTime(12, 0),
        'purpose' => BookingPurpose::Normal,
    ]);

    app(VehicleAdminService::class)->updateAvailability($vehicle, $availableFrom, null);
})->throws(ValidationException::class);

test('active workshop booking is detected only while trip is running', function (): void {
    $admin = User::factory()->create();
    $driver = User::factory()->create();
    fuhrparkGrantValidLicense($driver);
    $vehicle = vehicleLockVehicle();
    $service = app(VehicleAdminService::class);

    expect($service->activeWorkshopBooking($vehicle))->toBeNull();

    $running = app(LogbookService::class)->createWorkshopTrip(
        $vehicle,
        $admin,
        $driver->id,
        now()->subHour(),
        now()->addHour(),
    );

    $future = app(LogbookService::class)->createWorkshopTrip(
        $vehicle,
        $admin,
        $driver->id,
        now()->addDay(),
        now()->addDay()->addHours(2),
    );

    expect($service->activeWorkshopBooking($vehicle->fresh())?->id)->toBe($running->id)
        ->and($service->activeWorkshopBooking($vehicle->fresh())?->id)->not->toBe($future->id);
});
