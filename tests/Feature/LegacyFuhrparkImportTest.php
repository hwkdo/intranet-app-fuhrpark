<?php

declare(strict_types=1);

use App\Models\Standort;
use App\Models\User;
use App\Services\IntranetLegacyService;
use Hwkdo\IntranetAppFuhrpark\Models\Booking;
use Hwkdo\IntranetAppFuhrpark\Models\Vehicle;
use Hwkdo\IntranetAppFuhrpark\Models\VehicleCategory;
use Hwkdo\IntranetAppFuhrpark\Services\Legacy\LegacyFuhrparkImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

test('legacy import creates bookings with calendar id from bulk api', function (): void {
    config([
        'legacy.base_api_url' => 'https://legacy.test/api/',
        'legacy.base_api_token' => 'test-token',
    ]);

    $standort = Standort::query()->create(['name' => 'Import-Standort', 'legacy_id' => 5]);
    fuhrparkMarkVehicleStandort($standort);

    $user = User::factory()->create(['legacy_id' => 10]);
    $driver = User::factory()->create(['legacy_id' => 11]);

    Http::fake([
        'legacy.test/api/base/fuhrpark-export/categories' => Http::response([
            ['id' => 1, 'name' => 'Kombi', 'braucht_fuehrerschein' => true, 'elektro' => false],
        ]),
        'legacy.test/api/base/fuhrpark-export/projects' => Http::response([]),
        'legacy.test/api/base/fuhrpark-export/vehicles' => Http::response([
            [
                'id' => 7,
                'kennzeichen' => 'DO-FP 100',
                'fahrzeugkategorie_id' => 1,
                'standort_id' => 5,
                'aktiv' => true,
                'kraftstoff' => 'Benzin',
            ],
        ]),
        'legacy.test/api/base/fuhrpark-export/driver-licenses' => Http::response([]),
        'legacy.test/api/base/fuhrpark-export/driver-license-controls' => Http::response([]),
        'legacy.test/api/base/fuhrpark-export/bookings*' => Http::response([
            [
                'id' => 99,
                'zweck' => 'Dienstfahrt',
                'fahrzeug_id' => 7,
                'user_id' => 10,
                'fahrer_id' => 11,
                'start' => '2025-06-01 08:00:00',
                'ende' => '2025-06-01 12:00:00',
                'kalender' => 'graph-import-99',
                'arbeitsfahrt' => 0,
            ],
        ]),
        'legacy.test/api/base/fuhrpark-export/handouts*' => Http::response([]),
        'legacy.test/api/base/fuhrpark-export/returns*' => Http::response([]),
        'legacy.test/api/base/fuhrpark-export/logbook-entries*' => Http::response([]),
    ]);

    app(LegacyFuhrparkImportService::class)->import(
        app(IntranetLegacyService::class),
        '2025-01-01',
        dryRun: false,
        only: ['categories', 'vehicles', 'bookings'],
    );

    $booking = Booking::query()->where('legacy_id', 99)->first();

    expect($booking)->not->toBeNull()
        ->and($booking->ms_graph_event_id)->toBe('graph-import-99')
        ->and($booking->sync_to_calendar)->toBeTrue()
        ->and($booking->vehicle_id)->toBe(Vehicle::query()->where('legacy_id', 7)->value('id'));
});

test('legacy import creates separate vehicles for duplicate license plates', function (): void {
    config([
        'legacy.base_api_url' => 'https://legacy.test/api/',
        'legacy.base_api_token' => 'test-token',
    ]);

    $standort = Standort::query()->create(['name' => 'Import-Standort', 'legacy_id' => 5]);
    fuhrparkMarkVehicleStandort($standort);

    Http::fake([
        'legacy.test/api/base/fuhrpark-export/categories' => Http::response([
            ['id' => 1, 'name' => 'Kombi', 'braucht_fuehrerschein' => true, 'elektro' => false],
        ]),
        'legacy.test/api/base/fuhrpark-export/vehicles' => Http::response([
            [
                'id' => 2,
                'kennzeichen' => 'DO HK 2052',
                'fahrzeugkategorie_id' => 1,
                'standort_id' => 5,
                'aktiv' => false,
                'kraftstoff' => 'Benzin',
            ],
            [
                'id' => 4,
                'kennzeichen' => 'DO HK 2052',
                'fahrzeugkategorie_id' => 1,
                'standort_id' => 5,
                'aktiv' => true,
                'kraftstoff' => 'Benzin',
            ],
        ]),
    ]);

    app(LegacyFuhrparkImportService::class)->import(
        app(IntranetLegacyService::class),
        '2025-01-01',
        dryRun: false,
        only: ['categories', 'vehicles'],
    );

    $olderVehicle = Vehicle::query()->where('legacy_id', 2)->first();
    $currentVehicle = Vehicle::query()->where('legacy_id', 4)->first();

    expect($olderVehicle)->not->toBeNull()
        ->and($currentVehicle)->not->toBeNull()
        ->and($olderVehicle->id)->not->toBe($currentVehicle->id)
        ->and($olderVehicle->license_plate)->toBe('DO HK 2052')
        ->and($currentVehicle->license_plate)->toBe('DO HK 2052');
});

test('legacy import updates existing driver license by user id when legacy id is new', function (): void {
    config([
        'legacy.base_api_url' => 'https://legacy.test/api/',
        'legacy.base_api_token' => 'test-token',
    ]);

    $user = User::factory()->create(['legacy_id' => 13]);

    \Hwkdo\IntranetAppFuhrpark\Models\DriverLicense::query()->create([
        'user_id' => $user->id,
        'valid_until' => now()->addMonths(6),
    ]);

    Http::fake([
        'legacy.test/api/base/fuhrpark-export/driver-licenses' => Http::response([
            [
                'id' => 67,
                'user_id' => 13,
                'gueltigbis' => '2028-09-17',
                'befristung' => null,
            ],
        ]),
    ]);

    app(LegacyFuhrparkImportService::class)->import(
        app(IntranetLegacyService::class),
        '2025-01-01',
        dryRun: false,
        only: ['driver-licenses'],
    );

    expect(\Hwkdo\IntranetAppFuhrpark\Models\DriverLicense::query()->count())->toBe(1);

    $license = \Hwkdo\IntranetAppFuhrpark\Models\DriverLicense::query()->where('user_id', $user->id)->first();

    expect($license)->not->toBeNull()
        ->and($license->legacy_id)->toBe(67)
        ->and($license->valid_until->toDateString())->toBe('2028-09-17');
});
