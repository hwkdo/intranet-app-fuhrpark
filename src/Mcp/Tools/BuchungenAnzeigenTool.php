<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppFuhrpark\Mcp\Tools;

use Carbon\Carbon;
use Hwkdo\IntranetAppFuhrpark\Enums\BookingPurpose;
use Hwkdo\IntranetAppFuhrpark\Enums\BookingStatus;
use Hwkdo\IntranetAppFuhrpark\Mcp\Support\McpBookingPresenter;
use Hwkdo\IntranetAppFuhrpark\Mcp\Support\McpMissingLogbookSupport;
use Hwkdo\IntranetAppFuhrpark\Models\Booking;
use Hwkdo\IntranetAppFuhrpark\Services\BookingStatusResolver;
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
class BuchungenAnzeigenTool extends Tool
{
    protected string $name = 'buchungen_anzeigen';

    protected string $description = 'Zeigt eine Übersicht der Fuhrpark-Buchungen des angemeldeten Nutzers an — als Fahrer und/oder als Bucher. Unterstützt Filter für offene Buchungen, Rolle und Zeitraum.';

    public function handle(Request $request): Response|ResponseFactory
    {
        $user = $request->user();
        if ($user === null) {
            return Response::error('Nicht authentifiziert.');
        }

        Gate::forUser($user)->authorize('see-app-fuhrpark');

        $validated = $request->validate([
            'filter' => ['nullable', 'string', 'in:open,all'],
            'rolle' => ['nullable', 'string', 'in:fahrer,bucher,beide'],
            'from_date' => ['nullable', 'date'],
            'to_date' => ['nullable', 'date', 'after_or_equal:from_date'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:100'],
        ], [
            'filter.in' => 'filter muss "open" (nur offene) oder "all" (alle) sein.',
            'rolle.in' => 'rolle muss "fahrer", "bucher" oder "beide" sein.',
        ]);

        $filter = $validated['filter'] ?? 'open';
        $rolle = $validated['rolle'] ?? 'beide';
        $limit = (int) ($validated['limit'] ?? 50);
        $userId = (int) $user->getAuthIdentifier();

        $query = Booking::query()
            ->where(function ($builder) use ($rolle, $userId): void {
                if ($rolle === 'fahrer') {
                    $builder->where('driver_id', $userId);

                    return;
                }

                if ($rolle === 'bucher') {
                    $builder->where('user_id', $userId);

                    return;
                }

                $builder->where('user_id', $userId)
                    ->orWhere('driver_id', $userId);
            })
            ->whereNotIn('purpose', [BookingPurpose::Lock, BookingPurpose::ChargeLock])
            ->with(['vehicle.category', 'driver', 'booker', 'handout.returnRecord', 'logbookEntry'])
            ->orderByDesc('starts_at');

        if (isset($validated['from_date'])) {
            $query->where('ends_at', '>=', Carbon::parse($validated['from_date'])->startOfDay());
        }

        if (isset($validated['to_date'])) {
            $query->where('starts_at', '<=', Carbon::parse($validated['to_date'])->endOfDay());
        }

        $statusResolver = app(BookingStatusResolver::class);
        $presenter = app(McpBookingPresenter::class);

        $bookings = $query->limit($filter === 'open' ? 200 : $limit)->get();

        if ($filter === 'open') {
            $bookings = $bookings
                ->filter(fn (Booking $booking): bool => $statusResolver->resolve($booking) !== BookingStatus::Completed)
                ->take($limit)
                ->values();
        }

        $items = $bookings
            ->map(fn (Booking $booking): array => $presenter->summarize($booking, $user))
            ->values()
            ->all();

        $fehlendeFahrtenbuecher = [];
        if (in_array($rolle, ['fahrer', 'beide'], true)) {
            $fehlendeFahrtenbuecher = McpMissingLogbookSupport::summarizeForDriver($userId, $user);
        }

        return Response::structured([
            'filter' => $filter,
            'rolle' => $rolle,
            'total' => count($items),
            'buchungen' => $items,
            'fehlende_fahrtenbuecher_count' => count($fehlendeFahrtenbuecher),
            'fehlende_fahrtenbuecher' => $fehlendeFahrtenbuecher,
            'hinweis_fuer_assistent' => count($fehlendeFahrtenbuecher) > 0
                ? 'Der Nutzer hat offene Fahrtenbucheinträge — zu Beginn der Antwort darauf hinweisen.'
                : null,
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'filter' => $schema->string()
                ->description('Optional: "open" zeigt nur offene Buchungen (Standard), "all" alle Buchungen im Limit.')
                ->nullable(),
            'rolle' => $schema->string()
                ->description('Optional: "fahrer" (nur als Fahrer), "bucher" (nur selbst gebucht), "beide" (Standard).')
                ->nullable(),
            'from_date' => $schema->string()
                ->description('Optional: Startdatum für den Zeitraumfilter (YYYY-MM-DD).')
                ->nullable(),
            'to_date' => $schema->string()
                ->description('Optional: Enddatum für den Zeitraumfilter (YYYY-MM-DD).')
                ->nullable(),
            'limit' => $schema->integer()
                ->description('Maximale Anzahl Treffer (Standard 50, max. 100).')
                ->nullable(),
        ];
    }

    public function outputSchema(JsonSchema $schema): array
    {
        return [
            'filter' => $schema->string()->required(),
            'rolle' => $schema->string()->required(),
            'total' => $schema->integer()->required(),
            'buchungen' => $schema->array()->required(),
            'fehlende_fahrtenbuecher_count' => $schema->integer()->required(),
            'fehlende_fahrtenbuecher' => $schema->array()->required(),
            'hinweis_fuer_assistent' => $schema->string()->nullable(),
        ];
    }
}
