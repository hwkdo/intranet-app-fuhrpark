<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppFuhrpark\Mcp\Servers;

use Hwkdo\IntranetAppBase\Mcp\Tools\BenutzerSuchenTool;
use Hwkdo\IntranetAppFuhrpark\Mcp\Tools\BuchungDetailTool;
use Hwkdo\IntranetAppFuhrpark\Mcp\Tools\BuchungErstellenTool;
use Hwkdo\IntranetAppFuhrpark\Mcp\Tools\BuchungOptionenAuflistenTool;
use Hwkdo\IntranetAppFuhrpark\Mcp\Tools\BuchungenAnzeigenTool;
use Hwkdo\IntranetAppFuhrpark\Mcp\Tools\FahrtenbuchEintragenTool;
use Hwkdo\IntranetAppFuhrpark\Mcp\Tools\FehlendeFahrtenbuecherAnzeigenTool;
use Laravel\Mcp\Server;

class FuhrparkServer extends Server
{
    protected string $name = 'Fuhrpark Server';

    protected string $version = '1.0.0';

    protected string $instructions = 'Dieser Server verwaltet Fuhrpark-Buchungen (Fahrzeugbuchungen). Zu Beginn jeder Konversation zuerst fehlende_fahrtenbuecher_anzeigen aufrufen; wenn count > 0, den Nutzer prominent auf fehlende Fahrtenbucheinträge hinweisen. Relative Zeitangaben («morgen», «heute») anhand des vom Client gelieferten Zeitbezugs in ISO-8601 umrechnen. Übersicht: buchungen_anzeigen. Details: buchung_detail. Fahrtenbuch erfassen: fahrtenbuch_eintragen (nur Status returned, nur als Fahrer). Neue Buchung: buchung_optionen_auflisten, dann buchung_erstellen. Bei E-Fahrzeugen electric_route_km. Status returned=zurückgegeben ohne Fahrtenbuch, completed=mit Fahrtenbuch.';

    /**
     * @var array<int, class-string<\Laravel\Mcp\Server\Tool>>
     */
    protected array $tools = [
        BenutzerSuchenTool::class,
        FehlendeFahrtenbuecherAnzeigenTool::class,
        BuchungenAnzeigenTool::class,
        BuchungDetailTool::class,
        BuchungOptionenAuflistenTool::class,
        BuchungErstellenTool::class,
        FahrtenbuchEintragenTool::class,
    ];

    /**
     * @var array<int, class-string<\Laravel\Mcp\Server\Resource>>
     */
    protected array $resources = [];

    /**
     * @var array<int, class-string<\Laravel\Mcp\Server\Prompt>>
     */
    protected array $prompts = [];
}
