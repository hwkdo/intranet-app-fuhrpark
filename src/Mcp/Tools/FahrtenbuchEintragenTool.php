<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppFuhrpark\Mcp\Tools;

use Hwkdo\IntranetAppFuhrpark\Enums\BookingStatus;
use Hwkdo\IntranetAppFuhrpark\Mcp\Support\McpBookingPresenter;
use Hwkdo\IntranetAppFuhrpark\Models\Booking;
use Hwkdo\IntranetAppFuhrpark\Models\Project;
use Hwkdo\IntranetAppFuhrpark\Services\BookingStatusResolver;
use Hwkdo\IntranetAppFuhrpark\Services\LogbookService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsOpenWorld;

#[IsOpenWorld]
class FahrtenbuchEintragenTool extends Tool
{
    protected string $name = 'fahrtenbuch_eintragen';

    protected string $description = 'Erfasst einen Fahrtenbucheintrag für eine zurückgegebene Buchung (Status returned). Nur als Fahrer der Buchung. Pflicht: booking_id, route, km_commute, km_project. Bei km_project > 0 ist project_id erforderlich.';

    public function handle(Request $request): Response|ResponseFactory
    {
        $user = $request->user();
        if ($user === null) {
            return Response::error('Nicht authentifiziert.');
        }

        Gate::forUser($user)->authorize('see-app-fuhrpark');

        $validated = $request->validate([
            'booking_id' => ['required', 'integer', 'exists:intranet_app_fuhrpark_bookings,id'],
            'route' => ['required', 'string', 'max:500'],
            'km_commute' => ['required', 'integer', 'min:0'],
            'km_project' => ['required', 'integer', 'min:0'],
            'project_id' => ['nullable', 'integer', 'exists:intranet_app_fuhrpark_projects,id'],
            'fueled' => ['nullable', 'boolean'],
            'cleaned' => ['nullable', 'boolean'],
            'note' => ['nullable', 'string', 'max:1000'],
        ], [
            'booking_id.required' => 'booking_id ist erforderlich. Nutze fehlende_fahrtenbuecher_anzeigen.',
            'route.required' => 'route (Streckenbeschreibung) ist Pflicht.',
            'km_commute.required' => 'km_commute (Kilometer Arbeitsfahrt) ist Pflicht.',
            'km_project.required' => 'km_project (Kilometer Projektfahrt) ist Pflicht.',
        ]);

        if ((int) $validated['km_project'] > 0 && ! isset($validated['project_id'])) {
            return Response::error('Bei km_project > 0 ist project_id erforderlich.');
        }

        $booking = Booking::query()
            ->with(['vehicle.category', 'driver', 'booker', 'handout.returnRecord', 'logbookEntry'])
            ->findOrFail((int) $validated['booking_id']);

        if ((int) $booking->driver_id !== (int) $user->getAuthIdentifier()) {
            return Response::error('Nur der Fahrer der Buchung kann den Fahrtenbucheintrag erfassen.');
        }

        $status = app(BookingStatusResolver::class)->resolve($booking);

        if ($status === BookingStatus::Completed) {
            return Response::error('Für diese Buchung existiert bereits ein Fahrtenbucheintrag.');
        }

        if ($status !== BookingStatus::Returned) {
            return Response::error(
                'Fahrtenbuch kann nur erfasst werden, wenn das Fahrzeug zurückgegeben wurde (Status: returned). Aktueller Status: '.$status->value
            );
        }

        if (isset($validated['project_id'])) {
            $projectActive = Project::query()
                ->where('id', (int) $validated['project_id'])
                ->where('active', true)
                ->exists();

            if (! $projectActive) {
                return Response::error('Das gewählte Projekt ist nicht aktiv oder existiert nicht.');
            }
        }

        try {
            app(LogbookService::class)->create($user, [
                'booking_id' => $booking->id,
                'route' => $validated['route'],
                'km_commute' => (int) $validated['km_commute'],
                'km_project' => (int) $validated['km_project'],
                'project_id' => $validated['project_id'] ?? null,
                'fueled' => (bool) ($validated['fueled'] ?? false),
                'cleaned' => (bool) ($validated['cleaned'] ?? false),
                'note' => $validated['note'] ?? null,
            ]);
        } catch (ValidationException $exception) {
            return Response::structured([
                'success' => false,
                'message' => 'Fahrtenbucheintrag konnte nicht gespeichert werden.',
                'errors' => $exception->errors(),
            ]);
        }

        $booking->refresh()->load([
            'vehicle.category',
            'driver',
            'booker',
            'handout.returnRecord',
            'logbookEntry.project',
            'logbookEntry.user',
        ]);

        $presenter = app(McpBookingPresenter::class);

        return Response::structured([
            'success' => true,
            'message' => 'Fahrtenbucheintrag erfolgreich erfasst.',
            'buchung' => $presenter->detail($booking, $user),
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'booking_id' => $schema->integer()
                ->description('ID der Buchung (aus fehlende_fahrtenbuecher_anzeigen).')
                ->required(),
            'route' => $schema->string()
                ->description('Streckenbeschreibung (Pflicht).')
                ->required(),
            'km_commute' => $schema->integer()
                ->description('Kilometer Arbeits-/Pendelfahrt (Pflicht, >= 0).')
                ->required(),
            'km_project' => $schema->integer()
                ->description('Kilometer Projektfahrt (Pflicht, >= 0). Bei > 0 ist project_id nötig.')
                ->required(),
            'project_id' => $schema->integer()
                ->description('Projekt-ID, Pflicht wenn km_project > 0.')
                ->nullable(),
            'fueled' => $schema->boolean()
                ->description('Optional: Getankt.')
                ->nullable(),
            'cleaned' => $schema->boolean()
                ->description('Optional: Gereinigt.')
                ->nullable(),
            'note' => $schema->string()
                ->description('Optionale Notiz.')
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
        ];
    }
}
