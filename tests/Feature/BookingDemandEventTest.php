<?php

declare(strict_types=1);

use App\Models\Standort;
use App\Models\User;
use Hwkdo\IntranetAppFuhrpark\Enums\BookingDemandReason;
use Hwkdo\IntranetAppFuhrpark\Enums\BookingDemandSource;
use Hwkdo\IntranetAppFuhrpark\Models\BookingDemandEvent;
use Hwkdo\IntranetAppFuhrpark\Models\VehicleCategory;
use Hwkdo\IntranetAppFuhrpark\Services\BookingDemandEventService;
use Hwkdo\IntranetAppFuhrpark\Services\FuhrparkAdminStatisticsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

test('booking demand event service deduplicates identical events within one hour', function (): void {
    $user = User::factory()->create();
    $service = app(BookingDemandEventService::class);
    $start = now()->addDay()->setTime(9, 0);
    $end = $start->copy()->addHours(2);

    $first = $service->record(
        userId: $user->id,
        startsAt: $start,
        endsAt: $end,
        reason: BookingDemandReason::NoVehicleInCategory,
        source: BookingDemandSource::Create,
        standortId: 1,
        vehicleCategoryId: 2,
    );

    $second = $service->record(
        userId: $user->id,
        startsAt: $start,
        endsAt: $end,
        reason: BookingDemandReason::NoVehicleInCategory,
        source: BookingDemandSource::Create,
        standortId: 1,
        vehicleCategoryId: 2,
    );

    expect($first)->toBeInstanceOf(BookingDemandEvent::class)
        ->and($second)->toBeNull()
        ->and(BookingDemandEvent::query()->count())->toBe(1);
});

test('admin statistics aggregates unmet booking demand', function (): void {
    $standort = Standort::query()->create(['name' => 'Demand-Standort']);
    $category = VehicleCategory::factory()->create(['name' => 'Kombi']);

    $user = User::factory()->create();
    $start = now()->startOfMonth()->addDay()->setTime(9, 0);
    $end = $start->copy()->addHours(3);

    BookingDemandEvent::factory()->create([
        'user_id' => $user->id,
        'standort_id' => $standort->id,
        'vehicle_category_id' => $category->id,
        'starts_at' => $start,
        'ends_at' => $end,
        'reason' => BookingDemandReason::NoVehicleInCategory,
        'source' => BookingDemandSource::Create,
        'had_alternative_category' => false,
        'created_at' => now(),
    ]);

    BookingDemandEvent::factory()->create([
        'user_id' => $user->id,
        'standort_id' => $standort->id,
        'vehicle_category_id' => $category->id,
        'starts_at' => $start,
        'ends_at' => $end,
        'reason' => BookingDemandReason::NoVehicleInCategory,
        'source' => BookingDemandSource::Create,
        'had_alternative_category' => true,
        'created_at' => now(),
    ]);

    BookingDemandEvent::factory()->create([
        'user_id' => $user->id,
        'standort_id' => $standort->id,
        'starts_at' => $start,
        'ends_at' => $end,
        'reason' => BookingDemandReason::AllCategoriesUnavailable,
        'source' => BookingDemandSource::Preview,
        'created_at' => now(),
    ]);

    $unmet = app(FuhrparkAdminStatisticsService::class)->collect('month')['unmet_demand'];

    expect($unmet['total_events'])->toBe(3)
        ->and($unmet['hard_shortage_events'])->toBe(2)
        ->and($unmet['with_alternative_category'])->toBe(1)
        ->and($unmet['by_category'])->toHaveCount(1)
        ->and($unmet['by_category'][0]['category'])->toBe('Kombi')
        ->and($unmet['by_standort'])->toHaveCount(1)
        ->and($unmet['by_standort'][0]['standort'])->toBe('Demand-Standort');
});
