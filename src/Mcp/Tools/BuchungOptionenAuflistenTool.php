<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppFuhrpark\Mcp\Tools;

use Carbon\Carbon;
use Hwkdo\IntranetAppFuhrpark\Services\VehicleAvailabilityService;
use Hwkdo\IntranetAppFuhrpark\Support\FuhrparkModels;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Gate;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsOpenWorld;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
#[IsOpenWorld]
class BuchungOptionenAuflistenTool extends Tool
{
    protected string $name = 'buchung_optionen_auflisten';

    protected string $description = 'Listet Fahrzeugstandorte und verfügbare Fahrzeugkategorien für einen Buchungszeitraum auf. Vor buchung_erstellen verwenden, um standort_id und vehicle_category_id zu ermitteln.';

    public function handle(Request $request): Response|ResponseFactory
    {
        $user = $request->user();
        if ($user === null) {
            return Response::error('Nicht authentifiziert.');
        }

        Gate::forUser($user)->authorize('see-app-fuhrpark');

        $validated = $request->validate([
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date', 'after:starts_at'],
            'standort_id' => ['nullable', 'integer'],
            'electric_route_km' => ['nullable', 'integer', 'min:1'],
        ], [
            'starts_at.required' => 'starts_at ist erforderlich (ISO 8601, z. B. 2026-07-15T08:00:00).',
            'ends_at.after' => 'ends_at muss nach starts_at liegen.',
        ]);

        $startsAt = Carbon::parse($validated['starts_at']);
        $endsAt = Carbon::parse($validated['ends_at']);
        $electricRouteKm = isset($validated['electric_route_km']) ? (int) $validated['electric_route_km'] : null;

        $standorte = FuhrparkModels::vehicleStandorte()
            ->map(fn ($standort): array => [
                'id' => (int) $standort->id,
                'name' => (string) $standort->name,
                'is_default_for_user' => FuhrparkModels::vehicleStandortIdFor($user->standort_id ?? null) === (int) $standort->id,
            ])
            ->values()
            ->all();

        $standortId = isset($validated['standort_id'])
            ? (int) $validated['standort_id']
            : FuhrparkModels::vehicleStandortIdFor($user->standort_id ?? null);

        $kategorien = [];
        if ($standortId !== null) {
            $kategorien = app(VehicleAvailabilityService::class)
                ->categoryBookingOptions($startsAt, $endsAt, $standortId, electricRouteKm: $electricRouteKm)
                ->map(fn ($option): array => [
                    'vehicle_category_id' => $option->category->id,
                    'name' => $option->category->name,
                    'is_available' => $option->isAvailable,
                    'label' => $option->label(),
                    'is_electric' => (bool) $option->category->is_electric,
                    'requires_license' => (bool) $option->category->requires_license,
                    'electric_range_km_hint' => $option->category->is_electric
                        ? $option->category->averageElectricRangeKm()
                        : null,
                ])
                ->values()
                ->all();
        }

        return Response::structured([
            'starts_at' => $startsAt->toIso8601String(),
            'ends_at' => $endsAt->toIso8601String(),
            'standort_id' => $standortId,
            'standorte' => $standorte,
            'kategorien' => $kategorien,
            'available_count' => collect($kategorien)->where('is_available', true)->count(),
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'starts_at' => $schema->string()
                ->description('Gewünschter Buchungsbeginn (ISO 8601).')
                ->required(),
            'ends_at' => $schema->string()
                ->description('Gewünschtes Buchungsende (ISO 8601).')
                ->required(),
            'standort_id' => $schema->integer()
                ->description('Optional: Fahrzeugstandort. Ohne Angabe wird der Nutzer-Standardstandort verwendet.')
                ->nullable(),
            'electric_route_km' => $schema->integer()
                ->description('Optional: Geplante E-Strecke in km — beeinflusst Verfügbarkeit bei Elektrofahrzeugen.')
                ->nullable(),
        ];
    }

    public function outputSchema(JsonSchema $schema): array
    {
        return [
            'starts_at' => $schema->string()->required(),
            'ends_at' => $schema->string()->required(),
            'standort_id' => $schema->integer()->nullable(),
            'standorte' => $schema->array()->required(),
            'kategorien' => $schema->array()->required(),
            'available_count' => $schema->integer()->required(),
        ];
    }
}
