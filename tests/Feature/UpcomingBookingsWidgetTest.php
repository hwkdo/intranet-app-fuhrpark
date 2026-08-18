<?php

declare(strict_types=1);

use App\Data\UserDashboardPersonalGrid;
use App\Data\UserDashboardSettings;
use App\Data\UserSettings;
use App\Models\User;
use Hwkdo\IntranetAppBase\Services\DashboardWidgetRegistry;
use Hwkdo\IntranetAppFuhrpark\Dashboard\FuhrparkDashboardWidgetProvider;
use Hwkdo\IntranetAppFuhrpark\Enums\BookingPurpose;
use Hwkdo\IntranetAppFuhrpark\IntranetAppFuhrpark;
use Hwkdo\IntranetAppFuhrpark\Models\Booking;
use Hwkdo\IntranetAppFuhrpark\Models\Vehicle;
use Hwkdo\IntranetAppFuhrpark\Models\VehicleCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Permission::findOrCreate('see-app-fuhrpark', 'web');
    $this->travelTo(now()->setTime(12, 0));
});

function fuhrparkUpcomingWidgetUser(): User
{
    $user = User::factory()->create();
    $user->givePermissionTo('see-app-fuhrpark');

    return $user;
}

function fuhrparkUpcomingWidgetVehicle(string $licensePlate): Vehicle
{
    $category = VehicleCategory::factory()->create();

    return Vehicle::factory()->create([
        'vehicle_category_id' => $category->id,
        'license_plate' => $licensePlate,
    ]);
}

function fuhrparkUpcomingWidgetBooking(
    User $driver,
    Vehicle $vehicle,
    Carbon $startsAt,
    string $description,
    BookingPurpose $purpose = BookingPurpose::Normal,
): Booking {
    return Booking::factory()->create([
        'vehicle_id' => $vehicle->id,
        'user_id' => $driver->id,
        'driver_id' => $driver->id,
        'purpose' => $purpose,
        'description' => $description,
        'starts_at' => $startsAt,
        'ends_at' => $startsAt->copy()->addHours(2),
    ]);
}

test('fuhrpark stellt das kommende-buchungen widget bereit', function (): void {
    expect(IntranetAppFuhrpark::dashboardWidgetProviders())->toContain(FuhrparkDashboardWidgetProvider::class);

    $widget = collect(FuhrparkDashboardWidgetProvider::widgets())
        ->firstWhere('key', FuhrparkDashboardWidgetProvider::KEY_KOMMENDE_BUCHUNGEN);

    expect($widget)->not->toBeNull()
        ->and($widget->title)->toBe('Kommende Buchungen')
        ->and($widget->component)->toBe('intranet-app-fuhrpark::apps.fuhrpark.widgets.kommende-buchungen')
        ->and($widget->supportsItemCount)->toBeTrue()
        ->and($widget->defaultEnabled)->toBeTrue();
});

test('main dashboard registriert das fuhrpark widget für berechtigte nutzer', function (): void {
    $user = fuhrparkUpcomingWidgetUser();

    $keys = collect(app(DashboardWidgetRegistry::class)->widgetsForMainDashboard($user))
        ->map(fn ($definition): string => $definition->key)
        ->all();

    expect($keys)->toContain('fuhrpark.kommende-buchungen');
});

test('widget zeigt beginn, zweck und kennzeichen der nächsten buchungen', function (): void {
    $user = fuhrparkUpcomingWidgetUser();
    $vehicle = fuhrparkUpcomingWidgetVehicle('DO-HW 101');

    fuhrparkUpcomingWidgetBooking(
        $user,
        $vehicle,
        now()->addHours(3),
        'Kundentermin in Münster',
    );

    Livewire::actingAs($user)
        ->test('intranet-app-fuhrpark::apps.fuhrpark.widgets.kommende-buchungen')
        ->assertSee('Kommende Buchungen')
        ->assertSee(now()->addHours(3)->format('d.m.Y H:i'))
        ->assertSee('Kundentermin in Münster')
        ->assertSee('DO-HW 101');
});

test('widget sortiert buchungen nach nähesten beginn und zeigt standardmäßig fünf', function (): void {
    $user = fuhrparkUpcomingWidgetUser();
    $other = User::factory()->create();
    $category = VehicleCategory::factory()->create();

    $visiblePlates = [];
    foreach (range(1, 6) as $index) {
        $plate = 'DO-HW 20'.$index;
        $visiblePlates[$index] = $plate;
        $vehicle = Vehicle::factory()->create([
            'vehicle_category_id' => $category->id,
            'license_plate' => $plate,
        ]);

        fuhrparkUpcomingWidgetBooking(
            $user,
            $vehicle,
            now()->addHours($index),
            'Termin '.$index,
        );
    }

    $pastVehicle = Vehicle::factory()->create([
        'vehicle_category_id' => $category->id,
        'license_plate' => 'DO-HW PAST',
    ]);
    fuhrparkUpcomingWidgetBooking(
        $user,
        $pastVehicle,
        now()->subDay(),
        'Vergangene Fahrt',
    );

    $otherVehicle = Vehicle::factory()->create([
        'vehicle_category_id' => $category->id,
        'license_plate' => 'DO-HW OTHR',
    ]);
    fuhrparkUpcomingWidgetBooking(
        $other,
        $otherVehicle,
        now()->addMinutes(30),
        'Fremde Buchung',
    );

    $lockVehicle = Vehicle::factory()->create([
        'vehicle_category_id' => $category->id,
        'license_plate' => 'DO-HW LOCK',
    ]);
    fuhrparkUpcomingWidgetBooking(
        $user,
        $lockVehicle,
        now()->addMinutes(10),
        'Sperre',
        BookingPurpose::Lock,
    );

    Livewire::actingAs($user)
        ->test('intranet-app-fuhrpark::apps.fuhrpark.widgets.kommende-buchungen')
        ->assertSeeInOrder([
            $visiblePlates[1],
            $visiblePlates[2],
            $visiblePlates[3],
            $visiblePlates[4],
            $visiblePlates[5],
        ])
        ->assertDontSee($visiblePlates[6])
        ->assertDontSee('DO-HW PAST')
        ->assertDontSee('Vergangene Fahrt')
        ->assertDontSee('DO-HW OTHR')
        ->assertDontSee('Fremde Buchung')
        ->assertDontSee('DO-HW LOCK')
        ->assertSee('Weitere anzeigen');
});

test('widget respektiert die konfigurierte anzahl im dashboard', function (): void {
    $user = fuhrparkUpcomingWidgetUser();
    $category = VehicleCategory::factory()->create();

    foreach (range(1, 3) as $index) {
        $vehicle = Vehicle::factory()->create([
            'vehicle_category_id' => $category->id,
            'license_plate' => 'DO-HW 30'.$index,
        ]);
        fuhrparkUpcomingWidgetBooking(
            $user,
            $vehicle,
            now()->addHours($index),
            'Limit-Termin '.$index,
        );
    }

    $existing = $user->settings;
    $user->settings = new UserSettings(
        app: $existing->app,
        general: $existing->general,
        dashboard: new UserDashboardSettings(
            autoplayInterval: $existing->dashboard->autoplayInterval,
            autoplay: $existing->dashboard->autoplay,
            hideAufgabenWhenEmpty: $existing->dashboard->hideAufgabenWhenEmpty,
            newsCount: $existing->dashboard->newsCount,
            personalGrid: new UserDashboardPersonalGrid(
                widgetItemCounts: [
                    'fuhrpark.kommende-buchungen' => 2,
                ],
            ),
        ),
        ai: $existing->ai,
    );
    $user->save();

    Livewire::actingAs($user->fresh())
        ->test('intranet-app-fuhrpark::apps.fuhrpark.widgets.kommende-buchungen')
        ->assertSee('DO-HW 301')
        ->assertSee('DO-HW 302')
        ->assertDontSee('DO-HW 303');
});

test('widget zeigt leerzustand ohne kommende buchungen', function (): void {
    $user = fuhrparkUpcomingWidgetUser();

    Livewire::actingAs($user)
        ->test('intranet-app-fuhrpark::apps.fuhrpark.widgets.kommende-buchungen')
        ->assertSee('Keine kommenden Buchungen.')
        ->assertDontSee('Weitere anzeigen');
});

test('widget ist ohne app-berechtigung nicht sichtbar', function (): void {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test('intranet-app-fuhrpark::apps.fuhrpark.widgets.kommende-buchungen')
        ->assertForbidden();
});
