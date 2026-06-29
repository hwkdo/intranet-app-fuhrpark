<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppFuhrpark\Commands;

use App\Services\IntranetLegacyService;
use Hwkdo\IntranetAppFuhrpark\Services\Legacy\LegacyFuhrparkImportService;
use Illuminate\Console\Command;

class ImportLegacyFuhrparkCommand extends Command
{
    protected $signature = 'fuhrpark:import-legacy
                            {--dry-run : Nur anzeigen, was importiert würde (keine Änderungen)}
                            {--from=2025-01-01 : Buchungen ab diesem Datum (Y-m-d)}
                            {--only= : Kommagetrennte Teilschritte: categories,projects,vehicles,driver-licenses,controls,bookings,charge-locks,handouts,returns,logbook}';

    protected $description = 'Importiert Fuhrpark-Daten aus dem Legacy-Intranet per Bulk-API.';

    public function handle(
        IntranetLegacyService $legacyService,
        LegacyFuhrparkImportService $importService,
    ): int {
        if (! config('legacy.base_api_url') || ! config('legacy.base_api_token')) {
            $this->error('Legacy-API nicht konfiguriert: INTRANET_LEGACY_BASE_API_URL und INTRANET_LEGACY_API_TOKEN müssen gesetzt sein.');

            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');
        $from = (string) $this->option('from');
        $only = $this->parseOnly((string) ($this->option('only') ?? ''));

        if ($dryRun) {
            $this->warn('DRY-RUN: Es werden keine Änderungen vorgenommen.');
        }

        $this->info('Starte Fuhrpark-Legacy-Import ab '.$from.'…');

        $importService->import($legacyService, $from, $dryRun, $only);

        $this->info('Fuhrpark-Legacy-Import abgeschlossen.');

        return self::SUCCESS;
    }

    /**
     * @return list<string>
     */
    private function parseOnly(string $only): array
    {
        if ($only === '') {
            return [];
        }

        return array_values(array_filter(array_map('trim', explode(',', $only))));
    }
}
