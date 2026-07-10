<?php

declare(strict_types=1);

use App\Models\User;
use Carbon\Carbon;
use Hwkdo\IntranetAppFuhrpark\Enums\BookingPurpose;
use Hwkdo\IntranetAppFuhrpark\Mcp\Support\McpBookingPresenter;
use Hwkdo\IntranetAppFuhrpark\Support\FuhrparkUrls;
use Hwkdo\IntranetAppFuhrpark\Models\Booking;
use Hwkdo\IntranetAppFuhrpark\Models\Vehicle;
use Hwkdo\IntranetAppFuhrpark\Models\VehicleCategory;
use Hwkdo\IntranetAppFuhrpark\Services\BookingStatusResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('presenter liefert menschenlesbare bezeichnung mit zweck und zeitraum', function (): void {
    Carbon::setTestNow('2026-07-10 12:00:00');
    config(['app.url' => 'https://v3-intranet.localhost.test']);

    $vehicle = Vehicle::factory()->create([
        'license_plate' => 'M-AB 1234',
        'vehicle_category_id' => VehicleCategory::factory(),
    ]);

    $booking = Booking::factory()->create([
        'vehicle_id' => $vehicle->id,
        'description' => 'Kundentermin Düsseldorf',
        'starts_at' => Carbon::parse('2026-07-11 08:00:00'),
        'ends_at' => Carbon::parse('2026-07-11 12:00:00'),
        'purpose' => BookingPurpose::Normal,
    ]);

    $presenter = new McpBookingPresenter(app(BookingStatusResolver::class));
    $summary = $presenter->summarize($booking, User::factory()->make());

    expect($summary['bezeichnung'])
        ->toBe('Kundentermin Düsseldorf — Sa. 11.07.2026, 08:00–12:00 (M-AB 1234)')
        ->and($summary['zweck'])->toBe('Kundentermin Düsseldorf')
        ->and($summary['zeitraum'])->toBe('Sa. 11.07.2026, 08:00–12:00')
        ->and($summary['url'])->toBe(FuhrparkUrls::route('apps.fuhrpark.meine'))
        ->and($summary['url_markdown'])->toContain('Kundentermin Düsseldorf')
        ->and($summary['url_markdown'])->toContain('https://v3-intranet.localhost.test/apps/fuhrpark/meine')
        ->and($summary['url_markdown'])->not->toContain('Buchung #');
});

test('presenter formatiert mehrtaegige zeitraeume', function (): void {
    $vehicle = Vehicle::factory()->create([
        'vehicle_category_id' => VehicleCategory::factory(),
    ]);

    $booking = Booking::factory()->create([
        'vehicle_id' => $vehicle->id,
        'description' => 'Messeeinsatz',
        'starts_at' => Carbon::parse('2026-07-11 08:00:00'),
        'ends_at' => Carbon::parse('2026-07-13 18:00:00'),
    ]);

    $presenter = new McpBookingPresenter(app(BookingStatusResolver::class));

    expect($presenter->formatPeriodLabel($booking))
        ->toBe('Sa. 11.07.2026, 08:00 – Mo. 13.07.2026, 18:00');
});
