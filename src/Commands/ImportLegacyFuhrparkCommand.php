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
                            {--only= : Kommagetrennte Teilschritte: categories,projects,vehicles,driver-licenses,controls,bookings,charge-locks,handouts,returns,logbook}
                            {--probe : Nur Legacy-API prüfen, kein Import}';

    protected $description = 'Importiert Fuhrpark-Daten aus dem Legacy-Intranet per Bulk-API.';

    public function handle(
        IntranetLegacyService $legacyService,
        LegacyFuhrparkImportService $importService,
    ): int {
        if (! config('legacy.base_api_url') || ! config('legacy.base_api_token')) {
            $this->error('Legacy-API nicht konfiguriert: INTRANET_LEGACY_BASE_API_URL und INTRANET_LEGACY_API_TOKEN müssen gesetzt sein.');
            $this->line('Hinweis: Nach Änderungen an der .env ggf. `php artisan config:clear` ausführen.');

            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');
        $from = (string) $this->option('from');
        $only = $this->parseOnly((string) ($this->option('only') ?? ''));

        $this->line('Legacy-API: '.config('legacy.base_api_url'));

        $probe = $legacyService->probeFuhrparkLegacyExport($from);
        $this->displayProbeTable($probe);

        if ((bool) $this->option('probe')) {
            return $this->resolveProbeExitCode($probe);
        }

        if ($this->probeIndicatesApiFailure($probe)) {
            $this->newLine();
            $this->error('Legacy-API liefert keine Daten. Import abgebrochen.');
            $this->line('Prüfe INTRANET_LEGACY_BASE_API_URL, INTRANET_LEGACY_API_TOKEN und ob die Fuhrpark-Export-Routen im Legacy deployed sind.');
            $this->line('Details stehen ab jetzt auch in storage/logs/laravel.log.');

            return self::FAILURE;
        }

        if ($dryRun) {
            $this->warn('DRY-RUN: Es werden keine Änderungen vorgenommen.');
        }

        $this->info('Starte Fuhrpark-Legacy-Import ab '.$from.'…');

        $summary = $importService->import($legacyService, $from, $dryRun, $only);

        $this->displayImportSummary($summary);

        $importedTotal = collect($summary)->sum('imported');
        if ($importedTotal === 0) {
            $this->newLine();
            $this->warn('Es wurden keine Datensätze importiert.');
            $this->line('Wenn die API oben Daten liefert, fehlen oft Standort-/User-Mappings (legacy_id) oder Fuhrpark-Standort-Einstellungen.');

            return self::FAILURE;
        }

        $this->info('Fuhrpark-Legacy-Import abgeschlossen.');

        return self::SUCCESS;
    }

    /**
     * @param  array<string, array{status: int, ok: bool, count: int}>  $probe
     */
    private function displayProbeTable(array $probe): void
    {
        $this->newLine();
        $this->info('Legacy-API Erreichbarkeit:');

        $rows = [];
        foreach ($probe as $endpoint => $result) {
            $rows[] = [
                $endpoint,
                (string) $result['status'],
                $result['ok'] ? 'OK' : 'FEHLER',
                (string) $result['count'],
            ];
        }

        $this->table(['Endpoint', 'HTTP', 'Status', 'Einträge'], $rows);
    }

    /**
     * @param  array<string, array{api: int, imported: int, skipped: int}>  $summary
     */
    private function displayImportSummary(array $summary): void
    {
        if ($summary === []) {
            return;
        }

        $this->newLine();
        $this->info('Import-Ergebnis:');

        $rows = [];
        foreach ($summary as $step => $result) {
            $rows[] = [
                $step,
                (string) $result['api'],
                (string) $result['imported'],
                (string) $result['skipped'],
            ];
        }

        $this->table(['Schritt', 'API', 'Importiert', 'Übersprungen'], $rows);
    }

    /**
     * @param  array<string, array{status: int, ok: bool, count: int}>  $probe
     */
    private function probeIndicatesApiFailure(array $probe): bool
    {
        $relevant = ['categories', 'vehicles', 'bookings'];

        foreach ($relevant as $endpoint) {
            if (! isset($probe[$endpoint])) {
                continue;
            }

            if ($probe[$endpoint]['ok'] && $probe[$endpoint]['count'] > 0) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  array<string, array{status: int, ok: bool, count: int}>  $probe
     */
    private function resolveProbeExitCode(array $probe): int
    {
        return $this->probeIndicatesApiFailure($probe) ? self::FAILURE : self::SUCCESS;
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
