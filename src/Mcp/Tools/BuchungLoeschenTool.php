<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppFuhrpark\Mcp\Tools;

use Hwkdo\IntranetAppFuhrpark\Mcp\Support\McpBookingPresenter;
use Hwkdo\IntranetAppFuhrpark\Mcp\Support\McpWriteVerification;
use Hwkdo\IntranetAppFuhrpark\Models\Booking;
use Hwkdo\IntranetAppFuhrpark\Services\BookingService;
use Hwkdo\IntranetAppFuhrpark\Services\BookingStatusResolver;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsOpenWorld;

#[IsOpenWorld]
class BuchungLoeschenTool extends Tool
{
    protected string $name = 'buchung_loeschen';

    protected string $description = 'Löscht/storniert eine Buchung (wie «Löschen» im Kalender-UI). Nur wenn can_cancel laut buchung_detail. Bei überfälligen Buchungen (overdue/no_show) ist reason (min. 3 Zeichen) Pflicht — die Administration wird informiert.';

    public function handle(Request $request): Response|ResponseFactory
    {
        $user = $request->user();
        if ($user === null) {
            return Response::error('Nicht authentifiziert.');
        }

        Gate::forUser($user)->authorize('see-app-fuhrpark');

        $validated = $request->validate([
            'booking_id' => ['required', 'integer', 'exists:intranet_app_fuhrpark_bookings,id'],
            'reason' => ['nullable', 'string', 'min:3', 'max:1000'],
        ], [
            'booking_id.required' => 'booking_id ist erforderlich.',
            'reason.min' => 'Die Begründung muss mindestens 3 Zeichen haben.',
        ]);

        $booking = Booking::query()
            ->with(['vehicle', 'driver', 'handout.returnRecord', 'logbookEntry'])
            ->findOrFail((int) $validated['booking_id']);

        Gate::forUser($user)->authorize('cancel', $booking);

        $statusResolver = app(BookingStatusResolver::class);
        $requiresReason = $statusResolver->requiresCancellationReason($booking);

        if ($requiresReason && blank($validated['reason'] ?? null)) {
            return Response::structured([
                'success' => false,
                'message' => 'Die Buchung ist überfällig. Bitte geben Sie eine Begründung an (reason, min. 3 Zeichen). Die Fuhrpark-Administration wird informiert.',
                'requires_reason' => true,
            ]);
        }

        $bookingId = $booking->id;
        $bezeichnung = app(McpBookingPresenter::class)->formatBezeichnung($booking);

        try {
            app(BookingService::class)->cancel(
                $booking,
                $validated['reason'] ?? null,
                $user,
            );
        } catch (ValidationException $exception) {
            return Response::structured([
                'success' => false,
                'verified' => false,
                'message' => collect($exception->errors())->flatten()->first() ?? 'Buchung konnte nicht gelöscht werden.',
                'errors' => $exception->errors(),
                'hinweis_fuer_assistent' => McpWriteVerification::assistantWriteHint(),
            ]);
        }

        $verification = McpWriteVerification::verifyDeleted($bookingId);

        if (! $verification['verified']) {
            return Response::structured([
                'success' => false,
                'verified' => false,
                'message' => 'Löschen fehlgeschlagen — die Buchung existiert noch in der Datenbank.',
                'bezeichnung' => $bezeichnung,
                'deleted_booking_id' => $bookingId,
                'verification' => $verification,
                'hinweis_fuer_assistent' => McpWriteVerification::assistantWriteHint(),
            ]);
        }

        return Response::structured([
            'success' => true,
            'verified' => true,
            'message' => 'Die Buchung «'.$bezeichnung.'» wurde erfolgreich gelöscht.',
            'bezeichnung' => $bezeichnung,
            'deleted_booking_id' => $bookingId,
            'verification' => $verification,
            'hinweis_fuer_assistent' => 'Dem Nutzer nur bei success=true und verified=true Erfolg melden. buchung_loeschen war erforderlich — buchung_detail allein reicht nicht.',
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'booking_id' => $schema->integer()
                ->description('ID der zu löschenden Buchung.')
                ->required(),
            'reason' => $schema->string()
                ->description('Begründung — Pflicht bei überfälligen Buchungen (Status overdue/no_show), sonst optional.')
                ->nullable(),
        ];
    }

    public function outputSchema(JsonSchema $schema): array
    {
        return [
            'success' => $schema->boolean()->required(),
            'message' => $schema->string()->required(),
            'deleted_booking_id' => $schema->integer()->nullable(),
            'requires_reason' => $schema->boolean()->nullable(),
        ];
    }
}
