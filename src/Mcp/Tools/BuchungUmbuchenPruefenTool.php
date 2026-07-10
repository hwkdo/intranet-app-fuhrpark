<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppFuhrpark\Mcp\Tools;

use Hwkdo\IntranetAppFuhrpark\Enums\BookingDemandReason;
use Hwkdo\IntranetAppFuhrpark\Enums\BookingDemandSource;
use Hwkdo\IntranetAppFuhrpark\Mcp\Support\McpRescheduleSupport;
use Hwkdo\IntranetAppFuhrpark\Models\Booking;
use Hwkdo\IntranetAppFuhrpark\Services\BookingDemandEventService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsOpenWorld;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
#[IsOpenWorld]
class BuchungUmbuchenPruefenTool extends Tool
{
    protected string $name = 'buchung_umbuchen_pruefen';

    protected string $description = 'Prüft Verfügbarkeit für eine Umbuchung (wie «Verfügbarkeit prüfen» im Kalender-UI). Vor buchung_umbuchen verwenden. Liefert freie Fahrzeuge/Kategorien für den neuen Zeitraum.';

    public function handle(Request $request): Response|ResponseFactory
    {
        $user = $request->user();
        if ($user === null) {
            return Response::error('Nicht authentifiziert.');
        }

        Gate::forUser($user)->authorize('see-app-fuhrpark');

        $validated = $request->validate([
            'booking_id' => ['required', 'integer', 'exists:intranet_app_fuhrpark_bookings,id'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date', 'after:starts_at'],
        ], [
            'booking_id.required' => 'booking_id ist erforderlich.',
            'starts_at.required' => 'starts_at ist erforderlich (ISO 8601).',
            'ends_at.after' => 'ends_at muss nach starts_at liegen.',
        ]);

        $booking = Booking::query()
            ->with(['vehicle.category', 'handout.returnRecord', 'logbookEntry'])
            ->findOrFail((int) $validated['booking_id']);

        Gate::forUser($user)->authorize('update', $booking);

        try {
            [$start, $end] = McpRescheduleSupport::parsePeriod($validated['starts_at'], $validated['ends_at']);
        } catch (ValidationException $exception) {
            return Response::structured([
                'success' => false,
                'message' => 'Ungültiger Zeitraum.',
                'errors' => $exception->errors(),
            ]);
        }

        $result = McpRescheduleSupport::findAlternatives($booking, $start, $end);
        $canSelectVehicle = $user->can('manage-app-fuhrpark');

        if ($result->noneAvailable) {
            app(BookingDemandEventService::class)->record(
                userId: (int) $user->getAuthIdentifier(),
                startsAt: $start,
                endsAt: $end,
                reason: BookingDemandReason::RescheduleUnavailable,
                source: BookingDemandSource::Reschedule,
                standortId: $booking->vehicle->standort_id,
                vehicleCategoryId: $booking->vehicle->vehicle_category_id,
                driverId: (int) $booking->driver_id,
            );
        }

        $availability = McpRescheduleSupport::formatAvailability($result, $booking, $canSelectVehicle);

        $recommendedCategoryId = $availability['default_vehicle_category_id'];

        return Response::structured([
            'success' => true,
            'booking_id' => $booking->id,
            'starts_at' => $start->toIso8601String(),
            'ends_at' => $end->toIso8601String(),
            'availability' => $availability,
            'recommended_vehicle_category_id' => $recommendedCategoryId,
            'next_step' => $availability['none_available']
                ? null
                : ($availability['same_category_available']
                    ? 'buchung_umbuchen nur mit booking_id, starts_at, ends_at (gleiche Kategorie frei)'
                    : ($canSelectVehicle
                        ? 'buchung_umbuchen mit vehicle_id oder vehicle_category_id aus other_categories'
                        : 'buchung_umbuchen mit vehicle_category_id aus other_categories')),
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'booking_id' => $schema->integer()
                ->description('ID der umzubuchenden Buchung.')
                ->required(),
            'starts_at' => $schema->string()
                ->description('Neuer Buchungsbeginn (ISO 8601).')
                ->required(),
            'ends_at' => $schema->string()
                ->description('Neues Buchungsende (ISO 8601).')
                ->required(),
        ];
    }

    public function outputSchema(JsonSchema $schema): array
    {
        return [
            'success' => $schema->boolean()->required(),
            'booking_id' => $schema->integer()->nullable(),
            'availability' => $schema->object()->nullable(),
            'next_step' => $schema->string()->nullable(),
        ];
    }
}
