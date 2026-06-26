<?php

declare(strict_types=1);

use App\Models\Standort;
use App\Models\User;
use Carbon\Carbon;
use Hwkdo\IntranetAppFuhrpark\Models\Booking;
use Hwkdo\IntranetAppFuhrpark\Models\StandortSetting;
use Hwkdo\IntranetAppFuhrpark\Models\Vehicle;
use Hwkdo\IntranetAppFuhrpark\Models\VehicleCategory;
use Hwkdo\IntranetAppFuhrpark\Services\StandortAdminService;
use Hwkdo\IntranetAppFuhrpark\Support\FuhrparkModels;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

test('standort admin service lists only marked vehicle standorte for booking', function (): void {
    $vehicleStandort = Standort::query()->create(['name' => 'Fuhrpark']);
    $office = Standort::query()->create(['name' => 'Büro']);

    fuhrparkMarkVehicleStandort($vehicleStandort);
    fuhrparkMarkVehicleStandort($office, isVehicleStandort: false, vehicleStandortId: $vehicleStandort->id);

    expect(app(StandortAdminService::class)->vehicleStandorte())->toHaveCount(1)
        ->and(app(StandortAdminService::class)->vehicleStandorte()->first()->id)->toBe($vehicleStandort->id);
});

test('standort admin service can mark and assign standorte in package table', function (): void {
    $vehicleStandort = Standort::query()->create(['name' => 'Fuhrpark']);
    $office = Standort::query()->create(['name' => 'Büro']);

    app(StandortAdminService::class)->setVehicleStandort($vehicleStandort, true);
    app(StandortAdminService::class)->assignVehicleStandort($office, $vehicleStandort->id);

    $officeSetting = StandortSetting::query()->where('standort_id', $office->id)->first();

    expect(StandortSetting::query()->where('standort_id', $vehicleStandort->id)->value('is_vehicle_standort'))->toBeTrue()
        ->and($officeSetting?->is_vehicle_standort)->toBeFalse()
        ->and($officeSetting?->vehicle_standort_id)->toBe($vehicleStandort->id)
        ->and(FuhrparkModels::vehicleStandortIdFor($office->id))->toBe($vehicleStandort->id);
});

test('standort admin service rejects assigning vehicle standort to another', function (): void {
    $vehicleStandort = Standort::query()->create(['name' => 'Fuhrpark']);

    fuhrparkMarkVehicleStandort($vehicleStandort);

    app(StandortAdminService::class)->assignVehicleStandort($vehicleStandort, $vehicleStandort->id);
})->throws(ValidationException::class);

test('desk filters handouts by selected vehicle standort', function (): void {
    Carbon::setTestNow(Carbon::today()->setTime(10, 0));

    Permission::findOrCreate('operate-app-fuhrpark-zentrale', 'web');

    $standortA = Standort::query()->create(['name' => 'Standort A']);
    $standortB = Standort::query()->create(['name' => 'Standort B']);

    fuhrparkMarkVehicleStandort($standortA);
    fuhrparkMarkVehicleStandort($standortB);

    $category = VehicleCategory::factory()->create();
    $vehicleA = Vehicle::factory()->create([
        'vehicle_category_id' => $category->id,
        'standort_id' => $standortA->id,
    ]);
    $vehicleB = Vehicle::factory()->create([
        'vehicle_category_id' => $category->id,
        'standort_id' => $standortB->id,
    ]);

    $driver = User::factory()->create(['active' => true]);

    Booking::factory()->create([
        'vehicle_id' => $vehicleA->id,
        'driver_id' => $driver->id,
        'starts_at' => now()->setTime(8, 0),
        'ends_at' => now()->setTime(12, 0),
    ]);

    Booking::factory()->create([
        'vehicle_id' => $vehicleB->id,
        'driver_id' => $driver->id,
        'starts_at' => now()->setTime(8, 0),
        'ends_at' => now()->setTime(12, 0),
    ]);

    $operator = User::factory()->create(['active' => true, 'standort_id' => $standortA->id]);
    $operator->givePermissionTo('operate-app-fuhrpark-zentrale');

    Livewire::actingAs($operator)
        ->test('apps.fuhrpark.desk')
        ->assertSet('deskStandortId', $standortA->id)
        ->assertSee($vehicleA->license_plate)
        ->assertDontSee($vehicleB->license_plate)
        ->set('deskStandortId', $standortB->id)
        ->assertDontSee($vehicleA->license_plate)
        ->assertSee($vehicleB->license_plate);
});
