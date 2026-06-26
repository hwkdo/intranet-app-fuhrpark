<?php

declare(strict_types=1);

use App\Models\Standort;
use App\Models\User;
use Carbon\Carbon;
use Hwkdo\IntranetAppFuhrpark\Contracts\BookingCalendarSyncInterface;
use Hwkdo\IntranetAppFuhrpark\Data\BookingStoreData;
use Hwkdo\IntranetAppFuhrpark\Models\Booking;
use Hwkdo\IntranetAppFuhrpark\Models\Vehicle;
use Hwkdo\IntranetAppFuhrpark\Models\VehicleCategory;
use Hwkdo\IntranetAppFuhrpark\Services\BookingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery\MockInterface;

uses(RefreshDatabase::class);

test('booking service stores calendar event id when sync is enabled', function (): void {
    $user = User::factory()->create();
    fuhrparkGrantValidLicense($user);
    $category = VehicleCategory::factory()->create();
    $standort = Standort::query()->create(['name' => 'Kalender-Standort']);
    $vehicle = Vehicle::factory()->create([
        'vehicle_category_id' => $category->id,
        'standort_id' => $standort->id,
    ]);

    $start = now()->addDays(2)->setTime(8, 0);
    $end = $start->copy()->addHours(4);

    $this->mock(BookingCalendarSyncInterface::class, function (MockInterface $mock): void {
        $mock->shouldReceive('createEvent')
            ->once()
            ->andReturn('graph-event-123');
        $mock->shouldNotReceive('updateEvent');
        $mock->shouldNotReceive('deleteEvent');
    });

    $booking = app(BookingService::class)->create(
        new BookingStoreData(
            driverId: $user->id,
            description: 'Kundentermin',
            startsAt: $start,
            endsAt: $end,
            vehicleCategoryId: $category->id,
            standortId: $standort->id,
            syncToCalendar: true,
        ),
        $user,
    );

    expect($booking->sync_to_calendar)->toBeTrue()
        ->and($booking->ms_graph_event_id)->toBe('graph-event-123');
});

test('booking service does not call calendar sync when disabled', function (): void {
    $user = User::factory()->create();
    fuhrparkGrantValidLicense($user);
    $category = VehicleCategory::factory()->create();
    $standort = Standort::query()->create(['name' => 'Kalender-Standort']);
    Vehicle::factory()->create([
        'vehicle_category_id' => $category->id,
        'standort_id' => $standort->id,
    ]);

    $start = now()->addDays(2)->setTime(8, 0);
    $end = $start->copy()->addHours(4);

    $this->mock(BookingCalendarSyncInterface::class, function (MockInterface $mock): void {
        $mock->shouldNotReceive('createEvent');
        $mock->shouldNotReceive('updateEvent');
        $mock->shouldNotReceive('deleteEvent');
    });

    $booking = app(BookingService::class)->create(
        new BookingStoreData(
            driverId: $user->id,
            description: 'Ohne Kalender',
            startsAt: $start,
            endsAt: $end,
            vehicleCategoryId: $category->id,
            standortId: $standort->id,
        ),
        $user,
    );

    expect($booking->sync_to_calendar)->toBeFalse()
        ->and($booking->ms_graph_event_id)->toBeNull();
});

test('booking service updates calendar event on reschedule when event id exists', function (): void {
    $user = User::factory()->create();
    fuhrparkGrantValidLicense($user);
    $vehicle = Vehicle::factory()->create();
    $start = now()->addDays(2)->setTime(8, 0);
    $end = $start->copy()->addHours(4);
    $newStart = $start->copy()->addDay();
    $newEnd = $newStart->copy()->addHours(4);

    $booking = Booking::factory()->create([
        'vehicle_id' => $vehicle->id,
        'driver_id' => $user->id,
        'user_id' => $user->id,
        'starts_at' => $start,
        'ends_at' => $end,
        'sync_to_calendar' => true,
        'ms_graph_event_id' => 'graph-event-456',
    ]);

    $this->mock(BookingCalendarSyncInterface::class, function (MockInterface $mock) use ($newStart, $newEnd): void {
        $mock->shouldReceive('updateEvent')
            ->once()
            ->withArgs(function (Booking $booking, Carbon $start, Carbon $end) use ($newStart, $newEnd): bool {
                return $booking->ms_graph_event_id === 'graph-event-456'
                    && $start->equalTo($newStart)
                    && $end->equalTo($newEnd);
            });
    });

    app(BookingService::class)->reschedule($booking, $newStart, $newEnd, $vehicle->id);
});

test('booking service deletes calendar event on cancel when event id exists', function (): void {
    $user = User::factory()->create();
    $vehicle = Vehicle::factory()->create();
    $start = now()->addDays(2)->setTime(8, 0);
    $end = $start->copy()->addHours(4);

    $booking = Booking::factory()->create([
        'vehicle_id' => $vehicle->id,
        'driver_id' => $user->id,
        'user_id' => $user->id,
        'starts_at' => $start,
        'ends_at' => $end,
        'sync_to_calendar' => true,
        'ms_graph_event_id' => 'graph-event-789',
    ]);

    $this->mock(BookingCalendarSyncInterface::class, function (MockInterface $mock): void {
        $mock->shouldReceive('deleteEvent')
            ->once()
            ->withArgs(fn (Booking $booking): bool => $booking->ms_graph_event_id === 'graph-event-789');
    });

    app(BookingService::class)->cancel($booking);

    expect(Booking::query()->find($booking->id))->toBeNull();
});

test('booking is still created when calendar sync returns no event id', function (): void {
    $user = User::factory()->create();
    fuhrparkGrantValidLicense($user);
    $category = VehicleCategory::factory()->create();
    $standort = Standort::query()->create(['name' => 'Kalender-Standort']);
    Vehicle::factory()->create([
        'vehicle_category_id' => $category->id,
        'standort_id' => $standort->id,
    ]);

    $start = now()->addDays(2)->setTime(8, 0);
    $end = $start->copy()->addHours(4);

    $this->mock(BookingCalendarSyncInterface::class, function (MockInterface $mock): void {
        $mock->shouldReceive('createEvent')->once()->andReturn(null);
    });

    $booking = app(BookingService::class)->create(
        new BookingStoreData(
            driverId: $user->id,
            description: 'Sync fehlgeschlagen',
            startsAt: $start,
            endsAt: $end,
            vehicleCategoryId: $category->id,
            standortId: $standort->id,
            syncToCalendar: true,
        ),
        $user,
    );

    expect($booking->id)->not->toBeNull()
        ->and($booking->sync_to_calendar)->toBeTrue()
        ->and($booking->ms_graph_event_id)->toBeNull();
});
