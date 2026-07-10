<?php

declare(strict_types=1);

use Hwkdo\IntranetAppBase\Mcp\Tools\BenutzerSuchenTool;
use Hwkdo\IntranetAppFuhrpark\Mcp\Servers\FuhrparkServer;
use Hwkdo\IntranetAppFuhrpark\Mcp\Tools\BuchungDetailTool;
use Hwkdo\IntranetAppFuhrpark\Mcp\Tools\BuchungenAnzeigenTool;
use Hwkdo\IntranetAppFuhrpark\Mcp\Tools\BuchungErstellenTool;
use Hwkdo\IntranetAppFuhrpark\Mcp\Tools\BuchungLoeschenTool;
use Hwkdo\IntranetAppFuhrpark\Mcp\Tools\BuchungOptionenAuflistenTool;
use Hwkdo\IntranetAppFuhrpark\Mcp\Tools\BuchungUmbuchenPruefenTool;
use Hwkdo\IntranetAppFuhrpark\Mcp\Tools\BuchungUmbuchenTool;
use Hwkdo\IntranetAppFuhrpark\Mcp\Tools\FahrtenbuchEintragenTool;
use Hwkdo\IntranetAppFuhrpark\Mcp\Tools\FehlendeFahrtenbuecherAnzeigenTool;
use Hwkdo\IntranetAppFuhrpark\Mcp\Tools\FuhrparkAppLinksTool;

it('registriert die MCP tools in der erwarteten Reihenfolge', function (): void {
    $reflection = new ReflectionClass(FuhrparkServer::class);
    $defaultProperties = $reflection->getDefaultProperties();

    expect($defaultProperties['tools'])->toBe([
        BenutzerSuchenTool::class,
        FuhrparkAppLinksTool::class,
        FehlendeFahrtenbuecherAnzeigenTool::class,
        BuchungenAnzeigenTool::class,
        BuchungDetailTool::class,
        BuchungOptionenAuflistenTool::class,
        BuchungErstellenTool::class,
        BuchungUmbuchenPruefenTool::class,
        BuchungUmbuchenTool::class,
        BuchungLoeschenTool::class,
        FahrtenbuchEintragenTool::class,
    ]);
});

it('hat klare server instructions fuer den buchungs flow', function (): void {
    $reflection = new ReflectionClass(FuhrparkServer::class);
    $defaultProperties = $reflection->getDefaultProperties();
    $instructions = $defaultProperties['instructions'];

    expect($instructions)
        ->toContain('buchungen_anzeigen')
        ->toContain('buchung_detail')
        ->toContain('buchung_optionen_auflisten')
        ->toContain('buchung_erstellen')
        ->toContain('buchung_umbuchen_pruefen')
        ->toContain('buchung_umbuchen')
        ->toContain('buchung_loeschen')
        ->toContain('verified')
        ->toContain('buchung_loeschen')
        ->toContain('fahrtenbuch_eintragen')
        ->toContain('fehlende_fahrtenbuecher_anzeigen')
        ->toContain('returned');
});
