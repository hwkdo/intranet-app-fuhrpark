<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppFuhrpark\Mcp\Servers;

use Hwkdo\IntranetAppBase\Mcp\Tools\BenutzerSuchenTool;
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
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Prompt;
use Laravel\Mcp\Server\Tool;

class FuhrparkServer extends Server
{
    protected string $name = 'Fuhrpark Server';

    protected string $version = '1.0.0';

    protected string $instructions = 'Dieser Server verwaltet Fuhrpark-Buchungen (Fahrzeugbuchungen). Zu Beginn jeder Konversation zuerst fehlende_fahrtenbuecher_anzeigen aufrufen; wenn count > 0, den Nutzer prominent auf fehlende Fahrtenbucheinträge hinweisen. Buchungen gegenüber Nutzern immer mit «bezeichnung» (Zweck + Datum/Uhrzeit) nennen — nie nur ID. Links an Nutzer: fuhrpark_app_links oder url-Felder aus Tool-Antworten — niemals localhost erfinden. Schreibende Aktionen: Erfolg nur bei success=true und verified=true melden; buchung_detail/buchungen_anzeigen ändern nichts; löschen nur via buchung_loeschen, umbuchen nur via buchung_umbuchen. Relative Zeitangaben («morgen», «heute») anhand des vom Client gelieferten Zeitbezugs in ISO-8601 umrechnen. Übersicht: buchungen_anzeigen. Details: buchung_detail (can_cancel, can_update). Fahrtenbuch: fahrtenbuch_eintragen. Neue Buchung: buchung_optionen_auflisten, dann buchung_erstellen. Umbuchen: buchung_umbuchen_pruefen, dann buchung_umbuchen (nur reserved/handed_out). Löschen: buchung_loeschen wenn can_cancel; bei overdue/no_show reason Pflicht. Bei E-Fahrzeugen electric_route_km.';

    /**
     * @var array<int, class-string<Tool>>
     */
    protected array $tools = [
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
    ];

    /**
     * @var array<int, class-string<Server\Resource>>
     */
    protected array $resources = [];

    /**
     * @var array<int, class-string<Prompt>>
     */
    protected array $prompts = [];
}
