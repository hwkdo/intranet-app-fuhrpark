<?php

declare(strict_types=1);

use App\Models\Standort;
use App\Models\User;
use Hwkdo\IntranetAppFuhrpark\Contracts\BookingCalendarSyncInterface;
use Hwkdo\IntranetAppFuhrpark\Mcp\Servers\FuhrparkServer;
use Hwkdo\IntranetAppFuhrpark\Mcp\Tools\BuchungErstellenTool;
use Hwkdo\IntranetAppFuhrpark\Models\Booking;
use Hwkdo\IntranetAppFuhrpark\Models\Vehicle;
use Hwkdo\IntranetAppFuhrpark\Models\VehicleCategory;
use Mockery\MockInterface;
use Spatie\Permission\Models\Permission;

beforeEach(function (): void {
    Permission::findOrCreate('see-app-fuhrpark', 'web');
});

/**
 * @return array{user: User, category: VehicleCategory, standort: Standort, starts_at: string, ends_at: string}
 */
function fuhrparkMcpCreateContext(): array
{
    $user = User::factory()->create();
    $user->givePermissionTo('see-app-fuhrpark');
    fuhrparkGrantValidLicense($user);

    $standort = Standort::query()->create(['name' => 'MCP-Kalender-Standort']);
    fuhrparkMarkVehicleStandort($standort);
    $category = VehicleCategory::factory()->create();
    Vehicle::factory()->create([
        'vehicle_category_id' => $category->id,
        'standort_id' => $standort->id,
    ]);

    $start = now()->addDays(3)->setTime(8, 0);
    $end = $start->copy()->addHours(4);

    return [
        'user' => $user,
        'category' => $category,
        'standort' => $standort,
        'starts_at' => $start->toIso8601String(),
        'ends_at' => $end->toIso8601String(),
    ];
}

/**
 * @param  array{user: User, category: VehicleCategory, standort: Standort, starts_at: string, ends_at: string}  $context
 * @return array<string, mixed>
 */
function fuhrparkMcpCreatePayload(array $context, ?bool $syncToCalendar = null): array
{
    $payload = [
        'starts_at' => $context['starts_at'],
        'ends_at' => $context['ends_at'],
        'description' => 'Kundentermin',
        'vehicle_category_id' => $context['category']->id,
        'standort_id' => $context['standort']->id,
    ];

    if ($syncToCalendar !== null) {
        $payload['sync_to_calendar'] = $syncToCalendar;
    }

    return $payload;
}

test('buchung erstellen fragt nach kalendereintrag wenn er nicht angegeben ist', function (): void {
    $context = fuhrparkMcpCreateContext();

    $this->mock(BookingCalendarSyncInterface::class, function (MockInterface $mock): void {
        $mock->shouldNotReceive('createEvent');
    });

    $response = FuhrparkServer::actingAs($context['user'])->tool(
        BuchungErstellenTool::class,
        fuhrparkMcpCreatePayload($context),
    );

    $response->assertOk();
    $response->assertSee('needs_calendar_decision');
    $response->assertSee('Outlook-Kalender');
    $response->assertSee('nutzer_nach_kalendereintrag_fragen');

    expect(Booking::query()->count())->toBe(0);
});

test('buchung erstellen legt ohne kalender an wenn sync_to_calendar false ist', function (): void {
    $context = fuhrparkMcpCreateContext();

    $this->mock(BookingCalendarSyncInterface::class, function (MockInterface $mock): void {
        $mock->shouldNotReceive('createEvent');
    });

    $response = FuhrparkServer::actingAs($context['user'])->tool(
        BuchungErstellenTool::class,
        fuhrparkMcpCreatePayload($context, false),
    );

    $response->assertOk();
    $response->assertSee('Buchung erfolgreich erstellt');

    $booking = Booking::query()->first();

    expect($booking)->not->toBeNull()
        ->and($booking->sync_to_calendar)->toBeFalse()
        ->and($booking->ms_graph_event_id)->toBeNull();
});

test('buchung erstellen synchronisiert den kalender wenn sync_to_calendar true ist', function (): void {
    $context = fuhrparkMcpCreateContext();

    $this->mock(BookingCalendarSyncInterface::class, function (MockInterface $mock): void {
        $mock->shouldReceive('createEvent')
            ->once()
            ->andReturn('graph-event-mcp');
    });

    $response = FuhrparkServer::actingAs($context['user'])->tool(
        BuchungErstellenTool::class,
        fuhrparkMcpCreatePayload($context, true),
    );

    $response->assertOk();
    $response->assertSee('Buchung erfolgreich erstellt');

    $booking = Booking::query()->first();

    expect($booking)->not->toBeNull()
        ->and($booking->sync_to_calendar)->toBeTrue()
        ->and($booking->ms_graph_event_id)->toBe('graph-event-mcp');
});
