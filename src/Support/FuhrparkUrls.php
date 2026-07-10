<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppFuhrpark\Support;

class FuhrparkUrls
{
    public static function publicBaseUrl(): string
    {
        return rtrim((string) config('app.url'), '/');
    }

    /**
     * @param  array<string, mixed>  $parameters
     */
    public static function route(string $name, array $parameters = []): string
    {
        return self::publicBaseUrl().route($name, $parameters, false);
    }

    /**
     * @return array<string, array{label: string, url: string, beschreibung: string}>
     */
    public static function appLinks(): array
    {
        return [
            'kalender' => [
                'label' => 'Kalender',
                'url' => self::route('apps.fuhrpark.index'),
                'beschreibung' => 'Fahrzeuge buchen und Kalenderübersicht',
            ],
            'meine_buchungen' => [
                'label' => 'Meine Buchungen',
                'url' => self::route('apps.fuhrpark.meine'),
                'beschreibung' => 'Eigene Buchungen verwalten, umbuchen, stornieren',
            ],
            'ki_chat' => [
                'label' => 'KI-Chat',
                'url' => self::route('apps.fuhrpark.chat'),
                'beschreibung' => 'Fuhrpark-Assistent mit MCP-Tools',
            ],
            'info' => [
                'label' => 'Info',
                'url' => self::route('apps.fuhrpark.info'),
                'beschreibung' => 'Informationen zur Fuhrpark-App',
            ],
            'zentrale' => [
                'label' => 'Zentrale',
                'url' => self::route('apps.fuhrpark.zentrale'),
                'beschreibung' => 'Ausgabe und Rückgabe von Fahrzeugen (Berechtigung erforderlich)',
            ],
            'admin' => [
                'label' => 'Administration',
                'url' => self::route('apps.fuhrpark.admin.index'),
                'beschreibung' => 'Fuhrpark-Administration (Berechtigung erforderlich)',
            ],
        ];
    }

    public static function assistantLinkHint(): string
    {
        return 'Links an Nutzer immer aus «app_url» bzw. «links» dieses Tools oder aus url-Feldern der Buchungsdaten verwenden. Niemals localhost oder andere Hosts erfinden.';
    }
}
