<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppFuhrpark\Mcp\Tools;

use Hwkdo\IntranetAppFuhrpark\Mcp\Support\McpBookingPresenter;
use Hwkdo\IntranetAppFuhrpark\Mcp\Support\McpRescheduleSupport;
use Hwkdo\IntranetAppFuhrpark\Mcp\Support\McpWriteVerification;
use Hwkdo\IntranetAppFuhrpark\Models\Booking;
use Hwkdo\IntranetAppFuhrpark\Services\BookingService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsOpenWorld;

#[IsOpenWorld]
class BuchungUmbuchenTool extends Tool
{
    protected string $name = 'buchung_umbuchen';

    protected string $description = 'Bucht eine bestehende Buchung auf einen neuen Zeitraum um (wie im Kalender-UI). Vorher buchung_umbuchen_pruefen. Normale Nutzer: vehicle_category_id (optional wenn eigene Kategorie frei). Admins (manage-app-fuhrpark): konkretes vehicle_id. Nur bei Status reserved oder handed_out.';

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
            'vehicle_category_id' => ['nullable', 'integer', 'exists:intranet_app_fuhrpark_vehicle_categories,id'],
            'vehicle_id' => ['nullable', 'integer', 'exists:intranet_app_fuhrpark_vehicles,id'],
        ]);

        $booking = Booking::query()
            ->with(['vehicle.category', 'driver', 'booker', 'handout.returnRecord', 'logbookEntry'])
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
            return Response::structured([
                'success' => false,
                'message' => 'Kein freies Fahrzeug in keiner Kategorie im gewählten Zeitraum verfügbar.',
                'next_step' => 'buchung_umbuchen_pruefen',
            ]);
        }

        $vehicleId = isset($validated['vehicle_id']) ? (int) $validated['vehicle_id'] : null;
        $categoryId = isset($validated['vehicle_category_id']) ? (int) $validated['vehicle_category_id'] : null;

        try {
            if ($canSelectVehicle && $vehicleId !== null) {
                if (! McpRescheduleSupport::isVehicleAllowed($result, $vehicleId)) {
                    return Response::error('Das gewählte Fahrzeug ist im Zeitraum nicht verfügbar.');
                }

                $booking = app(BookingService::class)->reschedule($booking, $start, $end, $vehicleId);
            } elseif ($categoryId !== null) {
                if (! McpRescheduleSupport::isCategoryAllowed($result, $booking, $categoryId)) {
                    return Response::error('Diese Kategorie ist im gewählten Zeitraum nicht verfügbar.');
                }

                $booking = app(BookingService::class)->rescheduleByCategory($booking, $start, $end, $categoryId);
            } elseif ($result->hasSameCategoryAlternatives()) {
                $booking = app(BookingService::class)->rescheduleByCategory(
                    $booking,
                    $start,
                    $end,
                    $booking->vehicle->vehicle_category_id,
                );
            } else {
                return Response::structured([
                    'success' => false,
                    'message' => 'Keine freie Fahrzeugkategorie im Zeitraum. Bitte buchung_umbuchen_pruefen und vehicle_category_id oder (als Admin) vehicle_id aus other_categories bzw. same_category_vehicles angeben.',
                    'next_step' => 'buchung_umbuchen_pruefen',
                    'availability' => McpRescheduleSupport::formatAvailability($result, $booking, $canSelectVehicle),
                ]);
            }
        } catch (ValidationException $exception) {
            return Response::structured([
                'success' => false,
                'message' => collect($exception->errors())->flatten()->first() ?? 'Umbuchen fehlgeschlagen.',
                'errors' => $exception->errors(),
            ]);
        }

        $booking->load(['vehicle.category', 'driver', 'booker', 'handout.returnRecord', 'logbookEntry']);
        $presenter = app(McpBookingPresenter::class);
        $verification = McpWriteVerification::verifyReschedule($booking, $start, $end);
        $detail = $presenter->detail($booking, $user);

        if (! $verification['verified']) {
            return Response::structured([
                'success' => false,
                'verified' => false,
                'message' => 'Umbuchen fehlgeschlagen — der Zeitraum wurde in der Datenbank nicht aktualisiert.',
                'verification' => $verification,
                'buchung' => $detail,
                'hinweis_fuer_assistent' => McpWriteVerification::assistantWriteHint(),
            ]);
        }

        return Response::structured([
            'success' => true,
            'verified' => true,
            'message' => 'Buchung wurde erfolgreich umgebucht: '.$detail['bezeichnung'],
            'buchung' => $detail,
            'verification' => $verification,
            'hinweis_fuer_assistent' => 'Dem Nutzer nur bei success=true und verified=true Erfolg melden. Zeitraum aus buchung.zeitraum nennen.',
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'booking_id' => $schema->integer()
                ->description('ID der Buchung.')
                ->required(),
            'starts_at' => $schema->string()
                ->description('Neuer Buchungsbeginn (ISO 8601).')
                ->required(),
            'ends_at' => $schema->string()
                ->description('Neues Buchungsende (ISO 8601).')
                ->required(),
            'vehicle_category_id' => $schema->integer()
                ->description('Ziel-Kategorie (Pflicht wenn eigene Kategorie ausgebucht; sonst optional).')
                ->nullable(),
            'vehicle_id' => $schema->integer()
                ->description('Nur für Admins (manage-app-fuhrpark): konkretes Fahrzeug.')
                ->nullable(),
        ];
    }

    public function outputSchema(JsonSchema $schema): array
    {
        return [
            'success' => $schema->boolean()->required(),
            'message' => $schema->string()->required(),
            'buchung' => $schema->object()->nullable(),
            'errors' => $schema->object()->nullable(),
            'next_step' => $schema->string()->nullable(),
        ];
    }
}
