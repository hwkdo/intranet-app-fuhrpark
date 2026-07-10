<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppFuhrpark\Mcp\Tools;

use Hwkdo\IntranetAppFuhrpark\Mcp\Support\McpBookingPresenter;
use Hwkdo\IntranetAppFuhrpark\Models\Booking;
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
class BuchungDetailTool extends Tool
{
    protected string $name = 'buchung_detail';

    protected string $description = 'Zeigt alle Details einer Fuhrpark-Buchung inklusive Fahrzeug, Fahrer, Bucher, Ausgabe/Rückgabe und Fahrtenbuch. Nur für Buchungen, auf die der Nutzer Zugriff hat.';

    public function handle(Request $request): Response|ResponseFactory
    {
        $user = $request->user();
        if ($user === null) {
            return Response::error('Nicht authentifiziert.');
        }

        Gate::forUser($user)->authorize('see-app-fuhrpark');

        $validated = $request->validate([
            'booking_id' => ['required', 'integer', 'exists:intranet_app_fuhrpark_bookings,id'],
        ], [
            'booking_id.required' => 'booking_id ist erforderlich. Nutze buchungen_anzeigen, um IDs zu finden.',
            'booking_id.exists' => 'Keine Buchung mit dieser ID gefunden.',
        ]);

        $booking = Booking::query()
            ->with([
                'vehicle.category',
                'driver',
                'booker',
                'handout.processedBy',
                'handout.returnRecord.processedBy',
                'logbookEntry.project',
                'logbookEntry.user',
            ])
            ->findOrFail((int) $validated['booking_id']);

        Gate::forUser($user)->authorize('view', $booking);

        $presenter = app(McpBookingPresenter::class);

        return Response::structured([
            'buchung' => $presenter->detail($booking, $user),
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'booking_id' => $schema->integer()
                ->description('ID der Buchung (aus buchungen_anzeigen).')
                ->required(),
        ];
    }

    public function outputSchema(JsonSchema $schema): array
    {
        return [
            'buchung' => $schema->object()->required(),
        ];
    }
}
