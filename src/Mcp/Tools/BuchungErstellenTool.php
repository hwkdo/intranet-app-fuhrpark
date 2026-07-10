<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppFuhrpark\Mcp\Tools;

use Carbon\Carbon;
use Hwkdo\IntranetAppFuhrpark\Data\BookingStoreData;
use Hwkdo\IntranetAppFuhrpark\Mcp\Support\McpBookingPresenter;
use Hwkdo\IntranetAppFuhrpark\Models\VehicleCategory;
use Hwkdo\IntranetAppFuhrpark\Services\BookingService;
use Hwkdo\IntranetAppFuhrpark\Services\VehicleAvailabilityService;
use Hwkdo\IntranetAppFuhrpark\Support\FuhrparkModels;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsOpenWorld;

#[IsOpenWorld]
class BuchungErstellenTool extends Tool
{
    protected string $name = 'buchung_erstellen';

    protected string $description = 'Bucht ein Fahrzeug für einen Zeitraum. Vorher buchung_optionen_auflisten nutzen, um verfügbare Standorte und Kategorien zu ermitteln. Bei Buchung für andere Personen zuerst benutzer_suchen für driver_id. Bei E-Fahrzeugen ist electric_route_km Pflicht.';

    public function handle(Request $request): Response|ResponseFactory
    {
        $user = $request->user();
        if ($user === null) {
            return Response::error('Nicht authentifiziert.');
        }

        Gate::forUser($user)->authorize('fuhrpark.book');

        $validated = $request->validate([
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date', 'after:starts_at'],
            'description' => ['required', 'string', 'max:255'],
            'vehicle_category_id' => ['required', 'integer', 'exists:intranet_app_fuhrpark_vehicle_categories,id'],
            'standort_id' => ['nullable', 'integer'],
            'driver_id' => ['nullable', 'integer'],
            'is_commute' => ['nullable', 'boolean'],
            'electric_route_km' => ['nullable', 'integer', 'min:1'],
            'sync_to_calendar' => ['nullable', 'boolean'],
        ], [
            'starts_at.required' => 'starts_at ist erforderlich (ISO 8601, z. B. 2026-07-15T08:00:00).',
            'ends_at.after' => 'ends_at muss nach starts_at liegen.',
            'description.required' => 'description (Zweck/Anlass der Fahrt) ist erforderlich.',
            'vehicle_category_id.required' => 'vehicle_category_id ist erforderlich. Nutze buchung_optionen_auflisten für verfügbare Kategorien.',
        ]);

        $startsAt = Carbon::parse($validated['starts_at']);
        $endsAt = Carbon::parse($validated['ends_at']);

        if ($startsAt->isPast()) {
            return Response::error('Der Buchungsbeginn muss in der Zukunft liegen.');
        }

        $standortId = isset($validated['standort_id'])
            ? (int) $validated['standort_id']
            : FuhrparkModels::vehicleStandortIdFor($user->standort_id ?? null);

        if ($standortId === null) {
            return Response::structured([
                'success' => false,
                'message' => 'Kein Fahrzeugstandort ermittelbar. Bitte standort_id angeben oder buchung_optionen_auflisten nutzen.',
                'next_step' => 'buchung_optionen_auflisten',
            ]);
        }

        $validStandortIds = FuhrparkModels::vehicleStandorte()->pluck('id')->map(fn ($id): int => (int) $id);
        if (! $validStandortIds->contains($standortId)) {
            return Response::error('Ungültiger Fahrzeugstandort. Nutze buchung_optionen_auflisten für gültige Standort-IDs.');
        }

        $category = VehicleCategory::query()->findOrFail((int) $validated['vehicle_category_id']);
        $electricRouteKm = isset($validated['electric_route_km']) ? (int) $validated['electric_route_km'] : null;

        if ($category->is_electric && $electricRouteKm === null) {
            return Response::error('Bei E-Fahrzeugen ist electric_route_km (geplante Strecke in km) erforderlich.');
        }

        $availabilityService = app(VehicleAvailabilityService::class);
        $categoryAvailable = $availabilityService
            ->categoryBookingOptions($startsAt, $endsAt, $standortId, electricRouteKm: $electricRouteKm)
            ->contains(fn ($option): bool => $option->category->id === $category->id && $option->isAvailable);

        if (! $categoryAvailable) {
            $alternatives = $availabilityService
                ->categoryBookingOptions($startsAt, $endsAt, $standortId, electricRouteKm: $electricRouteKm)
                ->filter(fn ($option): bool => $option->isAvailable)
                ->map(fn ($option): array => [
                    'vehicle_category_id' => $option->category->id,
                    'name' => $option->category->name,
                    'is_electric' => (bool) $option->category->is_electric,
                ])
                ->values()
                ->all();

            return Response::structured([
                'success' => false,
                'message' => 'Die gewählte Kategorie ist im Zeitraum ausgebucht.',
                'available_categories' => $alternatives,
                'next_step' => 'buchung_optionen_auflisten',
            ]);
        }

        $driverId = isset($validated['driver_id'])
            ? (int) $validated['driver_id']
            : (int) $user->getAuthIdentifier();

        try {
            $booking = app(BookingService::class)->create(
                new BookingStoreData(
                    driverId: $driverId,
                    description: $validated['description'],
                    startsAt: $startsAt,
                    endsAt: $endsAt,
                    vehicleCategoryId: $category->id,
                    standortId: $standortId,
                    isCommute: (bool) ($validated['is_commute'] ?? false),
                    electricRouteKm: $electricRouteKm,
                    syncToCalendar: (bool) ($validated['sync_to_calendar'] ?? false),
                ),
                $user,
            );
        } catch (ValidationException $exception) {
            return Response::structured([
                'success' => false,
                'message' => 'Buchung konnte nicht erstellt werden.',
                'errors' => $exception->errors(),
            ]);
        }

        $booking->load(['vehicle.category', 'driver', 'booker', 'handout.returnRecord', 'logbookEntry']);
        $presenter = app(McpBookingPresenter::class);

        return Response::structured([
            'success' => true,
            'message' => 'Buchung erfolgreich erstellt.',
            'buchung' => $presenter->detail($booking, $user),
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'starts_at' => $schema->string()
                ->description('Buchungsbeginn als ISO-8601-Datum/Zeit, z. B. 2026-07-15T08:00:00.')
                ->required(),
            'ends_at' => $schema->string()
                ->description('Buchungsende als ISO-8601-Datum/Zeit, z. B. 2026-07-15T12:00:00.')
                ->required(),
            'description' => $schema->string()
                ->description('Zweck oder Anlass der Fahrt (Pflichtfeld).')
                ->required(),
            'vehicle_category_id' => $schema->integer()
                ->description('ID der Fahrzeugkategorie (aus buchung_optionen_auflisten).')
                ->required(),
            'standort_id' => $schema->integer()
                ->description('Optional: ID des Fahrzeugstandorts. Standard: Standort des angemeldeten Nutzers.')
                ->nullable(),
            'driver_id' => $schema->integer()
                ->description('Optional: ID des Fahrers. Standard: angemeldeter Nutzer. Für andere: benutzer_suchen nutzen.')
                ->nullable(),
            'is_commute' => $schema->boolean()
                ->description('Optional: true wenn Arbeits-/Pendelfahrt.')
                ->nullable(),
            'electric_route_km' => $schema->integer()
                ->description('Pflicht bei E-Fahrzeugen: geplante Strecke in km.')
                ->nullable(),
            'sync_to_calendar' => $schema->boolean()
                ->description('Optional: Buchung in Outlook-Kalender synchronisieren.')
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
            'available_categories' => $schema->array()->nullable(),
            'next_step' => $schema->string()->nullable(),
        ];
    }
}
