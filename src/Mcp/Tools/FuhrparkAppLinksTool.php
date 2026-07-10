<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppFuhrpark\Mcp\Tools;

use Hwkdo\IntranetAppFuhrpark\Support\FuhrparkUrls;
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
class FuhrparkAppLinksTool extends Tool
{
    protected string $name = 'fuhrpark_app_links';

    protected string $description = 'Liefert die korrekten öffentlichen URLs zur Fuhrpark-App (app_url und Seitenlinks). Vor jedem Link an den Nutzer verwenden — niemals localhost erfinden.';

    public function handle(Request $request): Response|ResponseFactory
    {
        $user = $request->user();
        if ($user === null) {
            return Response::error('Nicht authentifiziert.');
        }

        Gate::forUser($user)->authorize('see-app-fuhrpark');

        return Response::structured([
            'app_url' => FuhrparkUrls::publicBaseUrl(),
            'links' => FuhrparkUrls::appLinks(),
            'hinweis_fuer_assistent' => FuhrparkUrls::assistantLinkHint(),
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [];
    }

    public function outputSchema(JsonSchema $schema): array
    {
        return [
            'app_url' => $schema->string()->required(),
            'links' => $schema->object()->required(),
            'hinweis_fuer_assistent' => $schema->string()->required(),
        ];
    }
}
