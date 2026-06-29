<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppFuhrpark\Services\Legacy;

use App\Services\IntranetLegacyService;
use Hwkdo\IntranetAppFuhrpark\Models\Booking;
use Hwkdo\IntranetAppFuhrpark\Models\DriverLicense;
use Hwkdo\IntranetAppFuhrpark\Models\DriverLicenseControl;
use Hwkdo\IntranetAppFuhrpark\Models\Handout;
use Hwkdo\IntranetAppFuhrpark\Models\LogbookEntry;
use Hwkdo\IntranetAppFuhrpark\Models\Project;
use Hwkdo\IntranetAppFuhrpark\Models\Vehicle;
use Hwkdo\IntranetAppFuhrpark\Models\VehicleCategory;
use Hwkdo\IntranetAppFuhrpark\Models\VehicleReturn;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class LegacyFuhrparkImportService
{
    public function __construct(
        private readonly LegacyFuhrparkMapper $mapper,
    ) {}

    /**
     * @param  list<string>  $only
     * @return array<string, array{api: int, imported: int, skipped: int}>
     */
    public function import(
        IntranetLegacyService $legacyService,
        string $from = '2025-01-01',
        bool $dryRun = false,
        array $only = [],
    ): array {
        $steps = $only === [] ? [
            'categories',
            'projects',
            'vehicles',
            'driver-licenses',
            'controls',
            'bookings',
            'charge-locks',
            'handouts',
            'returns',
            'logbook',
        ] : $only;

        $summary = [];

        if ($this->shouldRun($steps, 'categories')) {
            $summary['categories'] = $this->importCategories($legacyService, $dryRun);
        }

        if ($this->shouldRun($steps, 'projects')) {
            $summary['projects'] = $this->importProjects($legacyService, $dryRun);
        }

        if ($this->shouldRun($steps, 'vehicles')) {
            $summary['vehicles'] = $this->importVehicles($legacyService, $dryRun);
        }

        if ($this->shouldRun($steps, 'driver-licenses')) {
            $summary['driver-licenses'] = $this->importDriverLicenses($legacyService, $dryRun);
        }

        if ($this->shouldRun($steps, 'controls')) {
            $summary['controls'] = $this->importDriverLicenseControls($legacyService, $dryRun);
        }

        if ($this->shouldRun($steps, 'bookings')) {
            $summary['bookings'] = $this->importBookings($legacyService, $from, $dryRun);
        }

        if ($this->shouldRun($steps, 'charge-locks')) {
            $summary['charge-locks'] = $this->importChargeLockBookings($legacyService, $from, $dryRun);
        }

        if ($this->shouldRun($steps, 'handouts')) {
            $summary['handouts'] = $this->importHandouts($legacyService, $from, $dryRun);
        }

        if ($this->shouldRun($steps, 'returns')) {
            $summary['returns'] = $this->importReturns($legacyService, $from, $dryRun);
        }

        if ($this->shouldRun($steps, 'logbook')) {
            $summary['logbook'] = $this->importLogbookEntries($legacyService, $from, $dryRun);
        }

        return $summary;
    }

    /**
     * @return array{api: int, imported: int, skipped: int}
     */
    private function stepResult(int $api, int $imported): array
    {
        return [
            'api' => $api,
            'imported' => $imported,
            'skipped' => max(0, $api - $imported),
        ];
    }

    /**
     * @param  list<string>  $steps
     */
    private function shouldRun(array $steps, string $step): bool
    {
        return in_array($step, $steps, true);
    }

    private function importCategories(IntranetLegacyService $legacyService, bool $dryRun): array
    {
        $legacyItems = $legacyService->getFuhrparkCategoriesExport();
        $imported = 0;

        foreach ($legacyItems as $legacy) {
            $legacyId = (int) ($legacy['id'] ?? 0);
            if ($legacyId < 1) {
                continue;
            }

            if (! $dryRun) {
                VehicleCategory::query()->updateOrCreate(
                    ['legacy_id' => $legacyId],
                    $this->mapper->mapVehicleCategory($legacy),
                );
            }

            $imported++;
        }

        return $this->stepResult(count($legacyItems), $imported);
    }

    private function importProjects(IntranetLegacyService $legacyService, bool $dryRun): array
    {
        $legacyItems = $legacyService->getFuhrparkProjectsExport();
        $imported = 0;

        foreach ($legacyItems as $legacy) {
            $legacyId = (int) ($legacy['id'] ?? 0);
            if ($legacyId < 1) {
                continue;
            }

            if (! $dryRun) {
                Project::query()->updateOrCreate(
                    ['legacy_id' => $legacyId],
                    $this->mapper->mapProject($legacy),
                );
            }

            $imported++;
        }

        return $this->stepResult(count($legacyItems), $imported);
    }

    private function importVehicles(IntranetLegacyService $legacyService, bool $dryRun): array
    {
        $categoryMap = VehicleCategory::query()->pluck('id', 'legacy_id');
        $legacyItems = $legacyService->getFuhrparkVehiclesExport();
        $imported = 0;
        $skippedMissingCategory = 0;
        $skippedMissingStandort = 0;

        foreach ($legacyItems as $legacy) {
            $legacyId = (int) ($legacy['id'] ?? 0);
            if ($legacyId < 1) {
                continue;
            }

            $categoryId = $categoryMap[(int) ($legacy['fahrzeugkategorie_id'] ?? 0)] ?? null;
            $standortId = $this->mapper->resolveStandortId($legacy['standort_id'] ?? null);

            if ($categoryId === null) {
                $skippedMissingCategory++;

                continue;
            }

            if ($standortId === null) {
                $skippedMissingStandort++;

                continue;
            }

            $attributes = $this->mapper->mapVehicle($legacy, (int) $categoryId, (int) $standortId);

            if (! $dryRun) {
                Vehicle::query()->updateOrCreate(
                    ['legacy_id' => $legacyId],
                    $attributes,
                );
            }

            $imported++;
        }

        if ($skippedMissingCategory > 0 || $skippedMissingStandort > 0) {
            Log::warning('LegacyFuhrparkImportService: Fahrzeuge übersprungen', [
                'missing_category' => $skippedMissingCategory,
                'missing_standort' => $skippedMissingStandort,
            ]);
        }

        return $this->stepResult(count($legacyItems), $imported);
    }

    private function importDriverLicenses(IntranetLegacyService $legacyService, bool $dryRun): array
    {
        $legacyItems = $legacyService->getFuhrparkDriverLicensesExport();
        $imported = 0;

        foreach ($legacyItems as $legacy) {
            $legacyId = (int) ($legacy['id'] ?? 0);
            if ($legacyId < 1) {
                continue;
            }

            $userId = $this->mapper->resolveUserId($legacy['user_id'] ?? null);
            if ($userId === null) {
                continue;
            }

            if (! $dryRun) {
                $this->upsertDriverLicense($legacyId, $legacy, $userId);
            }

            $imported++;
        }

        return $this->stepResult(count($legacyItems), $imported);
    }

    private function importDriverLicenseControls(IntranetLegacyService $legacyService, bool $dryRun): array
    {
        $licenseMap = DriverLicense::query()->pluck('id', 'legacy_id');
        $legacyItems = $legacyService->getFuhrparkDriverLicenseControlsExport();
        $imported = 0;

        foreach ($legacyItems as $legacy) {
            $legacyId = (int) ($legacy['id'] ?? 0);
            if ($legacyId < 1) {
                continue;
            }

            $licenseLegacyId = (int) ($legacy['fuehrerschein_id'] ?? 0);
            $licenseId = $licenseMap[$licenseLegacyId] ?? null;
            $inspectorId = $this->mapper->resolveUserId($legacy['kontrolleur_id'] ?? null);

            if ($licenseId === null || $inspectorId === null) {
                continue;
            }

            if (! $dryRun) {
                $control = DriverLicenseControl::query()->updateOrCreate(
                    ['legacy_id' => $legacyId],
                    $this->mapper->mapDriverLicenseControl($legacy, (int) $licenseId, $inspectorId),
                );

                if (! empty($legacy['file'])) {
                    $binary = $legacyService->getFuhrparkDriverLicenseControlFile($legacyId);
                    if ($binary !== null) {
                        $directory = "fuhrpark/driver-license-controls/{$control->id}";
                        $fileName = (string) $legacy['file'];
                        $path = $directory.'/'.$fileName;
                        Storage::disk('local')->put($path, $binary);
                        $control->update([
                            'file_path' => $path,
                            'file_name' => $fileName,
                        ]);
                    }
                }
            }

            $imported++;
        }

        return $this->stepResult(count($legacyItems), $imported);
    }

    private function importBookings(IntranetLegacyService $legacyService, string $from, bool $dryRun): array
    {
        $vehicleMap = Vehicle::query()
            ->whereNotNull('legacy_id')
            ->pluck('id', 'legacy_id')
            ->all();
        $legacyItems = $legacyService->getFuhrparkBookingsExport($from);
        $imported = 0;

        foreach ($legacyItems as $legacy) {
            $legacyId = (int) ($legacy['id'] ?? 0);
            if ($legacyId < 1 || $this->mapper->isChargeLockSecondPass($legacy)) {
                continue;
            }

            $vehicleId = $vehicleMap[(int) ($legacy['fahrzeug_id'] ?? 0)] ?? null;
            $userId = $this->mapper->resolveUserId($legacy['user_id'] ?? null);
            $driverId = $this->mapper->resolveUserId($legacy['fahrer_id'] ?? null);
            $lockUserId = $this->mapper->resolveUserId($legacy['sperre_user_id'] ?? null);

            if ($vehicleId === null || $userId === null || $driverId === null) {
                continue;
            }

            $attributes = $this->mapper->mapBooking($legacy, (int) $vehicleId, $userId, $driverId, $lockUserId);

            if (! $dryRun) {
                Booking::query()->updateOrCreate(
                    ['legacy_id' => $legacyId],
                    $attributes,
                );
            }

            $imported++;
        }

        return $this->stepResult(count($legacyItems), $imported);
    }

    private function importChargeLockBookings(IntranetLegacyService $legacyService, string $from, bool $dryRun): array
    {
        $vehicleMap = Vehicle::query()
            ->whereNotNull('legacy_id')
            ->pluck('id', 'legacy_id')
            ->all();
        $parentMap = Booking::query()->pluck('id', 'legacy_id');
        $legacyItems = $legacyService->getFuhrparkBookingsExport($from);
        $imported = 0;

        foreach ($legacyItems as $legacy) {
            if (! $this->mapper->isChargeLockSecondPass($legacy)) {
                continue;
            }

            $legacyId = (int) ($legacy['id'] ?? 0);
            $parentLegacyId = (int) ($legacy['sperre_buchung_id'] ?? 0);
            $parentId = $parentMap[$parentLegacyId] ?? null;

            $vehicleId = $vehicleMap[(int) ($legacy['fahrzeug_id'] ?? 0)] ?? null;
            $userId = $this->mapper->resolveUserId($legacy['user_id'] ?? null);
            $driverId = $this->mapper->resolveUserId($legacy['fahrer_id'] ?? null);
            $lockUserId = $this->mapper->resolveUserId($legacy['sperre_user_id'] ?? null);

            if ($legacyId < 1 || $parentId === null || $vehicleId === null || $userId === null || $driverId === null) {
                continue;
            }

            $attributes = array_merge(
                $this->mapper->mapBooking($legacy, (int) $vehicleId, $userId, $driverId, $lockUserId),
                ['charge_lock_for_booking_id' => $parentId],
            );

            if (! $dryRun) {
                Booking::query()->updateOrCreate(
                    ['legacy_id' => $legacyId],
                    $attributes,
                );
            }

            $imported++;
        }

        return $this->stepResult(count($legacyItems), $imported);
    }

    private function importHandouts(IntranetLegacyService $legacyService, string $from, bool $dryRun): array
    {
        $bookingMap = Booking::query()->pluck('id', 'legacy_id');
        $legacyItems = $legacyService->getFuhrparkHandoutsExport($from);
        $imported = 0;

        foreach ($legacyItems as $legacy) {
            $legacyId = (int) ($legacy['id'] ?? 0);
            if ($legacyId < 1) {
                continue;
            }

            $bookingId = $bookingMap[(int) ($legacy['buchung_id'] ?? 0)] ?? null;
            $driverId = $this->mapper->resolveUserId($legacy['unterschrift_id'] ?? $legacy['fahrer_id'] ?? null);
            $processedBy = $this->mapper->resolveUserId($legacy['user_id'] ?? null);

            if ($bookingId === null || $driverId === null || $processedBy === null) {
                continue;
            }

            if (! $dryRun) {
                $attributes = $this->mapper->mapHandout($legacy, (int) $bookingId, $driverId, $processedBy);
                $this->upsertHandout($legacyId, (int) $bookingId, $attributes);
            }

            $imported++;
        }

        return $this->stepResult(count($legacyItems), $imported);
    }

    private function importReturns(IntranetLegacyService $legacyService, string $from, bool $dryRun): array
    {
        $handoutMap = Handout::query()->pluck('id', 'legacy_id');
        $ausgabeToHandout = Handout::query()->pluck('id', 'booking_id');
        $bookingMap = Booking::query()->pluck('id', 'legacy_id');
        $legacyItems = $legacyService->getFuhrparkReturnsExport($from);
        $imported = 0;

        foreach ($legacyItems as $legacy) {
            $legacyId = (int) ($legacy['id'] ?? 0);
            if ($legacyId < 1) {
                continue;
            }

            $handoutId = $handoutMap[(int) ($legacy['ausgabe_id'] ?? 0)] ?? null;
            if ($handoutId === null && isset($legacy['buchung_id'])) {
                $bookingId = $bookingMap[(int) $legacy['buchung_id']] ?? null;
                $handoutId = $bookingId ? ($ausgabeToHandout[$bookingId] ?? null) : null;
            }

            $driverId = $this->mapper->resolveUserId($legacy['unterschrift_id'] ?? null);
            $processedBy = $this->mapper->resolveUserId($legacy['user_id'] ?? null);

            if ($handoutId === null || $driverId === null || $processedBy === null) {
                continue;
            }

            if (! $dryRun) {
                $attributes = $this->mapper->mapReturn($legacy, (int) $handoutId, $driverId, $processedBy);
                $this->upsertReturn($legacyId, (int) $handoutId, $attributes);
            }

            $imported++;
        }

        return $this->stepResult(count($legacyItems), $imported);
    }

    private function importLogbookEntries(IntranetLegacyService $legacyService, string $from, bool $dryRun): array
    {
        $bookingMap = Booking::query()->pluck('id', 'legacy_id');
        $projectMap = Project::query()->pluck('id', 'legacy_id');
        $legacyItems = $legacyService->getFuhrparkLogbookEntriesExport($from);
        $imported = 0;

        foreach ($legacyItems as $legacy) {
            $legacyId = (int) ($legacy['id'] ?? 0);
            if ($legacyId < 1) {
                continue;
            }

            $bookingId = $bookingMap[(int) ($legacy['buchung_id'] ?? 0)] ?? null;
            $userId = $this->mapper->resolveUserId($legacy['user_id'] ?? null);
            $projectId = $projectMap[(int) ($legacy['projekt_id'] ?? 0)] ?? null;

            if ($bookingId === null || $userId === null) {
                continue;
            }

            if (! $dryRun) {
                $attributes = $this->mapper->mapLogbookEntry(
                    $legacy,
                    (int) $bookingId,
                    $userId,
                    $projectId ? (int) $projectId : null,
                );
                $this->upsertLogbookEntry($legacyId, (int) $bookingId, $attributes);
            }

            $imported++;
        }

        return $this->stepResult(count($legacyItems), $imported);
    }

    /**
     * @param  array<string, mixed>  $legacy
     */
    private function upsertDriverLicense(int $legacyId, array $legacy, int $userId): void
    {
        $attributes = array_merge(
            $this->mapper->mapDriverLicense($legacy, $userId),
            ['legacy_id' => $legacyId],
        );

        $existing = DriverLicense::query()
            ->where('legacy_id', $legacyId)
            ->orWhere('user_id', $userId)
            ->first();

        if ($existing) {
            $existing->update($attributes);

            return;
        }

        DriverLicense::query()->create($attributes);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function upsertHandout(int $legacyId, int $bookingId, array $attributes): void
    {
        $attributes['legacy_id'] = $legacyId;

        $existing = Handout::query()
            ->where('legacy_id', $legacyId)
            ->orWhere('booking_id', $bookingId)
            ->first();

        if ($existing) {
            $existing->update($attributes);

            return;
        }

        Handout::query()->create($attributes);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function upsertReturn(int $legacyId, int $handoutId, array $attributes): void
    {
        $attributes['legacy_id'] = $legacyId;

        $existing = VehicleReturn::query()
            ->where('legacy_id', $legacyId)
            ->orWhere('handout_id', $handoutId)
            ->first();

        if ($existing) {
            $existing->update($attributes);

            return;
        }

        VehicleReturn::query()->create($attributes);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function upsertLogbookEntry(int $legacyId, int $bookingId, array $attributes): void
    {
        $attributes['legacy_id'] = $legacyId;

        $existing = LogbookEntry::query()
            ->where('legacy_id', $legacyId)
            ->orWhere('booking_id', $bookingId)
            ->first();

        if ($existing) {
            $existing->update($attributes);

            return;
        }

        LogbookEntry::query()->create($attributes);
    }
}
