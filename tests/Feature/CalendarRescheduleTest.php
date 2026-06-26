<?php

declare(strict_types=1);

use App\Models\User;
use Hwkdo\IntranetAppFuhrpark\Livewire\Calendar;
use Hwkdo\IntranetAppFuhrpark\Models\Booking;
use Hwkdo\IntranetAppFuhrpark\Models\Vehicle;
use Hwkdo\IntranetAppFuhrpark\Models\VehicleCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

function fuhrparkCalendarStandort(): \App\Models\Standort
{
    $standort = \App\Models\Standort::query()->create(['name' => 'Kalender-Standort']);

    fuhrparkMarkVehicleStandort($standort);

    return $standort;
}

function fuhrparkCalendarVehicle(?VehicleCategory $category = null, ?\App\Models\Standort $standort = null, array $attributes = []): Vehicle
{
    $category ??= VehicleCategory::factory()->create();
    $standort ??= fuhrparkCalendarStandort();

    return Vehicle::factory()->create(array_merge([
        'vehicle_category_id' => $category->id,
        'standort_id' => $standort->id,
    ], $attributes));
}

function fuhrparkCalendarAdmin(): User
{
    Permission::findOrCreate('manage-app-fuhrpark', 'web');
    $admin = User::factory()->create();
    $admin->givePermissionTo('manage-app-fuhrpark');

    return $admin;
}

test('calendar reschedule assigns best vehicle automatically for normal users in same category', function (): void {
    $user = User::factory()->create();
    $category = VehicleCategory::factory()->create();
    $standort = fuhrparkCalendarStandort();
    $vehicleA = fuhrparkCalendarVehicle($category, $standort, ['initial_km' => 50_000]);
    $vehicleB = fuhrparkCalendarVehicle($category, $standort, ['initial_km' => 10_000]);

    $start = now()->addDays(2)->setTime(8, 0);
    $end = $start->copy()->addHours(4);

    $booking = Booking::factory()->create([
        'vehicle_id' => $vehicleA->id,
        'user_id' => $user->id,
        'driver_id' => $user->id,
        'starts_at' => $start,
        'ends_at' => $end,
    ]);

    $afternoonStart = $start->copy()->setTime(14, 0);
    $afternoonEnd = $afternoonStart->copy()->addHours(4);

    Livewire::actingAs($user)
        ->test(Calendar::class)
        ->set('selectedBookingId', $booking->id)
        ->call('startReschedule')
        ->set('rescheduleStartDate', $afternoonStart->format('Y-m-d'))
        ->set('rescheduleEndDate', $afternoonEnd->format('Y-m-d'))
        ->set('rescheduleStartTime', $afternoonStart->format('H:i'))
        ->set('rescheduleEndTime', $afternoonEnd->format('H:i'))
        ->call('checkRescheduleAvailability')
        ->assertSet('rescheduleChecked', true)
        ->assertSet('rescheduleCategoryId', $category->id)
        ->assertSet('rescheduleVehicleId', null)
        ->assertSee('automatisch das beste Fahrzeug zugewiesen')
        ->assertDontSee('Freie Fahrzeuge in Ihrer Kategorie')
        ->call('confirmReschedule')
        ->assertSet('showRescheduleModal', false);

    $booking->refresh();

    expect($booking->vehicle_id)->toBe($vehicleB->id)
        ->and($booking->starts_at->eq($afternoonStart))->toBeTrue()
        ->and($booking->ends_at->eq($afternoonEnd))->toBeTrue();
});

test('calendar reschedule allows admins to select a specific vehicle in same category', function (): void {
    $admin = fuhrparkCalendarAdmin();
    $category = VehicleCategory::factory()->create();
    $standort = fuhrparkCalendarStandort();
    $vehicleA = fuhrparkCalendarVehicle($category, $standort);
    $vehicleB = fuhrparkCalendarVehicle($category, $standort);

    $start = now()->addDays(2)->setTime(8, 0);
    $end = $start->copy()->addHours(4);

    $booking = Booking::factory()->create([
        'vehicle_id' => $vehicleA->id,
        'user_id' => $admin->id,
        'driver_id' => $admin->id,
        'starts_at' => $start,
        'ends_at' => $end,
    ]);

    $afternoonStart = $start->copy()->setTime(14, 0);
    $afternoonEnd = $afternoonStart->copy()->addHours(4);

    Livewire::actingAs($admin)
        ->test(Calendar::class)
        ->set('selectedBookingId', $booking->id)
        ->call('startReschedule')
        ->set('reschedulePreferSameVehicle', false)
        ->set('rescheduleStartDate', $afternoonStart->format('Y-m-d'))
        ->set('rescheduleEndDate', $afternoonEnd->format('Y-m-d'))
        ->set('rescheduleStartTime', $afternoonStart->format('H:i'))
        ->set('rescheduleEndTime', $afternoonEnd->format('H:i'))
        ->call('checkRescheduleAvailability')
        ->assertSet('rescheduleChecked', true)
        ->assertSet('rescheduleVehicleId', null)
        ->assertSee('Freie Fahrzeuge in Ihrer Kategorie')
        ->assertSee($vehicleA->license_plate)
        ->assertSee($vehicleB->license_plate)
        ->call('selectRescheduleVehicle', $vehicleB->id)
        ->assertSet('rescheduleVehicleId', $vehicleB->id)
        ->call('confirmReschedule')
        ->assertSet('showRescheduleModal', false);

    $booking->refresh();

    expect($booking->vehicle_id)->toBe($vehicleB->id)
        ->and($booking->starts_at->eq($afternoonStart))->toBeTrue()
        ->and($booking->ends_at->eq($afternoonEnd))->toBeTrue();
});

test('calendar reschedule shows other categories for admins even when same category has vehicles', function (): void {
    $admin = fuhrparkCalendarAdmin();
    $categoryA = VehicleCategory::factory()->create(['name' => 'Kleinwagen']);
    $categoryB = VehicleCategory::factory()->create(['name' => 'Kombi']);
    $standort = fuhrparkCalendarStandort();
    $vehicleA = fuhrparkCalendarVehicle($categoryA, $standort);
    fuhrparkCalendarVehicle($categoryA, $standort);
    $vehicleB = fuhrparkCalendarVehicle($categoryB, $standort);

    $start = now()->addDays(2)->setTime(8, 0);
    $end = $start->copy()->addHours(4);

    $booking = Booking::factory()->create([
        'vehicle_id' => $vehicleA->id,
        'user_id' => $admin->id,
        'driver_id' => $admin->id,
        'starts_at' => $start,
        'ends_at' => $end,
    ]);

    $afternoonStart = $start->copy()->setTime(14, 0);
    $afternoonEnd = $afternoonStart->copy()->addHours(4);

    Livewire::actingAs($admin)
        ->test(Calendar::class)
        ->set('selectedBookingId', $booking->id)
        ->call('startReschedule')
        ->set('rescheduleStartDate', $afternoonStart->format('Y-m-d'))
        ->set('rescheduleEndDate', $afternoonEnd->format('Y-m-d'))
        ->set('rescheduleStartTime', $afternoonStart->format('H:i'))
        ->set('rescheduleEndTime', $afternoonEnd->format('H:i'))
        ->call('checkRescheduleAvailability')
        ->assertSee('Freie Fahrzeuge in Ihrer Kategorie')
        ->assertSee('Weitere verfügbare Kategorien')
        ->assertSee('Kombi')
        ->call('selectRescheduleOtherCategory', $categoryB->id)
        ->call('selectRescheduleVehicle', $vehicleB->id)
        ->call('confirmReschedule');

    $booking->refresh();

    expect($booking->vehicle_id)->toBe($vehicleB->id)
        ->and($booking->starts_at->eq($afternoonStart))->toBeTrue();
});

test('calendar reschedule preselects current vehicle for admins when prefer same vehicle is enabled', function (): void {
    $admin = fuhrparkCalendarAdmin();
    $vehicle = fuhrparkCalendarVehicle();

    $start = now()->addDays(2)->setTime(8, 0);
    $end = $start->copy()->addHours(4);

    $booking = Booking::factory()->create([
        'vehicle_id' => $vehicle->id,
        'user_id' => $admin->id,
        'driver_id' => $admin->id,
        'starts_at' => $start,
        'ends_at' => $end,
    ]);

    $afternoonStart = $start->copy()->setTime(14, 0);
    $afternoonEnd = $afternoonStart->copy()->addHours(4);

    Livewire::actingAs($admin)
        ->test(Calendar::class)
        ->set('selectedBookingId', $booking->id)
        ->call('startReschedule')
        ->set('rescheduleStartDate', $afternoonStart->format('Y-m-d'))
        ->set('rescheduleEndDate', $afternoonEnd->format('Y-m-d'))
        ->set('rescheduleStartTime', $afternoonStart->format('H:i'))
        ->set('rescheduleEndTime', $afternoonEnd->format('H:i'))
        ->call('checkRescheduleAvailability')
        ->assertSet('rescheduleVehicleId', $vehicle->id)
        ->call('confirmReschedule');

    expect($booking->fresh()->vehicle_id)->toBe($vehicle->id);
});

test('calendar reschedule selects category only for normal users when same category is full', function (): void {
    $user = User::factory()->create();
    $categoryA = VehicleCategory::factory()->create(['name' => 'Kleinwagen']);
    $categoryB = VehicleCategory::factory()->create(['name' => 'Kombi']);
    $standort = fuhrparkCalendarStandort();
    $vehicleA = fuhrparkCalendarVehicle($categoryA, $standort);
    $vehicleB = fuhrparkCalendarVehicle($categoryB, $standort);

    $start = now()->addDays(2)->setTime(8, 0);
    $end = $start->copy()->addHours(4);

    $booking = Booking::factory()->create([
        'vehicle_id' => $vehicleA->id,
        'user_id' => $user->id,
        'driver_id' => $user->id,
        'starts_at' => $start,
        'ends_at' => $end,
    ]);

    Booking::factory()->create([
        'vehicle_id' => $vehicleA->id,
        'starts_at' => $start->copy()->setTime(14, 0),
        'ends_at' => $start->copy()->setTime(18, 0),
    ]);

    $afternoonStart = $start->copy()->setTime(14, 0);
    $afternoonEnd = $afternoonStart->copy()->addHours(4);

    Livewire::actingAs($user)
        ->test(Calendar::class)
        ->set('selectedBookingId', $booking->id)
        ->call('startReschedule')
        ->set('rescheduleStartDate', $afternoonStart->format('Y-m-d'))
        ->set('rescheduleEndDate', $afternoonEnd->format('Y-m-d'))
        ->set('rescheduleStartTime', $afternoonStart->format('H:i'))
        ->set('rescheduleEndTime', $afternoonEnd->format('H:i'))
        ->call('checkRescheduleAvailability')
        ->assertSee('Verfügbare Kategorien')
        ->assertSee('Kombi')
        ->assertDontSee('Freie Fahrzeuge in dieser Kategorie')
        ->call('selectRescheduleOtherCategory', $categoryB->id)
        ->assertSet('showRescheduleModal', false);

    $booking->refresh();

    expect($booking->vehicle_id)->toBe($vehicleB->id)
        ->and($booking->starts_at->eq($afternoonStart))->toBeTrue();
});

test('calendar reschedule allows admins to select vehicle from other category when same category is full', function (): void {
    $admin = fuhrparkCalendarAdmin();
    $categoryA = VehicleCategory::factory()->create(['name' => 'Kleinwagen']);
    $categoryB = VehicleCategory::factory()->create(['name' => 'Kombi']);
    $standort = fuhrparkCalendarStandort();
    $vehicleA = fuhrparkCalendarVehicle($categoryA, $standort);
    $vehicleB = fuhrparkCalendarVehicle($categoryB, $standort);

    $start = now()->addDays(2)->setTime(8, 0);
    $end = $start->copy()->addHours(4);

    $booking = Booking::factory()->create([
        'vehicle_id' => $vehicleA->id,
        'user_id' => $admin->id,
        'driver_id' => $admin->id,
        'starts_at' => $start,
        'ends_at' => $end,
    ]);

    Booking::factory()->create([
        'vehicle_id' => $vehicleA->id,
        'starts_at' => $start->copy()->setTime(14, 0),
        'ends_at' => $start->copy()->setTime(18, 0),
    ]);

    $afternoonStart = $start->copy()->setTime(14, 0);
    $afternoonEnd = $afternoonStart->copy()->addHours(4);

    Livewire::actingAs($admin)
        ->test(Calendar::class)
        ->set('selectedBookingId', $booking->id)
        ->call('startReschedule')
        ->set('rescheduleStartDate', $afternoonStart->format('Y-m-d'))
        ->set('rescheduleEndDate', $afternoonEnd->format('Y-m-d'))
        ->set('rescheduleStartTime', $afternoonStart->format('H:i'))
        ->set('rescheduleEndTime', $afternoonEnd->format('H:i'))
        ->call('checkRescheduleAvailability')
        ->assertSee('Verfügbare Kategorien')
        ->assertSee('Kombi')
        ->call('selectRescheduleOtherCategory', $categoryB->id)
        ->call('selectRescheduleVehicle', $vehicleB->id)
        ->call('confirmReschedule');

    $booking->refresh();

    expect($booking->vehicle_id)->toBe($vehicleB->id)
        ->and($booking->starts_at->eq($afternoonStart))->toBeTrue();
});

test('calendar reschedule shows message when no vehicle is available', function (): void {
    $user = User::factory()->create();
    $category = VehicleCategory::factory()->create();
    $standort = fuhrparkCalendarStandort();
    $vehicle = fuhrparkCalendarVehicle($category, $standort);

    $start = now()->addDays(2)->setTime(8, 0);
    $end = $start->copy()->addHours(4);

    $booking = Booking::factory()->create([
        'vehicle_id' => $vehicle->id,
        'user_id' => $user->id,
        'driver_id' => $user->id,
        'starts_at' => $start,
        'ends_at' => $end,
    ]);

    Booking::factory()->create([
        'vehicle_id' => $vehicle->id,
        'starts_at' => $start->copy()->setTime(14, 0),
        'ends_at' => $start->copy()->setTime(18, 0),
    ]);

    $afternoonStart = $start->copy()->setTime(14, 0);
    $afternoonEnd = $afternoonStart->copy()->addHours(4);

    Livewire::actingAs($user)
        ->test(Calendar::class)
        ->set('selectedBookingId', $booking->id)
        ->call('startReschedule')
        ->set('rescheduleStartDate', $afternoonStart->format('Y-m-d'))
        ->set('rescheduleEndDate', $afternoonEnd->format('Y-m-d'))
        ->set('rescheduleStartTime', $afternoonStart->format('H:i'))
        ->set('rescheduleEndTime', $afternoonEnd->format('H:i'))
        ->call('checkRescheduleAvailability')
        ->assertSee('Kein freies Fahrzeug in keiner Kategorie');
});
