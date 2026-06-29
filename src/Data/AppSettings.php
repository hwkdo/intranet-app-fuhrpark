<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppFuhrpark\Data;

use Hwkdo\IntranetAppBase\Data\Attributes\Description;
use Hwkdo\IntranetAppBase\Data\BaseAppSettings;

class AppSettings extends BaseAppSettings
{
    public function __construct(
        #[Description('Maximale Buchungsdauer in Tagen')]
        public int $maxBookingDays = 10,

        #[Description('Max. offene Fahrtenbucheinträge pro Fahrer')]
        public int $maxOpenLogbook = 3,

        #[Description('Max. nicht angetretene Buchungen pro Fahrer')]
        public int $maxNoShow = 3,

        #[Description('URL zur Dienstanweisung')]
        public string $instructionUrl = 'https://intranet.hwkdo.net/objekte/538',

        #[Description('Spatie-Rolle für Projektfahrt-Benachrichtigungen')]
        public string $projectNotifyRole = 'App-Fuhrpark-Projekt-Empfaenger',

        #[Description('Spatie-Rolle für Admin-Benachrichtigungen')]
        public string $adminNotifyRole = 'App-Fuhrpark-Admin',

        #[Description('System-Username für automatische Lade-Sperrbuchungen')]
        public string $systemUserUsername = 'system',

        #[Description('Geschäftszeit Beginn (Stunde 0–23) für die Flottenauslastung in der Statistik')]
        public int $utilizationBusinessHourStart = 7,

        #[Description('Geschäftszeit Ende (Stunde 0–23) für die Flottenauslastung in der Statistik')]
        public int $utilizationBusinessHourEnd = 18,

        /** @var list<int> ISO-Wochentag: 1 = Montag … 7 = Sonntag */
        #[Description('Wochentage für die Flottenauslastung (JSON-Array, z. B. [1,2,3,4,5] für Mo–Fr)')]
        public array $utilizationBusinessDays = [1, 2, 3, 4, 5],
    ) {}
}
