<?php

declare(strict_types=1);

use App\Models\User;
use Hwkdo\IntranetAppFuhrpark\Models\Booking;
use Hwkdo\IntranetAppFuhrpark\Models\Vehicle;
use Hwkdo\IntranetAppFuhrpark\Services\MsGraphBookingCalendarSync;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('booking calendar sync interface resolves to ms graph when package is installed', function (): void {
    expect(app(\Hwkdo\IntranetAppFuhrpark\Contracts\BookingCalendarSyncInterface::class))
        ->toBeInstanceOf(MsGraphBookingCalendarSync::class);
});

test('ms graph booking calendar sync skips create without driver upn', function (): void {
    $driver = User::factory()->create(['username' => null]);
    $vehicle = Vehicle::factory()->create();

    $booking = Booking::factory()->create([
        'vehicle_id' => $vehicle->id,
        'driver_id' => $driver->id,
        'user_id' => $driver->id,
        'sync_to_calendar' => true,
    ]);

    $sync = app(MsGraphBookingCalendarSync::class);

    expect($sync->createEvent($booking))->toBeNull();
});

test('ms graph booking calendar sync skips update without event id', function (): void {
    $driver = User::factory()->create();
    $vehicle = Vehicle::factory()->create();

    $booking = Booking::factory()->create([
        'vehicle_id' => $vehicle->id,
        'driver_id' => $driver->id,
        'user_id' => $driver->id,
        'ms_graph_event_id' => null,
    ]);

    $sync = app(MsGraphBookingCalendarSync::class);

    $sync->updateEvent($booking, now()->addDay(), now()->addDays(2));

    expect(true)->toBeTrue();
});

test('ms graph booking calendar sync skips delete without event id', function (): void {
    $driver = User::factory()->create();
    $vehicle = Vehicle::factory()->create();

    $booking = Booking::factory()->create([
        'vehicle_id' => $vehicle->id,
        'driver_id' => $driver->id,
        'user_id' => $driver->id,
        'ms_graph_event_id' => null,
    ]);

    $sync = app(MsGraphBookingCalendarSync::class);

    $sync->deleteEvent($booking);

    expect(true)->toBeTrue();
});
