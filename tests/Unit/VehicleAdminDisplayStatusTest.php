<?php

declare(strict_types=1);

use App\Models\User;
use Hwkdo\IntranetAppFuhrpark\Enums\BookingPurpose;
use Hwkdo\IntranetAppFuhrpark\Enums\VehicleAdminDisplayStatus;
use Hwkdo\IntranetAppFuhrpark\Enums\VehicleAdminUnavailabilityCause;
use Hwkdo\IntranetAppFuhrpark\Models\Booking;
use Hwkdo\IntranetAppFuhrpark\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('admin display status is unavailable for inactive vehicles', function (): void {
    $vehicle = Vehicle::factory()->create(['active' => false]);

    expect($vehicle->adminDisplayStatus())->toBe(VehicleAdminDisplayStatus::Unavailable)
        ->and($vehicle->adminUnavailabilityCause())->toBe(VehicleAdminUnavailabilityCause::Deactivated)
        ->and($vehicle->adminStatusMenuIsUrgent('deactivate'))->toBeTrue()
        ->and($vehicle->adminStatusMenuIsUrgent('lock'))->toBeFalse();
});

test('admin display status is underway when vehicle has a regular booking now', function (): void {
    $vehicle = Vehicle::factory()->create(['active' => true]);
    $driver = User::factory()->create();

    Booking::factory()->create([
        'vehicle_id' => $vehicle->id,
        'driver_id' => $driver->id,
        'user_id' => $driver->id,
        'starts_at' => now()->subHour(),
        'ends_at' => now()->addHour(),
        'purpose' => BookingPurpose::Normal,
    ]);

    expect($vehicle->fresh()->adminDisplayStatus())->toBe(VehicleAdminDisplayStatus::Underway)
        ->and($vehicle->fresh()->adminUnavailabilityCause())->toBeNull()
        ->and($vehicle->fresh()->adminStatusMenuIsUrgent('deactivate'))->toBeFalse();
});

test('admin display status is unavailable when vehicle is locked now', function (): void {
    $vehicle = Vehicle::factory()->create(['active' => true]);
    $admin = User::factory()->create();

    Booking::factory()->create([
        'vehicle_id' => $vehicle->id,
        'driver_id' => $admin->id,
        'user_id' => $admin->id,
        'starts_at' => now()->subHour(),
        'ends_at' => now()->addHour(),
        'purpose' => BookingPurpose::Lock,
        'lock_reason' => 'Werkstatt',
    ]);

    expect($vehicle->fresh()->adminDisplayStatus())->toBe(VehicleAdminDisplayStatus::Unavailable)
        ->and($vehicle->fresh()->adminUnavailabilityCause())->toBe(VehicleAdminUnavailabilityCause::Lock)
        ->and($vehicle->fresh()->adminStatusMenuIsUrgent('lock'))->toBeTrue();
});

test('admin display status is limited when future locks exist', function (): void {
    $vehicle = Vehicle::factory()->create(['active' => true]);
    $vehicle->setAttribute('active_locks_count', 1);

    expect($vehicle->adminDisplayStatus())->toBe(VehicleAdminDisplayStatus::Limited)
        ->and($vehicle->adminUnavailabilityCause())->toBeNull();
});

test('admin display status is available when vehicle is active and unencumbered', function (): void {
    $vehicle = Vehicle::factory()->create(['active' => true]);
    $vehicle->setAttribute('active_locks_count', 0);

    expect($vehicle->adminDisplayStatus())->toBe(VehicleAdminDisplayStatus::Available);
});
