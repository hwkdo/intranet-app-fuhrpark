<?php

declare(strict_types=1);

use App\Models\Standort;
use App\Models\User;
use Hwkdo\IntranetAppFuhrpark\Models\Vehicle;
use Hwkdo\IntranetAppFuhrpark\Models\VehicleCategory;
use Hwkdo\IntranetAppFuhrpark\Services\Legacy\LegacyFuhrparkMapper;

test('legacy booking datetimes from utc iso are stored in app timezone', function (): void {
    config(['app.timezone' => 'Europe/Berlin']);

    $standort = Standort::query()->create(['name' => 'TZ-Standort', 'legacy_id' => 1]);
    fuhrparkMarkVehicleStandort($standort);

    $user = User::factory()->create(['legacy_id' => 10]);
    $driver = User::factory()->create(['legacy_id' => 11]);
    $category = VehicleCategory::factory()->create(['legacy_id' => 1]);
    $vehicle = Vehicle::factory()->create([
        'legacy_id' => 7,
        'vehicle_category_id' => $category->id,
        'standort_id' => $standort->id,
    ]);

    $mapper = app(LegacyFuhrparkMapper::class);

    $attributes = $mapper->mapBooking(
        [
            'zweck' => 'Dienstfahrt',
            'start' => '2026-06-30T05:00:00.000000Z',
            'ende' => '2026-06-30T12:00:00.000000Z',
            'arbeitsfahrt' => 0,
        ],
        $vehicle->id,
        $user->id,
        $driver->id,
    );

    expect($attributes['starts_at']->format('Y-m-d H:i'))->toBe('2026-06-30 07:00')
        ->and($attributes['ends_at']->format('Y-m-d H:i'))->toBe('2026-06-30 14:00')
        ->and($attributes['starts_at']->timezone->getName())->toBe('Europe/Berlin');
});
