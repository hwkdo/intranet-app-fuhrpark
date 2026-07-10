<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppFuhrpark\Mcp\Tools;

use Hwkdo\IntranetAppFuhrpark\Mcp\Support\McpMissingLogbookSupport;
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
class FehlendeFahrtenbuecherAnzeigenTool extends Tool
{
    protected string $name = 'fehlende_fahrtenbuecher_anzeigen';

    protected string $description = 'Zeigt Buchungen des angemeldeten Fahrers, bei denen das Fahrzeug zurückgegeben wurde, aber noch kein Fahrtenbucheintrag existiert (Status returned). Zu Beginn jeder Konversation prüfen und den Nutzer darauf hinweisen, wenn Einträge fehlen.';

    public function handle(Request $request): Response|ResponseFactory
    {
        $user = $request->user();
        if ($user === null) {
            return Response::error('Nicht authentifiziert.');
        }

        Gate::forUser($user)->authorize('see-app-fuhrpark');

        $items = McpMissingLogbookSupport::summarizeForDriver((int) $user->getAuthIdentifier(), $user);
        $count = count($items);

        return Response::structured([
            'count' => $count,
            'fehlende_fahrtenbuecher' => $items,
            'hinweis_fuer_assistent' => $count > 0
                ? 'Der Nutzer hat '.$count.' offene Fahrtenbucheinträge. Weise zu Beginn der Antwort klar darauf hin und biete an, diese per fahrtenbuch_eintragen zu erfassen.'
                : 'Keine offenen Fahrtenbucheinträge — kein Hinweis nötig.',
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [];
    }

    public function outputSchema(JsonSchema $schema): array
    {
        return [
            'count' => $schema->integer()->required(),
            'fehlende_fahrtenbuecher' => $schema->array()->required(),
            'hinweis_fuer_assistent' => $schema->string()->required(),
        ];
    }
}
