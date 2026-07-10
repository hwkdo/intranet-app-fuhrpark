<?php

declare(strict_types=1);

use App\Models\User;
use Hwkdo\IntranetAppFuhrpark\Models\Booking;
use Hwkdo\IntranetAppFuhrpark\Models\Vehicle;
use Hwkdo\IntranetAppFuhrpark\Models\VehicleCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Permission::findOrCreate('see-app-fuhrpark', 'web');
});

test('meine buchungen zeigt umbuchen button für aktualisierbare buchungen', function (): void {
    $user = User::factory()->create();
    $user->givePermissionTo('see-app-fuhrpark');
    fuhrparkGrantValidLicense($user);

    $category = VehicleCategory::factory()->create();
    $vehicle = Vehicle::factory()->create(['vehicle_category_id' => $category->id]);

    $booking = Booking::factory()->create([
        'vehicle_id' => $vehicle->id,
        'user_id' => $user->id,
        'driver_id' => $user->id,
        'starts_at' => now()->addDay()->setTime(8, 0),
        'ends_at' => now()->addDay()->setTime(12, 0),
    ]);

    Volt::test('apps.fuhrpark.my-bookings')
        ->actingAs($user)
        ->assertSee('Umbuchen')
        ->call('openReschedule', $booking->id)
        ->assertDispatched('open-booking-reschedule', bookingId: $booking->id);
});
