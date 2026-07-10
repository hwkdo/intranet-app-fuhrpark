<?php

declare(strict_types=1);

use App\Models\Standort;
use App\Models\User;
use Hwkdo\IntranetAppFuhrpark\Mcp\Servers\FuhrparkServer;
use Hwkdo\IntranetAppFuhrpark\Mcp\Tools\FahrtenbuchEintragenTool;
use Hwkdo\IntranetAppFuhrpark\Mcp\Tools\FehlendeFahrtenbuecherAnzeigenTool;
use Hwkdo\IntranetAppFuhrpark\Models\Booking;
use Hwkdo\IntranetAppFuhrpark\Models\Handout;
use Hwkdo\IntranetAppFuhrpark\Models\Vehicle;
use Hwkdo\IntranetAppFuhrpark\Models\VehicleCategory;
use Hwkdo\IntranetAppFuhrpark\Models\VehicleReturn;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Permission::findOrCreate('see-app-fuhrpark', 'web');
});

function fuhrparkMcpTestVehicle(): Vehicle
{
    $standort = Standort::query()->create(['name' => 'MCP-Test-Standort']);
    fuhrparkMarkVehicleStandort($standort);
    $category = VehicleCategory::factory()->create();

    return Vehicle::factory()->create([
        'vehicle_category_id' => $category->id,
        'standort_id' => $standort->id,
    ]);
}

function fuhrparkReturnedBookingWithoutLogbook(User $driver): Booking
{
    $processor = User::factory()->create();
    $vehicle = fuhrparkMcpTestVehicle();
    $booking = Booking::factory()->create([
        'vehicle_id' => $vehicle->id,
        'user_id' => $driver->id,
        'driver_id' => $driver->id,
        'starts_at' => now()->subDay(),
        'ends_at' => now()->subDay()->addHours(4),
        'km_start' => 1000,
        'km_end' => 1042,
    ]);

    $handout = Handout::query()->create([
        'booking_id' => $booking->id,
        'driver_id' => $driver->id,
        'processed_by_user_id' => $processor->id,
        'signature_data' => ['data' => 'signed'],
    ]);

    VehicleReturn::query()->create([
        'handout_id' => $handout->id,
        'driver_id' => $driver->id,
        'processed_by_user_id' => $processor->id,
        'km_end' => 1042,
        'checklist' => [],
        'has_damage' => false,
    ]);

    return $booking->fresh(['vehicle', 'handout.returnRecord', 'logbookEntry']);
}

test('fehlende fahrtenbuecher tool listet zurückgegebene buchungen ohne fahrtenbuch', function (): void {
    $user = User::factory()->create();
    $user->givePermissionTo('see-app-fuhrpark');
    $booking = fuhrparkReturnedBookingWithoutLogbook($user);

    $response = FuhrparkServer::actingAs($user)->tool(FehlendeFahrtenbuecherAnzeigenTool::class, []);

    $response->assertOk();
    $response->assertSee((string) $booking->id);
    $response->assertSee('hinweis_fuer_assistent');
});

test('fahrtenbuch eintragen tool erfasst fahrtenbuch für returned buchung', function (): void {
    $user = User::factory()->create();
    $user->givePermissionTo('see-app-fuhrpark');
    $booking = fuhrparkReturnedBookingWithoutLogbook($user);

    $response = FuhrparkServer::actingAs($user)->tool(FahrtenbuchEintragenTool::class, [
        'booking_id' => $booking->id,
        'route' => 'Aachen - Düren',
        'km_commute' => 30,
        'km_project' => 0,
        'fueled' => true,
        'cleaned' => false,
    ]);

    $response->assertOk();
    $response->assertSee('success');
    $response->assertSee('Fahrtenbucheintrag erfolgreich erfasst');

    expect($booking->fresh('logbookEntry')->logbookEntry)->not->toBeNull()
        ->and($booking->logbookEntry->route)->toBe('Aachen - Düren');
});
