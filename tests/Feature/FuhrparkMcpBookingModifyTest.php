<?php

declare(strict_types=1);

use App\Models\Standort;
use App\Models\User;
use Hwkdo\IntranetAppFuhrpark\Mcp\Servers\FuhrparkServer;
use Hwkdo\IntranetAppFuhrpark\Mcp\Tools\BuchungLoeschenTool;
use Hwkdo\IntranetAppFuhrpark\Mcp\Tools\BuchungUmbuchenPruefenTool;
use Hwkdo\IntranetAppFuhrpark\Mcp\Tools\BuchungUmbuchenTool;
use Hwkdo\IntranetAppFuhrpark\Models\Booking;
use Hwkdo\IntranetAppFuhrpark\Models\Vehicle;
use Hwkdo\IntranetAppFuhrpark\Models\VehicleCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Permission::findOrCreate('see-app-fuhrpark', 'web');
});

function fuhrparkMcpReservedBooking(User $driver): Booking
{
    fuhrparkGrantValidLicense($driver);
    $standort = Standort::query()->create(['name' => 'MCP-Umbuchen-Standort']);
    fuhrparkMarkVehicleStandort($standort);
    $category = VehicleCategory::factory()->create();
    $vehicle = Vehicle::factory()->create([
        'vehicle_category_id' => $category->id,
        'standort_id' => $standort->id,
    ]);

    return Booking::factory()->create([
        'vehicle_id' => $vehicle->id,
        'user_id' => $driver->id,
        'driver_id' => $driver->id,
        'starts_at' => now()->addDay()->setTime(8, 0),
        'ends_at' => now()->addDay()->setTime(12, 0),
    ]);
}

test('buchung loeschen tool storniert reservierte buchung ohne begruendung', function (): void {
    $user = User::factory()->create();
    $user->givePermissionTo('see-app-fuhrpark');
    $booking = fuhrparkMcpReservedBooking($user);

    $response = FuhrparkServer::actingAs($user)->tool(BuchungLoeschenTool::class, [
        'booking_id' => $booking->id,
    ]);

    $response->assertOk();
    $response->assertSee('success');
    $response->assertSee('verified');
    $response->assertSee('erfolgreich gelöscht');

    expect(Booking::query()->find($booking->id))->toBeNull();
});

test('buchung loeschen tool verlangt begruendung bei ueberfaelliger buchung', function (): void {
    $user = User::factory()->create();
    $user->givePermissionTo('see-app-fuhrpark');
    $booking = fuhrparkMcpReservedBooking($user);
    $booking->update([
        'starts_at' => now()->subHours(2),
        'ends_at' => now()->addHours(2),
    ]);

    $response = FuhrparkServer::actingAs($user)->tool(BuchungLoeschenTool::class, [
        'booking_id' => $booking->id,
    ]);

    $response->assertOk();
    $response->assertSee('requires_reason');
    $response->assertSee('Begründung');

    expect(Booking::query()->find($booking->id))->not->toBeNull();
});

test('buchung umbuchen pruefen und umbuchen verschiebt reservierte buchung', function (): void {
    $user = User::factory()->create();
    $user->givePermissionTo('see-app-fuhrpark');
    $booking = fuhrparkMcpReservedBooking($user);

    $newStart = now()->addDays(2)->setTime(9, 0);
    $newEnd = now()->addDays(2)->setTime(13, 0);

    $checkResponse = FuhrparkServer::actingAs($user)->tool(BuchungUmbuchenPruefenTool::class, [
        'booking_id' => $booking->id,
        'starts_at' => $newStart->toIso8601String(),
        'ends_at' => $newEnd->toIso8601String(),
    ]);

    $checkResponse->assertOk();
    $checkResponse->assertSee('success');
    $checkResponse->assertSee('same_category_available');

    $rescheduleResponse = FuhrparkServer::actingAs($user)->tool(BuchungUmbuchenTool::class, [
        'booking_id' => $booking->id,
        'starts_at' => $newStart->toIso8601String(),
        'ends_at' => $newEnd->toIso8601String(),
    ]);

    $rescheduleResponse->assertOk();
    $rescheduleResponse->assertSee('erfolgreich umgebucht');
    $rescheduleResponse->assertSee('verified');

    $booking->refresh();

    expect($booking->starts_at->equalTo($newStart))->toBeTrue()
        ->and($booking->ends_at->equalTo($newEnd))->toBeTrue();
});

test('buchung umbuchen funktioniert fuer admin ohne vehicle_id wenn gleiche kategorie frei', function (): void {
    Permission::findOrCreate('manage-app-fuhrpark', 'web');

    $user = User::factory()->create();
    $user->givePermissionTo(['see-app-fuhrpark', 'manage-app-fuhrpark']);
    $booking = fuhrparkMcpReservedBooking($user);

    $newStart = now()->addDays(2)->setTime(9, 0);
    $newEnd = now()->addDays(2)->setTime(13, 0);

    $rescheduleResponse = FuhrparkServer::actingAs($user)->tool(BuchungUmbuchenTool::class, [
        'booking_id' => $booking->id,
        'starts_at' => $newStart->toIso8601String(),
        'ends_at' => $newEnd->toIso8601String(),
    ]);

    $rescheduleResponse->assertOk();
    $rescheduleResponse->assertSee('erfolgreich umgebucht');
    $rescheduleResponse->assertSee('verified');

    $booking->refresh();

    expect($booking->starts_at->equalTo($newStart))->toBeTrue()
        ->and($booking->ends_at->equalTo($newEnd))->toBeTrue();
});
