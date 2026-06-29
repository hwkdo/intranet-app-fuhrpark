<?php

declare(strict_types=1);

use App\Models\Standort;
use App\Models\User;
use Carbon\Carbon;
use Hwkdo\IntranetAppFuhrpark\Models\Booking;
use Hwkdo\IntranetAppFuhrpark\Models\Handout;
use Hwkdo\IntranetAppFuhrpark\Models\Vehicle;
use Hwkdo\IntranetAppFuhrpark\Models\VehicleCategory;
use Hwkdo\IntranetAppFuhrpark\Services\BookingStatusResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

function fuhrparkDeskStandort(): Standort
{
    $standort = Standort::query()->create(['name' => 'Test-Standort']);

    fuhrparkMarkVehicleStandort($standort);

    return $standort;
}

function fuhrparkDeskVehicle(): Vehicle
{
    $category = VehicleCategory::factory()->create();
    $standort = fuhrparkDeskStandort();

    return Vehicle::factory()->create([
        'vehicle_category_id' => $category->id,
        'standort_id' => $standort->id,
    ]);
}

function fuhrparkDeskOperator(): User
{
    Permission::findOrCreate('operate-app-fuhrpark-zentrale', 'web');

    $user = User::factory()->create(['active' => true]);
    $user->givePermissionTo('operate-app-fuhrpark-zentrale');

    return $user;
}

test('desk opens return modal without querying user name column', function (): void {
    Carbon::setTestNow(Carbon::today()->setTime(10, 0));

    $driver = User::factory()->create([
        'active' => true,
        'vorname' => 'Rückgabe',
        'nachname' => 'Fahrer',
    ]);
    $processor = User::factory()->create(['active' => true]);
    $vehicle = fuhrparkDeskVehicle();

    $booking = Booking::factory()->create([
        'vehicle_id' => $vehicle->id,
        'driver_id' => $driver->id,
        'starts_at' => now()->setTime(8, 0),
        'ends_at' => now()->setTime(12, 0),
        'km_start' => 1000,
    ]);

    Handout::query()->create([
        'booking_id' => $booking->id,
        'driver_id' => $driver->id,
        'processed_by_user_id' => $processor->id,
    ]);

    Livewire::actingAs(fuhrparkDeskOperator())
        ->test('apps.fuhrpark.desk')
        ->call('openReturn', $booking->id)
        ->assertSet('showReturnModal', true)
        ->assertSee('Rückgabe bestätigen')
        ->assertDontSee('Fahrer auswählen');
});

test('desk shows predecessor warning when handout is blocked', function (): void {
    Carbon::setTestNow(Carbon::today()->setTime(10, 0));

    $vehicle = fuhrparkDeskVehicle();
    $driver = User::factory()->create(['active' => true, 'vorname' => 'Vorgänger', 'nachname' => 'Fahrer']);
    $processor = User::factory()->create(['active' => true]);

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
        'starts_at' => now()->setTime(8, 0),
        'ends_at' => now()->setTime(12, 0),
    ]);

    $predecessorStatus = app(BookingStatusResolver::class)->resolve($predecessor->fresh(['handout.returnRecord', 'logbookEntry']));

    Livewire::actingAs(fuhrparkDeskOperator())
        ->test('apps.fuhrpark.desk')
        ->call('openHandout', $booking->id)
        ->assertSet('showHandoutModal', true)
        ->assertSee('Vorgängerbuchung')
        ->assertSee('noch nicht zurückgegeben')
        ->assertSee($vehicle->license_plate)
        ->assertSee('Vorgänger Fahrer')
        ->assertSee($predecessorStatus->label())
        ->assertDontSee('Ausgabe bestätigen');
});

test('desk confirms return with signature', function (): void {
    Carbon::setTestNow(Carbon::today()->setTime(10, 0));

    $driver = User::factory()->create(['active' => true]);
    $processor = User::factory()->create(['active' => true]);
    $vehicle = fuhrparkDeskVehicle();

    $booking = Booking::factory()->create([
        'vehicle_id' => $vehicle->id,
        'driver_id' => $driver->id,
        'starts_at' => now()->setTime(8, 0),
        'ends_at' => now()->setTime(12, 0),
        'km_start' => 1000,
    ]);

    Handout::query()->create([
        'booking_id' => $booking->id,
        'driver_id' => $driver->id,
        'processed_by_user_id' => $processor->id,
        'signature_data' => ['data' => 'handout-signed'],
    ]);

    Livewire::actingAs(fuhrparkDeskOperator())
        ->test('apps.fuhrpark.desk')
        ->call('openReturn', $booking->id)
        ->set('returnKmEnd', 1050)
        ->set('returnSignatureData', 'return-base64-signature')
        ->call('confirmReturn')
        ->assertHasNoErrors()
        ->assertSet('showReturnModal', false);

    $return = $booking->fresh('handout.returnRecord')->handout?->returnRecord;

    expect($return)->not->toBeNull()
        ->and($return->signature_data)->toBe(['data' => 'return-base64-signature']);
});

test('desk passes selected handout driver to handout service', function (): void {
    Carbon::setTestNow(Carbon::today()->setTime(10, 0));

    $bookingDriver = User::factory()->create(['active' => true, 'vorname' => 'Buchungs', 'nachname' => 'Fahrer']);
    $handoutDriver = User::factory()->create(['active' => true, 'vorname' => 'Ausgabe', 'nachname' => 'Fahrer']);
    $vehicle = fuhrparkDeskVehicle();

    $booking = Booking::factory()->create([
        'vehicle_id' => $vehicle->id,
        'driver_id' => $bookingDriver->id,
        'starts_at' => now()->setTime(8, 0),
        'ends_at' => now()->setTime(12, 0),
    ]);

    Livewire::actingAs(fuhrparkDeskOperator())
        ->test('apps.fuhrpark.desk')
        ->call('openHandout', $booking->id)
        ->assertSet('handoutDriverId', $bookingDriver->id)
        ->set('handoutDriverId', $handoutDriver->id)
        ->assertSet('selectedHandoutDriverName', 'Ausgabe Fahrer')
        ->set('signatureData', 'base64-signature')
        ->call('confirmHandout')
        ->assertHasNoErrors()
        ->assertSet('showHandoutModal', false);

    $handout = Handout::query()->where('booking_id', $booking->id)->first();

    expect($handout)->not->toBeNull()
        ->and($handout->driver_id)->toBe($handoutDriver->id)
        ->and($handout->signature_data)->toBe(['data' => 'base64-signature']);
});
