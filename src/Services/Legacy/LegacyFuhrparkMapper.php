<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppFuhrpark\Services\Legacy;

use App\Models\Standort;
use App\Models\User;
use Carbon\Carbon;
use Hwkdo\IntranetAppFuhrpark\Enums\BookingPurpose;
use Hwkdo\IntranetAppFuhrpark\Models\Vehicle;
use Hwkdo\IntranetAppFuhrpark\Support\FuhrparkModels;

class LegacyFuhrparkMapper
{
    /**
     * @param  array<string, mixed>  $legacy
     * @return array<string, mixed>
     */
    public function mapVehicleCategory(array $legacy): array
    {
        return [
            'name' => (string) ($legacy['name'] ?? ''),
            'requires_license' => (bool) ($legacy['braucht_fuehrerschein'] ?? true),
            'is_electric' => (bool) ($legacy['elektro'] ?? false),
        ];
    }

    /**
     * @param  array<string, mixed>  $legacy
     * @return array<string, mixed>
     */
    public function mapProject(array $legacy): array
    {
        return [
            'name' => (string) ($legacy['name'] ?? ''),
            'active' => (bool) ($legacy['aktiv'] ?? true),
        ];
    }

    /**
     * @param  array<string, mixed>  $legacy
     * @return array<string, mixed>
     */
    public function mapVehicle(array $legacy, ?int $categoryId, ?int $standortId): array
    {
        $kraftstoff = (string) ($legacy['kraftstoff'] ?? '');

        return array_filter([
            'vehicle_category_id' => $categoryId,
            'standort_id' => $standortId,
            'license_plate' => (string) ($legacy['kennzeichen'] ?? ''),
            'manufacturer' => $legacy['hersteller'] ?? null,
            'model' => $legacy['modell'] ?? null,
            'vin' => $legacy['fargestellnr'] ?? null,
            'fuel_type' => $this->mapFuelType($kraftstoff),
            'initial_km' => (int) ($legacy['kminitial'] ?? 0),
            'active' => (bool) ($legacy['aktiv'] ?? true),
            'is_new' => (bool) ($legacy['jungfrau'] ?? true),
            'inactive_reason' => $legacy['inaktiv_grund'] ?? null,
            'inactive_by_user_id' => $this->resolveUserId($legacy['inaktiv_user_id'] ?? null),
            'available_from' => $this->parseDateTime($legacy['verfuegbar_ab'] ?? null),
            'available_until' => $this->parseDateTime($legacy['verfuegbar_bis'] ?? null),
            'electric_range_km' => $legacy['elektro_reichweite'] ?? null,
            'electric_charge_minutes' => $legacy['elektro_ladezeit_minuten'] ?? null,
        ], fn (mixed $value): bool => $value !== null);
    }

    /**
     * @param  array<string, mixed>  $legacy
     * @return array<string, mixed>
     */
    public function mapDriverLicense(array $legacy, ?int $userId): array
    {
        return [
            'user_id' => $userId,
            'valid_until' => $this->parseDate($legacy['gueltigbis'] ?? null) ?? now()->addYear()->startOfDay(),
            'restricted_until' => $this->parseDate($legacy['befristung'] ?? null),
        ];
    }

    /**
     * @param  array<string, mixed>  $legacy
     * @return array<string, mixed>
     */
    public function mapDriverLicenseControl(array $legacy, ?int $driverLicenseId, ?int $inspectorId): array
    {
        return array_filter([
            'driver_license_id' => $driverLicenseId,
            'inspected_by_user_id' => $inspectorId,
            'note' => $legacy['bemerkung'] ?? $legacy['note'] ?? null,
        ], fn (mixed $value): bool => $value !== null);
    }

    /**
     * @param  array<string, mixed>  $legacy
     * @return array<string, mixed>
     */
    public function mapBooking(
        array $legacy,
        ?int $vehicleId,
        ?int $userId,
        ?int $driverId,
        ?int $lockUserId = null,
    ): array {
        $purpose = $this->resolveBookingPurpose($legacy);
        $kalender = $legacy['kalender'] ?? null;

        $attributes = [
            'vehicle_id' => $vehicleId,
            'user_id' => $userId,
            'driver_id' => $driverId,
            'purpose' => $purpose->value,
            'description' => $this->resolveBookingDescription($legacy, $purpose),
            'purpose_note' => $this->resolvePurposeNote($legacy, $purpose),
            'lock_reason' => $purpose === BookingPurpose::Lock ? ($legacy['sperre'] ?? null) : null,
            'lock_user_id' => $purpose === BookingPurpose::Lock ? $lockUserId : null,
            'is_commute' => (bool) ($legacy['arbeitsfahrt'] ?? false),
            'electric_route_km' => $legacy['elektro_strecke'] ?? null,
            'starts_at' => $this->parseDateTime($legacy['start'] ?? null),
            'ends_at' => $this->parseDateTime($legacy['ende'] ?? null),
            'km_start' => $legacy['kmanfang'] ?? null,
            'km_end' => $legacy['kmende'] ?? null,
            'ms_graph_event_id' => filled($kalender) ? (string) $kalender : null,
            'sync_to_calendar' => filled($kalender),
        ];

        return array_filter($attributes, fn (mixed $value): bool => $value !== null);
    }

    /**
     * @param  array<string, mixed>  $legacy
     * @return array<string, mixed>
     */
    public function mapHandout(array $legacy, ?int $bookingId, ?int $driverId, ?int $processedByUserId): array
    {
        return array_filter([
            'booking_id' => $bookingId,
            'driver_id' => $driverId,
            'processed_by_user_id' => $processedByUserId,
            'signature_data' => $this->mapSignatureData($legacy['unterschrift'] ?? null),
        ], fn (mixed $value): bool => $value !== null);
    }

    /**
     * @param  array<string, mixed>  $legacy
     * @return array<string, mixed>
     */
    public function mapReturn(array $legacy, ?int $handoutId, ?int $driverId, ?int $processedByUserId): array
    {
        return array_filter([
            'handout_id' => $handoutId,
            'driver_id' => $driverId,
            'processed_by_user_id' => $processedByUserId,
            'km_end' => (int) ($legacy['kmende'] ?? 0),
            'checklist' => $this->mapReturnChecklist($legacy),
            'has_damage' => $this->mapReturnHasDamage($legacy),
            'damage_note' => $this->mapReturnDamageNote($legacy),
            'signature_data' => $this->mapSignatureData($legacy['unterschrift'] ?? null),
        ], fn (mixed $value): bool => $value !== null);
    }

    /**
     * @param  array<string, mixed>  $legacy
     * @return array<string, mixed>
     */
    public function mapLogbookEntry(array $legacy, ?int $bookingId, ?int $userId, ?int $projectId): array
    {
        return array_filter([
            'booking_id' => $bookingId,
            'user_id' => $userId,
            'project_id' => $projectId,
            'route' => (string) ($legacy['route'] ?? ''),
            'km_commute' => (int) ($legacy['km_arbeit'] ?? 0),
            'km_project' => (int) ($legacy['km_projekt'] ?? 0),
            'fueled' => (bool) ($legacy['getankt'] ?? false),
            'cleaned' => (bool) ($legacy['gereinigt'] ?? false),
            'note' => $legacy['bemerkung'] ?? null,
        ], fn (mixed $value): bool => $value !== null);
    }

    /**
     * @param  array<string, mixed>  $legacy
     */
    public function resolveBookingPurpose(array $legacy): BookingPurpose
    {
        if (! empty($legacy['sperre_buchung_id'])) {
            return BookingPurpose::ChargeLock;
        }

        $sperre = $legacy['sperre'] ?? null;
        if (filled($sperre) && $sperre !== 'Laden') {
            return BookingPurpose::Lock;
        }

        if (($legacy['zweck'] ?? '') === 'Werkstattfahrt') {
            return BookingPurpose::Workshop;
        }

        if (($legacy['zweck'] ?? '') === 'Sperre' && $sperre === 'Laden') {
            return BookingPurpose::ChargeLock;
        }

        return BookingPurpose::Normal;
    }

    public function isChargeLockSecondPass(array $legacy): bool
    {
        return ! empty($legacy['sperre_buchung_id']);
    }

    /**
     * @return array{data: string}|null
     */
    public function mapSignatureData(?string $unterschrift): ?array
    {
        if (blank($unterschrift)) {
            return null;
        }

        return ['data' => $unterschrift];
    }

    public function resolveUserId(mixed $legacyUserId): ?int
    {
        if ($legacyUserId === null || $legacyUserId === '') {
            return null;
        }

        return User::query()->where('legacy_id', (int) $legacyUserId)->value('id');
    }

    public function resolveVehicleId(mixed $legacyVehicleId): ?int
    {
        if ($legacyVehicleId === null || $legacyVehicleId === '') {
            return null;
        }

        $vehicleId = Vehicle::query()->where('legacy_id', (int) $legacyVehicleId)->value('id');

        return $vehicleId !== null ? (int) $vehicleId : null;
    }

    public function resolveStandortId(mixed $legacyStandortId): ?int
    {
        if ($legacyStandortId === null || $legacyStandortId === '') {
            return null;
        }

        $standortId = Standort::query()->where('legacy_id', (int) $legacyStandortId)->value('id');

        if ($standortId === null) {
            return null;
        }

        return FuhrparkModels::vehicleStandortIdFor((int) $standortId);
    }

    public function mapFuelType(string $kraftstoff): string
    {
        return match (mb_strtolower(trim($kraftstoff))) {
            'elektro', 'electric' => 'electric',
            'diesel' => 'diesel',
            'benzin', 'petrol' => 'petrol',
            default => 'petrol',
        };
    }

    /**
     * @param  array<string, mixed>  $legacy
     * @return array<string, bool>
     */
    public function mapReturnChecklist(array $legacy): array
    {
        $empfang = $legacy['data_empfang'] ?? [];
        if (! is_array($empfang)) {
            return [];
        }

        return [
            'key' => (bool) ($empfang['schluessel'] ?? false),
            'license' => (bool) ($empfang['fzgschein'] ?? $empfang['verskarte'] ?? false),
            'fuel_card' => (bool) ($empfang['tankkarte'] ?? false),
            'vehicle_registration' => (bool) ($empfang['fzgschein'] ?? false),
            'insurance_card' => (bool) ($empfang['verskarte'] ?? false),
            'damage_card' => (bool) ($empfang['schadenkarte'] ?? false),
        ];
    }

    /**
     * @param  array<string, mixed>  $legacy
     */
    public function mapReturnHasDamage(array $legacy): bool
    {
        $data = $legacy['data'] ?? [];
        if (! is_array($data)) {
            return false;
        }

        foreach ($data as $item) {
            if (! is_array($item)) {
                continue;
            }

            if (($item['id'] ?? null) === 'schaden') {
                $value = $item['value'] ?? false;

                return $value === true || $value === 'TRUE' || $value === 1 || $value === '1';
            }
        }

        if (isset($data[0]['value'])) {
            return $data[0]['value'] === 'TRUE' || $data[0]['value'] === true;
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $legacy
     */
    public function mapReturnDamageNote(array $legacy): ?string
    {
        if (! $this->mapReturnHasDamage($legacy)) {
            return null;
        }

        return $legacy['schaden_notiz'] ?? $legacy['damage_note'] ?? null;
    }

    /**
     * @param  array<string, mixed>  $legacy
     */
    private function resolveBookingDescription(array $legacy, BookingPurpose $purpose): string
    {
        $zweck = trim((string) ($legacy['zweck'] ?? ''));

        if ($purpose === BookingPurpose::Lock && filled($legacy['sperre'] ?? null)) {
            return (string) $legacy['sperre'];
        }

        return $zweck !== '' ? $zweck : 'Importiert';
    }

    /**
     * @param  array<string, mixed>  $legacy
     */
    private function resolvePurposeNote(array $legacy, BookingPurpose $purpose): ?string
    {
        if ($purpose === BookingPurpose::Workshop) {
            return 'Werkstattfahrt';
        }

        if ($purpose === BookingPurpose::ChargeLock) {
            return 'Laden';
        }

        return null;
    }

    private function parseDateTime(mixed $value): ?Carbon
    {
        if (blank($value)) {
            return null;
        }

        return Carbon::parse($value)->timezone(config('app.timezone'));
    }

    private function parseDate(mixed $value): ?Carbon
    {
        if (blank($value)) {
            return null;
        }

        return Carbon::parse($value)->startOfDay();
    }
}
