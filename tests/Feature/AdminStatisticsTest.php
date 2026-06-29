<?php

declare(strict_types=1);

use App\Models\Standort;
use App\Models\User;
use Hwkdo\IntranetAppFuhrpark\Enums\BookingPurpose;
use Hwkdo\IntranetAppFuhrpark\Models\Booking;
use Hwkdo\IntranetAppFuhrpark\Models\Handout;
use Hwkdo\IntranetAppFuhrpark\Models\Vehicle;
use Hwkdo\IntranetAppFuhrpark\Models\VehicleCategory;
use Hwkdo\IntranetAppFuhrpark\Models\VehicleReturn;
use Hwkdo\IntranetAppFuhrpark\Services\FuhrparkAdminStatisticsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

function fuhrparkStatisticsVehicle(Standort $standort, string $licensePlate): Vehicle
{
    return Vehicle::factory()->create([
        'vehicle_category_id' => VehicleCategory::factory()->create()->id,
        'standort_id' => $standort->id,
        'license_plate' => $licensePlate,
    ]);
}

test('admin statistics aggregates km and booking leaders', function (): void {
    $standort = Standort::query()->create(['name' => 'Statistik-Standort']);
    fuhrparkMarkVehicleStandort($standort);

    $vehicleA = fuhrparkStatisticsVehicle($standort, 'DO-HW 100');
    $vehicleB = fuhrparkStatisticsVehicle($standort, 'DO-HW 200');

    $driverA = User::factory()->create(['name' => 'Anna Fahrer']);
    $driverB = User::factory()->create(['name' => 'Bernd Fahrer']);
    $booker = User::factory()->create();

    Booking::factory()->create([
        'vehicle_id' => $vehicleA->id,
        'user_id' => $booker->id,
        'driver_id' => $driverA->id,
        'purpose' => BookingPurpose::Normal,
        'starts_at' => now()->startOfMonth()->addDay()->setTime(8, 0),
        'ends_at' => now()->startOfMonth()->addDay()->setTime(12, 0),
        'km_start' => 1000,
        'km_end' => 1150,
        'is_commute' => true,
    ]);

    Booking::factory()->create([
        'vehicle_id' => $vehicleA->id,
        'user_id' => $booker->id,
        'driver_id' => $driverA->id,
        'purpose' => BookingPurpose::Normal,
        'starts_at' => now()->startOfMonth()->addDays(2)->setTime(9, 0),
        'ends_at' => now()->startOfMonth()->addDays(2)->setTime(11, 0),
        'km_start' => 1150,
        'km_end' => 1200,
    ]);

    Booking::factory()->create([
        'vehicle_id' => $vehicleB->id,
        'user_id' => $booker->id,
        'driver_id' => $driverB->id,
        'purpose' => BookingPurpose::Normal,
        'starts_at' => now()->startOfMonth()->addDays(3)->setTime(10, 0),
        'ends_at' => now()->startOfMonth()->addDays(3)->setTime(14, 0),
        'km_start' => 5000,
        'km_end' => 5300,
    ]);

    Booking::factory()->create([
        'vehicle_id' => $vehicleB->id,
        'user_id' => $booker->id,
        'driver_id' => $driverB->id,
        'purpose' => BookingPurpose::Lock,
        'starts_at' => now()->startOfMonth()->addDays(4)->setTime(10, 0),
        'ends_at' => now()->startOfMonth()->addDays(4)->setTime(14, 0),
    ]);

    $stats = app(FuhrparkAdminStatisticsService::class)->collect('month');

    expect($stats['overview']['total_km'])->toBe(450)
        ->and($stats['overview']['completed_trips'])->toBe(3)
        ->and($stats['overview']['average_km_per_trip'])->toBe(150.0)
        ->and($stats['overview']['total_bookings'])->toBe(3)
        ->and($stats['overview']['commute_bookings'])->toBe(1)
        ->and($stats['top_vehicle_by_km']['license_plate'])->toBe('DO-HW 200')
        ->and($stats['top_vehicle_by_km']['km'])->toBe(300)
        ->and($stats['top_vehicle_by_bookings']['license_plate'])->toBe('DO-HW 100')
        ->and($stats['top_vehicle_by_bookings']['bookings'])->toBe(2)
        ->and($stats['top_driver_by_km']['name'])->toBe('Bernd Fahrer')
        ->and($stats['top_driver_by_km']['km'])->toBe(300)
        ->and($stats['top_driver_by_bookings']['name'])->toBe('Anna Fahrer')
        ->and($stats['top_driver_by_bookings']['bookings'])->toBe(2)
        ->and($stats['top_vehicles_by_km'])->toHaveCount(2)
        ->and($stats['top_drivers_by_km'])->toHaveCount(2);
});

test('admin statistics separates electric planned route and combustion driven km', function (): void {
    $standort = Standort::query()->create(['name' => 'Statistik-Standort']);
    fuhrparkMarkVehicleStandort($standort);

    $electricVehicle = Vehicle::factory()->electric()->create([
        'standort_id' => $standort->id,
    ]);
    $combustionVehicle = fuhrparkStatisticsVehicle($standort, 'DO-HW 400');

    $booker = User::factory()->create();
    $driver = User::factory()->create();

    Booking::factory()->create([
        'vehicle_id' => $electricVehicle->id,
        'user_id' => $booker->id,
        'driver_id' => $driver->id,
        'starts_at' => now()->startOfMonth()->addDay(),
        'ends_at' => now()->startOfMonth()->addDay()->addHours(4),
        'electric_route_km' => 120,
        'km_start' => 1000,
        'km_end' => 1120,
    ]);

    Booking::factory()->create([
        'vehicle_id' => $combustionVehicle->id,
        'user_id' => $booker->id,
        'driver_id' => $driver->id,
        'starts_at' => now()->startOfMonth()->addDays(2),
        'ends_at' => now()->startOfMonth()->addDays(2)->addHours(4),
        'km_start' => 2000,
        'km_end' => 2180,
    ]);

    $stats = app(FuhrparkAdminStatisticsService::class)->collect('month');

    expect($stats['overview']['electric_route_km'])->toBe(120)
        ->and($stats['overview']['combustion_route_km'])->toBe(180);
});

test('admin statistics component renders key metrics', function (): void {
    Volt::test('apps.fuhrpark.admin.statistics')
        ->assertSee('Fuhrpark-Statistik')
        ->assertSee('Gefahrene Kilometer')
        ->assertSee('Meiste Kilometer (Fahrzeug)')
        ->assertSee('Elektro-Strecke')
        ->assertSee('Verbrenner-Strecke');
});

test('admin statistics page is available for fuhrpark managers', function (): void {
    Permission::findOrCreate('manage-app-fuhrpark', 'web');

    $admin = User::factory()->create();
    $admin->givePermissionTo('manage-app-fuhrpark');

    $this->actingAs($admin)
        ->get(route('apps.fuhrpark.admin.index', ['tab' => 'statistiken']))
        ->assertOk();
});

test('admin statistics counts handouts and returns in period', function (): void {
    $standort = Standort::query()->create(['name' => 'Statistik-Standort']);
    fuhrparkMarkVehicleStandort($standort);

    $vehicle = fuhrparkStatisticsVehicle($standort, 'DO-HW 300');
    $driver = User::factory()->create();
    $processor = User::factory()->create();
    $booker = User::factory()->create();

    $booking = Booking::factory()->create([
        'vehicle_id' => $vehicle->id,
        'user_id' => $booker->id,
        'driver_id' => $driver->id,
        'starts_at' => now()->startOfMonth()->addDay(),
        'ends_at' => now()->startOfMonth()->addDay()->addHours(4),
        'km_start' => 1000,
        'km_end' => 1040,
    ]);

    $handout = Handout::query()->create([
        'booking_id' => $booking->id,
        'driver_id' => $driver->id,
        'processed_by_user_id' => $processor->id,
    ]);

    VehicleReturn::query()->create([
        'handout_id' => $handout->id,
        'driver_id' => $driver->id,
        'processed_by_user_id' => $processor->id,
        'km_end' => 1040,
    ]);

    $stats = app(FuhrparkAdminStatisticsService::class)->collect('month');

    expect($stats['overview']['handouts'])->toBe(1)
        ->and($stats['overview']['returns'])->toBe(1);
});
